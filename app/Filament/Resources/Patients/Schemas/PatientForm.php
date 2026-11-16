<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\Gender;
use App\Enums\IdentificationType;
use App\Livewire\Patients\PatientOdontogram;
use App\Models\Patient;
use App\Support\MedicalHistoryCatalog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                                            ->disk('public')
                                            ->visibility('public')
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
                                                ...static::buildQuestionComponents(),
                                                Textarea::make('general_observations')
                                                    ->label(__('General observations'))
                                                    ->placeholder(__('Additional clinical notes, patient warnings or relevant observations...'))
                                                    ->rows(3)
                                                    ->columnSpanFull()
                                                    ->extraAttributes(['class' => 'mt-4']),
                                            ]),
                                    ]),
                            ]),
                        Tab::make(__('Odontogram'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->hiddenOn('create')
                            ->badge(fn (?Patient $record): ?string => ($record && ! $record->hasOdontogram()) ? '!' : null)
                            ->badgeColor('danger')
                            ->badgeTooltip(fn (?Patient $record): ?string => ($record && ! $record->hasOdontogram()) ? __('Odontogram has no records yet') : null)
                            ->schema([
                                Livewire::make(PatientOdontogram::class, ['editable' => true])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Build predefined question radio and conditional detail components.
     *
     * @return array<Component>
     */
    protected static function buildQuestionComponents(): array
    {
        $catalog = MedicalHistoryCatalog::questions();
        $components = [];

        foreach ($catalog as $key => $config) {
            $isCritical = $config['is_critical'];
            $hasDetail = $config['has_detail'];

            $components[] = Group::make([
                Radio::make("medical_data.{$key}.value")
                    ->label($config['label'])
                    ->boolean()
                    ->in([1, 0, true, false])
                    ->inline()
                    ->inlineLabel()
                    ->default(false)
                    ->live()
                    ->columnSpanFull(),
                Hidden::make("medical_data.{$key}.is_critical")
                    ->default($isCritical),
                Hidden::make("medical_data.{$key}.has_detail")
                    ->default($hasDetail),
                ...($hasDetail ? [
                    TextInput::make("medical_data.{$key}.detail")
                        ->placeholder($config['placeholder'])
                        ->hiddenLabel()
                        ->visible(fn (Get $get): bool => (bool) $get("medical_data.{$key}.value"))
                        ->maxLength(255)
                        ->extraAttributes(['class' => 'mt-1 max-w-xl'])
                        ->columnSpanFull(),
                ] : []),
            ]);
        }

        return $components;
    }
}
