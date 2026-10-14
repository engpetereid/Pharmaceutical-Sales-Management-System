<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * تسجيل عملية في سجل التدقيق
     */
    public static function log(
        string $action,
        string $module,
        ?Model $entity = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $changes = null,
        string $outcome = 'success',
        ?int $userId = null,
        ?string $userName = null,
        ?string $userRole = null
    ): ?AuditLog {
        try {
            $user = Auth::user();
            $effectiveUserId = $userId ?? ($user ? $user->id : null);
            $effectiveUserName = $userName ?? ($user ? $user->name : 'نظام / زائر');
            $effectiveUserRole = $userRole ?? ($user ? $user->role : 'system');

            $entityType = $entity ? get_class($entity) : null;
            $entityId = $entity ? $entity->getKey() : null;
            $entityName = $entity ? static::resolveEntityName($entity) : null;

            // إذا لم يتم تمرير diff، نحسبه تلقائياً لو توفرت القيم القديمة والجديدة
            if ($changes === null && $oldValues && $newValues) {
                $changes = static::computeChanges($oldValues, $newValues);
            }

            return AuditLog::create([
                'user_id' => $effectiveUserId,
                'user_name' => $effectiveUserName,
                'user_role' => $effectiveUserRole,
                'action' => $action,
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changes' => $changes,
                'outcome' => $outcome,
                'ip_address' => Request::ip(),
                'user_agent' => Request::header('User-Agent'),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // نظام الحماية: خطأ التدقيق لا يعطل حركة العمل في بيئة الإنتاج
            Log::error('AuditService Failed to write log: '.$e->getMessage(), [
                'action' => $action,
                'module' => $module,
                'error' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * تسجيل أحداث المصادقة (تسجيل الدخول / الخروج)
     */
    public static function logAuth(string $action, ?string $email = null, string $outcome = 'success', ?string $description = null): ?AuditLog
    {
        $user = Auth::user();
        $userName = $user ? $user->name : ($email ?? 'مجهول');
        $userRole = $user ? $user->role : 'guest';

        return static::log(
            action: $action,
            module: 'Auth',
            entity: $user,
            description: $description ?? ($action === 'login_success' ? "تسجيل دخول ناجح للمستخدم ($userName)" : ($action === 'login_failed' ? "محاولة تسجيل دخول فاشلة بالبريد ($email)" : "تسجيل خروج للمستخدم ($userName)")),
            outcome: $outcome,
            userId: $user ? $user->id : null,
            userName: $userName,
            userRole: $userRole
        );
    }

    /**
     * استخراج اسم وصفي معبر للكيان
     */
    public static function resolveEntityName(Model $entity): string
    {
        if (isset($entity->serial_number)) {
            return "فاتورة #{$entity->serial_number}";
        }
        if (isset($entity->return_number)) {
            return "مرتجع #{$entity->return_number}";
        }
        if (isset($entity->name)) {
            return (string) $entity->name;
        }
        if (isset($entity->description)) {
            return (string) $entity->description;
        }
        if ($entity instanceof \App\Models\InvoicePayment) {
            return 'دفعة بقيمة '.number_format($entity->amount, 2)." ج.م للفاتورة #{$entity->invoice_id}";
        }
        if ($entity instanceof \App\Models\DoctorDeal) {
            return 'اتفاق الطبيب ('.($entity->doctor->name ?? "ID: {$entity->doctor_id}").')';
        }

        return class_basename($entity)." #{$entity->getKey()}";
    }

    /**
     * حساب الفروقات بدقة بين القيم القديمة والجديدة
     */
    public static function computeChanges(array $old, array $new): array
    {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($keys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal !== $newVal) {
                $changes[$key] = [
                    'old' => $oldVal,
                    'new' => $newVal,
                ];
            }
        }

        return $changes;
    }
}
