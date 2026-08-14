<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderPayout extends Model
{
    use HasFactory;
    protected $table = 'provider_payouts';
    protected $fillable = [
        'provider_id', 'booking_id', 'payment_method', 'description','amount','status','paid_date','bank_id',
        'stripe_transfer_id', 'stripe_payout_id', 'failure_reason',
    ];
    protected $casts = [
        'provider_id'     => 'integer',
        'booking_id'      => 'integer',
        'amount'    => 'double',
    ];
    public function providers(){
        return $this->belongsTo(User::class, 'provider_id','id');
    }
    public function booking(){
        return $this->belongsTo(Booking::class, 'booking_id', 'id')->withTrashed();
    }
    public function scopeMyPayout($query)
    {
        if(auth()->user()->hasRole('admin')) {
            return $query;
        }

        if(auth()->user()->hasRole('provider')) {
            return $query->where('provider_id', \Auth::id());
        }

        return $query;
    }

}
