<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $guarded = [];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function items() { return $this->hasMany(SaleReturnItem::class); }
}
