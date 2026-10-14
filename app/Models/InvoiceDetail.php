<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    protected $table = 'invoice_details';

    protected $fillable = [
        'invoice_id',
        'drug_id',
        'quantity',
        'returned_quantity',
        'unit_price',
        'pharmacist_discount_percentage',
        'row_total'
    ];

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }

    public function drug(){
        return $this->belongsTo(Drug::class);
    }

    public function returnItems()
    {
        return $this->hasMany(InvoiceReturnItem::class, 'invoice_detail_id');
    }

    public function getOriginalQuantityAttribute(): int
    {
        return (int) ($this->quantity + ($this->returned_quantity ?? 0));
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity);
    }
}
