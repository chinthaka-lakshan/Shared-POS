<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function users() { return $this->hasMany(User::class); }
    public function suppliers() { return $this->hasMany(Supplier::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function terminals() { return $this->hasMany(Terminal::class); }
}
