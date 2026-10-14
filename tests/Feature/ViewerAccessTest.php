<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Center;
use App\Models\Drug;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceReturn;
use App\Models\InvoiceReturnItem;
use App\Models\Pharmacist;
use App\Models\Province;
use App\Models\Representative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $viewer;

    protected User $admin;

    protected Invoice $invoice;

    protected InvoiceReturn $return;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = User::factory()->create([
            'role' => 'viewer',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $province = Province::create(['name' => 'الجيزة']);
        $center = Center::create(['name' => 'الدقي', 'province_id' => $province->id]);
        $rep = Representative::create(['name' => 'مندوب تجريبي', 'phone' => '01011111111']);
        $pharmacist = Pharmacist::create([
            'name' => 'صيدلية الأمل',
            'phone' => '01222222222',
            'address' => 'شارع التحرير',
            'center_id' => $center->id,
        ]);
        $drug = Drug::create([
            'name' => 'أوجمنتين 1 جم',
            'price' => 120.00,
            'line' => 1,
        ]);

        $this->invoice = Invoice::create([
            'serial_number' => 'INV-TEST-888',
            'invoice_date' => '2026-10-15',
            'line' => 1,
            'representative_id' => $rep->id,
            'medical_representative_id' => $rep->id,
            'pharmacist_id' => $pharmacist->id,
            'total_amount' => 1200.00,
            'total_discount' => 120.00,
            'final_total' => 1080.00,
            'paid_amount' => 1080.00,
            'remaining_amount' => 0.00,
            'status' => 1,
        ]);

        $detail = InvoiceDetail::create([
            'invoice_id' => $this->invoice->id,
            'drug_id' => $drug->id,
            'quantity' => 8,
            'returned_quantity' => 2,
            'unit_price' => 120.00,
            'pharmacist_discount_percentage' => 10.00,
            'row_total' => 864.00,
        ]);

        $this->return = InvoiceReturn::create([
            'return_number' => 'RET-INV-TEST-888-1',
            'invoice_id' => $this->invoice->id,
            'return_date' => '2026-10-15',
            'total_return_public_price' => 240.00,
            'total_return_discount' => 24.00,
            'total_return_amount' => 216.00,
            'refund_amount' => 216.00,
            'notes' => 'مرتجع تجريبي للفحص',
            'created_by' => $this->admin->id,
        ]);

        InvoiceReturnItem::create([
            'invoice_return_id' => $this->return->id,
            'invoice_detail_id' => $detail->id,
            'drug_id' => $drug->id,
            'quantity' => 2,
            'unit_price' => 120.00,
            'pharmacist_discount_percentage' => 10.00,
            'subtotal_public' => 240.00,
            'discount_amount' => 24.00,
            'subtotal_net' => 216.00,
        ]);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'user_role' => 'admin',
            'action' => 'returned',
            'module' => 'InvoiceReturn',
            'entity_type' => InvoiceReturn::class,
            'entity_id' => $this->return->id,
            'entity_name' => $this->return->return_number,
            'description' => 'تسجيل مرتجع تجريبي',
            'outcome' => 'success',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_viewer_can_view_returns_index_and_see_records()
    {
        $response = $this->actingAs($this->viewer)->get(route('viewer.returns.index'));

        $response->assertOk();
        $response->assertSee('سجل مرتجعات المبيعات');
        $response->assertSee($this->return->return_number);
        $response->assertSee('صيدلية الأمل');
    }

    public function test_viewer_can_view_single_return_voucher()
    {
        $response = $this->actingAs($this->viewer)->get(route('viewer.returns.show', $this->return->id));

        $response->assertOk();
        $response->assertSee('إيصال مرتجع مبيعات');
        $response->assertSee($this->return->return_number);
        $response->assertSee('أوجمنتين 1 جم');
        $response->assertDontSee('إلغاء وحذف');
        $response->assertDontSee('تعديل المرتجع');
    }

    public function test_viewer_can_view_returns_history_on_invoice_show()
    {
        $response = $this->actingAs($this->viewer)->get(route('viewer.invoices.show', $this->invoice->id));

        $response->assertOk();
        $response->assertSee('سجل المرتجعات الصادرة لهذه الفاتورة');
        $response->assertSee($this->return->return_number);
        $response->assertSee('مرتجع: 2');
    }

    public function test_viewer_can_view_audit_logs_index()
    {
        $response = $this->actingAs($this->viewer)->get(route('viewer.audit-logs.index'));

        $response->assertOk();
        $response->assertSee('سجل العمليات والرقابة');
        $response->assertSee($this->return->return_number);
        $response->assertSee('تسجيل مرتجع تجريبي');
    }

    public function test_viewer_can_fetch_audit_log_details_json()
    {
        $log = AuditLog::where('action', 'returned')->first();

        $response = $this->actingAs($this->viewer)->getJson(route('viewer.audit-logs.show', $log->id));

        $response->assertOk();
        $response->assertJson([
            'id' => $log->id,
            'action' => 'مرتجع مبيعات',
            'module' => 'مرتجعات الفواتير',
            'entity' => $this->return->return_number,
        ]);
    }

    public function test_viewer_can_export_returns_and_audit_logs_to_csv()
    {
        $returnsExport = $this->actingAs($this->viewer)->get(route('viewer.returns.index', ['export' => 'excel']));
        $returnsExport->assertOk();
        $returnsExport->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $auditExport = $this->actingAs($this->viewer)->get(route('viewer.audit-logs.index', ['export' => 'excel']));
        $auditExport->assertOk();
        $auditExport->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_viewer_cannot_access_admin_write_routes()
    {
        // المشاهد ممنوع من دخول مسارات الإدارة (403 Forbidden)
        $this->actingAs($this->viewer)->get(route('admin.invoices.returns.create', $this->invoice->id))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('admin.returns.edit', $this->return->id))->assertForbidden();
        $this->actingAs($this->viewer)->delete(route('admin.returns.destroy', $this->return->id))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('admin.audit-logs.index'))->assertForbidden();
    }
}
