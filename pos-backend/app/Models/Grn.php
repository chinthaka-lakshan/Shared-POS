<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grn extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function items() { return $this->hasMany(GrnItem::class); }
}
