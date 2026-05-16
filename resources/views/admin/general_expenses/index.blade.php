@extends('layouts.admin')

@section('title', 'المصروفات العامة للشركة')

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2">
                <div class="content-header-left col-md-6 col-12">
                    <h3 class="content-header-title"> <i class="la la-money"></i> المصروفات العامة للشركة </h3>
                    <div class="row breadcrumbs-top">
                        <div class="breadcrumb-wrapper col-12">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item active">المصروفات العامة</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="content-header-right col-md-6 col-12 text-right">
                    <a href="{{ route('admin.general-expenses.create') }}" class="btn btn-primary box-shadow-2">
                        <i class="ft-plus"></i> إضافة مصروف عام
                    </a>
                </div>
            </div>

            <div class="content-body">
                @include('admin.includes.alerts.success')
                @include('admin.includes.alerts.errors')

                {{-- الفلتر وبطاقة الإجمالي --}}
                <div class="row">
                    <div class="col-md-9">
                        <div class="card box-shadow-1">
                            <div class="card-body">
                                <form action="{{ route('admin.general-expenses.index') }}" method="GET">
                                    <div class="row align-items-end">
                                        <div class="col-md-3 mb-1">
                                            <label class="text-muted font-small-3">من تاريخ</label>
                                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                                        </div>
                                        <div class="col-md-3 mb-1">
                                            <label class="text-muted font-small-3">إلى تاريخ</label>
                                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                                        </div>
                                        <div class="col-md-4 mb-1">
                                            <label class="text-muted font-small-3">بحث في البيان</label>
                                            <div class="position-relative has-icon-left">
                                                <input type="text" class="form-control" name="search" placeholder="مثال: إيجار، كهرباء..." value="{{ request('search') }}">
                                                <div class="form-control-position"><i class="ft-search"></i></div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mb-1">
                                            <button type="submit" class="btn btn-info btn-block"><i class="ft-filter"></i> تصفية</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white box-shadow-1">
                            <div class="card-body text-center p-2">
                                <h6 class="text-white mb-1">إجمالي المصروفات (للفلتر)</h6>
                                <h2 class="text-white font-weight-bold mb-0">{{ number_format($totalExpenses, 2) }} ج.م</h2>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- جدول البيانات --}}
                <div class="card">
                    <div class="card-content collapse show">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered mb-0">
                                    <thead class="bg-light">
                                    <tr>
                                        <th width="10%">#</th>
                                        <th width="15%">التاريخ</th>
                                        <th width="40%">بيان المصروف</th>
                                        <th width="15%">المبلغ</th>
                                        <th width="20%" class="text-center">إجراءات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($expenses as $index => $expense)
                                        <tr>
                                            <td>{{ $expense->id }}</td>
                                            <td class="font-weight-bold">{{ $expense->expense_date->format('Y-m-d') }}</td>
                                            <td>{{ $expense->description }}</td>
                                            <td class="text-danger font-weight-bold">{{ number_format($expense->amount, 2) }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('admin.general-expenses.edit', $expense->id) }}" class="btn btn-sm btn-warning" title="تعديل">
                                                    <i class="ft-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.general-expenses.destroy', $expense->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المصروف؟')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                                        <i class="ft-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">لا توجد مصروفات عامة مسجلة تطابق البحث.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2 d-flex justify-content-center">
                                {{ $expenses->links() }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
