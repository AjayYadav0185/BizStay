<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Logins: staff accounts (admin / manager / warden) and resident app logins.
 * Owner-only. Disabling a login also revokes its app sessions.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Logins & Staff';

    protected static ?string $modelLabel = 'login';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * Nobody can delete their own login (avoids locking the owner out).
     */
    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record->getKey() !== auth()->id();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Select::make('role')
                    ->options(fn (?User $record): array => $record?->isTenant() ? [UserRole::Tenant->value => UserRole::Tenant->getLabel()] : UserRole::staffOptions())
                    ->default(UserRole::Staff->value)
                    ->required()
                    ->disabled(fn (?User $record): bool => (bool) $record?->isTenant() || $record?->getKey() === auth()->id())
                    ->helperText('Staff/Warden: residents, visitors, complaints, collections. Manager: + expenses, reports, settings. Owner: + logins.'),
                Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(20)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password' : null),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record?->getKey() === auth()->id()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->description(fn (User $r): string => $r->email),
                Tables\Columns\TextColumn::make('role')->badge()->sortable(),
                Tables\Columns\TextColumn::make('phone')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('guest.full_name')->label('Resident')->placeholder('—')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->date('d M Y')->label('Since')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $r): array => [$r->value => $r->getLabel()])->all()),
                Tables\Filters\Filter::make('staff')->label('Staff only')->query(fn (Builder $query): Builder => $query->where('role', '!=', UserRole::Tenant->value))->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (User $r): string => $r->isActive() ? 'Disable' : 'Enable')
                    ->icon(fn (User $r): string => $r->isActive() ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (User $r): string => $r->isActive() ? 'danger' : 'success')
                    ->visible(fn (User $r): bool => $r->getKey() !== auth()->id())
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->forceFill(['is_active' => ! $record->isActive()])->save();

                        if (! $record->isActive()) {
                            $record->tokens()->delete();
                        }

                        Notification::make()->title($record->name.($record->isActive() ? ' enabled' : ' disabled'))->success()->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->after(function (User $record): void {
                        if (! $record->isActive()) {
                            $record->tokens()->delete();
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('guest');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
