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
                Tables\Columns\TextColumn::make('priority')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Raised')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ComplaintStatus::class),
                Tables\Filters\Filter::make('open')->label('Open only')->query(fn (Builder $q): Builder => $q->open())->default(),
            ])
            ->actions([
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
