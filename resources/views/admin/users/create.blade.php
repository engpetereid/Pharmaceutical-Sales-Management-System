@extends('layouts.admin')

@section('title', 'إضافة مستخدم جديد')

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2">
                <div class="content-header-left col-md-6 col-12">
                    <h3 class="content-header-title"> <i class="ft-user-plus"></i> إضافة مستخدم جديد </h3>
                    <div class="row breadcrumbs-top">
                        <div class="breadcrumb-wrapper col-12">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">المستخدمين</a></li>
                                <li class="breadcrumb-item active">إضافة مستخدم</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <div class="row match-height">
                    <div class="col-md-12">
                        <div class="card border-top-primary border-top-3 box-shadow-1">
                            <div class="card-content collapse show">
                                <div class="card-body">
                                    <form class="form" action="{{ route('admin.users.store') }}" method="POST">
                                        @csrf

                                        <div class="form-body">
                                            <h4 class="form-section"><i class="ft-info"></i> بيانات الحساب</h4>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="name">الاسم بالكامل <span class="text-danger">*</span></label>
                                                        <div class="position-relative has-icon-left">
                                                            <input type="text" id="name" name="name" class="form-control" placeholder="أدخل اسم المستخدم" value="{{ old('name') }}" required>
                                                            <div class="form-control-position"><i class="ft-user"></i></div>
                                                        </div>
                                                        @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="email">البريد الإلكتروني <span class="text-danger">*</span></label>
                                                        <div class="position-relative has-icon-left">
                                                            <input type="email" id="email" name="email" class="form-control" placeholder="example@email.com" value="{{ old('email') }}" required>
                                                            <div class="form-control-position"><i class="ft-mail"></i></div>
                                                        </div>
                                                        @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="password">كلمة المرور <span class="text-danger">*</span></label>
                                                        <div class="position-relative has-icon-left">
                                                            <input type="password" id="password" name="password" class="form-control" placeholder="كلمة المرور (8 أحرف على الأقل)" required>
                                                            <div class="form-control-position"><i class="ft-lock"></i></div>
                                                        </div>
                                                        @error('password') <span class="text-danger small">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="password_confirmation">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                                                        <div class="position-relative has-icon-left">
                                                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="أعد إدخال كلمة المرور" required>
                                                            <div class="form-control-position"><i class="ft-lock"></i></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <h4 class="form-section mt-2"><i class="ft-shield"></i> الصلاحيات</h4>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="role">اختر الصلاحية <span class="text-danger">*</span></label>
                                                        <select name="role" id="role" class="form-control" required>
                                                            <option value="" disabled selected>-- اختر الصلاحية --</option>
                                                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>مسؤول (Admin) - كامل الصلاحيات</option>
{{--                                                            <option value="accountant" {{ old('role') == 'accountant' ? 'selected' : '' }}>محاسب (Accountant)</option>--}}
                                                            <option value="viewer" {{ old('role') == 'viewer' ? 'selected' : '' }}>مشاهد (Viewer) - للعرض فقط</option>
                                                        </select>
                                                        @error('role') <span class="text-danger small">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="form-actions text-right">
                                            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary mr-1">
                                                <i class="ft-x"></i> إلغاء
                                            </a>
                                            <button type="submit" class="btn btn-primary box-shadow-2">
                                                <i class="la la-check-square-o"></i> حفظ وإنشاء المستخدم
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
