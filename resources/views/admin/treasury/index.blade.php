@extends('layouts.admin')

@section('title', 'الخزينة ')

@section('style')
    <style>
        .stat-card { border-radius: 8px; overflow: hidden; transition: transform 0.3s; color: white; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .card-body { padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .stat-card h3 { color: white; margin-bottom: 0; font-weight: bold; font-size: 2rem; }
        .stat-card i { font-size: 3rem; opacity: 0.4; }

        .table th { background-color: #f4f5fa; border-top: none; }
        .table td { vertical-align: middle; }

        .scrollable-table { max-height: 400px; overflow-y: auto; }
        .scrollable-table thead th { position: sticky; top: 0; z-index: 1; background: #f4f5fa; }

        /* تحسينات حقل الاختيار المتعدد */
        .select2-container--default .select2-selection--multiple { border: 1px solid #ccd6e6; min-height: 40px; }

        @media print {
            .no-print, .main-menu, .header-navbar, .filter-card, .footer { display: none !important; }
            body { background-color: #fff; height: auto !important; }
            .app-content, .content-wrapper, .content-body { margin: 0 !important; padding: 0 !important; width: 100% !important; }

            /* الحل الجذري لمشكلة قص الجداول في الطباعة */
            .table-responsive { overflow: visible !important; display: table !important; width: 100% !important; }
            .scrollable-table { max-height: none !important; overflow: visible !important; height: auto !important; }

            .card, .card-content, .card-body { border: none !important; box-shadow: none !important; height: auto !important; overflow: visible !important; }

            .stat-card { color: #000 !important; background: #fff !important; border: 2px solid #ccc !important; page-break-inside: avoid; margin-bottom: 10px; }
            .stat-card h3, .stat-card span { color: #000 !important; }
            .stat-card i { display: none; }

            /* تجنب قطع الصفوف بين الصفحات */
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }
    </style>
@endsection

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2 no-print">
                <div class="content-header-left col-md-8 col-12">
                    <h3 class="content-header-title"> <i class="la la-bank"></i> الخزينة </h3>
                    <div class="row breadcrumbs-top">
                        <div class="breadcrumb-wrapper col-12">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item active">الخزينة</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="content-header-right col-md-4 col-12 text-right">
                    <button onclick="window.print()" class="btn btn-secondary box-shadow-2">
                        <i class="ft-printer"></i> طباعة التقرير
                    </button>
                </div>
            </div>

            <div class="content-body">
                {{-- 1. الفلتر (تاريخ ومناطق) --}}
                <div class="card filter-card box-shadow-1 border-top-primary border-top-3 no-print">
                    <div class="card-body">
                        <form action="{{ route('admin.treasury.index') }}" method="GET">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label class="text-bold-600">من تاريخ</label>
                                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label class="text-bold-600">إلى تاريخ</label>
                                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label class="text-bold-600">المناطق (التحصيلات فقط)</label>
                                        @php $selectedZones = request('zone_id', []); @endphp
                                        <select name="zone_id[]" class="form-control select2" multiple="multiple" data-placeholder="كل المناطق">
                                            @foreach($zones as $zone)
                                                <option value="{{ $zone->id }}" {{ in_array($zone->id, (array)$selectedZones) ? 'selected' : '' }}>
                                                    {{ $zone->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="btn-group w-100">
                                        <button type="submit" class="btn btn-primary" title="تصفية">
                                            <i class="ft-filter"></i>
                                        </button>
                                        <a href="{{ route('admin.treasury.index') }}" class="btn btn-secondary" title="إلغاء الفلتر">
                                            <i class="ft-x"></i>
                                        </a>
                                        <button type="submit" name="export" value="excel" class="btn btn-success" title="تصدير">
                                            <i class="la la-file-excel-o"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ترويسة الطباعة --}}
                <div class="d-none d-print-block mb-2 text-center">
                    <h2>تقرير الخزينة</h2>
                    @if(request('start_date') || request('end_date'))
                        <p>الفترة: {{ request('start_date') ?? '...' }} إلى {{ request('end_date') ?? '...' }}</p>
                    @else
                        <p>إجمالي حتى الآن (كل البيانات)</p>
                    @endif
                    @if(!empty(request('zone_id')))
                        <p>مناطق محددة فقط</p>
                    @endif
                    <hr>
                </div>

                {{-- 2. بطاقات الإحصائيات --}}
                <div class="row">
                    <div class="col-xl-4 col-md-6 col-12">
                        <div class="card stat-card bg-gradient-x-success box-shadow-1">
                            <div class="card-body">
                                <div>
                                    <span>إجمالي الدخل (تحصيلات)</span>
                                    <h3>{{ number_format($totalIncome, 2) }}</h3>
                                </div>
                                <i class="la la-arrow-circle-down text-white" style="transform: rotate(180deg);"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 col-12">
                        <div class="card stat-card bg-gradient-x-danger box-shadow-1" title="عامة: {{ number_format($totalGeneralExpenses, 2) }}">
                            <div class="card-body">
                                <div>
                                    <span>إجمالي المصروفات العامة</span>
                                    <h3>{{ number_format($totalExpenses, 2) }}</h3>
                                </div>
                                <i class="la la-arrow-circle-up text-white" style="transform: rotate(180deg);"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-12 col-12">
                        <div class="card stat-card {{ $netBalance >= 0 ? 'bg-gradient-x-info' : 'bg-gradient-x-warning' }} box-shadow-1">
                            <div class="card-body">
                                <div>
                                    <span>رصيد الخزنة (الصافي)</span>
                                    <h3>{{ number_format($netBalance, 2) }}</h3>
                                </div>
                                <i class="la la-bank text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. الجداول التفصيلية --}}
                <div class="row match-height">

                    {{-- أ. جدول الدخل (التحصيلات) --}}
                    <div class="col-xl-6 col-lg-12">
                        <div class="card border-top-success border-top-3">
                            <div class="card-header pb-0">
                                <h4 class="card-title text-success"><i class="la la-plus-circle"></i> التحصيلات والدخل</h4>
                            </div>
                            <div class="card-content">
                                <div class="card-body pt-0 mt-1">
                                    <div class="table-responsive scrollable-table">
                                        <table class="table table-hover table-bordered mb-0">
                                            <thead>
                                            <tr>
                                                <th>التاريخ</th>
                                                <th>الفاتورة</th>
                                                <th>المبلغ</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($payments as $payment)
                                                <tr>
                                                    <td>{{ $payment->payment_date->format('Y-m-d') }}</td>
                                                    <td>
                                                        <a href="{{ route('admin.invoices.show', $payment->invoice_id) }}" target="_blank">
                                                            #{{ $payment->invoice->serial_number ?? $payment->invoice_id }}
                                                        </a>
                                                    </td>
                                                    <td class="text-success font-weight-bold">+{{ number_format($payment->amount, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">لا توجد تحصيلات مسجلة.</td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-right mt-1 font-weight-bold text-success border-top pt-1">
                                        إجمالي الدخل: {{ number_format($totalIncome, 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ب. المصروفات العامة --}}
                    <div class="col-xl-6 col-lg-12">
                        <div class="card border-top-warning border-top-3">
                            <div class="card-header pb-0">
                                <h4 class="card-title text-warning"><i class="la la-building"></i> المصروفات العامة</h4>
                            </div>
                            <div class="card-content">
                                <div class="card-body pt-0 mt-1">
                                    <div class="table-responsive scrollable-table">
                                        <table class="table table-hover table-bordered mb-0">
                                            <thead>
                                            <tr>
                                                <th>التاريخ</th>
                                                <th>البيان</th>
                                                <th>المبلغ</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($generalExpenses as $gExpense)
                                                <tr>
                                                    <td>{{ $gExpense->expense_date->format('Y-m-d') }}</td>
                                                    <td title="{{ $gExpense->description }}"><span class="font-small-3">{{ Str::limit($gExpense->description, 20) }}</span></td>
                                                    <td class="text-warning font-weight-bold">-{{ number_format($gExpense->amount, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">لا توجد مصروفات عامة.</td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-right mt-1 font-weight-bold text-warning border-top pt-1">
                                        إجمالي العامة: {{ number_format($totalGeneralExpenses, 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
@endsection
