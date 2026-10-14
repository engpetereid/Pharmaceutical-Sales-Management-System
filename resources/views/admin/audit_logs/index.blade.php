@extends('layouts.admin')

@section('title', 'سجل العمليات والرقابة')

@section('content')
<div class="app-content content">
    <div class="content-wrapper">
        <div class="mb-2 content-header row">
            <div class="col-md-6 col-12 content-header-left">
                <h3 class="content-header-title">
                    <i class="ft-shield text-primary"></i> سجل العمليات والرقابة
                </h3>
            </div>
            <div class="text-right col-md-6 col-12 content-header-right">
                <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="d-inline">
                    <input type="hidden" name="export" value="excel">
                    @foreach(request()->except(['export', 'page']) as $key => $val)
                        <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                    @endforeach
                    <button type="submit" class="btn btn-success">
                        <i class="la la-file-excel-o"></i> تصدير السجل إلى Excel
                    </button>
                </form>
            </div>
        </div>

        <div class="content-body">
            <!-- كارت الفلاتر والبحث -->
            <div class="card mb-2">
                <div class="card-header">
                    <h4 class="card-title"><i class="ft-filter"></i> تصفية وبحث في سجل التدقيق</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.audit-logs.index') }}">
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
                                <label class="text-muted font-small-3">المستخدم</label>
                                <select name="user_id" class="form-control select2">
                                    <option value="">-- كل المستخدمين --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-1">
                                <label class="text-muted font-small-3">القسم / الكيان</label>
                                <select name="module" class="form-control select2">
                                    <option value="">-- كل الأقسام --</option>
                                    @foreach($modules as $key => $label)
                                        <option value="{{ $key }}" {{ request('module') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-1">
                                <label class="text-muted font-small-3">نوع الإجراء</label>
                                <select name="action" class="form-control select2">
                                    <option value="">-- كل الإجراءات --</option>
                                    @foreach($actions as $key => $label)
                                        <option value="{{ $key }}" {{ request('action') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-1">
                                <label class="text-muted font-small-3">النتيجة</label>
                                <select name="outcome" class="form-control">
                                    <option value="">-- الكل --</option>
                                    <option value="success" {{ request('outcome') === 'success' ? 'selected' : '' }}>ناجح</option>
                                    <option value="failed" {{ request('outcome') === 'failed' ? 'selected' : '' }}>فاشل</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-1">
                                <label class="text-muted font-small-3">بحث عام (رقم، اسم، IP، بيان)</label>
                                <input type="text" name="search" class="form-control" placeholder="ابحث بالرقم، الاسم، أو IP..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2 mb-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block mr-1">
                                    <i class="ft-search"></i> تصفية
                                </button>
                                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-secondary">
                                    <i class="ft-refresh-cw"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول سجل العمليات -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 15%;">الوقت والتاريخ</th>
                                    <th style="width: 15%;">المستخدم</th>
                                    <th style="width: 10%;">الإجراء</th>
                                    <th style="width: 12%;">القسم</th>
                                    <th style="width: 18%;">السجل المتأثر</th>
                                    <th style="width: 10%;">عنوان IP</th>
                                    <th style="width: 8%;" class="text-center">النتيجة</th>
                                    <th style="width: 12%;" class="text-center">التفاصيل</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>
                                            <span class="font-weight-bold text-dark">{{ $log->created_at->format('Y-m-d') }}</span>
                                            <small class="text-muted d-block">{{ $log->created_at->format('h:i:s A') }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $log->user_name ?: ($log->user->name ?? 'النظام') }}</strong>
                                            @if($log->user_role)
                                                <span class="badge badge-sm badge-info d-block mt-1">{{ $log->user_role }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->action_badge_class }}">
                                                {{ $log->action_label }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light border">{{ $log->module_label }}</span>
                                        </td>
                                        <td>
                                            <strong class="text-dark">{{ $log->entity_name ?: '-' }}</strong>
                                            @if($log->description)
                                                <small class="text-muted d-block">{{ Str::limit($log->description, 60) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <code>{{ $log->ip_address ?: '-' }}</code>
                                        </td>
                                        <td class="text-center">
                                            @if($log->outcome === 'success')
                                                <span class="badge badge-success">ناجح</span>
                                            @else
                                                <span class="badge badge-danger">فاشل</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary btn-view-audit"
                                                    data-id="{{ $log->id }}"
                                                    data-action="{{ $log->action_label }}"
                                                    data-module="{{ $log->module_label }}"
                                                    data-user="{{ $log->user_name ?: ($log->user->name ?? 'النظام') }}"
                                                    data-time="{{ $log->created_at->format('Y-m-d h:i:s A') }}"
                                                    data-ip="{{ $log->ip_address ?: '-' }}"
                                                    data-entity="{{ $log->entity_name ?: '-' }}"
                                                    data-desc="{{ $log->description ?: '-' }}"
                                                    data-url="{{ $log->url ?: '-' }}"
                                                    data-changes='@json($log->changes)'
                                                    data-old='@json($log->old_values)'
                                                    data-new='@json($log->new_values)'>
                                                <i class="ft-eye"></i> معاينة
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="ft-info font-medium-1"></i> لا توجد سجلات تدقيق مسجلة تطابق البحث.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal تفاصيل سجل التدقيق والفروقات -->
<div class="modal fade text-right" id="auditDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary white">
                <h5 class="modal-title text-white">
                    <i class="ft-shield"></i> تفاصيل عملية التدقيق والفروقات
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3 pb-2 border-bottom">
                    <div class="col-md-4 mb-1">
                        <span class="text-muted d-block">المستخدم:</span>
                        <strong id="modalUser" class="text-dark"></strong>
                    </div>
                    <div class="col-md-4 mb-1">
                        <span class="text-muted d-block">الوقت والتاريخ:</span>
                        <strong id="modalTime" class="text-dark"></strong>
                    </div>
                    <div class="col-md-4 mb-1">
                        <span class="text-muted d-block">عنوان IP:</span>
                        <code id="modalIp"></code>
                    </div>
                    <div class="col-md-4 mb-1">
                        <span class="text-muted d-block">القسم والعملية:</span>
                        <span id="modalModule" class="badge badge-info"></span>
                        <span id="modalAction" class="badge badge-primary"></span>
                    </div>
                    <div class="col-md-4 mb-1">
                        <span class="text-muted d-block">السجل المتأثر:</span>
                        <strong id="modalEntity" class="text-primary"></strong>
                    </div>
                    <div class="col-md-4 mb-1">
                        <span class="text-muted d-block">مسار الطلب:</span>
                        <small id="modalUrl" class="text-muted" style="word-break: break-all;"></small>
                    </div>
                    <div class="col-12 mt-1">
                        <span class="text-muted d-block">البيان والوصف:</span>
                        <p id="modalDesc" class="mb-0 alert alert-light border font-medium-1"></p>
                    </div>
                </div>

                <!-- جدول الفروقات قبل وبعد -->
                <h5 class="font-weight-bold text-primary mb-2">
                    <i class="ft-git-commit"></i> ما الذي تغير في هذا السجل؟ (Diff Before & After)
                </h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="modalChangesTable">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 30%;">الحقل</th>
                                <th style="width: 35%;" class="text-danger">القيمة السابقة (قبل)</th>
                                <th style="width: 35%;" class="text-success">القيمة الحالية (بعد)</th>
                            </tr>
                        </thead>
                        <tbody id="modalChangesBody">
                            <!-- تم إنشاؤه ديناميكياً بواسطة جافاسكربت -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        $('.btn-view-audit').on('click', function() {
            const btn = $(this);
            $('#modalUser').text(btn.data('user'));
            $('#modalTime').text(btn.data('time'));
            $('#modalIp').text(btn.data('ip'));
            $('#modalModule').text(btn.data('module'));
            $('#modalAction').text(btn.data('action'));
            $('#modalEntity').text(btn.data('entity'));
            $('#modalDesc').text(btn.data('desc'));
            $('#modalUrl').text(btn.data('url'));

            const changes = btn.data('changes');
            const oldValues = btn.data('old');
            const newValues = btn.data('new');
            const tbody = $('#modalChangesBody');
            tbody.empty();

            if (changes && typeof changes === 'object' && Object.keys(changes).length > 0) {
                // تعديل: يوجد diff مباشر
                $.each(changes, function(field, diff) {
                    const oldVal = (diff.old !== null && diff.old !== undefined) ? JSON.stringify(diff.old) : '<span class="text-muted">فارغ</span>';
                    const newVal = (diff.new !== null && diff.new !== undefined) ? JSON.stringify(diff.new) : '<span class="text-muted">فارغ</span>';

                    tbody.append(`
                        <tr>
                            <td><code>${field}</code></td>
                            <td class="bg-light-danger"><code>${oldVal}</code></td>
                            <td class="bg-light-success font-weight-bold"><code>${newVal}</code></td>
                        </tr>
                    `);
                });
            } else if (newValues && typeof newValues === 'object' && Object.keys(newValues).length > 0) {
                // إضافة: قيم جديدة مسجلة
                $.each(newValues, function(field, val) {
                    if (val === null || val === undefined || val === '') return;
                    tbody.append(`
                        <tr>
                            <td><code>${field}</code></td>
                            <td class="text-muted"><em>لا يوجد (جديد)</em></td>
                            <td class="bg-light-success font-weight-bold"><code>${JSON.stringify(val)}</code></td>
                        </tr>
                    `);
                });
            } else if (oldValues && typeof oldValues === 'object' && Object.keys(oldValues).length > 0) {
                // حذف: القيم التي كانت مسجلة قبل الحذف
                $.each(oldValues, function(field, val) {
                    tbody.append(`
                        <tr>
                            <td><code>${field}</code></td>
                            <td class="bg-light-danger"><code>${JSON.stringify(val)}</code></td>
                            <td class="text-muted"><em>تم الحذف</em></td>
                        </tr>
                    `);
                });
            } else {
                tbody.append(`
                    <tr>
                        <td colspan="3" class="text-center py-2 text-muted">
                            لا توجد تفاصيل حقول إضافية مسجلة لهذه العملية.
                        </td>
                    </tr>
                `);
            }

            $('#auditDetailModal').modal('show');
        });
    });
</script>
@endsection
