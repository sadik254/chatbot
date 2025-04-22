<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'industry',
        'email',
        'phone',
        'address',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function chatLogs()
    {
        return $this->hasMany(ChatLog::class);
    }

}
