<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ComplaintsRelationManager extends RelationManager
{
    protected static string $relationship = 'complaints';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('title')->required()->columnSpan(2),
                    Forms\Components\Textarea::make('description')->rows(2)->columnSpan(2),
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
                    Forms\Components\TextInput::make('assigned_to')->label('Assigned To (Staff)'),
                    Forms\Components\Select::make('status')
                        ->options(ComplaintStatus::class)
                        ->default(ComplaintStatus::Open->value)->required(),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('category')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('priority')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Raised'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ComplaintStatus::class),
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
