@extends('layouts.admin')

@section('title', 'تعديل إيصال مرتجع: ' . $return->return_number)

@section('content')
<div class="app-content content">
    <div class="content-wrapper">
        <div class="mb-2 content-header row">
            <div class="col-md-6 col-12 content-header-left">
                <h3 class="content-header-title">
                    <i class="ft-edit text-warning"></i> تعديل إيصال مرتجع مبيعات
                </h3>
                <span class="text-muted">رقم الإيصال: <strong class="text-danger">{{ $return->return_number }}</strong> | الفاتورة: <strong class="text-primary">{{ $invoice->serial_number ?? '#' . $invoice->id }}</strong></span>
            </div>
            <div class="text-right col-md-6 col-12 content-header-right">
                <div class="btn-group">
                    <a href="{{ route('admin.returns.show', $return->id) }}" class="btn btn-secondary mr-1">
                        <i class="ft-arrow-right"></i> عرض الإيصال
                    </a>
                    <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#deleteReturnModal">
                        <i class="ft-trash-2"></i> إلغاء وحذف المرتجع
                    </button>
                </div>
            </div>
        </div>

        <div class="content-body">
            @if(session('error'))
                <div class="alert alert-danger mb-2">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger mb-2">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- بطاقة معلومات الفاتورة والصيدلية -->
            <div class="card mb-2">
                <div class="card-body">
                    <div class="row text-center text-md-right">
                        <div class="col-md-3 mb-1">
                            <span class="text-muted d-block">الصيدلية:</span>
                            <strong class="font-medium-1 text-dark">{{ $invoice->pharmacist->name ?? '-' }}</strong>
                            <small class="text-muted d-block">{{ $invoice->pharmacist->center->name ?? '-' }}</small>
                        </div>
                        <div class="col-md-3 mb-1">
                            <span class="text-muted d-block">تاريخ الفاتورة:</span>
                            <strong class="font-medium-1 text-dark">{{ $invoice->invoice_date }}</strong>
                            <span class="badge badge-{{ $invoice->line == 1 ? 'info' : 'warning' }}">Line {{ $invoice->line }}</span>
                        </div>
                        <div class="col-md-3 mb-1">
                            <span class="text-muted d-block">المندوب:</span>
                            <strong class="font-medium-1 text-dark">{{ $invoice->representative->name ?? '-' }}</strong>
                        </div>
                        <div class="col-md-3 mb-1">
                            <span class="text-muted d-block">الرصيد المالي للفاتورة حالياً:</span>
                            <div>الصافي: <strong class="text-primary">{{ number_format($invoice->final_total, 2) }}</strong> ج.م</div>
                            <div>المدفوع: <strong class="text-success">{{ number_format($invoice->paid_amount, 2) }}</strong> | المتبقي: <strong class="text-danger">{{ number_format($invoice->remaining_amount, 2) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- نموذج تعديل المرتجع -->
            <form action="{{ route('admin.returns.update', $return->id) }}" method="POST" id="editReturnForm">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h4 class="card-title text-white mb-0">
                            <i class="ft-sliders"></i> تعديل بيانات المرتجع والأصناف
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="return_date" class="font-weight-bold">تاريخ المرتجع <span class="text-danger">*</span></label>
                                <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', $return->return_date->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-8">
                                <label for="notes" class="font-weight-bold">سبب المرتجع / ملاحظات</label>
                                <input type="text" name="notes" id="notes" class="form-control" placeholder="ملاحظات وتفاصيل التعديل..." value="{{ old('notes', $return->notes) }}">
                            </div>
                        </div>

                        <div class="alert alert-info py-1 mb-2 font-small-3">
                            <i class="ft-info"></i> <strong>توضيح:</strong> عند زيادة كمية المرتجع سيتم إضافة الفرق لمخزن المنطقة، وعند تقليل كمية المرتجع سيتم خصم الفرق من المخزن وإعادته للفاتورة مع التحقق من توفر رصيد كافٍ في المخزن.
                        </div>

                        <!-- جدول بنود المرتجع -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="returnItemsTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 25%;">اسم الدواء</th>
                                        <th class="text-center" style="width: 10%;">كمية الفاتورة الحالية</th>
                                        <th class="text-center" style="width: 10%;">مرتجع بهذا الإيصال</th>
                                        <th class="text-center" style="width: 10%;">أقصى متاح للإرجاع</th>
                                        <th class="text-center" style="width: 10%;">السعر</th>
                                        <th class="text-center" style="width: 8%;">الخصم %</th>
                                        <th class="text-center bg-warning text-dark font-weight-bold" style="width: 12%;">الكمية المرتجعة الجديدة</th>
                                        <th class="text-right" style="width: 10%;">صافي قيمة الصنف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($detailsWithReturn as $index => $item)
                                        @php
                                            $detail = $item['detail'];
                                            $currReturnQty = $item['current_return_qty'];
                                            $maxReturnable = $item['max_returnable'];
                                        @endphp
                                        <tr data-detail-id="{{ $detail->id }}" 
                                            data-price="{{ $detail->unit_price }}" 
                                            data-discount="{{ $detail->pharmacist_discount_percentage }}" 
                                            data-max="{{ $maxReturnable }}" 
                                            data-old-qty="{{ $currReturnQty }}">
                                            <td class="text-center align-middle">{{ $index + 1 }}</td>
                                            <td class="align-middle">
                                                <strong>{{ $detail->drug->name ?? 'صنف غير معروف' }}</strong>
                                                <input type="hidden" name="items[{{ $index }}][detail_id]" value="{{ $detail->id }}">
                                            </td>
                                            <td class="text-center align-middle font-weight-bold">
                                                {{ $detail->quantity }}
                                            </td>
                                            <td class="text-center align-middle font-weight-bold text-danger">
                                                {{ $currReturnQty }}
                                            </td>
                                            <td class="text-center align-middle text-muted">
                                                {{ $maxReturnable }}
                                            </td>
                                            <td class="text-center align-middle">
                                                {{ number_format($detail->unit_price, 2) }}
                                            </td>
                                            <td class="text-center align-middle text-danger font-weight-bold">
                                                {{ $detail->pharmacist_discount_percentage }}%
                                            </td>
                                            <td class="text-center align-middle">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" 
                                                           name="items[{{ $index }}][quantity]" 
                                                           class="form-control text-center return-qty-input font-weight-bold text-danger" 
                                                           min="0" 
                                                           max="{{ $maxReturnable }}" 
                                                           value="{{ old("items.$index.quantity", $currReturnQty) }}"
                                                           {{ $maxReturnable <= 0 ? 'disabled' : '' }}>
                                                    @if($maxReturnable > 0)
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-outline-warning btn-sm btn-return-all" title="أقصى كمية">كل</button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-right align-middle font-weight-bold text-danger item-return-subtotal">
                                                0.00 ج.م
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- الملخص المالي للمرتجع بعد التعديل -->
                        <div class="row mt-3 justify-content-end">
                            <div class="col-md-6">
                                <div class="border rounded p-3 bg-light">
                                    <h5 class="font-weight-bold text-primary mb-2">
                                        <i class="ft-calculator"></i> ملخص المرتجع بعد التعديل
                                    </h5>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>إجمالي سعر الجمهور المرتجع:</span>
                                        <strong id="summaryPublic">0.00 ج.م</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 text-danger">
                                        <span>إجمالي الخصم المرتجع:</span>
                                        <strong id="summaryDiscount">- 0.00 ج.م</strong>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="font-weight-bold font-medium-1 text-dark">صافي قيمة المرتجع الجديد:</span>
                                        <strong class="font-weight-bold font-large-1 text-danger" id="summaryNet">0.00 ج.م</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 text-info">
                                        <span>صافي الفاتورة المتوقع بعد التعديل:</span>
                                        <strong id="summaryNewInvoiceFinal">0.00 ج.م</strong>
                                    </div>
                                    <div class="alert alert-warning py-1 px-2 font-small-3 mt-2" id="refundNotice" style="display: none;">
                                        <i class="ft-info"></i> تنبيه: إجمالي المسدد سابقاً يتجاوز صافي الفاتورة الجديد بمقدار (<span id="refundAmount">0.00</span> ج.م)، وسيتم تعديل الدفعات والاسترداد تلقائياً.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right mt-3">
                            <a href="{{ route('admin.returns.show', $return->id) }}" class="btn btn-secondary btn-lg mr-1">
                                إلغاء
                            </a>
                            <button type="submit" class="btn btn-warning btn-lg" id="btnSubmitReturn">
                                <i class="ft-check"></i> حفظ التعديلات وتحديث المخزن والفاتورة
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal تأكيد الحذف / الإلغاء الكامل -->
<div class="modal fade" id="deleteReturnModal" tabindex="-1" role="dialog" aria-labelledby="deleteReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="deleteReturnModalLabel">
                    <i class="ft-alert-triangle"></i> تأكيد إلغاء وحذف إيصال المرتجع
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="font-medium-1">هل أنت متأكد من رغبتك في إلغاء وحذف إيصال المرتجع رقم <strong>{{ $return->return_number }}</strong>؟</p>
                <div class="alert alert-warning font-small-3">
                    <strong>الآثار المترتبة على الحذف:</strong>
                    <ul class="mb-0 pr-3">
                        <li>سيتم خصم الأصناف المرتجعة من رصيد مخزن المنطقة (بشرط توفر الرصيد).</li>
                        <li>سيتم إعادة الكميات إلى الفاتورة الأصلية ورفع إجمالياتها وصافيها المالي.</li>
                        <li>سيتم تحديث رصيد الفاتورة والمبلغ المتبقي على العميل تلقائياً.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">تراجع</button>
                <form action="{{ route('admin.returns.destroy', $return->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="ft-trash-2"></i> تأكيد الحذف والإلغاء
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        const invoiceDetails = [
            @foreach($invoice->details as $d)
                {
                    id: {{ $d->id }},
                    unitPrice: {{ (float) $d->unit_price }},
                    discountPerc: {{ (float) $d->pharmacist_discount_percentage }},
                    currentQty: {{ (int) $d->quantity }}
                },
            @endforeach
        ];
        const currentPaid = {{ (float) $invoice->paid_amount }};

        function recalculate() {
            let totalReturnPublic = 0;
            let totalReturnDiscount = 0;
            let hasAnyQty = false;

            // حساب أثر المرتجع الجديد على الفاتورة
            let simulatedInvoiceFinal = 0;

            $('#returnItemsTable tbody tr').each(function() {
                const row = $(this);
                const detailId = parseInt(row.data('detail-id'));
                const price = parseFloat(row.data('price')) || 0;
                const discountPerc = parseFloat(row.data('discount')) || 0;
                const maxQty = parseInt(row.data('max')) || 0;
                const oldReturnQty = parseInt(row.data('old-qty')) || 0;
                const input = row.find('.return-qty-input');
                let qty = parseInt(input.val()) || 0;

                if (qty > maxQty) {
                    qty = maxQty;
                    input.val(qty);
                }
                if (qty < 0) {
                    qty = 0;
                    input.val(0);
                }

                if (qty > 0) {
                    hasAnyQty = true;
                    const rowPublic = price * qty;
                    const rowDisc = rowPublic * (discountPerc / 100);
                    const rowNet = rowPublic - rowDisc;

                    totalReturnPublic += rowPublic;
                    totalReturnDiscount += rowDisc;

                    row.find('.item-return-subtotal').text(rowNet.toFixed(2) + ' ج.م');
                } else {
                    row.find('.item-return-subtotal').text('0.00 ج.م');
                }

                // حساب كمية بند الفاتورة المتوقعة: كمية الفاتورة الحالية + الكمية القديمة المرتجعة - الكمية الجديدة
                const detailObj = invoiceDetails.find(d => d.id === detailId);
                if (detailObj) {
                    const newInvoiceQty = detailObj.currentQty + oldReturnQty - qty;
                    const newRowPublic = newInvoiceQty * price;
                    const newRowDisc = newRowPublic * (discountPerc / 100);
                    simulatedInvoiceFinal += (newRowPublic - newRowDisc);
                }
            });

            const totalReturnNet = totalReturnPublic - totalReturnDiscount;

            $('#summaryPublic').text(totalReturnPublic.toFixed(2) + ' ج.م');
            $('#summaryDiscount').text('- ' + totalReturnDiscount.toFixed(2) + ' ج.م');
            $('#summaryNet').text(totalReturnNet.toFixed(2) + ' ج.م');
            $('#summaryNewInvoiceFinal').text(simulatedInvoiceFinal.toFixed(2) + ' ج.م');

            if (currentPaid > simulatedInvoiceFinal) {
                const refund = currentPaid - simulatedInvoiceFinal;
                $('#refundAmount').text(refund.toFixed(2));
                $('#refundNotice').show();
            } else {
                $('#refundNotice').hide();
            }

            $('#btnSubmitReturn').prop('disabled', !hasAnyQty);
        }

        // أزرار أقصى كمية
        $('.btn-return-all').on('click', function() {
            const row = $(this).closest('tr');
            const maxQty = row.data('max');
            row.find('.return-qty-input').val(maxQty);
            recalculate();
        });

        // رصد التغييرات في خانات الكمية
        $('.return-qty-input').on('input change', function() {
            recalculate();
        });

        // حساب مبدئي عند فتح الصفحة
        recalculate();
    });
</script>
@endsection
