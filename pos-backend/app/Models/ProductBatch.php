<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    protected $guarded = [];

    public function product() { return $this->belongsTo(Product::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function freeProduct() { return $this->belongsTo(Product::class, 'free_product_id'); }
}
