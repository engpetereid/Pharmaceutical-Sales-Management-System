<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ViewerAuditLogController extends Controller
{
    /**
     * عرض سجل التدقيق مع الفلاتر للمشاهد (Read-Only)
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('entity_name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%")
                    ->orWhere('user_name', 'like', "%{$term}%");
            });
        }

        if ($request->has('export') && $request->export == 'excel') {
            return $this->exportLogs($query->latest('created_at')->limit(5000)->get());
        }

        $logs = $query->latest('created_at')->paginate(25)->withQueryString();

        $users = User::select('id', 'name')->orderBy('name')->get();
        $modules = [
            'Invoice' => 'فواتير المبيعات',
            'InvoiceReturn' => 'مرتجعات الفواتير',
            'InvoicePayment' => 'الدفعات والسداد',
            'DoctorDeal' => 'اتفاقات الأطباء',
            'Warehouse' => 'المخازن',
            'Drug' => 'الأدوية',
            'Doctor' => 'الأطباء',
            'Pharmacist' => 'الصيدليات',
            'Representative' => 'المناديب',
            'Zone' => 'المناطق',
            'ZoneExpense' => 'مصروفات المناطق',
            'GeneralExpense' => 'المصروفات العامة',
            'User' => 'المستخدمين',
            'Auth' => 'نظام الدخول',
        ];

        $actions = [
            'created' => 'إضافة',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'returned' => 'مرتجع مبيعات',
            'payment_added' => 'إضافة دفعة',
            'stock_added' => 'توريد / نقل مخزون',
            'stock_returned' => 'صرف / مرتجع مخزون',
            'login_success' => 'تسجيل دخول ناجح',
            'login_failed' => 'فشل تسجيل الدخول',
            'logout' => 'تسجيل خروج',
        ];

        return view('viewer.audit_logs.index', compact('logs', 'users', 'modules', 'actions'));
    }

    /**
     * عرض تفاصيل سجل تدقيق محدد (للمعاينة التفصيلية عبر Modal)
     */
    public function show(AuditLog $auditLog)
    {
        return response()->json([
            'id' => $auditLog->id,
            'user' => $auditLog->user_name ?? ($auditLog->user->name ?? 'غير محدد'),
            'role' => $auditLog->user_role ?? '-',
            'action' => $auditLog->action_label,
            'module' => $auditLog->module_label,
            'entity' => $auditLog->entity_name,
            'description' => $auditLog->description,
            'outcome' => $auditLog->outcome,
            'ip' => $auditLog->ip_address,
            'user_agent' => $auditLog->user_agent,
            'url' => $auditLog->url,
            'method' => $auditLog->method,
            'created_at' => $auditLog->created_at->format('Y-m-d H:i:s'),
            'changes' => $auditLog->changes,
            'old_values' => $auditLog->old_values,
            'new_values' => $auditLog->new_values,
        ]);
    }

    /**
     * تصدير سجل التدقيق إلى Excel (CSV مع BOM)
     */
    private function exportLogs($logs)
    {
        $filename = 'audit_log_'.date('Y-m-d').'.csv';

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF)); // لدعم اللغة العربية

            fputcsv($file, [
                'المعرف',
                'التاريخ والوقت',
                'المستخدم',
                'الدور',
                'الإجراء',
                'القسم',
                'السجل المتأثر',
                'البيان',
                'النتيجة',
                'عنوان IP',
                'الرابط',
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user_name ?? ($log->user->name ?? 'النظام'),
                    $log->user_role ?? '-',
                    $log->action_label,
                    $log->module_label,
                    $log->entity_name ?? '-',
                    $log->description ?? '-',
                    $log->outcome === 'success' ? 'ناجح' : 'فاشل',
                    $log->ip_address ?? '-',
                    $log->url ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
