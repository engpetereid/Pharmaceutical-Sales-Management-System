<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneExpense extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'zone_id',
        'amount',
        'description',
        'expense_date'
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
