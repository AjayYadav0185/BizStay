<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('type')
                        ->options(PaymentType::class)
                        ->default(PaymentType::Rent->value)->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->prefix('₹')->required(),
                    Forms\Components\DatePicker::make('period_month')
                        ->label('Rent Period (Month)')
                        ->format('Y-m')->displayFormat('MM/Y')
                        ->default(now()->startOfMonth()),
                    Forms\Components\DatePicker::make('due_date'),
                    Forms\Components\Select::make('status')
                        ->options(PaymentStatus::class)
                        ->default(PaymentStatus::Pending->value)->required(),
                    Forms\Components\Select::make('method')->options(PaymentMethod::class)->label('Paid Via'),
                    Forms\Components\DatePicker::make('paid_at')->label('Paid On'),
                    Forms\Components\TextInput::make('transaction_ref')->label('UTR / Ref No.'),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('INR')->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('period_month')
                    ->label('For Month')
                    ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->format('M Y') : '—'),
                Tables\Columns\TextColumn::make('due_date')->date('d M Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('paid_at')->date('d M Y')->label('Paid On')->placeholder('—'),
                Tables\Columns\TextColumn::make('method')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PaymentStatus::class),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
