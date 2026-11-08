<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class PosSyncController extends Controller
{
    public function syncSales(Request $request)
    {
        $request->validate([
            'sales' => 'required|array'
        ]);

        $syncedUuids = [];
        $failedUuids = [];

        foreach ($request->sales as $saleData) {
            try {
                DB::transaction(function () use ($saleData, $request, &$syncedUuids) {
                    $terminal = $request->user(); // The authenticated terminal

                    $tenantId = $terminal->tenant_id ?? 1;

                    // Handle Customer for Credit Sales
                    $customerId = $saleData['customer_id'] ?? null;
                    if (($saleData['payment_status'] ?? 'paid') === 'credit' && !empty($saleData['customer_name'])) {
                        $customer = \App\Models\Customer::firstOrCreate(
                            ['tenant_id' => $tenantId, 'phone' => $saleData['customer_phone'] ?? null],
                            ['name' => $saleData['customer_name'], 'total_credit' => 0]
                        );
                        $customerId = $customer->id;
                        
                        // Increase customer's total credit
                        $customer->increment('total_credit', $saleData['total_payable']);
                    }

                    // Idempotency check: only create if it doesn't exist
                    $sale = Sale::firstOrCreate(
                        ['uuid' => $saleData['uuid']],
                        [
                            'tenant_id' => $tenantId,
                            'terminal_id' => $terminal->id,
                            'user_id' => $saleData['user_id'] ?? null,
                            'customer_id' => $customerId,
                            'invoice_number' => $saleData['invoice_number'],
                            'subtotal' => $saleData['subtotal'],
                            'discount_amount' => $saleData['discount_amount'] ?? 0,
                            'tax_amount' => $saleData['tax_amount'] ?? 0,
                            'total_payable' => $saleData['total_payable'],
                            'payment_method' => is_string($saleData['payment_method'] ?? null) ? json_decode($saleData['payment_method'], true) : ($saleData['payment_method'] ?? []),
                            'payment_status' => $saleData['payment_status'] ?? 'paid',
                            'note' => $saleData['note'] ?? null,
                            'billed_at' => $saleData['billed_at']
                        ]
                    );

                    // If it was recently created in this request, add items and decrement stock
                    if ($sale->wasRecentlyCreated && isset($saleData['items']) && is_array($saleData['items'])) {
                        foreach ($saleData['items'] as $itemData) {
                            SaleItem::create([
                                'sale_id' => $sale->id,
                                'product_id' => $itemData['product_id'],
                                'batch_number' => $itemData['batch_number'] ?? null,
                                'quantity' => $itemData['qty'] ?? $itemData['quantity'],
                                'unit_cost' => $itemData['unit_cost'] ?? 0,
                                'unit_price' => $itemData['unit_price'] ?? 0,
                                'subtotal' => $itemData['subtotal'] ?? 0,
                            ]);

                            // Atomic decrement of inventory from specific batch
                            $qtyToDeduct = $itemData['qty'] ?? $itemData['quantity'];
                            if (!empty($itemData['batch_number'])) {
                                \App\Models\ProductBatch::where('product_id', $itemData['product_id'])
                                    ->where('batch_number', $itemData['batch_number'])
                                    ->decrement('quantity', $qtyToDeduct);
                            }
                        }
                    }

                    $syncedUuids[] = $saleData['uuid'];
                });
            } catch (\Exception $e) {
                \Log::error('Error syncing sale: ' . $saleData['uuid'], ['error' => $e->getMessage()]);
                $failedUuids[] = [
                    'uuid' => $saleData['uuid'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'synced' => $syncedUuids,
            'failed' => $failedUuids
        ]);
    }

    public function syncWastes(Request $request)
    {
        $request->validate([
            'wastes' => 'required|array'
        ]);

        $syncedUuids = [];
        $failedUuids = [];
        $terminal = $request->user();
        $tenantId = $terminal->tenant_id ?? 1;

        foreach ($request->wastes as $wasteData) {
            try {
                \App\Models\Waste::firstOrCreate(
                    ['uuid' => $wasteData['uuid']],
                    [
                        'tenant_id' => $tenantId,
                        'terminal_id' => $terminal->id,
                        'product_id' => $wasteData['product_id'],
                        'batch_number' => $wasteData['batch_number'] ?? null,
                        'quantity' => $wasteData['quantity'],
                        'cost_price' => $wasteData['cost_price'] ?? 0,
                        'reason' => $wasteData['reason'] ?? null,
                        'status' => 'pending' // Always pending when synced from POS
                    ]
                );
                $syncedUuids[] = $wasteData['uuid'];
            } catch (\Exception $e) {
                \Log::error('Error syncing waste: ' . $wasteData['uuid'], ['error' => $e->getMessage()]);
                $failedUuids[] = [
                    'uuid' => $wasteData['uuid'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'synced' => $syncedUuids,
            'failed' => $failedUuids
        ]);
    }
}
