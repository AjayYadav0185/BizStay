<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Filament\Resources\ComplaintResource\Pages;
use App\Models\Complaint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Complaint')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('title')->required()->maxLength(255)->columnSpan(2),
                    Forms\Components\Select::make('priority')->options(ComplaintPriority::class)->default('medium')->required(),
                    Forms\Components\Select::make('guest_id')->relationship('guest', 'full_name')->searchable()->preload(),
                    Forms\Components\Select::make('room_id')->relationship('room', 'room_number')->searchable()->preload(),
                    Forms\Components\Select::make('status')->options(ComplaintStatus::class)->default('open')->required(),
                    Forms\Components\TextInput::make('category')->default('other'),
                    Forms\Components\Select::make('assigned_to')->relationship('assignedTo', 'name')->searchable()->preload(),
                    Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('resolution_notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->description(fn (Complaint $r): string => $r->guest?->full_name ?? ''),
                Tables\Columns\TextColumn::make('room.room_number')->label('Room')->placeholder('—'),
                Tables\Columns\TextColumn::make('assignedTo.name')->label('Staff')->placeholder('Unassigned')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('priority')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('sla')
                    ->label('SLA')
                    ->state(fn (Complaint $r): string => $r->slaBreached() ? 'Breached ('.$r->hoursOpen().'h)' : $r->hoursOpen().'h open')
                    ->badge()
                    ->color(fn (Complaint $r): string => $r->slaBreached() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')->label('Raised')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ComplaintStatus::class),
                Tables\Filters\Filter::make('open')->label('Open only')->query(fn (Builder $query): Builder => $query->open())->default(),
                Tables\Filters\Filter::make('sla_breached')->label('SLA breached')
                    ->query(fn (Builder $query): Builder => $query->open()->where('created_at', '<', now()->subHours(72)->toDateTimeString())),
                Tables\Filters\Filter::make('unassigned')->label('Unassigned')
                    ->query(fn (Builder $query): Builder => $query->open()->whereNull('assigned_to')),
            ])
            ->actions([
                Tables\Actions\Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(fn (Complaint $r): bool => $r->status === ComplaintStatus::Open)
                    ->action(fn (Complaint $r) => $r->startWork()),
                Tables\Actions\Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (Complaint $r): bool => $r->status === ComplaintStatus::Open || $r->status === ComplaintStatus::InProgress)
                    ->form([
                        Forms\Components\Select::make('assigned_to')
                            ->label('Staff member')
                            ->options(fn (): array => \App\Models\User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Complaint $record, array $data): void {
                        $record->forceFill(['assigned_to' => $data['assigned_to']])->save();

                        if ($record->status === ComplaintStatus::Open) {
                            $record->startWork();
                        }

                        Notification::make()->title('Assigned to '.$record->assignedTo?->name)->success()->send();
                    }),
                Tables\Actions\Action::make('resolve')->color('success')->icon('heroicon-o-check')->visible(fn (Complaint $r): bool => in_array($r->status, [ComplaintStatus::Open, ComplaintStatus::InProgress], true))->form([Forms\Components\Textarea::make('notes')->label('Resolution')])->action(fn (Complaint $r, array $d): bool => $r->resolve($d['notes'] ?? null)),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListComplaints::route('/'), 'create' => Pages\CreateComplaint::route('/create'), 'edit' => Pages\EditComplaint::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['guest', 'room']);
    }
}
