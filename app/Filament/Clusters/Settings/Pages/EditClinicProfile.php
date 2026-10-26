<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Actions\Tenants\ProvisionTenantMainBranchAction;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Clinic;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property-read Schema $form
 */
class EditClinicProfile extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'clinic-profile';

    public function getBreadcrumb(): ?string
    {
        return __('Clinic profile');
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Clinic profile');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Clinic profile');
    }

    public function mount(): void
    {
        $this->form->fill($this->getClinic()->attributesToArray());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->model($this->getClinic())
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Business & Clinic Information'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Clinic name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Clinic email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('Clinic phone'))
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('rnc')
                            ->label(__('Clinic RNC'))
                            ->maxLength(50),
                        Textarea::make('address')
                            ->label(__('Clinic address'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('filament-panels::pages/tenancy/edit-tenant-profile.form.actions.save.label'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->getClinic()->update($data);

        Notification::make()
            ->success()
            ->title(__('filament-panels::pages/tenancy/edit-tenant-profile.notifications.saved.title'))
            ->send();
    }

    protected function getClinic(): Clinic
    {
        $clinic = Clinic::query()->first() ?? Clinic::query()->create([
            'name' => (string) config('app.name', 'Mi Clínica'),
        ]);

        if ($clinic->branches()->doesntExist()) {
            app(ProvisionTenantMainBranchAction::class)->handle($clinic);
        }

        return $clinic;
    }
}
