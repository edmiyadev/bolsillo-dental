<?php

use App\Enums\Gender;
use Tests\TestCase;

uses(TestCase::class);

test('gender enum has expected cases and labels', function () {
    expect(Gender::cases())->toHaveCount(3)
        ->and(Gender::Male->value)->toBe('male')
        ->and(Gender::Female->value)->toBe('female')
        ->and(Gender::Other->value)->toBe('other')
        ->and(Gender::Male->getLabel())->toBe(__('Male'))
        ->and(Gender::Female->getLabel())->toBe(__('Female'))
        ->and(Gender::Other->getLabel())->toBe(__('Other'));
});
