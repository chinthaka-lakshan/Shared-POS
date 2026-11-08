<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use App\Models\Tenant;
use Filament\Notifications\Notification;

class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Settings';
    
    protected static string $view = 'filament.pages.store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = Tenant::first();
        if ($tenant) {
            $this->form->fill([
                'manager_pin' => $tenant->manager_pin,
            ]);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('manager_pin')
                    ->label('Manager PIN')
                    ->password()
                    ->revealable()
                    ->numeric()
                    ->minLength(4)
                    ->maxLength(6)
                    ->helperText('Required to authorize sales below cost price at the POS terminal.'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $tenant = Tenant::first();
        if ($tenant) {
            $tenant->update(
                $this->form->getState()
            );

            Notification::make()
                ->title('Saved successfully')
                ->success()
                ->send();
        }
    }
}
