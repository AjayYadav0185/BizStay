<?php

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

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Complaint / Request';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Complaint Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('property_id')
                            ->relationship('property', 'name')
                            ->searchable()->preload()->required()->live(),
                        Forms\Components\Select::make('tenant_id')
                            ->label('Raised By (Tenant)')
                            ->options(fn (Forms\Get $get) => \App\Models\Tenant::query()
                                ->where('property_id', $get('property_id'))
                                ->pluck('full_name', 'id')
                                ->all())
                            ->searchable()
                            ->hidden(fn (Forms\Get $get) => blank($get('property_id'))),
                        Forms\Components\TextInput::make('title')->required()->columnSpan(2),
                        Forms\Components\Textarea::make('description')->rows(3)->columnSpan(2),
                        Forms\Components\Select::make('category')
                            ->options([
                                'electrical' => 'Electrical',
                                'plumbing' => 'Plumbing',
                                'housekeeping' => 'Housekeeping',
                                'security' => 'Security',
                                'internet' => 'Internet / WiFi',
                                'food' => 'Food / Mess',
                                'other' => 'Other',
                            ])
                            ->default('other')->required(),
                        Forms\Components\Select::make('priority')
                            ->options(ComplaintPriority::class)
                            ->default(ComplaintPriority::Medium->value)->required(),
                        Forms\Components\TextInput::make('assigned_to')
                            ->label('Assigned To (Staff)')
                            ->placeholder('e.g. Ramesh (Electrician)'),
                        Forms\Components\Select::make('status')
                            ->options(ComplaintStatus::class)
                            ->default(ComplaintStatus::Open->value)->required()->live(),
                        Forms\Components\Textarea::make('resolution_notes')
                            ->rows(2)->columnSpan(2)
                            ->visible(fn (Forms\Get $get) => in_array($get('status'), [ComplaintStatus::Resolved->value, ComplaintStatus::Closed->value])),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('property.name')->label('Property')->sortable(),
                Tables\Columns\TextColumn::make('tenant.full_name')->label('Tenant')->placeholder('—'),
                Tables\Columns\TextColumn::make('category')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('priority')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('assigned_to')->label('Assigned To')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('resolved_at')->since()->label('Resolved')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('property')->relationship('property', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(ComplaintStatus::class),
                Tables\Filters\SelectFilter::make('priority')->options(ComplaintPriority::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Complaint $record) => ! in_array($record->status, [ComplaintStatus::Resolved, ComplaintStatus::Closed]))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('resolution_notes')->rows(3)->required(),
                    ])
                    ->action(function (array $data, Complaint $record) {
                        $record->update([
                            'status' => ComplaintStatus::Resolved,
                            'resolved_at' => now(),
                            'resolution_notes' => $data['resolution_notes'],
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageComplaints::route('/'),
        ];
    }
}
