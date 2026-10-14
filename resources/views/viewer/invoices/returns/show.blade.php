@extends('layouts.viewer')

@section('title', 'إيصال مرتجع: ' . $return->return_number)

@section('content')
<div class="app-content content">
    <div class="content-wrapper">
        <div class="mb-2 no-print content-header row">
            <div class="col-md-6 col-12 content-header-left">
                <h3 class="content-header-title">
                    <i class="ft-file-minus text-danger"></i> إيصال مرتجع مبيعات
                </h3>
                <span class="text-muted">رقم الإيصال: <strong>{{ $return->return_number }}</strong></span>
            </div>
            <div class="text-right col-md-6 col-12 content-header-right">
                <div class="btn-group">
                    <a href="{{ route('viewer.invoices.show', $return->invoice_id) }}" class="btn btn-secondary mr-1">
                        <i class="ft-arrow-right"></i> الفاتورة الأصلية
                    </a>
                    <a href="{{ route('viewer.returns.index') }}" class="btn btn-outline-primary mr-1">
                        <i class="ft-list"></i> سجل المرتجعات
                    </a>
                    <button type="button" class="btn btn-primary" onclick="window.print();">
                        <i class="la la-print"></i> طباعة الإيصال
                    </button>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="printable-area">
            <section class="card">
                <div class="p-4 card-body" id="return-receipt">
                    <div class="row mb-3">
                        <div class="text-left col-md-6">
                            <h3 class="text-primary text-bold-800">Bio vera</h3>
                            <p class="text-muted mb-0">نظام إدارة التوزيع الدوائي</p>
                            <span class="badge badge-danger font-medium-1 mt-1">إيصال مرتجع </span>
                        </div>
                        <div class="text-right col-md-6">
                            <h2 class="mb-1 text-bold-700 text-danger">{{ $return->return_number }}</h2>
                            <p class="mb-1">تاريخ المرتجع: <strong>{{ $return->return_date->format('Y-m-d') }}</strong></p>
                            <p class="mb-1">الفاتورة الأصلية: <a href="{{ route('viewer.invoices.show', $return->invoice_id) }}">#{{ $return->invoice->serial_number ?? $return->invoice_id }}</a></p>
                            <span class="badge badge-{{ $return->invoice->line == 1 ? 'info' : 'warning' }}">Line {{ $return->invoice->line }}</span>
                        </div>
                    </div>

                    <hr>

                    <div class="pt-2 row">
                        <div class="mb-2 col-md-4">
                            <h6 class="pb-1 border-bottom text-bold-700 text-muted">بيانات العميل (الصيدلية)</h6>
                            <p class="mb-0 font-medium-2 text-bold-800">{{ $return->invoice->pharmacist->name ?? '-' }}</p>
                            <p class="mb-0 text-muted">{{ $return->invoice->pharmacist->center->name ?? '-' }}</p>
                            <p class="text-muted">{{ $return->invoice->pharmacist->phone ?? '-' }}</p>
                        </div>
                        <div class="mb-2 col-md-4">
                            <h6 class="pb-1 border-bottom text-bold-700 text-muted">فريق التوزيع</h6>
                            <p class="mb-1"><span class="text-muted">مندوب البيع:</span> <strong>{{ $return->invoice->representative->name ?? '-' }}</strong></p>
                            <p class="mb-0"><span class="text-muted">مندوب الدعاية:</span> <strong>{{ $return->invoice->medicalRepresentative->name ?? '-' }}</strong></p>
                        </div>
                        <div class="mb-2 col-md-4">
                            <h6 class="pb-1 border-bottom text-bold-700 text-muted">بيانات التسجيل</h6>
                            <p class="mb-1"><span class="text-muted">المسجل بواسطة:</span> <strong>{{ $return->creator->name ?? 'النظام' }}</strong></p>
                            <p class="mb-0"><span class="text-muted">وقت القيد:</span> {{ $return->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <!-- جدول الأصناف المرتجعة -->
                    <div class="pt-2 table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center" style="width: 5%;">#</th>
                                    <th style="width: 35%;">اسم الدواء</th>
                                    <th class="text-center" style="width: 10%;">الكمية المرتجعة</th>
                                    <th class="text-right" style="width: 15%;">سعر الجمهور</th>
                                    <th class="text-center" style="width: 10%;">نسبة الخصم</th>
                                    <th class="text-right" style="width: 12%;">خصم الصنف</th>
                                    <th class="text-right" style="width: 13%;">الصافي المرتجع</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($return->items as $index => $item)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td class="font-weight-bold">{{ $item->drug->name ?? 'صنف غير معروف' }}</td>
                                        <td class="text-center font-weight-bold text-danger">{{ $item->quantity }}</td>
                                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-center">{{ $item->pharmacist_discount_percentage }}%</td>
                                        <td class="text-right text-muted">{{ number_format($item->discount_amount, 2) }}</td>
                                        <td class="text-right font-weight-bold text-danger">{{ number_format($item->subtotal_net, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- الملخص المالي للمرتجع -->
                    <div class="mt-2 row">
                        <div class="col-md-7">
                            <p class="text-bold-600">ملاحظات المرتجع:</p>
                            <div class="p-2 mb-2 border alert alert-light text-muted">
                                {{ $return->notes ?: 'لا توجد ملاحظات مسجلة على هذا المرتجع.' }}
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="p-3 border rounded bg-light">
                                <div class="mb-1 d-flex justify-content-between">
                                    <span class="text-muted">إجمالي سعر الجمهور:</span>
                                    <span class="font-weight-bold">{{ number_format($return->total_return_public_price, 2) }} ج.م</span>
                                </div>
                                <div class="mb-2 d-flex justify-content-between">
                                    <span class="text-danger">إجمالي الخصم المرتجع:</span>
                                    <span class="text-danger font-weight-bold">- {{ number_format($return->total_return_discount, 2) }} ج.م</span>
                                </div>
                                <div class="pt-2 mb-2 border-top d-flex justify-content-between">
                                    <span class="font-large-1 text-bold-700 text-danger">صافي المرتجع:</span>
                                    <span class="font-large-1 text-danger text-bold-700">{{ number_format($return->total_return_amount, 2) }} ج.م</span>
                                </div>
                                <div class="pt-1 border-top d-flex justify-content-between">
                                    <span class="text-bold-600 text-success">مبلغ الاسترداد النقدي المسدد:</span>
                                    <span class="text-bold-600 text-success">{{ number_format($return->refund_amount, 2) }} ج.م</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- توقيعات الإيصال للطباعة -->
                    <div class="mt-4 pt-3 border-top row text-center">
                        <div class="col-4">
                            <p class="font-weight-bold mb-4">توقيع المستلم (المندوب):</p>
                            <p class="text-muted">....................................</p>
                        </div>
                        <div class="col-4">
                            <p class="font-weight-bold mb-4">توقيع الصيدلية:</p>
                            <p class="text-muted">....................................</p>
                        </div>
                        <div class="col-4">
                            <p class="font-weight-bold mb-4">اعتماد إدارة الحسابات:</p>
                            <p class="text-muted">....................................</p>
                        </div>
                    </div>

                </div>
            </section>
            </div>
        </div>
    </div>
</div>
@endsection
