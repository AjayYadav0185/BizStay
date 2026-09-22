<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\InquiryStatus;
use App\Filament\Resources\InquiryResource\Pages;
use App\Models\Guest;
use App\Models\Inquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;
    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?string $navigationGroup = 'Guests';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Lead')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->required()->tel()->maxLength(20),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\Select::make('gender')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])->default('male'),
                    Forms\Components\Select::make('source')->options(['walk_in' => 'Walk-in', 'phone' => 'Phone', 'website' => 'Website', 'referral' => 'Referral', 'portal' => 'Portal', 'other' => 'Other'])->default('walk_in'),
                    Forms\Components\TextInput::make('budget')->numeric()->prefix('₹')->minValue(0),
                    Forms\Components\TextInput::make('interested_in')->label('Interested in')->columnSpan(2),
                    Forms\Components\DatePicker::make('preferred_move_in')->native(false),
                    Forms\Components\DatePicker::make('follow_up_date')->native(false),
                    Forms\Components\Select::make('status')->options(InquiryStatus::options())->default('new')->required(),
                    Forms\Components\Select::make('assigned_to')->relationship('assignedTo', 'name')->searchable()->preload(),
                    Forms\Components\Textarea::make('message')->rows(3)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->description(fn (Inquiry $r): string => $r->phone),
                Tables\Columns\TextColumn::make('source')->badge()->color('gray')->toggleable(),
                Tables\Columns\TextColumn::make('budget')->money('INR')->placeholder('—'),
                Tables\Columns\TextColumn::make('follow_up_date')->date('d M Y')->sortable()->placeholder('—')
                    ->color(fn (Inquiry $r): string => $r->isOverdueFollowUp() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(InquiryStatus::options()),
                Tables\Filters\Filter::make('needs_followup')->label('Needs follow-up')
                    ->query(fn (Builder $q): Builder => $q->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', now()->toDateString())->whereNotIn('status', ['converted', 'cancelled'])),
            ])
            ->actions([
                Tables\Actions\Action::make('convert')->label('Convert')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (Inquiry $r): bool => $r->status->isOpen() && ! $r->converted_guest_id)
                    ->form([
                        Forms\Components\TextInput::make('full_name')->default(fn (Inquiry $r): string => $r->name)->required(),
                        Forms\Components\TextInput::make('phone')->default(fn (Inquiry $r): string => $r->phone)->required(),
                        Forms\Components\TextInput::make('email')->default(fn (Inquiry $r): ?string => $r->email),
                    ])
                    ->action(function (Inquiry $record, array $data): void {
                        $guest = Guest::query()->firstOrCreate(['phone' => $data['phone']], ['full_name' => $data['full_name'], 'email' => $data['email'] ?? null]);
                        $record->forceFill(['status' => InquiryStatus::Converted, 'converted_guest_id' => $guest->id])->save();
                        Notification::make()->title('Converted: '.$guest->full_name)->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('follow_up_date');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInquiries::route('/'), 'create' => Pages\CreateInquiry::route('/create'), 'edit' => Pages\EditInquiry::route('/{record}/edit')];
    }
}
