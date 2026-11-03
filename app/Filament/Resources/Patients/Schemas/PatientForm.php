<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\Gender;
use App\Enums\IdentificationType;
use App\Models\PatientMedicalHistory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('PatientTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('General information'))
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make()
                                    ->columns()
                                    ->schema([
                                        FileUpload::make('photo_url')
                                            ->label(__('Profile photo'))
                                            ->image()
                                            ->avatar()
                                            ->imageEditor()
                                            ->circleCropper()
                                            ->directory('patients/avatars')
                                            ->columnSpanFull(),
                                        TextInput::make('first_name')
                                            ->label(__('First name'))
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->label(__('Last name'))
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('document_type')
                                            ->label(__('Document type'))
                                            ->options(IdentificationType::class)
                                            ->required(),
                                        TextInput::make('identification_number')
                                            ->label(__('Identification number'))
                                            ->unique()
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('gender')
                                            ->label(__('Gender'))
                                            ->options(Gender::class),
                                        DatePicker::make('birth_date')
                                            ->label(__('Birth date'))
                                            ->maxDate(now()),
                                        TextInput::make('phone')
                                            ->label(__('Mobile phone'))
                                            ->tel()
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('residential_phone')
                                            ->label(__('Residential phone'))
                                            ->tel()
                                            ->maxLength(255),
                                        TextInput::make('work_phone')
                                            ->label(__('Work phone'))
                                            ->tel()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->label(__('Email address'))
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('health_insurance')
                                            ->label(__('Health insurance'))
                                            ->maxLength(255),
                                        Textarea::make('address')
                                            ->label(__('Address'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make(__('Medical history'))
                            ->icon('heroicon-o-heart')
                            ->schema([
                                Group::make()
                                    ->relationship('medicalHistory')
                                    ->schema([
                                        Section::make(__('Medical history'))
                                            ->description(__('Predefined clinical questions and critical alerts.'))
                                            ->icon('heroicon-o-clipboard-document-check')
                                            ->schema([
                                                ...static::buildQuestionComponents('critical_alerts', PatientMedicalHistory::criticalAlertQuestions(), 'danger'),
                                                ...static::buildQuestionComponents('anamnesis_data', PatientMedicalHistory::anamnesisQuestions(), 'primary'),
                                                Textarea::make('general_observations')
                                                    ->label(__('General observations'))
                                                    ->placeholder(__('Additional clinical notes, patient warnings or relevant observations...'))
                                                    ->rows(3)
                                                    ->columnSpanFull()
                                                    ->extraAttributes(['class' => 'mt-4']),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Build predefined question toggle and conditional detail components.
     *
     * @param  array<string, array{label: string, short_label: string, placeholder: string}>  $catalog
     * @return array<Component>
     */
    protected static function buildQuestionComponents(string $parentField, array $catalog, string $onColor = 'primary'): array
    {
        $components = [];

        foreach ($catalog as $key => $config) {
            $components[] = Group::make([
                Toggle::make("{$parentField}.{$key}.value")
                    ->label($config['label'])
                    ->live()
                    ->default(false)
                    ->onColor($onColor)
                    ->onIcon(static::getYesIcon())
                    ->offIcon(static::getNoIcon())
                    ->columnSpanFull(),
                TextInput::make("{$parentField}.{$key}.detail")
                    ->placeholder($config['placeholder'])
                    ->hiddenLabel()
                    ->visible(fn (Get $get): bool => (bool) $get("{$parentField}.{$key}.value"))
                    ->maxLength(255)
                    ->extraAttributes(['class' => 'ml-12 mt-1 max-w-xl'])
                    ->columnSpanFull(),
            ])
                ->extraAttributes(['class' => 'py-2.5 border-b border-gray-100 dark:border-white/5 last:border-b-0']);
        }

        return $components;
    }

    protected static function getYesIcon(): HtmlString
    {
        return new HtmlString('<svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><text x="50%" y="60%" dominant-baseline="middle" text-anchor="middle" font-size="10" font-weight="bold" fill="currentColor">SÍ</text></svg>');
    }

    protected static function getNoIcon(): HtmlString
    {
        return new HtmlString('<svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><text x="50%" y="60%" dominant-baseline="middle" text-anchor="middle" font-size="10" font-weight="bold" fill="currentColor">NO</text></svg>');
    }
}
