@extends('layouts.admin')

@section('title', 'إدارة المستخدمين')

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2">
                <div class="content-header-left col-md-6 col-12">
                    <h3 class="content-header-title"> <i class="la la-users"></i> إدارة المستخدمين </h3>
                    <div class="row breadcrumbs-top">
                        <div class="breadcrumb-wrapper col-12">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item active">المستخدمين</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="content-header-right col-md-6 col-12 text-right">
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary box-shadow-2">
                        <i class="ft-user-plus"></i> إضافة مستخدم جديد
                    </a>
                </div>
            </div>

            <div class="content-body">
                @include('admin.includes.alerts.success')
                @include('admin.includes.alerts.errors')

                <div class="card border-top-primary border-top-3">
                    <div class="card-header">
                        <h4 class="card-title">قائمة مستخدمي النظام</h4>
                        <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                    </div>
                    <div class="card-content collapse show">
                        <div class="card-body card-dashboard">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 text-center">
                                    <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>الاسم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الصلاحية (Role)</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($users as $index => $user)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td class="text-bold-600">
                                                {{ $user->name }}
                                                @if(auth()->id() === $user->id)
                                                    <span class="badge badge-success ml-1">أنت</span>
                                                @endif
                                            </td>
                                            <td>{{ $user->email }}</td>
                                            <td>
                                                @if($user->role == 'admin')
                                                    <span class="badge badge-primary">مسؤول (Admin)</span>
                                                @elseif($user->role == 'accountant')
                                                    <span class="badge badge-warning text-dark">محاسب (Accountant)</span>
                                                @else
                                                    <span class="badge badge-secondary">مشاهد (Viewer)</span>
                                                @endif
                                            </td>
                                            <td class="text-muted"><small>{{ $user->created_at->format('Y-m-d') }}</small></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-warning mr-1" title="تعديل">
                                                        <i class="la la-edit"></i>
                                                    </a>

                                                    @if(auth()->id() !== $user->id)
                                                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا المستخدم نهائياً؟');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                                                <i class="la la-trash"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button class="btn btn-sm btn-secondary disabled" title="لا يمكنك حذف نفسك"><i class="la la-trash"></i></button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-3 text-muted">لا يوجد مستخدمين مسجلين.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2 d-flex justify-content-center">
                                {{ $users->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
