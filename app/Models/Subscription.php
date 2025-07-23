<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'paypal_subscription_id',
        'status',
        'started_at',
        'next_billing_time',
        'cancelled_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'next_billing_time' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * A subscription belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A subscription belongs to a plan.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }   
}
