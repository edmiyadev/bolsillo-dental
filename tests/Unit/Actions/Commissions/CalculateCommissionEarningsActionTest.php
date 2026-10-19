<?php

use App\Actions\Commissions\CalculateCommissionEarningsAction;
use App\Enums\CommissionType;

it('calculates earnings for fixed commission', function () {
    $action = new CalculateCommissionEarningsAction;

    $earnings = $action->handle(150.00, 35.50, CommissionType::Fixed);

    expect($earnings)->toBe(35.50);
});

it('calculates earnings for percentage commission', function () {
    $action = new CalculateCommissionEarningsAction;

    $earnings = $action->handle(250.00, 20.00, CommissionType::Percentage);

    expect($earnings)->toBe(50.00);
});

it('rounds percentage earnings to two decimal places', function () {
    $action = new CalculateCommissionEarningsAction;

    $earnings = $action->handle(33.33, 15.00, CommissionType::Percentage);

    expect($earnings)->toBe(5.00);
});

it('calculates fixed commission earnings when price is null', function () {
    $action = new CalculateCommissionEarningsAction;

    $earnings = $action->handle(null, 75.00, CommissionType::Fixed);

    expect($earnings)->toBe(75.00);
});
