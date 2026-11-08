<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Model;
use Filament\Panel;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'role', 'tenant_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
    
    public function getTenants(Panel $panel): array|Collection
    {
        if ($this->role === 'super_admin') {
            return Tenant::all();
        }
        return $this->tenant ? collect([$this->tenant]) : collect();
    }
    
    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }
        return $this->tenant_id === $tenant->id;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->role === 'super_admin';
        }

        // Super Admins can access the shop portal to view/manage any shop
        if ($this->role === 'super_admin') {
            return true;
        }
        
        // Block access if tenant is inactive
        if ($this->tenant_id !== null && $this->tenant && !$this->tenant->is_active) {
            return false;
        }

        // Allow shop_owner and manager to access the admin panel
        return in_array($this->role, ['shop_owner', 'manager']) && $this->tenant_id !== null;
    }
}
