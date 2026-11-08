<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function sales() { return $this->hasMany(Sale::class); }
    public function creditPayments() { return $this->hasMany(CreditPayment::class); }
}
