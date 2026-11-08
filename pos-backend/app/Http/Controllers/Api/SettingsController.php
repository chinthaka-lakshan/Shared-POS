<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $tenant = Tenant::first();
        return response()->json([
            'manager_pin' => $tenant ? $tenant->manager_pin : null,
        ]);
    }
}
