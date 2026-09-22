<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\KycStatus;
use App\Filament\Resources\GuestResource\Pages;
use App\Models\Guest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Guests';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('full_name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->required()->tel()->maxLength(20)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\Select::make('gender')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])->default('male'),
                    Forms\Components\DatePicker::make('date_of_birth')->native(false),
                    Forms\Components\TextInput::make('aadhaar_number')
                        ->label('Aadhaar (12 digits)')
                        ->password()->revealable()
                        ->rule('regex:/^[2-9][0-9 ]{11,14}$/')
                        ->dehydrated(false)
                        ->helperText(fn (?Guest $record): string => $record?->masked_aadhaar ?? 'Stored as hash only'),
                ]),
            ]),
            Forms\Components\Section::make('KYC')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('kyc_status')->options(KycStatus::options())->default('pending')->required(),
                    Forms\Components\Select::make('id_proof_type')->options(['aadhaar' => 'Aadhaar', 'pan' => 'PAN', 'passport' => 'Passport', 'dl' => 'Driving Licence'])->default('aadhaar'),
                    Forms\Components\TextInput::make('id_proof_number')->maxLength(50),
                    Forms\Components\Textarea::make('kyc_remarks')->rows(2)->columnSpanFull(),
                ]),
            ]),
            Forms\Components\Section::make('Contact & work')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('occupation'),
                    Forms\Components\TextInput::make('company_name'),
                    Forms\Components\TextInput::make('home_city'),
                    Forms\Components\Textarea::make('permanent_address')->rows(2)->columnSpanFull(),
                    Forms\Components\TextInput::make('emergency_contact_name'),
                    Forms\Components\TextInput::make('emergency_contact_phone')->tel(),
                    Forms\Components\TextInput::make('emergency_contact_relation'),
                    Forms\Components\Toggle::make('is_blacklisted')->label('Blacklisted'),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')->searchable()->sortable()->description(fn (Guest $r): string => $r->phone),
                Tables\Columns\TextColumn::make('kyc_status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('masked_aadhaar')->label('Aadhaar'),
                Tables\Columns\TextColumn::make('currentBooking.bed.bed_code')->label('Bed')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_blacklisted')->label('Blacklisted')->boolean()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kyc_status')->options(KycStatus::options()),
                Tables\Filters\Filter::make('staying')->label('Currently staying')->query(fn (Builder $q): Builder => $q->staying()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('full_name');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGuests::route('/'), 'create' => Pages\CreateGuest::route('/create'), 'edit' => Pages\EditGuest::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['currentBooking.bed']);
    }
}
