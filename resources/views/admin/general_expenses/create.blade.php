@extends('layouts.admin')

@section('title', 'إضافة مصروف عام')

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2">
                <div class="content-header-left col-md-6 col-12">
                    <h3 class="content-header-title"> <i class="la la-plus-circle"></i> إضافة مصروف عام </h3>
                    <div class="row breadcrumbs-top">
                        <div class="breadcrumb-wrapper col-12">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.general-expenses.index') }}">المصروفات العامة</a></li>
                                <li class="breadcrumb-item active">إضافة مصروف</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <div class="card border-top-primary border-top-3">
                    <div class="card-content">
                        <div class="card-body">
                            <form action="{{ route('admin.general-expenses.store') }}" method="POST">
                                @csrf

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="expense_date">تاريخ الصرف <span class="text-danger">*</span></label>
                                            <input type="date" id="expense_date" name="expense_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                            @error('expense_date') <span class="text-danger">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="amount">المبلغ (ج.م) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" id="amount" name="amount" class="form-control" placeholder="أدخل قيمة المصروف" value="{{ old('amount') }}" required>
                                            @error('amount') <span class="text-danger">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description">بيان المصروف (اتصرف في إيه؟) <span class="text-danger">*</span></label>
                                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="مثال: إيجار المقر الرئيسي، فواتير كهرباء، رواتب الموظفين..." required>{{ old('description') }}</textarea>
                                            @error('description') <span class="text-danger">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions text-right mt-2">
                                    <a href="{{ route('admin.general-expenses.index') }}" class="btn btn-secondary mr-1">
                                        <i class="ft-x"></i> إلغاء
                                    </a>
                                    <button type="submit" class="btn btn-primary box-shadow-2">
                                        <i class="la la-check-square-o"></i> حفظ المصروف
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
