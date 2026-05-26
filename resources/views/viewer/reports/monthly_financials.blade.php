@extends('layouts.viewer')

@section('title', 'الملخص المالي الشهري')

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
        .select2-container--default .select2-selection--multiple { border: 1px solid #ccd6e6; min-height: 40px; }

        @media print {
            .no-print, .main-menu, .header-navbar, .filter-card, .footer { display: none !important; }
            body { background-color: #fff; height: auto !important;}
            .app-content, .content-wrapper, .content-body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
            .stat-card { color: #000 !important; background: #fff !important; border: 2px solid #ccc !important; }
            .stat-card h3, .stat-card span { color: #000 !important; }
            .stat-card i { display: none; }

            /* تعديلات الطباعة للجدول */
            .table-responsive { overflow: visible !important; display: table !important; width: 100% !important; }
            .scrollable-table { max-height: none !important; overflow: visible !important; height: auto !important; }
            tr { page-break-inside: avoid; }
        }
    </style>
@endsection

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2 no-print">
                <div class="content-header-left col-md-8 col-12">
                    <h3 class="content-header-title"> <i class="la la-bar-chart"></i> الملخص المالي الشهري </h3>
                </div>
                <div class="content-header-right col-md-4 col-12 text-right">
                    <button onclick="window.print()" class="btn btn-secondary box-shadow-2"><i class="ft-printer"></i> طباعة</button>
                </div>
            </div>

            <div class="content-body">
                {{-- الفلتر --}}
                <div class="card filter-card box-shadow-1 border-top-primary border-top-3 no-print">
                    <div class="card-body">
                        <form action="{{ route('viewer.reports.monthly_financials') }}" method="GET">
                            <div class="row align-items-end">
                                <div class="col-md-3 mb-1">
                                    <label class="text-bold-600">اختر الشهر</label>
                                    <select name="month" class="form-control">
                                        <option value="all" {{ $selectedMonth === 'all' ? 'selected' : '' }}>-- كل الشهور --</option>
                                        @foreach($months as $num => $name)
                                            <option value="{{ $num }}" {{ $selectedMonth == $num ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-1">
                                    <label class="text-bold-600">اختر السنة</label>
                                    <select name="year" class="form-control">
                                        @foreach($years as $yr)
                                            <option value="{{ $yr }}" {{ $selectedYear == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-1">
                                    <label class="text-bold-600">المنطقة (يمكن اختيار أكثر من منطقة)</label>
                                    <select name="zone[]" class="form-control select2" multiple="multiple" data-placeholder="-- كل المناطق --">
                                        @foreach($zones as $id => $name)
                                            <option value="{{ $id }}" {{ in_array($id, $selectedZones) ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-1">
                                    <div class="btn-group w-100">
                                        <button type="submit" class="btn btn-primary"><i class="ft-filter"></i> عرض</button>
                                        <a href="{{ route('viewer.reports.monthly_financials') }}" class="btn btn-secondary" title="إلغاء الفلتر">
                                            <i class="ft-x"></i>
                                        </a>
                                        <button type="submit" name="export" value="excel" class="btn btn-success"><i class="la la-file-excel-o"></i> إكسيل</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ترويسة الطباعة --}}
                <div class="d-none d-print-block mb-2 text-center">
                    <h2>التقرير المالي</h2>
                    <p>الفترة: {{ $selectedMonth === 'all' ? 'كل الشهور' : $months[(int)$selectedMonth] }} {{ $selectedYear }}</p>
                    <hr>
                </div>

                {{-- البطاقات --}}
                <div class="row">
                    <div class="col-xl-4 col-md-6">
                        <div class="card stat-card bg-gradient-x-success box-shadow-1">
                            <div class="card-body">
                                <div><span>إجمالي التحصيلات</span><h3>{{ number_format($totalIncome, 2) }}</h3></div>
                                <i class="la la-arrow-circle-down" style="transform: rotate(180deg);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="card stat-card bg-gradient-x-danger box-shadow-1">
                            <div class="card-body">
                                <div><span>إجمالي المصروفات</span><h3>{{ number_format($totalExpenses, 2) }}</h3></div>
                                <i class="la la-arrow-circle-up" style="transform: rotate(180deg);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-12">
                        <div class="card stat-card {{ $netProfit >= 0 ? 'bg-gradient-x-info' : 'bg-gradient-x-warning' }} box-shadow-1">
                            <div class="card-body">
                                <div><span>الصافي</span><h3>{{ number_format($netProfit, 2) }}</h3></div>
                                <i class="la la-balance-scale"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- الجداول --}}
                <div class="row">
                    <div class="col-xl-6 col-lg-12">
                        <div class="card border-top-success border-top-3">
                            <div class="card-header pb-0"><h4 class="card-title text-success">تفاصيل التحصيلات</h4></div>
                            <div class="card-body pt-1">
                                <div class="table-responsive scrollable-table">
                                    <table class="table table-hover table-bordered">
                                        <thead><tr><th>التاريخ</th><th>العميل</th><th>المبلغ</th></tr></thead>
                                        <tbody>
                                        @foreach($payments as $p)
                                            <tr><td>{{ $p->payment_date->format('Y-m-d') }}</td><td>{{ $p->invoice->pharmacist->name ?? '-' }}</td><td class="text-success font-weight-bold">+{{ number_format($p->amount, 2) }}</td></tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-12">
                        <div class="card border-top-danger border-top-3">
                            <div class="card-header pb-0"><h4 class="card-title text-danger">مصروفات المناطق</h4></div>
                            <div class="card-body pt-1">
                                <div class="table-responsive scrollable-table">
                                    <table class="table table-hover table-bordered">
                                        <thead><tr><th>التاريخ</th><th>المنطقة</th><th>البيان</th><th>المبلغ</th></tr></thead>
                                        <tbody>
                                        @foreach($zoneExpenses as $e)
                                            <tr><td>{{ $e->expense_date }}</td><td>{{ $e->zone->name ?? '-' }}</td><td>{{ Str::limit($e->description, 20) }}</td><td class="text-danger font-weight-bold">-{{ number_format($e->amount, 2) }}</td></tr>
                                        @endforeach
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


`
