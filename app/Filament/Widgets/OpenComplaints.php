<?php

namespace App\Filament\Widgets;

use App\Models\Complaint;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpenComplaints extends TableWidget
{
    protected static ?string $heading = 'Open Complaints (needs attention)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Complaint::query()
                    ->whereIn('status', ['open', 'in_progress'])
                    ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
                    ->orderBy('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')->limit(35),
                Tables\Columns\TextColumn::make('property.name')->label('Property'),
                Tables\Columns\TextColumn::make('tenant.full_name')->label('Tenant')->placeholder('—'),
                Tables\Columns\TextColumn::make('priority')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Raised'),
            ])
            ->recordUrl(fn (Complaint $record) => \App\Filament\Resources\ComplaintResource::getUrl('index'))
            ->paginated(false);
    }
}
