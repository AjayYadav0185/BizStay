<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use Filament\Widgets\ChartWidget;

class OccupancyChart extends ChartWidget
{
    protected static ?string $heading = 'Property-wise Bed Status';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?array $options = [
        'scales' => [
            'x' => ['stacked' => true],
            'y' => ['stacked' => true, 'ticks' => ['stepSize' => 1]],
        ],
    ];

    protected function getData(): array
    {
        $properties = Property::with('beds')->where('is_active', true)->orderBy('name')->get();

        $labels = $properties->pluck('name')->all();
        $occupied = $properties->map(fn ($p) => $p->beds->where('status', 'occupied')->count())->all();
        $vacant = $properties->map(fn ($p) => $p->beds->where('status', 'vacant')->count())->all();
        $blocked = $properties->map(fn ($p) => $p->beds->where('status', 'blocked')->count())->all();

        return [
            'datasets' => [
                [
                    'label' => 'Occupied',
                    'data' => $occupied,
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'Vacant',
                    'data' => $vacant,
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Blocked',
                    'data' => $blocked,
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
