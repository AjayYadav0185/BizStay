<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\BedStatus;
use App\Models\Bed;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Floor-wise bed status breakdown — answers "which floor can I sell today?".
 */
final class OccupancyByFloorChart extends ChartWidget
{
    protected static ?string $heading = 'Floor-wise Bed Status';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?array $options = [
        'scales' => [
            'x' => ['stacked' => true],
            'y' => ['stacked' => true, 'ticks' => ['stepSize' => 1]],
        ],
    ];

    protected function getData(): array
    {
        $beds = Bed::query()->with('room:id,floor_no')->get()
            ->groupBy(fn (Bed $bed): int => (int) ($bed->room?->floor_no ?? 0))
            ->sortKeys();

        $labels = $beds->keys()
            ->map(fn (int $floor): string => $floor === 0 ? 'Ground' : 'Floor '.$floor)
            ->all();

        return [
            'datasets' => [
                $this->dataset('Available', $beds, BedStatus::Available, '#22c55e'),
                $this->dataset('Occupied', $beds, BedStatus::Occupied, '#3b82f6'),
                $this->dataset('Reserved', $beds, BedStatus::Reserved, '#f59e0b'),
                $this->dataset('Maintenance', $beds, BedStatus::Maintenance, '#ef4444'),
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @param  Collection<int|string, Collection<int, Bed>>  $beds
     * @return array<string, mixed>
     */
    private function dataset(string $label, Collection $beds, BedStatus $status, string $color): array
    {
        return [
            'label' => $label,
            'data' => $beds->map(
                fn (Collection $floorBeds): int => $floorBeds->where('status', $status)->count(),
            )->values()->all(),
            'backgroundColor' => $color,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
