<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\KycStatus;
use App\Enums\PoliceVerificationStatus;
use App\Enums\UserRole;
use App\Filament\Resources\GuestResource\Pages;
use App\Models\Guest;
use App\Models\Property;
use App\Models\User;
use App\Support\Ncr;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
            Forms\Components\Section::make('Police verification')
                ->description(fn (): string => 'Mandatory for every resident. Portal: '.Ncr::policePortalFor(Property::current()?->city)['name'].'.')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('police_verification_status')
                            ->label('Status')
                            ->options(PoliceVerificationStatus::options())
                            ->default(PoliceVerificationStatus::NotSubmitted->value)
                            ->required(),
                        Forms\Components\TextInput::make('police_verification_ref')->label('Application / reference no.')->maxLength(60),
                        Forms\Components\TextInput::make('police_station')->maxLength(255),
                        Forms\Components\DatePicker::make('police_verification_submitted_on')->label('Submitted on')->native(false),
                        Forms\Components\DatePicker::make('police_verified_on')->label('Verified on')->native(false),
                    ]),
                ])->collapsible(),
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
                Tables\Columns\TextColumn::make('police_verification_status')->label('Police')->badge()->sortable(),
                Tables\Columns\TextColumn::make('masked_aadhaar')->label('Aadhaar')->toggleable(),
                Tables\Columns\TextColumn::make('company_name')->label('Company')->toggleable(isToggledHiddenByDefault: true)->searchable(),
                Tables\Columns\TextColumn::make('currentBooking.bed.bed_code')->label('Bed')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_blacklisted')->label('Blacklisted')->boolean()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kyc_status')->options(KycStatus::options()),
                Tables\Filters\Filter::make('staying')->label('Currently staying')->query(fn (Builder $query): Builder => $query->staying()),
                Tables\Filters\SelectFilter::make('police_verification_status')->label('Police verification')->options(PoliceVerificationStatus::options()),
                Tables\Filters\Filter::make('police_pending')
                    ->label('Staying without police verification')
                    ->query(fn (Builder $query): Builder => $query->staying()->policeVerificationPending()),
            ])
            ->actions([
                Tables\Actions\Action::make('verifyKyc')
                    ->label('Verify KYC')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (Guest $r): bool => $r->kyc_status !== KycStatus::Verified)
                    ->requiresConfirmation()
                    ->modalDescription('Mark this guest\'s KYC as verified? Only verified (or explicitly bypassed) guests can be allocated a bed.')
                    ->action(function (Guest $record): void {
                        $record->forceFill(['kyc_status' => KycStatus::Verified, 'kyc_verified_at' => now()])->save();

                        Notification::make()->title('KYC verified: '.$record->full_name)->success()->send();
                    }),
                Tables\Actions\Action::make('rejectKyc')
                    ->label('Reject KYC')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (Guest $r): bool => $r->kyc_status === KycStatus::Pending)
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function (Guest $record): void {
                        $record->forceFill(['kyc_status' => KycStatus::Rejected])->save();

                        Notification::make()->title('KYC rejected: '.$record->full_name)->danger()->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('policeSubmitted')
                        ->label('Police verification submitted')
                        ->icon('heroicon-o-document-arrow-up')
                        ->visible(fn (Guest $r): bool => $r->police_verification_status === PoliceVerificationStatus::NotSubmitted)
                        ->form([
                            Forms\Components\TextInput::make('police_verification_ref')->label('Application / reference no.')->required()->maxLength(60),
                            Forms\Components\TextInput::make('police_station')->maxLength(255),
                            Forms\Components\DatePicker::make('police_verification_submitted_on')->label('Submitted on')->default(now())->required()->native(false),
                        ])
                        ->action(function (Guest $record, array $data): void {
                            $record->forceFill($data + ['police_verification_status' => PoliceVerificationStatus::Submitted])->save();
                            Notification::make()->title('Police verification submitted for '.$record->full_name)->success()->send();
                        }),
                    Tables\Actions\Action::make('policeVerified')
                        ->label('Police verification done')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->visible(fn (Guest $r): bool => $r->police_verification_status !== PoliceVerificationStatus::Verified)
                        ->form([
                            Forms\Components\DatePicker::make('police_verified_on')->label('Verified on')->default(now())->required()->native(false),
                        ])
                        ->action(function (Guest $record, array $data): void {
                            $record->forceFill($data + ['police_verification_status' => PoliceVerificationStatus::Verified])->save();
                            Notification::make()->title('Police verification complete: '.$record->full_name)->success()->send();
                        }),
                    Tables\Actions\Action::make('whatsappWelcome')
                        ->label('WhatsApp welcome')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->visible(fn (Guest $r): bool => $r->whatsappNumber() !== null)
                        ->url(fn (Guest $r): string => route('guests.whatsapp-welcome', $r))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('rentReceipts')
                        ->label('HRA rent receipts')
                        ->icon('heroicon-o-receipt-percent')
                        ->url(fn (Guest $r): string => route('guests.rent-receipts', $r))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('appLogin')
                        ->label(fn (Guest $r): string => $r->appLogin ? 'Reset app password' : 'Create app login')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->requiresConfirmation()
                        ->modalDescription(fn (Guest $r): string => 'The resident signs in to the BizStay app with '.$r->phone.' and a new 8-character password shown on the next screen. Share it with them on WhatsApp.')
                        ->action(function (Guest $record): void {
                            $password = Str::password(8, symbols: false);

                            $user = User::query()->firstOrNew(['guest_id' => $record->id]);
                            $user->fill([
                                'name' => $record->full_name,
                                'email' => $user->email ?? $record->phone.'@tenant.bizstay.local',
                                'phone' => $record->phone,
                                'role' => UserRole::Tenant,
                                'is_active' => true,
                                'password' => $password,
                            ])->save();

                            $user->tokens()->delete();

                            Notification::make()
                                ->title('App login ready for '.$record->full_name)
                                ->body('Mobile: '.$record->phone.' · Password: '.$password)
                                ->success()
                                ->persistent()
                                ->send();
                        }),
                ])->label('More')->icon('heroicon-m-ellipsis-vertical'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('full_name');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Guest::query()->staying()->policeVerificationPending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Residents staying without completed police verification';
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGuests::route('/'), 'create' => Pages\CreateGuest::route('/create'), 'edit' => Pages\EditGuest::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['currentBooking.bed', 'appLogin']);
    }
}
