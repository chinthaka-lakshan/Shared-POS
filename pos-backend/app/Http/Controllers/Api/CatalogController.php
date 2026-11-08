<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Carbon\Carbon;

class CatalogController extends Controller
{
    public function delta(Request $request)
    {
        $since = $request->query('since');
        
        $query = Product::withTrashed()
            ->where('tenant_id', $request->user()->tenant_id ?? 1); // Mock tenant for now or derive from terminal

        if ($since) {
            $date = Carbon::parse($since);
            $query->where('updated_at', '>=', $date);
        }

        $products = $query->with('batches')->get();

        return response()->json([
            'products' => $products,
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
