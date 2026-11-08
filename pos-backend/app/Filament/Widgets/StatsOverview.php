<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\ProductBatch;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = filament()->getTenant();
        if (!$tenant) {
            return [];
        }

        $today = Carbon::today();

        $todaySales = Sale::where('tenant_id', $tenant->id)
            ->whereDate('created_at', $today)
            ->sum('total_payable');

        $todayExpenses = Expense::where('tenant_id', $tenant->id)
            ->whereDate('expense_date', $today)
            ->sum('amount');

        $lowStockCount = ProductBatch::where('tenant_id', $tenant->id)
            ->where('quantity', '<=', 5)
            ->count();

        return [
            Stat::make('Today Sales', '$' . number_format($todaySales, 2))
                ->description('Total revenue today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
                
            Stat::make('Today Expenses', '$' . number_format($todayExpenses, 2))
                ->description('Total expenses today')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart([17, 16, 14, 15, 14, 13, 12]),
                
            Stat::make('Low Stock Batches', $lowStockCount)
                ->description('Batches near depletion')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }
}
