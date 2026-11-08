<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Sale;
use Carbon\Carbon;

class SalesChart extends ChartWidget
{
    protected static ?string $heading = 'Sales Trend (Last 7 Days)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $tenant = filament()->getTenant();
        
        $data = Sale::where('tenant_id', $tenant?->id ?? 0)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            // sqlite uses strftime, mysql uses DATE(), so we group differently based on DB
            ->get()
            ->groupBy(function($val) {
                return Carbon::parse($val->created_at)->format('Y-m-d');
            });
            
        $labels = [];
        $values = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M d');
            
            $daySales = isset($data[$date]) ? $data[$date]->sum('total_payable') : 0;
            $values[] = $daySales;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $values,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
