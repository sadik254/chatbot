<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'description',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
