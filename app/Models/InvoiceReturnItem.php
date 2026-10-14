<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceReturnItem extends Model
{
    protected $table = 'invoice_return_items';

    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'pharmacist_discount_percentage' => 'decimal:2',
        'subtotal_public' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal_net' => 'decimal:2',
    ];

    public function return()
    {
        return $this->belongsTo(InvoiceReturn::class, 'invoice_return_id');
    }

    public function detail()
    {
        return $this->belongsTo(InvoiceDetail::class, 'invoice_detail_id');
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
