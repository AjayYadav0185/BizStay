<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as AuthLogin;

class Login extends AuthLogin
{
    protected static ?string $navigationIcon = 'heroicon-o-user';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->autocomplete('email')
                    ->required()
                    ->autofocus()
                    ->helperText('Enter your registered email address'),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->autocomplete('current-password')
                    ->required()
                    ->helperText('Enter your account password'),
            ])
            ->columns(1);
    }
}