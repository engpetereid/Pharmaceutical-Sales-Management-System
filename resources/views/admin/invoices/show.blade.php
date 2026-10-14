@extends('layouts.admin')

@section('title', 'تفاصيل الفاتورة: ' . ($invoice->serial_number ?? $invoice->id))

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="mb-2 no-print content-header row">
                <div class="col-md-6 col-12 content-header-left">
                    <h3 class="content-header-title"> <i class="ft-file-text"></i> تفاصيل الفاتورة </h3>
                </div>
                <div class="text-right col-md-6 col-12 content-header-right">
                    <div class="btn-group">
                        <a href="{{ route('admin.invoices.returns.create', $invoice->id) }}" class="mr-1 btn btn-danger box-shadow-2">
                            <i class="ft-corner-down-left"></i> عمل مرتجع
                        </a>
                        <a href="{{ route('admin.invoices.edit', $invoice->id) }}" class="mr-1 btn btn-warning box-shadow-2">
                            <i class="ft-edit"></i> تعديل
                        </a>
                        <a href="{{ route('admin.invoices.pdf', $invoice->id) }}" target="_blank" class="btn btn-secondary box-shadow-2">
                            <i class="la la-file-pdf-o"></i> تصدير PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body printable-area">
                <section class="card">
                    <div id="invoice-template" class="p-4 card-body">

                        <div class="mb-3 row">
                            <div class="text-left col-md-6">
                                <h3 class="text-primary text-bold-800">Bio vera</h3>
                                <p class="text-muted">نظام التوزيع الدوائي</p>
                            </div>
                            <div class="text-right col-md-6">
                                <h2 class="mb-1 text-bold-700">فاتورة #{{ $invoice->serial_number ?? $invoice->id }}</h2>
                                <p class="mb-1">التاريخ: {{ $invoice->invoice_date }}</p>
                                <span class="px-2 badge badge-{{ $invoice->line == 1 ? 'info' : 'warning' }} font-medium-1">Line {{ $invoice->line }}</span>
                            </div>
                        </div>

                        <hr>

                        <div class="pt-2 row">
                            <div class="mb-2 col-md-4">
                                <h6 class="pb-1 border-bottom text-bold-700 text-muted">بيانات الصيدالية</h6>
                                <p class="mb-0 font-medium-2 text-bold-800">{{ $invoice->pharmacist->name ?? 'غير محدد' }}</p>
                                <p class="mb-0 text-muted">{{ $invoice->pharmacist->center->name ?? '-' }}</p>
                                <p class="text-muted">{{ $invoice->pharmacist->phone ?? '-' }}</p>
                            </div>

                            <div class="mb-2 col-md-4">
                                <h6 class="pb-1 border-bottom text-bold-700 text-muted">الأطباء </h6>
                                @if($invoice->doctors && $invoice->doctors->count() > 0)
                                    @foreach($invoice->doctors as $doctor)
                                        <div class="mb-1">
                                            <p class="mb-0 font-medium-2 text-bold-800">د. {{ $doctor->name }}</p>
                                            <p class="mb-0 text-muted font-small-3">{{ $doctor->speciality ?? '-' }}</p>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="mb-0 font-medium-2 text-bold-800 text-muted">لا يوجد أطباء</p>
                                @endif
                            </div>

                            <div class="mb-2 col-md-4">
                                <h6 class="pb-1 border-bottom text-bold-700 text-muted">فريق التوزيع</h6>
                                <div class="mb-1">
                                    <span class="text-muted">بيع:</span> <strong class="text-primary">{{ $invoice->representative->name ?? '-' }}</strong>
                                </div>
                                <div>
                                    <span class="text-muted">دعاية:</span> <strong class="text-info">{{ $invoice->medicalRepresentative->name ?? '-' }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- جدول الأصناف -->
                        <div class="pt-2 table-responsive">
                            <table class="table table-bordered">
                                <thead class="bg-light">
                                <tr>
                                    <th class="text-center" width="5%">#</th>
                                    <th width="40%">اسم الدواء</th>
                                    <th class="text-center" width="10%">الكمية</th>
                                    <th class="text-right" width="15%">السعر</th>
                                    <th class="text-center" width="10%">الخصم</th>
                                    <th class="text-right" width="20%">الإجمالي</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($invoice->details as $index => $item)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td class="font-weight-bold">{{ $item->drug->name ?? 'صنف محذوف' }}</td>
                                        <td class="text-center">
                                            <span class="font-weight-bold">{{ $item->quantity }}</span>
                                            @if(($item->returned_quantity ?? 0) > 0)
                                                <div class="mt-1">
                                                    <span class="badge badge-sm badge-danger" title="كمية تم إرجاعها">مرتجع: {{ $item->returned_quantity }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-center">{{ $item->pharmacist_discount_percentage }}%</td>
                                        <td class="text-right font-weight-bold">{{ number_format($item->row_total, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- الملخص المالي -->
                        <div class="mt-2 row">
                            <div class="col-md-7">
                                <p class="text-bold-600">ملاحظات:</p>
                                <div class="p-2 mb-2 border alert alert-light text-muted">
                                    {{ $invoice->notes ?? 'لا توجد ملاحظات.' }}
                                </div>
                                <p class="text-bold-600">حالة الدفع:</p>
                                @if($invoice->status == 1)
                                    <span class="p-1 badge badge-success">مدفوع بالكامل</span>
                                @elseif($invoice->status == 2)
                                    <span class="p-1 badge badge-warning">آجل (Deferred)</span>
                                @else
                                    <span class="p-1 badge badge-info">دفع جزئي</span>
                                @endif

                                @if($invoice->returns && $invoice->returns->count() > 0)
                                    <div class="mt-2">
                                        <span class="badge badge-danger p-1">
                                            <i class="ft-corner-down-left"></i> يوجد لهذه الفاتورة {{ $invoice->returns->count() }} حركة مرتجع مسجلة
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-5">
                                <div class="p-3 border rounded bg-light">
                                    <div class="mb-1 d-flex justify-content-between">
                                        <span class="text-muted">الإجمالي:</span>
                                        <span class="font-weight-bold">{{ number_format($invoice->total_amount, 2) }}</span>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-danger">الخصم:</span>
                                        <span class="text-danger font-weight-bold">- {{ number_format($invoice->total_discount, 2) }}</span>
                                    </div>
                                    <div class="pt-2 mb-2 border-top d-flex justify-content-between">
                                        <span class="font-large-1 text-bold-700">الصافي:</span>
                                        <span class="font-large-1 text-primary text-bold-700">{{ number_format($invoice->final_total, 2) }} ج.م</span>
                                    </div>

                                    <div class="mb-1 text-success d-flex justify-content-between">
                                        <span class="text-bold-600">المدفوع:</span>
                                        <span class="text-bold-600">{{ number_format($invoice->paid_amount, 2) }}</span>
                                    </div>
                                    @if($invoice->remaining_amount > 0)
                                        <div class="text-danger d-flex justify-content-between">
                                            <span class="text-bold-600">المتبقي (آجل):</span>
                                            <span class="text-bold-600">{{ number_format($invoice->remaining_amount, 2) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- سجل المرتجعات لهذه الفاتورة -->
                        @if($invoice->returns && $invoice->returns->count() > 0)
                            <div class="mt-4 pt-3 border-top">
                                <h5 class="font-weight-bold text-danger mb-2">
                                    <i class="ft-corner-down-left"></i> سجل المرتجعات الصادرة لهذه الفاتورة
                                </h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>رقم المرتجع</th>
                                                <th>تاريخ المرتجع</th>
                                                <th>الأصناف المرتجعة</th>
                                                <th class="text-right">صافي قيمة المرتجع</th>
                                                <th class="text-right">استرداد نقدي</th>
                                                <th>المسجل</th>
                                                <th>ملاحظات</th>
                                                <th class="text-center">معاينة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($invoice->returns as $ret)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('admin.returns.show', $ret->id) }}" class="font-weight-bold text-danger">
                                                            {{ $ret->return_number }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $ret->return_date->format('Y-m-d') }}</td>
                                                    <td>
                                                        @foreach($ret->items as $rItem)
                                                            <div class="font-small-2">
                                                                • {{ $rItem->drug->name ?? '-' }}: <strong>{{ $rItem->quantity }}</strong> عبوة
                                                            </div>
                                                        @endforeach
                                                    </td>
                                                    <td class="text-right font-weight-bold text-danger">
                                                        {{ number_format($ret->total_return_amount, 2) }} ج.م
                                                    </td>
                                                    <td class="text-right">
                                                        @if($ret->refund_amount > 0)
                                                            <span class="badge badge-success">{{ number_format($ret->refund_amount, 2) }} ج.م</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $ret->creator->name ?? 'النظام' }}</td>
                                                    <td>{{ $ret->notes ?: '-' }}</td>
                                                    <td class="text-center">
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="{{ route('admin.returns.show', $ret->id) }}" class="btn btn-outline-info" title="عرض الإيصال">
                                                                <i class="ft-eye"></i>
                                                            </a>
                                                            <a href="{{ route('admin.returns.edit', $ret->id) }}" class="btn btn-outline-warning" title="تعديل المرتجع">
                                                                <i class="ft-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger btn-delete-return" 
                                                                    data-id="{{ $ret->id }}" 
                                                                    data-number="{{ $ret->return_number }}" 
                                                                    title="إلغاء وحذف المرتجع">
                                                                <i class="ft-trash-2"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                    </div>
                </section>
            </div>
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
                <p class="font-medium-1">هل أنت متأكد من رغبتك في إلغاء وحذف إيصال المرتجع رقم <strong id="deleteReturnNumber" class="text-danger"></strong>؟</p>
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
                <form id="deleteReturnForm" action="" method="POST" class="d-inline">
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
        $('.btn-delete-return').on('click', function() {
            const id = $(this).data('id');
            const number = $(this).data('number');
            const url = "{{ url('admin/returns') }}/" + id;

            $('#deleteReturnNumber').text(number);
            $('#deleteReturnForm').attr('action', url);
            $('#deleteReturnModal').modal('show');
        });
    });
</script>
@endsection
