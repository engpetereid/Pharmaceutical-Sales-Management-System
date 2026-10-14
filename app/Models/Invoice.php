<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use Auditable;

    protected $table = 'invoices';

    protected $fillable = [
        'serial_number',
        'invoice_date',
        'line',
        'representative_id',
        'medical_representative_id',
        'pharmacist_id',
        'doctor_id',
        'doctor_commission_percentage',
        'doctor_commission_paid',
        'total_amount',
        'total_discount',
        'final_total',
        'paid_amount',
        'remaining_amount',
        'status',
        'notes',
        'falls_under_doctor_deal',
    ];


    public function medicalRepresentative()
    {
        return $this->belongsTo(Representative::class, 'medical_representative_id');
    }

    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }

//    public function doctor()
//    {
//        return $this->belongsTo(Doctor::class);
//    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }
    public function doctors()
    {
        return $this->belongsToMany(Doctor::class, 'doctor_invoice');
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class, 'invoice_id');
    }

    public function deals()
    {
        return $this->belongsToMany(DoctorDeal::class, 'doctor_deal_invoices', 'invoice_id', 'doctor_deal_id')
            ->withPivot('contribution_amount');
    }

    public function returns()
    {
        return $this->hasMany(InvoiceReturn::class)->latest('return_date');
    }

    public function hasReturns(): bool
    {
        return $this->returns()->exists();
    }

    public function totalReturnedAmount(): float
    {
        return (float) $this->returns()->sum('total_return_amount');
    }
}
