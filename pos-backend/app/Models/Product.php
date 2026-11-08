<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->barcode)) {
                $product->barcode = 'PRD-' . strtoupper(uniqid());
            }
        });
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function batches() { return $this->hasMany(ProductBatch::class); }

    public function getTotalStockAttribute()
    {
        return $this->stock_quantity + $this->batches()->sum('quantity');
    }
    public function saleItems() { return $this->hasMany(SaleItem::class); }
    public function grnItems() { return $this->hasMany(GrnItem::class); }
}
