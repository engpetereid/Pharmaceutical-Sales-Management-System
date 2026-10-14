<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ترجمة الأفعال للعربية
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'إضافة',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'returned' => 'مرتجع مبيعات',
            'payment_added' => 'إضافة دفعة',
            'payment_updated' => 'تعديل دفعة',
            'payment_deleted' => 'حذف دفعة',
            'stock_added' => 'توريد / نقل مخزون',
            'stock_returned' => 'صرف / مرتجع مخزون',
            'deal_paid' => 'تسوية / سداد اتفاق',
            'deal_toggled' => 'تغيير حالة اتفاق',
            'login_success' => 'تسجيل دخول ناجح',
            'login_failed' => 'فشل تسجيل الدخول',
            'logout' => 'تسجيل خروج',
            'exported' => 'تصدير بيانات',
            default => $this->action,
        };
    }

    /**
     * فئة لون البادج للحدث
     */
    public function getActionBadgeClassAttribute(): string
    {
        return match ($this->action) {
            'created', 'stock_added' => 'badge-success',
            'updated', 'payment_updated', 'deal_toggled' => 'badge-warning',
            'deleted', 'payment_deleted' => 'badge-danger',
            'returned', 'stock_returned' => 'badge-danger',
            'payment_added', 'deal_paid' => 'badge-info',
            'login_success' => 'badge-primary',
            'login_failed' => 'badge-danger',
            'logout' => 'badge-secondary',
            default => 'badge-dark',
        };
    }

    /**
     * ترجمة اسم الوحدة (Module) للعربية
     */
    public function getModuleLabelAttribute(): string
    {
        return match ($this->module) {
            'Invoice' => 'فواتير المبيعات',
            'InvoiceReturn' => 'مرتجعات الفواتير',
            'InvoicePayment' => 'دفعات الفواتير',
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
            default => $this->module,
        };
    }
}
