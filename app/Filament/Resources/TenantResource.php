<?php

namespace App\Filament\Resources;

use App\Enums\BedStatus;
use App\Enums\TenantStatus;
use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers\ComplaintsRelationManager;
use App\Filament\Resources\TenantResource\RelationManagers\PaymentsRelationManager;
use App\Models\Bed;
use App\Models\Property;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('full_name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('phone')->required()->tel()->maxLength(20),
                        Forms\Components\TextInput::make('email')->email(),
                        Forms\Components\Select::make('gender')
                            ->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])
                            ->default('male')->required(),
                        Forms\Components\Textarea::make('permanent_address')->rows(2)->columnSpan(2),
                        Forms\Components\TextInput::make('home_city')
                            ->placeholder('e.g. Patna, Jaipur, Lucknow'),
                        Forms\Components\TextInput::make('occupation')
                            ->placeholder('e.g. Software Engineer'),
                        Forms\Components\TextInput::make('company_name')
                            ->placeholder('e.g. Working at Cyber Hub, Udyog Vihar'),
                        Forms\Components\TextInput::make('emergency_contact_name'),
                        Forms\Components\TextInput::make('emergency_contact_phone')->tel(),
                    ]),
                ]),
                Forms\Components\Section::make('KYC')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('id_proof_type')
                            ->options(['aadhaar' => 'Aadhaar', 'pan' => 'PAN Card', 'passport' => 'Passport', 'dl' => 'Driving Licence'])
                            ->default('aadhaar')->required(),
                        Forms\Components\TextInput::make('id_proof_number')->maxLength(50),
                        Forms\Components\FileUpload::make('id_proof_file')
                            ->disk('public')->directory('tenant-docs')
                            ->columnSpan(2),
                        Forms\Components\Toggle::make('kyc_verified')->label('KYC Verified'),
                    ]),
                ]),
                Forms\Components\Section::make('Stay & Rent')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('property_id')
                            ->relationship('property', 'name')
                            ->searchable()->preload()->required()->live(),
                        Forms\Components\Select::make('bed_id')
                            ->label('Bed')
                            ->options(fn (Forms\Get $get) => Bed::query()
                                ->where('status', BedStatus::Vacant->value)
                                ->whereHas('room', fn ($q) => $q->where('property_id', $get('property_id')))
                                ->with('room')
                                ->get()
                                ->mapWithKeys(fn (Bed $bed) => [$bed->id => 'Room '.$bed->room->room_number.' — Bed '.$bed->bed_number])
                                ->all())
                            ->searchable()
                            ->hidden(fn (Forms\Get $get) => blank($get('property_id')))
                            ->helperText('Only vacant beds are shown'),
                        Forms\Components\TextInput::make('monthly_rent')->numeric()->prefix('₹')->required(),
                        Forms\Components\TextInput::make('security_deposit')->numeric()->prefix('₹')->default(0),
                        Forms\Components\DatePicker::make('joining_date')->required()->default(now()),
                        Forms\Components\TextInput::make('rent_due_day')->numeric()->minValue(1)->maxValue(28)->default(5),
                        Forms\Components\DatePicker::make('notice_date')->label('Notice Given On'),
                        Forms\Components\DatePicker::make('vacated_date')->visible(fn (Forms\Get $get) => $get('status') === TenantStatus::Vacated->value),
                        Forms\Components\Select::make('status')
                            ->options(TenantStatus::class)
                            ->default(TenantStatus::Active->value)->required()->live(),
                        Forms\Components\Textarea::make('notes')->columnSpan(2)->rows(2),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()->sortable()
                    ->description(fn (Tenant $record) => $record->company_name),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()->copyable()
                    ->icon('heroicon-o-phone'),
                Tables\Columns\TextColumn::make('property.name')
                    ->label('Property')->sortable(),
                Tables\Columns\TextColumn::make('bed.room.room_number')
                    ->label('Room / Bed')
                    ->formatStateUsing(fn (Tenant $record) => $record->bed
                        ? 'Room '.$record->bed->room->room_number.' · Bed '.$record->bed->bed_number
                        : '—'),
                Tables\Columns\TextColumn::make('monthly_rent')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('pending_due')
                    ->label('Pending Due')->money('INR')
                    ->color(fn (string $state): string => (float) $state > 0 ? 'danger' : 'success')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('joining_date')->date('d M Y')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('property')->relationship('property', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(TenantStatus::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markNotice')
                    ->label('Notice')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (Tenant $record) => $record->status === TenantStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Tenant $record) {
                        $record->update([
                            'status' => TenantStatus::NoticePeriod,
                            'notice_date' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('markVacated')
                    ->label('Vacate')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->visible(fn (Tenant $record) => in_array($record->status?->value ?? '', ['active', 'notice_period']))
                    ->requiresConfirmation()
                    ->action(function (Tenant $record) {
                        $record->update([
                            'status' => TenantStatus::Vacated,
                            'vacated_date' => now(),
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

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
            ComplaintsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
