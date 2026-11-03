<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Models\Patient;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

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
                                        ImageEntry::make('photo_url')
                                            ->label(__('Profile photo'))
                                            ->circular()
                                            ->columnSpanFull(),
                                        TextEntry::make('first_name')
                                            ->label(__('First name')),
                                        TextEntry::make('last_name')
                                            ->label(__('Last name')),
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
                                Section::make(__('Critical alerts'))
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->description(__('High-risk medical conditions and allergies requiring immediate attention.'))
                                    ->schema([
                                        TextEntry::make('critical_alerts_badges')
                                            ->label(__('Active critical alerts'))
                                            ->state(fn (Patient $record): array => collect($record->medicalHistory?->activeCriticalAlerts() ?? [])->pluck('summary')->all())
                                            ->badge()
                                            ->color('danger')
                                            ->placeholder(__('No critical alerts registered'))
                                            ->columnSpanFull(),
                                    ]),
                                Section::make(__('Anamnesis'))
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->description(__('General medical background and habits questionnaire.'))
                                    ->schema([
                                        TextEntry::make('anamnesis_badges')
                                            ->label(__('Positive anamnesis records'))
                                            ->state(fn (Patient $record): array => collect($record->medicalHistory?->activeAnamnesis() ?? [])->pluck('summary')->all())
                                            ->badge()
                                            ->color('info')
                                            ->placeholder(__('No anamnesis data registered'))
                                            ->columnSpanFull(),
                                    ]),
                                Section::make(__('General observations'))
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        TextEntry::make('medicalHistory.general_observations')
                                            ->label(__('General observations'))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
