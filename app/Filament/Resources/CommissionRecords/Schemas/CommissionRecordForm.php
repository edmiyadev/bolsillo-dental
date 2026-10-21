<?php

namespace App\Filament\Resources\CommissionRecords\Schemas;

use App\Actions\Commissions\CalculateCommissionEarningsAction;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Filament\Resources\Services\Schemas\ServiceForm;
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
                        Section::make(__('Procedure & Service'))
                            ->schema([
                                Select::make('service_id')
                                    ->label(__('Service'))
                                    ->relationship('service', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->createOptionForm(fn (Schema $schema) => ServiceForm::configure($schema))
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
                                    ->label(__('Worked at'))
                                    ->default(now())
                                    ->required(),
                                Select::make('status')
                                    ->label(__('Status'))
                                    ->options(CommissionStatus::class)
                                    ->default(CommissionStatus::Pending)
                                    ->required(),
                            ])
                            ->columns(3),

                        Section::make(__('Financial Details'))
                            ->schema([
                                TextInput::make('price')
                                    ->label(__('Price'))
                                    ->required(fn (Get $get): bool => in_array($get('commission_type'), [CommissionType::Percentage, CommissionType::Percentage->value], true))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('RD$')
                                    ->live()
                                    ->afterStateUpdated($updateEarnings),
                                Select::make('commission_type')
                                    ->label(__('Commission type'))
                                    ->options(CommissionType::class)
                                    ->default(CommissionType::Percentage)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated($updateEarnings),
                                TextInput::make('commission')
                                    ->label(__('Commission'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn (Get $get): ?int => in_array($get('commission_type'), [CommissionType::Percentage, CommissionType::Percentage->value], true) ? 100 : null)
                                    ->prefix(fn (Get $get): ?string => in_array($get('commission_type'), [CommissionType::Fixed, CommissionType::Fixed->value], true) ? 'RD$' : null)
                                    ->suffix(fn (Get $get): ?string => in_array($get('commission_type'), [CommissionType::Percentage, CommissionType::Percentage->value], true) ? '%' : null)
                                    ->live()
                                    ->afterStateUpdated($updateEarnings),
                                TextInput::make('calculated_earnings')
                                    ->label(__('Calculated earnings'))
                                    ->numeric()
                                    ->prefix('RD$')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->required(),
                            ])
                            ->columns(4),

                        Section::make(__('Additional Information'))
                            ->schema([
                                Textarea::make('notes')
                                    ->label(__('Notes'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
