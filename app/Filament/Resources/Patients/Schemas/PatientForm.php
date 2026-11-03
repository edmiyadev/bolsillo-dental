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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
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
                                        Section::make(__('Critical alerts'))
                                            ->description(__('High-risk medical conditions and allergies requiring immediate attention.'))
                                            ->icon('heroicon-o-exclamation-triangle')
                                            ->schema(static::buildQuestionComponents('critical_alerts', PatientMedicalHistory::criticalAlertQuestions())),
                                        Section::make(__('Anamnesis'))
                                            ->description(__('General medical background, habits, and systemic health questionnaire.'))
                                            ->icon('heroicon-o-clipboard-document-list')
                                            ->schema(static::buildQuestionComponents('anamnesis_data', PatientMedicalHistory::anamnesisQuestions())),
                                        Section::make(__('General observations'))
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                Textarea::make('general_observations')
                                                    ->label(__('General observations'))
                                                    ->placeholder(__('Additional clinical notes, patient warnings or relevant observations...'))
                                                    ->rows(4)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Build predefined question toggle and conditional detail components.
     *
     * @param  string  $parentField
     * @param  array<string, array{label: string, short_label: string, placeholder: string}>  $catalog
     * @return array<\Filament\Schemas\Components\Component>
     */
    protected static function buildQuestionComponents(string $parentField, array $catalog): array
    {
        $components = [];

        foreach ($catalog as $key => $config) {
            $components[] = Grid::make(['default' => 1, 'sm' => 12])
                ->schema([
                    Toggle::make("{$parentField}.{$key}.value")
                        ->label($config['label'])
                        ->live()
                        ->default(false)
                        ->columnSpan(['default' => 1, 'sm' => 7]),
                    TextInput::make("{$parentField}.{$key}.detail")
                        ->label(__('Detail / Specification'))
                        ->placeholder($config['placeholder'])
                        ->visible(fn (Get $get): bool => (bool) $get("{$parentField}.{$key}.value"))
                        ->maxLength(255)
                        ->columnSpan(['default' => 1, 'sm' => 5]),
                ]);
        }

        return $components;
    }
}
