<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'description',
        'expense_date'
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];
}
