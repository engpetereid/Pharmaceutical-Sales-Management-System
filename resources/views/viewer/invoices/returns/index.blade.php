@extends('layouts.viewer')

@section('title', 'سجل مرتجعات المبيعات')

@section('content')
<div class="app-content content">
    <div class="content-wrapper">
        <div class="mb-2 content-header row">
            <div class="col-md-6 col-12 content-header-left">
                <h3 class="content-header-title">
                    <i class="ft-corner-down-left text-danger"></i> سجل مرتجعات المبيعات
                </h3>
                <span class="text-muted">متابعة واستعراض كافة إيصالات المرتجعات وتفاصيلها</span>
            </div>
            <div class="text-right col-md-6 col-12 content-header-right">
                <a href="{{ route('viewer.invoices.index') }}" class="btn btn-primary">
                    <i class="ft-file-text"></i> الذهاب لفواتير المبيعات
                </a>
            </div>
        </div>

        <div class="content-body">
            <!-- إحصائيات سريعة -->
            <div class="row">
                <div class="col-xl-4 col-lg-6 col-12">
                    <div class="card">
                        <div class="card-content">
                            <div class="card-body">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="ft-file-minus text-primary font-large-2 float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>{{ number_format($stats['total_returns_count']) }}</h3>
                                        <span>إجمالي عدد إيصالات المرتجع</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-6 col-12">
                    <div class="card">
                        <div class="card-content">
                            <div class="card-body">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="ft-trending-down text-danger font-large-2 float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3 class="text-danger">{{ number_format($stats['total_net_returned'], 2) }} ج.م</h3>
                                        <span>إجمالي صافي قيمة المرتجعات</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-6 col-12">
                    <div class="card">
                        <div class="card-content">
                            <div class="card-body">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="la la-money text-success font-large-2 float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3 class="text-success">{{ number_format($stats['total_refunded'], 2) }} ج.م</h3>
                                        <span>إجمالي الاسترداد النقدي المسدد</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- بطاقة الفلاتر -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="ft-filter"></i> تصفية وبحث في المرتجعات</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('viewer.returns.index') }}">
                        <div class="row">
                            <div class="col-md-3 mb-1">
                                <label class="text-muted font-small-3">من تاريخ</label>
                                <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                            </div>
                            <div class="col-md-3 mb-1">
                                <label class="text-muted font-small-3">إلى تاريخ</label>
                                <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                            </div>
                            <div class="col-md-3 mb-1">
                                <label class="text-muted font-small-3">رقم المرتجع</label>
                                <input type="text" name="return_number" class="form-control" placeholder="RET-..." value="{{ request('return_number') }}">
                            </div>
                            <div class="col-md-3 mb-1">
                                <label class="text-muted font-small-3">رقم الفاتورة الأصلية</label>
                                <input type="text" name="invoice_serial" class="form-control" placeholder="رقم الفاتورة..." value="{{ request('invoice_serial') }}">
                            </div>
                            <div class="col-md-4 mb-1">
                                <label class="text-muted font-small-3">الصيدلية</label>
                                <select name="pharmacist_id" class="form-control select2">
                                    <option value="">-- كل الصيدليات --</option>
                                    @foreach($pharmacists as $ph)
                                        <option value="{{ $ph->id }}" {{ request('pharmacist_id') == $ph->id ? 'selected' : '' }}>
                                            {{ $ph->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary mr-1">
                                    <i class="ft-search"></i> بحث
                                </button>
                                <a href="{{ route('viewer.returns.index') }}" class="btn btn-secondary mr-1">
                                    <i class="ft-refresh-cw"></i> إعادة تعيين
                                </a>
                                <button type="submit" name="export" value="excel" class="btn btn-success">
                                    <i class="la la-file-excel-o"></i> تصدير Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول المرتجعات -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>رقم المرتجع</th>
                                    <th>التاريخ</th>
                                    <th>الفاتورة الأصلية</th>
                                    <th>الصيدلية</th>
                                    <th>الأصناف المرتجعة</th>
                                    <th class="text-right">صافي المرتجع</th>
                                    <th class="text-right">استرداد نقدي</th>
                                    <th class="text-center">معاينة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($returns as $ret)
                                    <tr>
                                        <td>
                                            <a href="{{ route('viewer.returns.show', $ret->id) }}" class="font-weight-bold text-danger">
                                                {{ $ret->return_number }}
                                            </a>
                                        </td>
                                        <td>{{ $ret->return_date->format('Y-m-d') }}</td>
                                        <td>
                                            <a href="{{ route('viewer.invoices.show', $ret->invoice_id) }}" class="text-primary font-weight-bold">
                                                {{ $ret->invoice->serial_number ?? '#' . $ret->invoice_id }}
                                            </a>
                                            <span class="badge badge-sm badge-{{ $ret->invoice->line == 1 ? 'info' : 'warning' }}">Line {{ $ret->invoice->line }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $ret->invoice->pharmacist->name ?? '-' }}</strong>
                                            <small class="text-muted d-block">{{ $ret->invoice->pharmacist->center->name ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">{{ $ret->items->count() }} أصناف</span>
                                            <small class="text-muted d-block">إجمالي: {{ $ret->items->sum('quantity') }} عبوة</small>
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
                                        <td class="text-center">
                                            <a href="{{ route('viewer.returns.show', $ret->id) }}" class="btn btn-sm btn-outline-info" title="عرض الإيصال">
                                                <i class="ft-eye"></i> عرض
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-3 text-muted">
                                            <i class="ft-info font-medium-1"></i> لا توجد مرتجعات مسجلة تطابق معايير البحث.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $returns->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
