<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Terminal extends Model
{
    use HasFactory, HasApiTokens;
    protected $guarded = [];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function sales() { return $this->hasMany(Sale::class); }
}
