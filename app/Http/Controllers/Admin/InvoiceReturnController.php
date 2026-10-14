<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorDeal;
use App\Models\Invoice;
use App\Models\InvoiceReturn;
use App\Models\InvoiceReturnItem;
use App\Models\Pharmacist;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceReturnController extends Controller
{
    /**
     * عرض شاشة المرتجعات العامة
     */
    public function index(Request $request)
    {
        $query = InvoiceReturn::with([
            'invoice.pharmacist.center',
            'invoice.representative',
            'items.drug',
        ]);

        if ($request->filled('start_date')) {
            $query->whereDate('return_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('return_date', '<=', $request->end_date);
        }
        if ($request->filled('return_number')) {
            $query->where('return_number', 'like', '%'.$request->return_number.'%');
        }
        if ($request->filled('invoice_serial')) {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('serial_number', 'like', '%'.$request->invoice_serial.'%');
            });
        }
        if ($request->filled('pharmacist_id')) {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('pharmacist_id', $request->pharmacist_id);
            });
        }

        // إحصائيات عامة للمرتجعات
        $statsQuery = clone $query;
        $stats = [
            'total_returns_count' => $statsQuery->count(),
            'total_net_returned' => $statsQuery->sum('total_return_amount'),
            'total_refunded' => $statsQuery->sum('refund_amount'),
        ];

        if ($request->has('export') && $request->export == 'excel') {
            return $this->exportReturns($query->latest('return_date')->get());
        }

        $returns = $query->latest('return_date')->latest('id')->paginate(20)->withQueryString();
        $pharmacists = Pharmacist::select('id', 'name')->orderBy('name')->get();

        return view('admin.invoices.returns.index', compact('returns', 'pharmacists', 'stats'));
    }

    /**
     * صفحة تسجيل مرتجع لفاتورة معينة
     */
    public function create(Invoice $invoice)
    {
        $invoice->load([
            'details.drug',
            'pharmacist.center',
            'representative',
            'medicalRepresentative',
            'doctors',
            'payments',
            'returns.items.drug',
        ]);

        // التحقق من وجود كميات قابلة للإرجاع
        $availableItemsCount = $invoice->details->sum('quantity');
        if ($availableItemsCount <= 0) {
            return redirect()->route('admin.invoices.show', $invoice->id)
                ->with('error', 'تم إرجاع جميع كميات هذه الفاتورة بالكامل مسبقاً ولا توجد بنود متاحة للإرجاع.');
        }

        return view('admin.invoices.returns.create', compact('invoice'));
    }

    /**
     * معالجة وحفظ المرتجع وتحديث كميات الفاتورة والمخزن والإجماليات
     */
    public function store(Request $request, Invoice $invoice)
    {
        $request->validate([
            'return_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.detail_id' => 'required|exists:invoice_details,id',
            'items.*.quantity' => 'required|integer|min:0',
        ], [
            'return_date.required' => 'تاريخ المرتجع مطلوب.',
            'items.required' => 'يجب تحديد أصناف المرتجع.',
        ]);

        // التحقق من إدخال كمية مرتجعة واحدة على الأقل أكبر من الصفر
        $totalItemsToReturn = collect($request->items)->sum('quantity');
        if ($totalItemsToReturn <= 0) {
            return redirect()->back()
                ->withErrors(['items' => 'يجب إدخال كمية مرتجعة أكبر من الصفر لصنف واحد على الأقل.'])
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // 1. تحديد مخزن المنطقة الخاص بالفاتورة
            $zoneData = $this->resolveZoneAndWarehouse($invoice->pharmacist_id, $invoice->line);
            $warehouse = $zoneData['warehouse'];

            // 2. التحقق من صحة الكميات المطلوبة للإرجاع لكل بند
            foreach ($request->items as $itemData) {
                $qty = (int) ($itemData['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $detail = $invoice->details()->where('id', $itemData['detail_id'])->first();
                if (! $detail) {
                    throw ValidationException::withMessages(['items' => 'أحد بنود المرتجع غير تابع لهذه الفاتورة.']);
                }

                if ($qty > $detail->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "الكمية المرتجعة للصنف ({$detail->drug->name}) هي {$qty} وهي أكبر من الكمية المتبقية بالفاتورة ({$detail->quantity}).",
                    ]);
                }
            }

            // 3. إنشاء إيصال المرتجع
            $returnNumber = InvoiceReturn::generateReturnNumber($invoice);
            $invoiceReturn = InvoiceReturn::create([
                'return_number' => $returnNumber,
                'invoice_id' => $invoice->id,
                'return_date' => $request->return_date,
                'total_return_public_price' => 0,
                'total_return_discount' => 0,
                'total_return_amount' => 0,
                'refund_amount' => 0,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            $totalReturnPublic = 0;
            $totalReturnDiscount = 0;

            // 4. معالجة الأصناف وإعادة المخزون وتحديث بنود الفاتورة
            foreach ($request->items as $itemData) {
                $qty = (int) ($itemData['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $detail = $invoice->details()->where('id', $itemData['detail_id'])->first();

                // إعادة الكمية المرتجعة لمخزن المنطقة
                $this->updateWarehouseStock($warehouse, $detail->drug_id, $qty, 'add');

                if ($warehouse) {
                    AuditService::log(
                        action: 'stock_added',
                        module: 'Warehouse',
                        entity: $warehouse,
                        description: "إعادة كمية مرتجع مبيعات ({$returnNumber}) للفاتورة #{$invoice->serial_number} إلى مخزن ({$warehouse->name}): ".($detail->drug->name ?? "دواء #{$detail->drug_id}")." (كمية +{$qty})",
                        newValues: [
                            'warehouse' => $warehouse->name,
                            'drug_id' => $detail->drug_id,
                            'drug_name' => $detail->drug->name ?? null,
                            'returned_quantity' => $qty,
                            'invoice_serial' => $invoice->serial_number,
                            'return_number' => $returnNumber,
                        ]
                    );
                }

                // حساب القيم المالية للبند المرتجع
                $unitPrice = $detail->unit_price;
                $discountPerc = $detail->pharmacist_discount_percentage;
                $rowPublic = $unitPrice * $qty;
                $rowDiscount = $rowPublic * ($discountPerc / 100);
                $rowNet = $rowPublic - $rowDiscount;

                $totalReturnPublic += $rowPublic;
                $totalReturnDiscount += $rowDiscount;

                // إنشاء بند في جدول تفاصيل المرتجع
                InvoiceReturnItem::create([
                    'invoice_return_id' => $invoiceReturn->id,
                    'invoice_detail_id' => $detail->id,
                    'drug_id' => $detail->drug_id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'pharmacist_discount_percentage' => $discountPerc,
                    'subtotal_public' => $rowPublic,
                    'discount_amount' => $rowDiscount,
                    'subtotal_net' => $rowNet,
                ]);

                // تحديث كميات بند الفاتورة وإجمالي السطر
                $newQty = $detail->quantity - $qty;
                $newReturnedQty = ($detail->returned_quantity ?? 0) + $qty;
                $newRowTotal = ($newQty * $unitPrice) * (1 - ($discountPerc / 100));

                $detail->update([
                    'quantity' => $newQty,
                    'returned_quantity' => $newReturnedQty,
                    'row_total' => round($newRowTotal, 2),
                ]);
            }

            $totalReturnNet = round($totalReturnPublic - $totalReturnDiscount, 2);

            // تحديث إجماليات إيصال المرتجع
            $invoiceReturn->update([
                'total_return_public_price' => $totalReturnPublic,
                'total_return_discount' => $totalReturnDiscount,
                'total_return_amount' => $totalReturnNet,
            ]);

            // 5. إعادة حساب إجماليات الفاتورة بناءً على الكميات الجديدة
            $invoice->refresh();
            $newInvoicePublic = $invoice->details()->sum(DB::raw('quantity * unit_price'));
            $newInvoiceDiscount = $invoice->details()->sum(DB::raw('quantity * unit_price * pharmacist_discount_percentage / 100'));
            $newInvoiceFinal = round($newInvoicePublic - $newInvoiceDiscount, 2);

            // 6. تسوية المدفوعات والمتبقي (الاسترداد النقدي في حال سداد سابق)
            $currentTotalPaid = (float) $invoice->payments()->sum('amount');
            $refundAmount = 0;

            if ($currentTotalPaid > $newInvoiceFinal) {
                // الفاتورة كان مسدد منها أكثر من الصافي الجديد بعد المرتجع، فيتم استرداد الفرق نقداً للصيدلية
                $refundAmount = round($currentTotalPaid - $newInvoiceFinal, 2);
                $toDeduct = $refundAmount;

                // تعديل سجلات المدفوعات السابقة تنازلياً (من الأحدث للأقدم) لضمان عدم وجود سجلات سالبة تشوه التحصيلات
                $payments = $invoice->payments()->orderBy('id', 'desc')->get();
                foreach ($payments as $payment) {
                    if ($toDeduct <= 0) {
                        break;
                    }

                    if ($payment->amount <= $toDeduct) {
                        $toDeduct = round($toDeduct - $payment->amount, 2);
                        $payment->delete();
                    } else {
                        $newAmount = round($payment->amount - $toDeduct, 2);
                        $payment->update([
                            'amount' => $newAmount,
                            'notes' => trim(($payment->notes ? $payment->notes.' | ' : '')."تم تعديل المبلغ بخصم {$toDeduct} ج.م لاسترداد نقدي لمرتجع {$returnNumber}"),
                        ]);
                        $toDeduct = 0;
                    }
                }

                $invoiceReturn->update(['refund_amount' => $refundAmount]);
            }

            // تحديث إجمالي المدفوع والمتبقي وحالة الفاتورة
            $finalPaidAmount = (float) $invoice->payments()->sum('amount');
            $newRemaining = round(max(0, $newInvoiceFinal - $finalPaidAmount), 2);

            $newStatus = 2; // آجل
            if ($newInvoiceFinal <= 0 || $newRemaining <= 0) {
                $newRemaining = 0;
                $newStatus = 1; // خالص
            } elseif ($finalPaidAmount > 0) {
                $newStatus = 3; // جزئي
            }

            $invoice->update([
                'total_amount' => $newInvoicePublic,
                'total_discount' => $newInvoiceDiscount,
                'final_total' => $newInvoiceFinal,
                'paid_amount' => $finalPaidAmount,
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
            ]);

            // 7. تحديث تارجت الأطباء إن كانت الفاتورة مرتبطة باتفاقات
            $this->syncDoctorDeals($invoice, $newStatus);

            // 8. تسجيل العملية في سجل التدقيق (Audit Logging)
            AuditService::log(
                action: 'returned',
                module: 'InvoiceReturn',
                entity: $invoiceReturn,
                description: "تم تسجيل مرتجع مبيعات رقم ({$returnNumber}) للفاتورة #{$invoice->serial_number} بصافي قيمة ".number_format($totalReturnNet, 2).' ج.م واسترداد نقدي بقيمة '.number_format($refundAmount, 2).' ج.م.',
                newValues: [
                    'return_number' => $returnNumber,
                    'invoice_id' => $invoice->id,
                    'invoice_serial' => $invoice->serial_number,
                    'returned_amount' => $totalReturnNet,
                    'refund_amount' => $refundAmount,
                    'new_invoice_net' => $newInvoiceFinal,
                    'new_paid' => $finalPaidAmount,
                    'new_remaining' => $newRemaining,
                ]
            );

            DB::commit();

            return redirect()->route('admin.returns.show', $invoiceReturn->id)
                ->with('success', "تم تسجيل المرتجع ({$returnNumber}) بنجاح وتحديث كميات الفاتورة والمخزن والإجماليات.");

        } catch (ValidationException $e) {
            DB::rollBack();

            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'حدث خطأ أثناء تسجيل المرتجع: '.$e->getMessage())->withInput();
        }
    }

    /**
     * عرض تفاصيل إيصال المرتجع
     */
    public function show(InvoiceReturn $return)
    {
        $return->load([
            'invoice.pharmacist.center',
            'invoice.representative',
            'invoice.medicalRepresentative',
            'invoice.doctors',
            'items.drug',
            'creator',
        ]);

        return view('admin.invoices.returns.show', compact('return'));
    }

    /**
     * عرض شاشة تعديل إيصال المرتجع
     */
    public function edit(InvoiceReturn $return)
    {
        $return->load([
            'invoice.pharmacist.center',
            'invoice.representative',
            'invoice.details.drug',
            'items.drug',
        ]);

        $invoice = $return->invoice;

        // تجهيز بيانات كل صنف في الفاتورة مع الكمية المرتجعة حالياً في هذا الإيصال والحد الأقصى المتاح للإرجاع
        $detailsWithReturn = $invoice->details->map(function ($detail) use ($return) {
            $returnItem = $return->items->firstWhere('invoice_detail_id', $detail->id);
            $currentReturnQty = $returnItem ? $returnItem->quantity : 0;
            $maxReturnable = $detail->quantity + $currentReturnQty;

            return [
                'detail' => $detail,
                'return_item' => $returnItem,
                'current_return_qty' => $currentReturnQty,
                'max_returnable' => $maxReturnable,
            ];
        });

        return view('admin.invoices.returns.edit', compact('return', 'invoice', 'detailsWithReturn'));
    }

    /**
     * تحديث إيصال المرتجع ومعالجة الفروق في المخازن والفاتورة
     */
    public function update(Request $request, InvoiceReturn $return)
    {
        $request->validate([
            'return_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.detail_id' => 'required|exists:invoice_details,id',
            'items.*.quantity' => 'required|integer|min:0',
        ], [
            'return_date.required' => 'تاريخ المرتجع مطلوب.',
            'items.required' => 'يجب تحديد الأصناف.',
            'items.*.quantity.min' => 'الكمية المرتجعة يجب ألا تقل عن 0.',
        ]);

        $invoice = $return->invoice;
        $returnNumber = $return->return_number;

        // التحقق من وجود صنف واحد على الأقل بكمية أكبر من 0
        $hasAnyQuantity = collect($request->items)->sum(fn ($i) => (int) ($i['quantity'] ?? 0)) > 0;
        if (! $hasAnyQuantity) {
            throw ValidationException::withMessages([
                'items' => 'يجب إرجاع كمية واحدة على الأقل أكبر من الصفر. إذا كنت ترغب في إلغاء المرتجع بالكامل، يمكنك استخدام زر الحذف.',
            ]);
        }

        // تحديد مخزن المنطقة
        ['zone' => $zone, 'warehouse' => $warehouse] = $this->resolveZoneAndWarehouse($invoice->pharmacist_id, $invoice->line);

        // التحقق المسبق من كل صنف (الحد الأقصى المتاح + كفاية رصيد المخزن في حال تقليل كمية المرتجع)
        foreach ($request->items as $itemData) {
            $newQty = (int) ($itemData['quantity'] ?? 0);
            $detail = $invoice->details()->where('id', $itemData['detail_id'])->first();
            if (! $detail) {
                continue;
            }

            $oldReturnItem = $return->items()->where('invoice_detail_id', $detail->id)->first();
            $oldQty = $oldReturnItem ? (int) $oldReturnItem->quantity : 0;
            $maxAllowed = $detail->quantity + $oldQty;

            if ($newQty > $maxAllowed) {
                $drugName = $detail->drug->name ?? "#{$detail->drug_id}";
                throw ValidationException::withMessages([
                    'items' => "الكمية المرتجعة للصنف ({$drugName}) وهي ({$newQty}) تتجاوز الحد الأقصى المسموح به وهو ({$maxAllowed}).",
                ]);
            }

            $delta = $newQty - $oldQty;
            // إذا كان التعديل يقضي بتقليل كمية المرتجع ($delta < 0)، فهذا يعني استرجاع بضاعة للفاتورة وخصمها من المخزن
            if ($delta < 0 && $warehouse) {
                $qtyToDeduct = abs($delta);
                $stockRecord = $warehouse->drugs()->where('drug_id', $detail->drug_id)->first();
                $currentStock = $stockRecord ? (int) $stockRecord->pivot->quantity : 0;

                if ($currentStock < $qtyToDeduct) {
                    $drugName = $detail->drug->name ?? "#{$detail->drug_id}";
                    throw ValidationException::withMessages([
                        'items' => "رصيد المخزن ({$warehouse->name}) الحالي للصنف ({$drugName}) هو ({$currentStock})، وهو غير كافٍ لخصم فرق الكمية المطلوب إعادتها للفاتورة ({$qtyToDeduct}).",
                    ]);
                }
            }
        }

        try {
            DB::beginTransaction();

            $oldReturnSummary = [
                'total_amount' => $return->total_return_amount,
                'refund_amount' => $return->refund_amount,
                'items' => $return->items->map(fn ($it) => ['drug_id' => $it->drug_id, 'qty' => $it->quantity])->toArray(),
            ];

            $totalReturnPublic = 0;
            $totalReturnDiscount = 0;

            foreach ($request->items as $itemData) {
                $newQty = (int) ($itemData['quantity'] ?? 0);
                $detail = $invoice->details()->where('id', $itemData['detail_id'])->first();
                if (! $detail) {
                    continue;
                }

                $oldReturnItem = $return->items()->where('invoice_detail_id', $detail->id)->first();
                $oldQty = $oldReturnItem ? (int) $oldReturnItem->quantity : 0;
                $delta = $newQty - $oldQty;

                // 1. معالجة رصيد المخزن للفارق
                if ($delta > 0 && $warehouse) {
                    $this->updateWarehouseStock($warehouse, $detail->drug_id, $delta, 'add');
                    AuditService::log(
                        action: 'stock_added',
                        module: 'Warehouse',
                        entity: $warehouse,
                        description: "إضافة كمية فرق تعديل مرتجع مبيعات ({$returnNumber}) للفاتورة #{$invoice->serial_number} إلى مخزن ({$warehouse->name}): ".($detail->drug->name ?? "دواء #{$detail->drug_id}")." (كمية +{$delta})",
                        newValues: [
                            'warehouse' => $warehouse->name,
                            'drug_id' => $detail->drug_id,
                            'drug_name' => $detail->drug->name ?? null,
                            'delta' => $delta,
                            'return_number' => $returnNumber,
                        ]
                    );
                } elseif ($delta < 0 && $warehouse) {
                    $this->updateWarehouseStock($warehouse, $detail->drug_id, abs($delta), 'deduct');
                    AuditService::log(
                        action: 'stock_returned',
                        module: 'Warehouse',
                        entity: $warehouse,
                        description: "خصم كمية فرق تعديل مرتجع مبيعات ({$returnNumber}) للفاتورة #{$invoice->serial_number} من مخزن ({$warehouse->name}): ".($detail->drug->name ?? "دواء #{$detail->drug_id}").' (كمية -'.abs($delta).')',
                        newValues: [
                            'warehouse' => $warehouse->name,
                            'drug_id' => $detail->drug_id,
                            'drug_name' => $detail->drug->name ?? null,
                            'delta' => $delta,
                            'return_number' => $returnNumber,
                        ]
                    );
                }

                // 2. تحديث بند الفاتورة
                $unitPrice = $detail->unit_price;
                $discountPerc = $detail->pharmacist_discount_percentage;

                $newDetailQty = $detail->quantity - $delta;
                $newReturnedQty = max(0, ($detail->returned_quantity ?? 0) + $delta);
                $newRowTotal = ($newDetailQty * $unitPrice) * (1 - ($discountPerc / 100));

                $detail->update([
                    'quantity' => $newDetailQty,
                    'returned_quantity' => $newReturnedQty,
                    'row_total' => round($newRowTotal, 2),
                ]);

                // 3. تحديث أو حذف أو إنشاء بند المرتجع
                if ($newQty > 0) {
                    $rowPublic = $unitPrice * $newQty;
                    $rowDiscount = $rowPublic * ($discountPerc / 100);
                    $rowNet = $rowPublic - $rowDiscount;

                    $totalReturnPublic += $rowPublic;
                    $totalReturnDiscount += $rowDiscount;

                    if ($oldReturnItem) {
                        $oldReturnItem->update([
                            'quantity' => $newQty,
                            'unit_price' => $unitPrice,
                            'pharmacist_discount_percentage' => $discountPerc,
                            'subtotal_public' => $rowPublic,
                            'discount_amount' => $rowDiscount,
                            'subtotal_net' => $rowNet,
                        ]);
                    } else {
                        InvoiceReturnItem::create([
                            'invoice_return_id' => $return->id,
                            'invoice_detail_id' => $detail->id,
                            'drug_id' => $detail->drug_id,
                            'quantity' => $newQty,
                            'unit_price' => $unitPrice,
                            'pharmacist_discount_percentage' => $discountPerc,
                            'subtotal_public' => $rowPublic,
                            'discount_amount' => $rowDiscount,
                            'subtotal_net' => $rowNet,
                        ]);
                    }
                } else {
                    if ($oldReturnItem) {
                        $oldReturnItem->delete();
                    }
                }
            }

            $totalReturnNet = round($totalReturnPublic - $totalReturnDiscount, 2);

            // 4. إعادة حساب إجماليات الفاتورة
            $invoice->refresh();
            $newInvoicePublic = $invoice->details()->sum(DB::raw('quantity * unit_price'));
            $newInvoiceDiscount = $invoice->details()->sum(DB::raw('quantity * unit_price * pharmacist_discount_percentage / 100'));
            $newInvoiceFinal = round($newInvoicePublic - $newInvoiceDiscount, 2);

            // 5. تسوية المدفوعات والمتبقي والاسترداد النقدي
            $currentTotalPaid = (float) $invoice->payments()->sum('amount');
            $refundAmount = (float) $return->refund_amount;

            if ($currentTotalPaid > $newInvoiceFinal) {
                // المبلغ المسدد أكبر من الصافي الجديد -> استرداد الفرق نقداً وخصمه من المدفوعات السابقة
                $excess = round($currentTotalPaid - $newInvoiceFinal, 2);
                $toDeduct = $excess;

                $payments = $invoice->payments()->orderBy('id', 'desc')->get();
                foreach ($payments as $payment) {
                    if ($toDeduct <= 0) {
                        break;
                    }

                    if ($payment->amount <= $toDeduct) {
                        $toDeduct = round($toDeduct - $payment->amount, 2);
                        $payment->delete();
                    } else {
                        $newAmount = round($payment->amount - $toDeduct, 2);
                        $payment->update([
                            'amount' => $newAmount,
                            'notes' => trim(($payment->notes ? $payment->notes.' | ' : '')."تم تعديل المبلغ بخصم {$toDeduct} ج.م لتعديل مرتجع {$returnNumber}"),
                        ]);
                        $toDeduct = 0;
                    }
                }

                $refundAmount = round($refundAmount + $excess, 2);
            } else {
                // إذا انخفض المرتجع وزاد صافي الفاتورة، لا يمكن للمسترد نقداً أن يتجاوز صافي قيمة المرتجع الجديد
                $refundAmount = min($refundAmount, $totalReturnNet);
            }

            // تحديث إيصال المرتجع
            $return->update([
                'return_date' => $request->return_date,
                'notes' => $request->notes,
                'total_return_public_price' => $totalReturnPublic,
                'total_return_discount' => $totalReturnDiscount,
                'total_return_amount' => $totalReturnNet,
                'refund_amount' => $refundAmount,
            ]);

            // تحديث حالة الفاتورة ورصيدها المتبقي
            $finalPaidAmount = (float) $invoice->payments()->sum('amount');
            $newRemaining = round(max(0, $newInvoiceFinal - $finalPaidAmount), 2);

            $newStatus = 2; // آجل
            if ($newInvoiceFinal <= 0 || $newRemaining <= 0) {
                $newRemaining = 0;
                $newStatus = 1; // خالص
            } elseif ($finalPaidAmount > 0) {
                $newStatus = 3; // جزئي
            }

            $invoice->update([
                'total_amount' => $newInvoicePublic,
                'total_discount' => $newInvoiceDiscount,
                'final_total' => $newInvoiceFinal,
                'paid_amount' => $finalPaidAmount,
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
            ]);

            // 6. مزامنة اتفاقات الأطباء
            $this->syncDoctorDeals($invoice, $newStatus);

            // 7. تسجيل التدقيق
            AuditService::log(
                action: 'updated',
                module: 'InvoiceReturn',
                entity: $return,
                description: "تم تعديل إيصال مرتجع المبيعات رقم ({$returnNumber}) للفاتورة #{$invoice->serial_number}. الصافي الجديد: ".number_format($totalReturnNet, 2).' ج.م.',
                oldValues: $oldReturnSummary,
                newValues: [
                    'return_number' => $returnNumber,
                    'returned_amount' => $totalReturnNet,
                    'refund_amount' => $refundAmount,
                    'invoice_final' => $newInvoiceFinal,
                    'paid_amount' => $finalPaidAmount,
                    'remaining_amount' => $newRemaining,
                ]
            );

            DB::commit();

            return redirect()->route('admin.returns.show', $return->id)
                ->with('success', "تم تحديث إيصال المرتجع ({$returnNumber}) بنجاح وتحديث أرصدة المخزن وبيانات الفاتورة.");

        } catch (ValidationException $e) {
            DB::rollBack();

            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'حدث خطأ أثناء تعديل المرتجع: '.$e->getMessage())->withInput();
        }
    }

    /**
     * إلغاء وحذف إيصال المرتجع وعكس جميع العمليات على المخزن والفاتورة
     */
    public function destroy(InvoiceReturn $return)
    {
        $invoice = $return->invoice;
        $returnNumber = $return->return_number;

        // تحديد مخزن المنطقة
        ['zone' => $zone, 'warehouse' => $warehouse] = $this->resolveZoneAndWarehouse($invoice->pharmacist_id, $invoice->line);

        // التحقق المسبق من توفر رصيد كافٍ في المخزن لخصم البضاعة المرتجعة المستردة للفاتورة
        if ($warehouse) {
            foreach ($return->items as $returnItem) {
                $qtyToDeduct = $returnItem->quantity;
                if ($qtyToDeduct <= 0) {
                    continue;
                }

                $stockRecord = $warehouse->drugs()->where('drug_id', $returnItem->drug_id)->first();
                $currentStock = $stockRecord ? (int) $stockRecord->pivot->quantity : 0;

                if ($currentStock < $qtyToDeduct) {
                    $drugName = $returnItem->drug->name ?? "#{$returnItem->drug_id}";

                    return redirect()->back()->with('error', "لا يمكن إلغاء المرتجع: رصيد المخزن ({$warehouse->name}) الحالي للصنف ({$drugName}) هو ({$currentStock}) وهو أقل من الكمية المراد خصمها ({$qtyToDeduct}) نتيجة بيع أو صرف بضاعة من المخزن لاحقاً.");
                }
            }
        }

        try {
            DB::beginTransaction();

            $oldReturnData = [
                'return_number' => $return->return_number,
                'invoice_serial' => $invoice->serial_number,
                'total_amount' => $return->total_return_amount,
                'refund_amount' => $return->refund_amount,
                'items' => $return->items->map(fn ($it) => ['drug' => $it->drug->name ?? $it->drug_id, 'quantity' => $it->quantity])->toArray(),
            ];

            // 1. خصم البضاعة من المخزن وإعادة كميات الفاتورة
            foreach ($return->items as $returnItem) {
                $qty = $returnItem->quantity;
                if ($qty <= 0) {
                    continue;
                }

                if ($warehouse) {
                    $this->updateWarehouseStock($warehouse, $returnItem->drug_id, $qty, 'deduct');
                    AuditService::log(
                        action: 'stock_returned',
                        module: 'Warehouse',
                        entity: $warehouse,
                        description: "خصم كمية من مخزن ({$warehouse->name}) نتيجة إلغاء وحذف مرتجع المبيعات ({$returnNumber}) للفاتورة #{$invoice->serial_number}: ".($returnItem->drug->name ?? "دواء #{$returnItem->drug_id}")." (كمية -{$qty})",
                        newValues: [
                            'warehouse' => $warehouse->name,
                            'drug_id' => $returnItem->drug_id,
                            'drug_name' => $returnItem->drug->name ?? null,
                            'deducted_quantity' => $qty,
                            'return_number' => $returnNumber,
                            'invoice_serial' => $invoice->serial_number,
                        ]
                    );
                }

                // استعادة الكمية لبند الفاتورة
                $detail = $invoice->details()->where('id', $returnItem->invoice_detail_id)->first();
                if ($detail) {
                    $newQty = $detail->quantity + $qty;
                    $newReturnedQty = max(0, ($detail->returned_quantity ?? 0) - $qty);
                    $newRowTotal = ($newQty * $detail->unit_price) * (1 - ($detail->pharmacist_discount_percentage / 100));

                    $detail->update([
                        'quantity' => $newQty,
                        'returned_quantity' => $newReturnedQty,
                        'row_total' => round($newRowTotal, 2),
                    ]);
                }
            }

            // 2. إعادة حساب إجماليات الفاتورة
            $invoice->refresh();
            $newInvoicePublic = $invoice->details()->sum(DB::raw('quantity * unit_price'));
            $newInvoiceDiscount = $invoice->details()->sum(DB::raw('quantity * unit_price * pharmacist_discount_percentage / 100'));
            $newInvoiceFinal = round($newInvoicePublic - $newInvoiceDiscount, 2);

            // 3. تحديث رصيد الفاتورة وحالتها
            $finalPaidAmount = (float) $invoice->payments()->sum('amount');
            $newRemaining = round(max(0, $newInvoiceFinal - $finalPaidAmount), 2);

            $newStatus = 2; // آجل
            if ($newInvoiceFinal <= 0 || $newRemaining <= 0) {
                $newRemaining = 0;
                $newStatus = 1; // خالص
            } elseif ($finalPaidAmount > 0) {
                $newStatus = 3; // جزئي
            }

            $invoice->update([
                'total_amount' => $newInvoicePublic,
                'total_discount' => $newInvoiceDiscount,
                'final_total' => $newInvoiceFinal,
                'paid_amount' => $finalPaidAmount,
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
            ]);

            // 4. مزامنة اتفاقات الأطباء
            $this->syncDoctorDeals($invoice, $newStatus);

            // 5. تسجيل التدقيق
            AuditService::log(
                action: 'deleted',
                module: 'InvoiceReturn',
                entity: $return,
                description: "تم حذف وإلغاء إيصال مرتجع المبيعات رقم ({$returnNumber}) للفاتورة #{$invoice->serial_number} واستعادة كميات الفاتورة وخصمها من المخزن.",
                oldValues: $oldReturnData
            );

            // 6. حذف بنود المرتجع وسجل المرتجع
            $return->items()->delete();
            $return->delete();

            DB::commit();

            return redirect()->route('admin.returns.index')
                ->with('success', "تم إلغاء وحذف إيصال المرتجع ({$returnNumber}) بنجاح وعكس جميع الكميات على المخزن والفاتورة.");

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'حدث خطأ أثناء إلغاء المرتجع: '.$e->getMessage());
        }
    }

    /**
     * تصدير سجل المرتجعات إلى Excel (CSV مع BOM)
     */
    private function exportReturns($returns)
    {
        $filename = 'sales_returns_'.date('Y-m-d').'.csv';

        $callback = function () use ($returns) {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF)); // لدعم اللغة العربية في Excel

            fputcsv($file, [
                'رقم المرتجع',
                'تاريخ المرتجع',
                'رقم الفاتورة الأصلية',
                'الصيدلية',
                'المركز',
                'المندوب',
                'إجمالي الجمهور المرتجع',
                'إجمالي الخصم المرتجع',
                'صافي قيمة المرتجع',
                'المبلغ المسترد نقداً',
                'المسجل بواسطة',
                'ملاحظات',
            ]);

            foreach ($returns as $ret) {
                fputcsv($file, [
                    $ret->return_number,
                    $ret->return_date->format('Y-m-d'),
                    $ret->invoice->serial_number ?? $ret->invoice_id,
                    $ret->invoice->pharmacist->name ?? '-',
                    $ret->invoice->pharmacist->center->name ?? '-',
                    $ret->invoice->representative->name ?? '-',
                    $ret->total_return_public_price,
                    $ret->total_return_discount,
                    $ret->total_return_amount,
                    $ret->refund_amount,
                    $ret->creator->name ?? 'النظام',
                    $ret->notes ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    /**
     * مزامنة تارجت الأطباء في الاتفاقات بعد أي عملية على المرتجع (إنشاء / تعديل / إلغاء)
     */
    private function syncDoctorDeals(Invoice $invoice, int $newStatus): void
    {
        $attachedDeals = $invoice->deals;

        if ($newStatus != 1) {
            // إذا أصبحت الفاتورة غير خالصة، تلغى مساهمتها من التارجت وتفصل لحين السداد التام
            if ($attachedDeals->isNotEmpty()) {
                foreach ($attachedDeals as $deal) {
                    $deal->decrement('achieved_amount', $deal->pivot->contribution_amount);
                    $deal->invoices()->detach($invoice->id);
                }
            }

            return;
        }

        // الفاتورة خالصة (status == 1):
        if ($attachedDeals->isNotEmpty()) {
            // الاتفاقات مربوطة بالفعل -> يعاد حساب المساهمة بالكميات الحالية
            foreach ($attachedDeals as $deal) {
                $dealDrugIds = $deal->drugs->pluck('id')->toArray();
                $isGeneral = empty($dealDrugIds);
                $oldContribution = (float) $deal->pivot->contribution_amount;

                $newContribution = 0;
                foreach ($invoice->details as $d) {
                    if ($isGeneral || in_array($d->drug_id, $dealDrugIds)) {
                        $newContribution += ($d->quantity * $d->unit_price);
                    }
                }

                $diff = $newContribution - $oldContribution;
                if ($diff != 0) {
                    $deal->increment('achieved_amount', $diff);
                    $deal->invoices()->updateExistingPivot($invoice->id, [
                        'contribution_amount' => $newContribution,
                    ]);
                }
            }
        } else {
            // لم تكن مربوطة وعادت خالصة بعد المرتجع أو بعد إلغائه -> نربطها مع الاتفاقات المستحقة
            $doctorIds = $invoice->doctors->pluck('id')->toArray();
            if (empty($doctorIds)) {
                return;
            }

            $invoiceDate = $invoice->invoice_date ?? $invoice->created_at;
            $deals = DoctorDeal::with('drugs')
                ->whereIn('doctor_id', $doctorIds)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', $invoiceDate)
                ->whereHas('pharmacists', function ($q) use ($invoice) {
                    $q->where('pharmacists.id', $invoice->pharmacist_id);
                })
                ->where(function ($query) {
                    $query->where('is_paid', false)->orWhere(function ($q) {
                        $q->where('is_paid', true)->where('paid_amount', '>', 0);
                    });
                })
                ->get();

            foreach ($deals as $deal) {
                $dealDrugIds = $deal->drugs->pluck('id')->toArray();
                $isGeneral = empty($dealDrugIds);
                $newContribution = 0;

                foreach ($invoice->details as $d) {
                    if ($isGeneral || in_array($d->drug_id, $dealDrugIds)) {
                        $newContribution += ($d->quantity * $d->unit_price);
                    }
                }

                if ($newContribution > 0) {
                    $deal->increment('achieved_amount', $newContribution);
                    $deal->invoices()->syncWithoutDetaching([
                        $invoice->id => ['contribution_amount' => $newContribution],
                    ]);
                }
            }
        }
    }

    /**
     * جلب مخزن المنطقة المرتبط بالصيدلية والخط
     */
    private function resolveZoneAndWarehouse($pharmacistId, $line): array
    {
        $pharmacist = Pharmacist::with('center')->find($pharmacistId);
        $zone = Zone::where('line', $line)->whereHas('centers', function ($q) use ($pharmacist) {
            $q->where('centers.id', $pharmacist->center_id);
        })->first();

        if (! $zone) {
            throw ValidationException::withMessages(['line' => "لا توجد منطقة مسجلة لـ Line {$line} تشمل مركز ({$pharmacist->center->name})."]);
        }
        if (! $zone->warehouse_id) {
            throw ValidationException::withMessages(['line' => "المنطقة ({$zone->name}) غير مربوطة بمخزن."]);
        }

        return [
            'zone' => $zone,
            'warehouse' => Warehouse::find($zone->warehouse_id),
        ];
    }

    /**
     * تحديث رصيد المخزن للصنف
     */
    private function updateWarehouseStock(Warehouse $warehouse, $drugId, $quantity, $operation): void
    {
        $stockRecord = $warehouse->drugs()->where('drug_id', $drugId)->first();
        $currentQty = $stockRecord ? $stockRecord->pivot->quantity : 0;

        if ($operation == 'deduct') {
            if ($currentQty < $quantity) {
                $drug = \App\Models\Drug::find($drugId);
                throw ValidationException::withMessages(['items' => 'الكمية غير متوفرة للصنف ('.($drug->name ?? $drugId).').']);
            }
            $newQty = $currentQty - $quantity;
        } else {
            $newQty = $currentQty + $quantity;
        }

        if ($stockRecord) {
            $warehouse->drugs()->updateExistingPivot($drugId, ['quantity' => $newQty]);
        } elseif ($operation == 'add') {
            $warehouse->drugs()->attach($drugId, ['quantity' => $newQty]);
        }
    }
}
