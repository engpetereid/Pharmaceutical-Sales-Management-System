<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class InvoiceReturn extends Model
{
    use Auditable;

    protected $table = 'invoice_returns';

    protected $guarded = ['id'];

    protected $casts = [
        'return_date' => 'date',
        'total_return_public_price' => 'decimal:2',
        'total_return_discount' => 'decimal:2',
        'total_return_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceReturnItem::class, 'invoice_return_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * توليد رقم تسلسلي فريد للمرتجع يضمن عدم التكرار عالمياً
     */
    public static function generateReturnNumber(Invoice $invoice): string
    {
        $prefix = trim((string) ($invoice->serial_number ?: $invoice->id));

        // الهروب من الرموز الخاصة بـ SQL LIKE
        $escapedLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix);

        // استخراج جميع أرقام المرتجعات المسجلة المطابقة للبادئة
        $existingNumbers = static::where('return_number', 'LIKE', "RET-{$escapedLike}-%")
            ->pluck('return_number')
            ->toArray();

        $maxSeq = 0;
        $escapedPrefix = preg_quote($prefix, '/');
        foreach ($existingNumbers as $num) {
            if (preg_match('/^RET-'.$escapedPrefix.'-(\d+)$/', $num, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;
        $candidate = "RET-{$prefix}-{$nextSeq}";

        // ضمان عدم وجود أي تصادم حتى في حالات الحذف أو التزامن
        while (static::where('return_number', $candidate)->exists()) {
            $nextSeq++;
            $candidate = "RET-{$prefix}-{$nextSeq}";
        }

        return $candidate;
    }
}
