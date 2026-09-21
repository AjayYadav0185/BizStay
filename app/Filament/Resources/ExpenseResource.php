<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Expense Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('property_id')
                            ->relationship('property', 'name')
                            ->searchable()->preload()->required(),
                        Forms\Components\Select::make('category')
                            ->options(ExpenseCategory::class)
                            ->default(ExpenseCategory::Other->value)->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()->prefix('₹')->required(),
                        Forms\Components\DatePicker::make('spent_on')->default(now())->required(),
                        Forms\Components\TextInput::make('vendor')
                            ->placeholder('e.g. DHBVN Electricity Board'),
                        Forms\Components\Select::make('method')
                            ->options(PaymentMethod::class)
                            ->label('Paid Via'),
                        Forms\Components\FileUpload::make('receipt_file')
                            ->disk('public')->directory('expense-receipts')
                            ->label('Receipt')
                            ->columnSpan(2),
                        Forms\Components\Textarea::make('notes')->columnSpan(2)->rows(2),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.name')->label('Property')->sortable(),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('INR')->sortable()->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('spent_on')->date('d M Y')->label('Spent On')->sortable(),
                Tables\Columns\TextColumn::make('vendor')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('property')->relationship('property', 'name'),
                Tables\Filters\SelectFilter::make('category')->options(ExpenseCategory::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('spent_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageExpenses::route('/'),
        ];
    }
}
