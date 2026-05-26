@extends('layouts.viewer')

@section('title', 'سداد الفاتورة #' . $invoice->serial_number)

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2">
                <div class="content-header-left col-md-8 col-12">
                    <h3 class="content-header-title">
                        <i class="ft-dollar-sign"></i> إدارة دفعات الفاتورة #{{ $invoice->serial_number ?? $invoice->id }}
                    </h3>
                    <div class="row breadcrumbs-top">
                        <div class="breadcrumb-wrapper col-12">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('viewer.dashboard') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('viewer.invoices.index') }}">الفواتير</a></li>
                                <li class="breadcrumb-item active">سداد الدفعات</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="content-header-right col-md-4 col-12 text-right">
                    <a href="{{ route('viewer.invoices.index') }}" class="btn btn-secondary box-shadow-2">
                        <i class="ft-arrow-right"></i> رجوع للفواتير
                    </a>
                </div>
            </div>

            <div class="content-body">
                @include('viewer.includes.alerts.success')
                @include('viewer.includes.alerts.errors')

                {{-- 1. ملخص الفاتورة المالي --}}
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-info text-white box-shadow-1">
                            <div class="card-body text-center p-2">
                                <h5 class="text-white mb-1"><i class="la la-file-text"></i> إجمالي الفاتورة</h5>
                                <h3 class="text-white font-weight-bold mb-0">{{ number_format($invoice->final_total, 2) }} ج.م</h3>
                                <small>العميل: {{ $invoice->pharmacist->name ?? '-' }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white box-shadow-1">
                            <div class="card-body text-center p-2">
                                <h5 class="text-white mb-1"><i class="la la-check-circle"></i> تم سداده</h5>
                                <h3 class="text-white font-weight-bold mb-0">{{ number_format($invoice->paid_amount, 2) }} ج.م</h3>
                                <small>{{ $invoice->payments->count() }} دفعات مسجلة</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white box-shadow-1">
                            <div class="card-body text-center p-2">
                                <h5 class="text-white mb-1"><i class="la la-warning"></i> المتبقي للدفع</h5>
                                <h3 class="text-white font-weight-bold mb-0">{{ number_format($invoice->remaining_amount, 2) }} ج.م</h3>
                                <small>
                                    @if($invoice->status == 1) خالص @elseif($invoice->status == 3) دفع جزئي @else آجل بالكامل @endif
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Viewer: payment history only (read-only) --}}
                <div class="row">

                        <div class="card">
                            <div class="card-header pb-0">
                                <h4 class="card-title"><i class="la la-history"></i> السجل الزمني للدفعات</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped text-center">
                                        <thead class="bg-light">
                                        <tr>
                                            <th>#</th>
                                            <th>تاريخ الدفعة</th>
                                            <th>المبلغ (ج.م)</th>
                                            <th>ملاحظات</th>
                                            <th>إجراءات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($invoice->payments as $index => $payment)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td class="font-weight-bold">{{ $payment->payment_date->format('Y-m-d') }}</td>
                                                <td class="text-success font-weight-bold">{{ number_format($payment->amount, 2) }}</td>
                                                <td class="text-muted">{{ $payment->notes ?: '-' }}</td>
                                                <td>
                                                    {{-- Viewer: read-only, no edit/delete --}}
                                                    <span class="text-muted font-small-2">عرض فقط</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-muted py-3">لم يتم تسجيل أي دفعات لهذه الفاتورة بعد.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
