<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Waste extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function terminal() { return $this->belongsTo(Terminal::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function expense() { return $this->belongsTo(Expense::class); }
}
