<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Models\Patient;
use App\Support\MedicalHistoryCatalog;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\VerticalAlignment;

class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('PatientInfolistTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('General information'))
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make()
                                    ->columns()
                                    ->schema([
                                        Flex::make([
                                            ImageEntry::make('photo_url')
                                                ->hiddenLabel()
                                                ->circular()
                                                ->disk('public')
                                                ->visibility('public')
                                                ->checkFileExistence(false)
                                                ->grow(false)
                                                ->defaultImageUrl(fn (Patient $record): string => 'https://ui-avatars.com/api/?name='.urlencode("{$record->first_name} {$record->last_name}").'&color=0284c7&background=e0f2fe'),
                                            TextEntry::make('full_name')
                                                ->hiddenLabel()
                                                ->weight('bold')
                                                ->size(TextSize::Large)
                                                ->grow(false)
                                                ->state(fn (Patient $record): string => "{$record->first_name} {$record->last_name}"),
                                        ])
                                            ->verticalAlignment(VerticalAlignment::Center)
                                            ->columnSpanFull(),
                                        TextEntry::make('document_type')
                                            ->label(__('Document type'))
                                            ->badge(),
                                        TextEntry::make('identification_number')
                                            ->label(__('Identification number')),
                                        TextEntry::make('gender')
                                            ->label(__('Gender'))
                                            ->badge(),
                                        TextEntry::make('birth_date')
                                            ->label(__('Birth date'))
                                            ->date()
                                            ->placeholder('-'),
                                        TextEntry::make('phone')
                                            ->label(__('Mobile phone')),
                                        TextEntry::make('residential_phone')
                                            ->label(__('Residential phone'))
                                            ->placeholder('-'),
                                        TextEntry::make('work_phone')
                                            ->label(__('Work phone'))
                                            ->placeholder('-'),
                                        TextEntry::make('email')
                                            ->label(__('Email address'))
                                            ->placeholder('-'),
                                        TextEntry::make('health_insurance')
                                            ->label(__('Health insurance'))
                                            ->placeholder('-'),
                                        TextEntry::make('address')
                                            ->label(__('Address'))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                        TextEntry::make('created_at')
                                            ->label(__('Created at'))
                                            ->dateTime()
                                            ->placeholder('-'),
                                        TextEntry::make('updated_at')
                                            ->label(__('Updated at'))
                                            ->dateTime()
                                            ->placeholder('-'),
                                    ]),
                            ]),
                        Tab::make(__('Medical history'))
                            ->icon('heroicon-o-heart')
                            ->schema([
                                Section::make(__('Clinical alerts summary'))
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->schema([
                                        TextEntry::make('critical_alerts_badges')
                                            ->label(__('Active critical alerts'))
                                            ->state(fn (Patient $record): array => collect(MedicalHistoryCatalog::activeCriticalAlerts($record->medicalHistory?->medical_data))->pluck('summary')->all())
                                            ->badge()
                                            ->color('danger')
                                            ->placeholder(__('No critical alerts registered'))
                                            ->columnSpanFull(),
                                        TextEntry::make('anamnesis_badges')
                                            ->label(__('Positive anamnesis records'))
                                            ->state(fn (Patient $record): array => collect(MedicalHistoryCatalog::activeConditions($record->medicalHistory?->medical_data))->pluck('summary')->all())
                                            ->badge()
                                            ->color('info')
                                            ->placeholder(__('No anamnesis data registered'))
                                            ->columnSpanFull(),
                                    ]),
                                Section::make(__('Full medical questionnaire'))
                                    ->description(__('Complete record of clinical responses and medical history.'))
                                    ->icon('heroicon-o-document-text')
                                    ->schema(static::buildDetailedQuestionEntries()),
                                Section::make(__('General observations'))
                                    ->icon('heroicon-o-pencil-square')
                                    ->schema([
                                        TextEntry::make('medicalHistory.general_observations')
                                            ->hiddenLabel()
                                            ->placeholder(__('No general observations recorded.'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Build detailed question infolist entries for the complete medical history.
     *
     * @return array<Component>
     */
    protected static function buildDetailedQuestionEntries(): array
    {
        $catalog = MedicalHistoryCatalog::questions();
        $entries = [];

        foreach ($catalog as $key => $config) {
            $isCritical = $config['is_critical'];

            $entries[] = TextEntry::make("medical_data_detail.{$key}")
                ->label($config['label'])
                ->inlineLabel()
                ->state(function (Patient $record) use ($key): string {
                    $entry = $record->medicalHistory?->medical_data[$key] ?? null;
                    $isPositive = is_array($entry) ? (bool) ($entry['value'] ?? false) : (bool) $entry;
                    $detail = is_array($entry) && ! empty($entry['detail']) ? (string) $entry['detail'] : null;

                    if ($isPositive) {
                        return $detail ? "SÍ ({$detail})" : 'SÍ';
                    }

                    return 'NO';
                })
                ->badge()
                ->color(function (Patient $record) use ($key, $isCritical): string {
                    $entry = $record->medicalHistory?->medical_data[$key] ?? null;
                    $isPositive = is_array($entry) ? (bool) ($entry['value'] ?? false) : (bool) $entry;

                    if (! $isPositive) {
                        return 'gray';
                    }

                    return $isCritical ? 'danger' : 'primary';
                })
                ->icon(function (Patient $record) use ($key): string {
                    $entry = $record->medicalHistory?->medical_data[$key] ?? null;
                    $isPositive = is_array($entry) ? (bool) ($entry['value'] ?? false) : (bool) $entry;

                    return $isPositive ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle';
                })
                ->columnSpanFull();
        }

        return $entries;
    }
}
