<?php

namespace App\Filament\Resources\CommissionRecords\Schemas;

use App\Actions\Commissions\CalculateCommissionEarningsAction;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Models\Service;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CommissionRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        $updateEarnings = function (Get $get, Set $set, CalculateCommissionEarningsAction $calculateEarnings): void {
            $price = $get('price');
            $commission = $get('commission');
            $typeValue = $get('commission_type');

            if ($commission === null || $commission === '' || $typeValue === null) {
                $set('calculated_earnings', null);

                return;
            }

            $type = $typeValue instanceof CommissionType
                ? $typeValue
                : CommissionType::tryFrom((string) $typeValue);

            if (! $type) {
                $set('calculated_earnings', null);

                return;
            }

            if ($type === CommissionType::Percentage && ($price === null || $price === '')) {
                $set('calculated_earnings', null);

                return;
            }

            $priceFloat = ($price !== null && $price !== '') ? (float) $price : null;
            $earnings = $calculateEarnings->handle($priceFloat, (float) $commission, $type);
            $set('calculated_earnings', number_format($earnings, 2, '.', ''));
        };

        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Procedure & Service')
                            ->schema([
                                Select::make('service_id')
                                    ->relationship('service', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state, CalculateCommissionEarningsAction $calculateEarnings): void {
                                        if (! $state) {
                                            $set('price', null);
                                            $set('commission', null);
                                            $set('commission_type', CommissionType::Percentage);
                                            $set('calculated_earnings', null);

                                            return;
                                        }

                                        $service = Service::find($state);
                                        if (! $service) {
                                            $set('price', null);
                                            $set('commission', null);
                                            $set('commission_type', CommissionType::Percentage);
                                            $set('calculated_earnings', null);

                                            return;
                                        }

                                        $set('price', $service->default_price);
                                        $set('commission', $service->default_commission);
                                        $type = $service->commission_type instanceof CommissionType
                                            ? $service->commission_type
                                            : CommissionType::from((string) $service->commission_type);
                                        $set('commission_type', $type);

                                        $earnings = $calculateEarnings->handle(
                                            (float) $service->default_price,
                                            (float) $service->default_commission,
                                            $type,
                                        );

                                        $set('calculated_earnings', number_format($earnings, 2, '.', ''));
                                    }),
                                DatePicker::make('worked_at')
                                    ->default(now())
                                    ->required(),
                                Select::make('status')
                                    ->options(CommissionStatus::class)
                                    ->default(CommissionStatus::Pending)
                                    ->required(),
                            ])
                            ->columns(3),

                        Section::make('Financial Details')
                            ->schema([
                                TextInput::make('price')
                                    ->required(fn (Get $get): bool => in_array($get('commission_type'), [CommissionType::Percentage, CommissionType::Percentage->value], true))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$')
                                    ->live()
                                    ->afterStateUpdated($updateEarnings),
                                Select::make('commission_type')
                                    ->options(CommissionType::class)
                                    ->default(CommissionType::Percentage)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated($updateEarnings),
                                TextInput::make('commission')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn (Get $get): ?int => in_array($get('commission_type'), [CommissionType::Percentage, CommissionType::Percentage->value], true) ? 100 : null)
                                    ->prefix(fn (Get $get): ?string => in_array($get('commission_type'), [CommissionType::Fixed, CommissionType::Fixed->value], true) ? '$' : null)
                                    ->suffix(fn (Get $get): ?string => in_array($get('commission_type'), [CommissionType::Percentage, CommissionType::Percentage->value], true) ? '%' : null)
                                    ->live()
                                    ->afterStateUpdated($updateEarnings),
                                TextInput::make('calculated_earnings')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->required(),
                            ])
                            ->columns(4),

                        Section::make('Additional Information')
                            ->schema([
                                Textarea::make('notes')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
