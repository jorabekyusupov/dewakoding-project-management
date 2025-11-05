<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Auth\Pages\EditProfile as EditProfilePage;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EditProfile  extends EditProfilePage
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                TextInput::make('chat_id')
                    ->label(__('Телеграм Чат ID'))
                    ->maxLength(255)
                    ->helperText(__('Укажите ваш Telegram Chat ID, если вы хотите получать уведомления в Telegram. Чтобы узнать ваш чат-идентификатор, посетите @myidbot
Если вы отправите команду /getid, она отправит вам ваш чат-идентификатор.')),
            ]);
    }
}