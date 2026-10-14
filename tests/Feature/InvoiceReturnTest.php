<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Doctor;
use App\Models\DoctorDeal;
use App\Models\Drug;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePayment;
use App\Models\InvoiceReturn;
use App\Models\Pharmacist;
use App\Models\Province;
use App\Models\Representative;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Warehouse $warehouse;

    protected Zone $zone;

    protected Pharmacist $pharmacist;

    protected Drug $drugA;

    protected Drug $drugB;

    protected Representative $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $province = Province::create(['name' => 'القاهرة']);
        $center = Center::create(['name' => 'مدينة نصر', 'province_id' => $province->id]);

        $this->rep = Representative::create(['name' => 'مندوب 1', 'phone' => '01000000000']);

        $this->warehouse = Warehouse::create([
            'name' => 'المخزن الرئيسي',
            'type' => 'main',
        ]);

        $this->zone = Zone::create([
            'name' => 'منطقة 1',
            'province_id' => $province->id,
            'line' => 1,
            'sales_representative_id' => $this->rep->id,
            'medical_representative_id' => $this->rep->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $this->zone->centers()->attach($center->id);

        $this->pharmacist = Pharmacist::create([
            'name' => 'صيدلية النور',
            'phone' => '01200000000',
            'address' => 'شارع عباس العقاد',
            'center_id' => $center->id,
        ]);

        $this->drugA = Drug::create([
            'name' => 'بانادول',
            'price' => 100.00,
            'line' => 1,
        ]);

        $this->drugB = Drug::create([
            'name' => 'كونجستال',
            'price' => 50.00,
            'line' => 1,
        ]);

        // رصيد أولي في المخزن
        $this->warehouse->drugs()->attach($this->drugA->id, ['quantity' => 100]);
        $this->warehouse->drugs()->attach($this->drugB->id, ['quantity' => 50]);
    }

    /**
     * مساعدة لإنشاء فاتورة مبيعات
     */
    protected function createInvoice(array $items, int $status = 2, float $paid = 0): Invoice
    {
        $invoice = Invoice::create([
            'serial_number' => 'INV-TEST-001',
            'invoice_date' => '2026-10-12',
            'line' => 1,
            'representative_id' => $this->rep->id,
            'medical_representative_id' => $this->rep->id,
            'pharmacist_id' => $this->pharmacist->id,
            'total_amount' => 0,
            'total_discount' => 0,
            'final_total' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => $status,
        ]);

        $totalPublic = 0;
        $totalDiscount = 0;

        foreach ($items as $item) {
            $drug = $item['drug'];
            $qty = $item['qty'];
            $discount = $item['discount'] ?? 0;

            $public = $drug->price * $qty;
            $discAmount = $public * ($discount / 100);
            $rowTotal = $public - $discAmount;

            InvoiceDetail::create([
                'invoice_id' => $invoice->id,
                'drug_id' => $drug->id,
                'quantity' => $qty,
                'returned_quantity' => 0,
                'unit_price' => $drug->price,
                'pharmacist_discount_percentage' => $discount,
                'row_total' => $rowTotal,
            ]);

            // خصم الكمية من المخزن كما يحدث في البيع
            $currentStock = $this->warehouse->drugs()->where('drug_id', $drug->id)->first()->pivot->quantity;
            $this->warehouse->drugs()->updateExistingPivot($drug->id, ['quantity' => $currentStock - $qty]);

            $totalPublic += $public;
            $totalDiscount += $discAmount;
        }

        $final = $totalPublic - $totalDiscount;

        if ($paid > 0) {
            $invoice->payments()->create([
                'amount' => $paid,
                'payment_date' => $invoice->invoice_date,
                'notes' => 'دفعة أولية',
            ]);
        }

        $remaining = max(0, $final - $paid);

        $invoice->update([
            'total_amount' => $totalPublic,
            'total_discount' => $totalDiscount,
            'final_total' => $final,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'status' => $remaining <= 0 ? 1 : ($paid > 0 ? 3 : 2),
        ]);

        return $invoice;
    }

    public function test_admin_can_view_invoice_return_page()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.invoices.returns.create', $invoice->id));

        $response->assertOk();
        $response->assertSee('تسجيل مرتجع مبيعات');
        $response->assertSee('بانادول');
    }

    public function test_processing_partial_return_on_deferred_invoice_updates_stock_and_totals()
    {
        // 10 بانادول بسعر 100 وخصم 10% = 900 صافي، غير مسددة
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);

        // الرصيد بالمخزن بعد الفاتورة كان 90
        $initialStock = $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity;
        $this->assertEquals(90, $initialStock);

        $detail = $invoice->details->first();

        // إرجاع 2 عبوة بانادول
        $response = $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'notes' => 'فائض بالصيدلية',
            'items' => [
                [
                    'detail_id' => $detail->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 1. التحقق من إعادة المخزون
        $newStock = $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity;
        $this->assertEquals(92, $newStock); // 90 + 2 = 92

        // 2. التحقق من تحديث بند الفاتورة
        $detail->refresh();
        $this->assertEquals(8, $detail->quantity); // 10 - 2 = 8
        $this->assertEquals(2, $detail->returned_quantity);
        $this->assertEquals(720.00, $detail->row_total); // 8 * 100 * 0.9 = 720

        // 3. التحقق من تحديث إجماليات الفاتورة
        $invoice->refresh();
        $this->assertEquals(800.00, $invoice->total_amount); // 8 * 100 = 800
        $this->assertEquals(80.00, $invoice->total_discount);
        $this->assertEquals(720.00, $invoice->final_total);
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertEquals(720.00, $invoice->remaining_amount);
        $this->assertEquals(2, $invoice->status); // آجل

        // 4. التحقق من إنشاء إيصال المرتجع
        $this->assertDatabaseHas('invoice_returns', [
            'invoice_id' => $invoice->id,
            'total_return_public_price' => 200.00,
            'total_return_discount' => 20.00,
            'total_return_amount' => 180.00,
            'refund_amount' => 0.00,
        ]);

        $this->assertDatabaseHas('invoice_return_items', [
            'drug_id' => $this->drugA->id,
            'quantity' => 2,
            'subtotal_net' => 180.00,
        ]);
    }

    public function test_return_on_fully_paid_invoice_generates_cash_refund_and_adjusts_deal()
    {
        // إنشاء طبيب واتفاق
        $doctor = Doctor::create([
            'name' => 'د. أحمد محمود',
            'speciality' => 'باطنة',
            'address' => 'القاهرة',
            'phone' => '01011111111',
            'center_id' => $this->pharmacist->center_id,
        ]);

        $deal = DoctorDeal::create([
            'doctor_id' => $doctor->id,
            'target_amount' => 5000,
            'achieved_amount' => 1000, // مساهمة مبدئية
            'commission_percentage' => 10,
            'is_active' => true,
            'is_archived' => false,
            'is_paid' => false,
            'start_date' => '2026-01-01',
        ]);
        $deal->pharmacists()->attach($this->pharmacist->id);

        // فاتورة مدفوعة بالكامل بقيمة 1000 جمهور (900 صافي)
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 1, paid: 900);

        $invoice->doctors()->attach($doctor->id);
        $deal->invoices()->attach($invoice->id, ['contribution_amount' => 1000]);

        $detail = $invoice->details->first();

        // إرجاع 4 قطع بقيمة صافية 360 ج.م
        $response = $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                [
                    'detail_id' => $detail->id,
                    'quantity' => 4,
                ],
            ],
        ]);

        $response->assertRedirect();

        $invoice->refresh();
        // صافي الفاتورة الجديد أصبح 6 * 90 = 540 ج.م
        $this->assertEquals(540.00, $invoice->final_total);
        $this->assertEquals(540.00, $invoice->paid_amount);
        $this->assertEquals(0, $invoice->remaining_amount);
        $this->assertEquals(1, $invoice->status); // ما زالت خالصة

        // التحقق من تعديل المدفوعات دون إنشاء سجلات سالبة تشوه التحصيلات
        $negativePaymentsCount = InvoicePayment::where('invoice_id', $invoice->id)->where('amount', '<', 0)->count();
        $this->assertEquals(0, $negativePaymentsCount);
        $this->assertEquals(540.00, $invoice->payments()->sum('amount'));

        // التحقق من إيصال المرتجع ومبلغ الاسترداد
        $return = InvoiceReturn::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(360.00, $return->refund_amount);

        // التحقق من تعديل مساهمة اتفاق الطبيب: الجمهور أصبح 600 بدلاً من 1000 (تراجع بمقدار 400)
        $deal->refresh();
        $this->assertEquals(600, $deal->achieved_amount);
        $pivotContribution = $deal->invoices()->where('invoice_id', $invoice->id)->first()->pivot->contribution_amount;
        $this->assertEquals(600, $pivotContribution);
    }

    public function test_validation_prevents_exceeding_available_quantity()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 5, 'discount' => 0],
        ]);

        $detail = $invoice->details->first();

        // محاولة إرجاع 6 وهي أكبر من 5
        $response = $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                [
                    'detail_id' => $detail->id,
                    'quantity' => 6,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, InvoiceReturn::count());
    }

    public function test_validation_prevents_zero_quantity_returns()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 5, 'discount' => 0],
        ]);

        $detail = $invoice->details->first();

        $response = $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                [
                    'detail_id' => $detail->id,
                    'quantity' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, InvoiceReturn::count());
    }

    public function test_admin_can_view_returns_index_and_show()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 0],
        ]);

        $detail = $invoice->details->first();

        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                [
                    'detail_id' => $detail->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $return = InvoiceReturn::first();

        $indexRes = $this->actingAs($this->admin)->get(route('admin.returns.index'));
        $indexRes->assertOk();
        $indexRes->assertSee($return->return_number);

        $showRes = $this->actingAs($this->admin)->get(route('admin.returns.show', $return->id));
        $showRes->assertOk();
        $showRes->assertSee($return->return_number);
    }

    public function test_admin_can_view_edit_return_page()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ]);

        $detail = $invoice->details->first();

        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 3],
            ],
        ]);

        $return = InvoiceReturn::first();

        $response = $this->actingAs($this->admin)->get(route('admin.returns.edit', $return->id));
        $response->assertOk();
        $response->assertSee('تعديل إيصال مرتجع مبيعات');
        $response->assertSee($return->return_number);
    }

    public function test_admin_can_update_return_quantities_and_reconcile_warehouse_and_invoice()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);

        $detail = $invoice->details->first();

        // 1. تسجيل مرتجع مبدئي بـ 2 عبوة
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 2],
            ],
        ]);

        $return = InvoiceReturn::first();
        $this->assertEquals(92, $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity);

        // 2. تعديل المرتجع بزيادة الكمية إلى 5 عبوات (+3 للمخزن)
        $response = $this->actingAs($this->admin)->put(route('admin.returns.update', $return->id), [
            'return_date' => '2026-10-14',
            'notes' => 'تعديل بالزيادة',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect(route('admin.returns.show', $return->id));

        // التحقق من المخزن: 92 + 3 = 95
        $this->assertEquals(95, $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity);

        // التحقق من بند الفاتورة: 10 - 5 = 5
        $detail->refresh();
        $this->assertEquals(5, $detail->quantity);
        $this->assertEquals(5, $detail->returned_quantity);
        $this->assertEquals(450.00, $detail->row_total);

        // التحقق من إجماليات الفاتورة
        $invoice->refresh();
        $this->assertEquals(450.00, $invoice->final_total);
        $this->assertEquals(450.00, $invoice->remaining_amount);

        // التحقق من إيصال المرتجع
        $return->refresh();
        $this->assertEquals(450.00, $return->total_return_amount);
        $this->assertEquals('2026-10-14', $return->return_date->format('Y-m-d'));
        $this->assertEquals('تعديل بالزيادة', $return->notes);
    }

    public function test_admin_can_decrease_return_quantity_and_deduct_warehouse_stock()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);

        $detail = $invoice->details->first();

        // مرتجع بـ 5 عبوات (المخزن يصبح 95)
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 5],
            ],
        ]);

        $return = InvoiceReturn::first();
        $this->assertEquals(95, $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity);

        // تعديل بتقليل الكمية إلى 2 عبوة (خصم 3 من المخزن وإعادتها للفاتورة)
        $response = $this->actingAs($this->admin)->put(route('admin.returns.update', $return->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('admin.returns.show', $return->id));

        // التحقق من المخزن: 95 - 3 = 92
        $this->assertEquals(92, $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity);

        // التحقق من بند الفاتورة: أصبح 8
        $detail->refresh();
        $this->assertEquals(8, $detail->quantity);
        $this->assertEquals(2, $detail->returned_quantity);
        $this->assertEquals(720.00, $detail->row_total);

        // التحقق من الفاتورة
        $invoice->refresh();
        $this->assertEquals(720.00, $invoice->final_total);
        $this->assertEquals(720.00, $invoice->remaining_amount);
    }

    public function test_cannot_update_return_if_warehouse_stock_insufficient()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);

        $detail = $invoice->details->first();

        // مرتجع بـ 5 عبوات
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 5],
            ],
        ]);

        $return = InvoiceReturn::first();

        // محاكاة نفاد رصيد المخزن (تم بيع أو صرف البضاعة من المخزن ولم يتبق سوى 1 عبوة)
        $this->warehouse->drugs()->updateExistingPivot($this->drugA->id, ['quantity' => 1]);

        // محاولة تقليل المرتجع إلى 2 (يتطلب خصم 3 من المخزن ورصيده 1 فقط)
        $response = $this->actingAs($this->admin)->put(route('admin.returns.update', $return->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 2],
            ],
        ]);

        $response->assertSessionHasErrors('items');

        // التأكد من عدم تغيير المخزن أو الفاتورة
        $this->assertEquals(1, $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity);
        $detail->refresh();
        $this->assertEquals(5, $detail->quantity);
    }

    public function test_cannot_update_return_exceeding_max_available_quantity()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ]);

        $detail = $invoice->details->first();

        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 3],
            ],
        ]);

        $return = InvoiceReturn::first();

        // محاولة التعديل لكمية 12 (الحد الأقصى هو 10 الأصلية)
        $response = $this->actingAs($this->admin)->put(route('admin.returns.update', $return->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 12],
            ],
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_admin_can_delete_return_and_fully_reverse_warehouse_and_invoice()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);

        $detail = $invoice->details->first();

        // 1. تسجيل مرتجع بـ 4 قطع (المخزن 90 -> 94، الفاتورة 10 -> 6، الصافي 900 -> 540)
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 4],
            ],
        ]);

        $return = InvoiceReturn::first();
        $this->assertNotNull($return);
        $this->assertEquals(94, $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity);

        // 2. إلغاء وحذف المرتجع
        $response = $this->actingAs($this->admin)->delete(route('admin.returns.destroy', $return->id));
        $response->assertRedirect(route('admin.returns.index'));
        $response->assertSessionHas('success');

        // التحقق من حذف سجل المرتجع وبنوده
        $this->assertDatabaseMissing('invoice_returns', ['id' => $return->id]);
        $this->assertDatabaseMissing('invoice_return_items', ['invoice_return_id' => $return->id]);

        // التحقق من استعادة المخزون الأصلي (خصم الـ 4 من المخزن): 94 - 4 = 90
        $this->assertEquals(90, $this->warehouse->drugs()->where('drug_id', $this->drugA->id)->first()->pivot->quantity);

        // التحقق من استعادة كميات بند الفاتورة بالكامل
        $detail->refresh();
        $this->assertEquals(10, $detail->quantity);
        $this->assertEquals(0, $detail->returned_quantity);
        $this->assertEquals(900.00, $detail->row_total);

        // التحقق من استعادة إجماليات الفاتورة
        $invoice->refresh();
        $this->assertEquals(900.00, $invoice->final_total);
        $this->assertEquals(900.00, $invoice->remaining_amount);
        $this->assertEquals(2, $invoice->status); // آجل

        // التحقق من توثيق عملية الحذف في سجل الرقابة
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'module' => 'InvoiceReturn',
            'entity_id' => $return->id,
        ]);
    }

    public function test_cannot_delete_return_if_warehouse_stock_insufficient()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);

        $detail = $invoice->details->first();

        // تسجيل مرتجع بـ 4 قطع
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 4],
            ],
        ]);

        $return = InvoiceReturn::first();

        // استنفاد المخزن (رصيد 2 فقط بينما المراد خصمه 4)
        $this->warehouse->drugs()->updateExistingPivot($this->drugA->id, ['quantity' => 2]);

        // محاولة الحذف
        $response = $this->actingAs($this->admin)->delete(route('admin.returns.destroy', $return->id));
        $response->assertSessionHas('error');

        // التأكد من بقاء المرتجع في قاعدة البيانات
        $this->assertDatabaseHas('invoice_returns', ['id' => $return->id]);
    }

    public function test_return_number_is_globally_unique_when_invoices_share_same_serial_number()
    {
        // فاتورتان مختلفتان تشتركان في نفس الرقم التسلسلي (مثلاً 5000)
        $invoice1 = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);
        $invoice1->update(['serial_number' => '5000']);

        $invoice2 = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);
        $invoice2->update(['serial_number' => '5000']);

        // إنشاء مرتجع للفاتورة الأولى
        $detail1 = $invoice1->details->first();
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice1->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail1->id, 'quantity' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $return1 = InvoiceReturn::where('invoice_id', $invoice1->id)->first();
        $this->assertEquals('RET-5000-1', $return1->return_number);

        // إنشاء مرتجع للفاتورة الثانية التي تحمل نفس السيريال 5000
        $detail2 = $invoice2->details->first();
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice2->id), [
            'return_date' => '2026-10-14',
            'items' => [
                ['detail_id' => $detail2->id, 'quantity' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $return2 = InvoiceReturn::where('invoice_id', $invoice2->id)->first();
        $this->assertNotNull($return2);
        $this->assertEquals('RET-5000-2', $return2->return_number);
    }

    public function test_return_number_does_not_collide_after_return_deletion()
    {
        $invoice = $this->createInvoice([
            ['drug' => $this->drugA, 'qty' => 10, 'discount' => 10],
        ], status: 2, paid: 0);
        $invoice->update(['serial_number' => '7777']);

        $detail = $invoice->details->first();

        // إنشاء مرتجع أول
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-12',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 1],
            ],
        ]);
        $return1 = InvoiceReturn::where('invoice_id', $invoice->id)->first();
        $this->assertEquals('RET-7777-1', $return1->return_number);

        // إنشاء مرتجع ثانٍ
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-13',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 1],
            ],
        ]);
        $return2 = InvoiceReturn::where('invoice_id', $invoice->id)->where('id', '!=', $return1->id)->first();
        $this->assertEquals('RET-7777-2', $return2->return_number);

        // حذف المرتجع الأول
        $this->actingAs($this->admin)->delete(route('admin.returns.destroy', $return1->id));

        // إنشاء مرتجع جديد (يجب أن يحصل على RET-7777-3 ولا يتصادم مع RET-7777-2 الموجود)
        $this->actingAs($this->admin)->post(route('admin.invoices.returns.store', $invoice->id), [
            'return_date' => '2026-10-14',
            'items' => [
                ['detail_id' => $detail->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $return3 = InvoiceReturn::where('invoice_id', $invoice->id)->latest('id')->first();
        $this->assertEquals('RET-7777-3', $return3->return_number);
    }
}
