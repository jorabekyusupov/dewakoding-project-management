<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();
        

    }
}