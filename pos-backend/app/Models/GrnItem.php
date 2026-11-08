<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function grn() { return $this->belongsTo(Grn::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
