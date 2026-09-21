<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

class FollowUpInquiries extends TableWidget
{
    protected static ?string $heading = 'Inquiries — Due for Follow-up';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->whereNotIn('status', [BookingStatus::Booked->value, BookingStatus::Cancelled->value])
                    ->orderByRaw('follow_up_date IS NULL, follow_up_date ASC')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->copyable(),
                Tables\Columns\TextColumn::make('property.name')->label('Property')->placeholder('—'),
                Tables\Columns\TextColumn::make('source')->badge()->color('info'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('follow_up_date')
                    ->date('d M')
                    ->color(fn ($state) => $state && Carbon::parse($state)->isPast() ? 'danger' : 'gray'),
            ])
            ->recordUrl(fn (Booking $record) => BookingResource::getUrl('index'))
            ->paginated(false);
    }
}
