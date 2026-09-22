<?php

declare(strict_types=1);

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
    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Expense')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('category')->options(ExpenseCategory::class)->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->prefix('₹')->required()->minValue(0),
                    Forms\Components\DatePicker::make('spent_on')->required()->native(false)->default(now()),
                    Forms\Components\TextInput::make('vendor'),
                    Forms\Components\Select::make('payment_method')->options(PaymentMethod::collectionOptions()),
                    Forms\Components\TextInput::make('receipt_path')->label('Receipt path'),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('spent_on')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\TextColumn::make('vendor')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')->badge()->toggleable(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('category')->options(ExpenseCategory::class)])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('spent_on', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListExpenses::route('/'), 'create' => Pages\CreateExpense::route('/create'), 'edit' => Pages\EditExpense::route('/{record}/edit')];
    }
}
