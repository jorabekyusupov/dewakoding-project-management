<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;
use Filament\Support\Icons\Heroicon;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static string | UnitEnum | null $navigationGroup = 'settings';

    protected static ?string $title = 'System Settings';
    protected string $view = 'filament.pages.system-settings';

    public ?array $data = [];

    /**
     * @return string|UnitEnum|null
     */
    public static function getNavigationGroup(): UnitEnum|string|null
    {
        return __('navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('sytem_settings');
    }
    public function getTitle(): string|Htmlable
    {
        return __('sytem_settings');
    }

    public function mount(): void
    {
        $this->form->fill([
            'navigation_style' => Setting::getValue('navigation_style', 'sidebar'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('navigation_style')
                    ->label('Navigation Style')
                    ->options([
                        'sidebar' => 'Sidebar (default)',
                        'top' => 'Top Navigation',
                    ])
                    ->inline()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $style = $state['navigation_style'] ?? 'sidebar';

        Setting::updateOrCreate(
            ['key' => 'navigation_style'],
            ['value' => $style, 'group' => 'ui']
        );

        Notification::make()
            ->title('Setting saved successfully')
            ->success()
            ->send();
    }
}