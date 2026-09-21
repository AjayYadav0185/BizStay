<?php

namespace App\Filament\Resources;

use App\Enums\BedStatus;
use App\Enums\BookingStatus;
use App\Enums\TenantStatus;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Inquiry / Booking';

    protected static ?string $pluralModelLabel = 'Inquiries & Bookings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Inquiry Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('phone')->required()->tel()->maxLength(20),
                        Forms\Components\TextInput::make('email')->email(),
                        Forms\Components\Select::make('gender')
                            ->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])
                            ->default('male'),
                        Forms\Components\Select::make('property_id')
                            ->label('Interested Property')
                            ->relationship('property', 'name')->searchable()->preload(),
                        Forms\Components\Select::make('source')
                            ->options([
                                'walk_in' => 'Walk-in',
                                'nobroker' => 'NoBroker',
                                '99acres' => '99acres',
                                'magicbricks' => 'MagicBricks',
                                'reference' => 'Reference',
                                'facebook' => 'Facebook',
                                'other' => 'Other',
                            ])
                            ->default('walk_in')->required(),
                        Forms\Components\TextInput::make('budget')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('interested_in')
                            ->placeholder('e.g. 2 sharing AC'),
                        Forms\Components\DatePicker::make('preferred_move_in')->label('Preferred Move-in'),
                        Forms\Components\DatePicker::make('follow_up_date')->label('Follow-up Date'),
                        Forms\Components\Select::make('assigned_to')
                            ->relationship('assignedTo', 'name')
                            ->label('Assigned To'),
                        Forms\Components\Select::make('status')
                            ->options(BookingStatus::class)
                            ->default(BookingStatus::New->value)->required(),
                        Forms\Components\Textarea::make('message')->columnSpan(2)->rows(2),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('property.name')->label('Property')->placeholder('—'),
                Tables\Columns\TextColumn::make('source')->badge()->color('info'),
                Tables\Columns\TextColumn::make('budget')->money('INR')->placeholder('—'),
                Tables\Columns\TextColumn::make('preferred_move_in')->date('d M')->label('Move-in')->placeholder('—'),
                Tables\Columns\TextColumn::make('follow_up_date')
                    ->date('d M')->label('Follow-up')
                    ->color(fn ($state) => $state && \Illuminate\Support\Carbon::parse($state)->isPast() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(BookingStatus::class),
                Tables\Filters\Filter::make('overdue_follow_up')
                    ->label('Overdue Follow-ups')
                    ->query(fn ($query) => $query->whereDate('follow_up_date', '<', now())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('convert')
                    ->label('Convert to Tenant')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (Booking $record) => $record->status !== BookingStatus::Booked)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('bed_id')
                            ->label('Allocate Bed')
                            ->options(fn (Booking $record) => Bed::query()
                                ->where('status', BedStatus::Vacant->value)
                                ->whereHas('room', fn ($q) => $q->where('property_id', $record->property_id))
                                ->with('room')
                                ->get()
                                ->mapWithKeys(fn ($bed) => [$bed->id => 'Room '.$bed->room->room_number.' — Bed '.$bed->bed_number])
                                ->all())
                            ->required(),
                        Forms\Components\TextInput::make('monthly_rent')->numeric()->prefix('₹')->required(),
                        Forms\Components\TextInput::make('security_deposit')->numeric()->prefix('₹')->default(0),
                        Forms\Components\DatePicker::make('joining_date')->default(now())->required(),
                    ])
                    ->action(function (array $data, Booking $record) {
                        $tenant = Tenant::create([
                            'property_id' => $record->property_id,
                            'bed_id' => $data['bed_id'],
                            'full_name' => $record->name,
                            'phone' => $record->phone,
                            'email' => $record->email,
                            'gender' => $record->gender,
                            'monthly_rent' => $data['monthly_rent'],
                            'security_deposit' => $data['security_deposit'],
                            'joining_date' => $data['joining_date'],
                            'status' => TenantStatus::Active->value,
                        ]);

                        $record->update(['status' => BookingStatus::Booked]);

                        return redirect(TenantResource::getUrl('edit', ['record' => $tenant]));
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
            'index' => Pages\ManageBookings::route('/'),
        ];
    }
}
