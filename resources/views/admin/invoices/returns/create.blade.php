@extends('layouts.admin')

@section('title', 'تسجيل مرتجع للفاتورة: ' . ($invoice->serial_number ?? $invoice->id))

@section('content')
<div class="app-content content">
    <div class="content-wrapper">
        <div class="mb-2 content-header row">
            <div class="col-md-6 col-12 content-header-left">
                <h3 class="content-header-title">
                    <i class="ft-corner-down-left text-danger"></i> تسجيل مرتجع مبيعات
                </h3>
                <span class="text-muted">للفاتورة رقم: <strong class="text-primary">{{ $invoice->serial_number ?? '#' . $invoice->id }}</strong></span>
            </div>
            <div class="text-right col-md-6 col-12 content-header-right">
                <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="btn btn-secondary">
                    <i class="ft-arrow-right"></i> رجوع للفاتورة
                </a>
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

            <!-- بيانات الفاتورة الأساسية -->
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
                            <span class="text-muted d-block">الرصيد المالي الحالي:</span>
                            <div>الصافي: <strong class="text-primary">{{ number_format($invoice->final_total, 2) }}</strong> ج.م</div>
                            <div>المدفوع: <strong class="text-success">{{ number_format($invoice->paid_amount, 2) }}</strong> | المتبقي: <strong class="text-danger">{{ number_format($invoice->remaining_amount, 2) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- نموذج تسجيل المرتجع -->
            <form action="{{ route('admin.invoices.returns.store', $invoice->id) }}" method="POST" id="returnForm">
                @csrf
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title text-white mb-0">
                            <i class="ft-list"></i> تحديد الأصناف والكميات المرتجعة
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="return_date" class="font-weight-bold">تاريخ المرتجع <span class="text-danger">*</span></label>
                                <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', date('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-8">
                                <label for="notes" class="font-weight-bold">سبب المرتجع / ملاحظات</label>
                                <input type="text" name="notes" id="notes" class="form-control" placeholder="اكتب سبب المرتجع (تلف، انتهاء صلاحية، فائض...)" value="{{ old('notes') }}">
                            </div>
                        </div>

                        <!-- جدول بنود الفاتورة المتاحة للإرجاع -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="returnItemsTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 30%;">اسم الدواء</th>
                                        <th class="text-center" style="width: 10%;">الكمية الحالية</th>
                                        <th class="text-center" style="width: 10%;">مرتجع سابق</th>
                                        <th class="text-center" style="width: 12%;">السعر (جمهور)</th>
                                        <th class="text-center" style="width: 8%;">الخصم %</th>
                                        <th class="text-center bg-danger text-white" style="width: 15%;">الكمية المرتجعة الآن</th>
                                        <th class="text-right" style="width: 10%;">قيمة المرتجع (صافي)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoice->details as $index => $detail)
                                        <tr data-detail-id="{{ $detail->id }}" data-price="{{ $detail->unit_price }}" data-discount="{{ $detail->pharmacist_discount_percentage }}" data-max="{{ $detail->quantity }}">
                                            <td class="text-center align-middle">{{ $index + 1 }}</td>
                                            <td class="align-middle">
                                                <strong>{{ $detail->drug->name ?? 'صنف غير معروف' }}</strong>
                                                <input type="hidden" name="items[{{ $index }}][detail_id]" value="{{ $detail->id }}">
                                            </td>
                                            <td class="text-center align-middle font-weight-bold">
                                                {{ $detail->quantity }}
                                            </td>
                                            <td class="text-center align-middle text-muted">
                                                {{ $detail->returned_quantity ?? 0 }}
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
                                                           max="{{ $detail->quantity }}" 
                                                           value="{{ old("items.$index.quantity", 0) }}"
                                                           {{ $detail->quantity <= 0 ? 'disabled' : '' }}>
                                                    @if($detail->quantity > 0)
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-outline-danger btn-sm btn-return-all" title="إرجاع الكل">كل</button>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if($detail->quantity <= 0)
                                                    <span class="badge badge-secondary mt-1">تم إرجاعه بالكامل</span>
                                                @endif
                                            </td>
                                            <td class="text-right align-middle font-weight-bold text-danger item-return-subtotal">
                                                0.00 ج.م
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- الملخص المالي للمرتجع -->
                        <div class="row mt-3 justify-content-end">
                            <div class="col-md-6">
                                <div class="border rounded p-3 bg-light">
                                    <h5 class="font-weight-bold text-primary mb-2">
                                        <i class="ft-calculator"></i> ملخص حركة المرتجع الحالية
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
                                        <span class="font-weight-bold font-medium-1 text-dark">صافي قيمة المرتجع:</span>
                                        <strong class="font-weight-bold font-large-1 text-danger" id="summaryNet">0.00 ج.م</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 text-info">
                                        <span>الصافي الجديد المتوقع للفاتورة:</span>
                                        <strong id="summaryNewInvoiceFinal">{{ number_format($invoice->final_total, 2) }} ج.م</strong>
                                    </div>
                                    <div class="alert alert-warning py-1 px-2 font-small-3 mt-2" id="refundNotice" style="display: none;">
                                        <i class="ft-info"></i> تنبيه: إجمالي المسدد سابقاً يتجاوز صافي الفاتورة الجديد بمقدار (<span id="refundAmount">0.00</span> ج.م)، سيتم تسجيل هذا الفارق كاسترداد نقدي مسدد للصيدلية تلقائياً.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right mt-3">
                            <button type="submit" class="btn btn-danger btn-lg" id="btnSubmitReturn" disabled>
                                <i class="ft-check"></i> تأكيد وحفظ المرتجع وتحديث المخزن
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        const currentInvoiceFinal = {{ $invoice->final_total }};
        const currentPaid = {{ $invoice->paid_amount }};

        function recalculate() {
            let totalReturnPublic = 0;
            let totalReturnDiscount = 0;
            let hasAnyQty = false;

            $('#returnItemsTable tbody tr').each(function() {
                const row = $(this);
                const price = parseFloat(row.data('price')) || 0;
                const discountPerc = parseFloat(row.data('discount')) || 0;
                const maxQty = parseInt(row.data('max')) || 0;
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
                }

                const linePublic = price * qty;
                const lineDiscount = linePublic * (discountPerc / 100);
                const lineNet = linePublic - lineDiscount;

                row.find('.item-return-subtotal').text(lineNet.toFixed(2) + ' ج.م');

                totalReturnPublic += linePublic;
                totalReturnDiscount += lineDiscount;
            });

            const totalReturnNet = totalReturnPublic - totalReturnDiscount;
            const newInvoiceFinal = Math.max(0, currentInvoiceFinal - totalReturnNet);

            $('#summaryPublic').text(totalReturnPublic.toFixed(2) + ' ج.م');
            $('#summaryDiscount').text('- ' + totalReturnDiscount.toFixed(2) + ' ج.م');
            $('#summaryNet').text(totalReturnNet.toFixed(2) + ' ج.م');
            $('#summaryNewInvoiceFinal').text(newInvoiceFinal.toFixed(2) + ' ج.م');

            if (currentPaid > newInvoiceFinal && totalReturnNet > 0) {
                const excess = currentPaid - newInvoiceFinal;
                $('#refundAmount').text(excess.toFixed(2));
                $('#refundNotice').show();
            } else {
                $('#refundNotice').hide();
            }

            $('#btnSubmitReturn').prop('disabled', !hasAnyQty);
        }

        $('.return-qty-input').on('input change', function() {
            recalculate();
        });

        $('.btn-return-all').on('click', function() {
            const row = $(this).closest('tr');
            const max = row.data('max');
            row.find('.return-qty-input').val(max).trigger('change');
        });

        $('#returnForm').on('submit', function(e) {
            if (!confirm('تأكيد: سيتم إرجاع الكميات المحددة إلى المخزن وتعديل كميات الفاتورة وإجمالياتها. هل أنت متأكد؟')) {
                e.preventDefault();
                return false;
            }
        });

        recalculate();
    });
</script>
@endsection
