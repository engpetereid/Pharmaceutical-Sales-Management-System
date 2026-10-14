<?php

namespace App\Traits;

use App\Services\AuditService;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (static::isAuditingDisabled()) {
                return;
            }

            $module = class_basename($model);
            $newValues = $model->getAuditableAttributes();

            $description = 'تم إضافة '.static::getAuditModelArabicName($module).' جديد: '.AuditService::resolveEntityName($model);

            AuditService::log(
                action: 'created',
                module: $module,
                entity: $model,
                description: $description,
                oldValues: null,
                newValues: $newValues
            );
        });

        static::updated(function ($model) {
            if (static::isAuditingDisabled()) {
                return;
            }

            $ignored = array_merge(
                ['updated_at', 'created_at', 'password', 'remember_token'],
                $model->getAuditIgnoredAttributes()
            );

            $changes = [];
            $oldValues = [];
            $newValues = [];

            foreach ($model->getChanges() as $attribute => $newValue) {
                if (in_array($attribute, $ignored)) {
                    continue;
                }

                $originalValue = $model->getOriginal($attribute);

                // مقارنة مرنة للأرقام والقيم العشرية لتفادي الفروق الشكلية
                if ((string) $originalValue === (string) $newValue) {
                    continue;
                }

                $oldValues[$attribute] = $originalValue;
                $newValues[$attribute] = $newValue;
                $changes[$attribute] = [
                    'old' => $originalValue,
                    'new' => $newValue,
                ];
            }

            if (empty($changes)) {
                return;
            }

            $module = class_basename($model);
            $description = 'تم تعديل '.static::getAuditModelArabicName($module).': '.AuditService::resolveEntityName($model);

            AuditService::log(
                action: 'updated',
                module: $module,
                entity: $model,
                description: $description,
                oldValues: $oldValues,
                newValues: $newValues,
                changes: $changes
            );
        });

        static::deleted(function ($model) {
            if (static::isAuditingDisabled()) {
                return;
            }

            $module = class_basename($model);
            $oldValues = $model->getAuditableAttributes();
            $description = 'تم حذف '.static::getAuditModelArabicName($module).': '.AuditService::resolveEntityName($model);

            AuditService::log(
                action: 'deleted',
                module: $module,
                entity: $model,
                description: $description,
                oldValues: $oldValues,
                newValues: null
            );
        });
    }

    public function getAuditableAttributes(): array
    {
        $ignored = array_merge(
            ['password', 'remember_token'],
            $this->getAuditIgnoredAttributes()
        );

        $attributes = $this->attributesToArray();

        foreach ($ignored as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    public function getAuditIgnoredAttributes(): array
    {
        return property_exists($this, 'auditIgnored') ? (array) $this->auditIgnored : [];
    }

    protected static bool $auditingDisabled = false;

    public static function disableAuditing(): void
    {
        static::$auditingDisabled = true;
    }

    public static function enableAuditing(): void
    {
        static::$auditingDisabled = false;
    }

    public static function isAuditingDisabled(): bool
    {
        return static::$auditingDisabled;
    }

    public static function getAuditModelArabicName(string $module): string
    {
        return match ($module) {
            'Invoice' => 'فاتورة مبيعات',
            'InvoiceDetail' => 'بند فاتورة',
            'InvoicePayment' => 'سداد دفعة',
            'InvoiceReturn' => 'إيصال مرتجع',
            'Warehouse' => 'مخزن',
            'Drug' => 'دواء',
            'DoctorDeal' => 'اتفاق طبيب',
            'Doctor' => 'طبيب',
            'Pharmacist' => 'صيدلية',
            'Representative' => 'مندوب',
            'Zone' => 'منطقة',
            'ZoneExpense' => 'مصروف منطقة',
            'GeneralExpense' => 'مصروف عام',
            'User' => 'مستخدم',
            default => $module,
        };
    }
}
