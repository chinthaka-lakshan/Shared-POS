<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }
    public function grns() { return $this->hasMany(Grn::class); }

    public function getTotalSuppliedAttribute()
    {
        return $this->grns()->join('grn_items', 'grns.id', '=', 'grn_items.grn_id')
                    ->sum('grn_items.received_qty');
    }

    public function getCurrentlyAvailableAttribute()
    {
        $batchNumbers = $this->grns()->join('grn_items', 'grns.id', '=', 'grn_items.grn_id')
                             ->pluck('grn_items.batch_number');
                             
        return \App\Models\ProductBatch::whereIn('batch_number', $batchNumbers)->sum('quantity');
    }

    public function getSoldStockAttribute()
    {
        return max(0, $this->total_supplied - $this->currently_available);
    }
}
