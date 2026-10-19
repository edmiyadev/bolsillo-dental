<?php

use App\Filament\Widgets\CommissionOverview;
use App\Models\CommissionRecord;
use App\Models\User;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders dashboard with only the commission overview widget', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSeeLivewire(CommissionOverview::class)
        ->assertDontSeeLivewire(AccountWidget::class)
        ->assertDontSeeLivewire(FilamentInfoWidget::class);
});

it('calculates pending, paid, and monthly commissions correctly', function () {
    $this->actingAs(User::factory()->create());

    // Pending record from this month
    CommissionRecord::factory()->pending()->fixedCommission(1500)->create([
        'worked_at' => now()->startOfMonth()->addDays(2),
    ]);

    // Paid record from this month
    CommissionRecord::factory()->paid()->fixedCommission(2500)->create([
        'worked_at' => now()->startOfMonth()->addDays(3),
    ]);

    // Paid record from last month
    CommissionRecord::factory()->paid()->fixedCommission(3000)->create([
        'worked_at' => now()->subMonths(2)->startOfMonth(),
    ]);

    // Cancelled record from this month (should be excluded from pending, paid, and monthly)
    CommissionRecord::factory()->cancelled()->fixedCommission(5000)->create([
        'worked_at' => now()->startOfMonth()->addDays(4),
    ]);

    // Pending = 1500 -> RD$1,500.00
    // Total Paid = 2500 + 3000 = 5500 -> RD$5,500.00
    // Month = 1500 (pending) + 2500 (paid) = 4000 -> RD$4,000.00
    Livewire::test(CommissionOverview::class)
        ->assertOk()
        ->assertSee('RD$1,500.00')
        ->assertSee('RD$5,500.00')
        ->assertSee('RD$4,000.00')
        ->assertSee(__('Pending commissions'))
        ->assertSee(__('Total earned commissions'))
        ->assertSee(__("This month's commissions"));
});
