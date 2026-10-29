<?php

namespace App\Filament\Widgets;

use App\Enums\CommissionStatus;
use App\Models\CommissionRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class CommissionOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('view_commission_records') ?? false;
    }
    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $pendingQuery = CommissionRecord::where('status', CommissionStatus::Pending);
        $pendingAmount = (float) $pendingQuery->sum('calculated_earnings');
        $pendingCount = $pendingQuery->count();

        $paidQuery = CommissionRecord::where('status', CommissionStatus::Paid);
        $paidAmount = (float) $paidQuery->sum('calculated_earnings');
        $paidCount = $paidQuery->count();

        $monthQuery = CommissionRecord::query()
            ->whereBetween('worked_at', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->where('status', '!=', CommissionStatus::Cancelled);
        $monthAmount = (float) $monthQuery->sum('calculated_earnings');
        $monthCount = $monthQuery->count();

        return [
            Stat::make(__('Pending commissions'), Number::currency($pendingAmount, 'DOP'))
                ->description(__(':count pending payment', ['count' => $pendingCount]))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),

            Stat::make(__('Total earned commissions'), Number::currency($paidAmount, 'DOP'))
                ->description(__(':count paid out', ['count' => $paidCount]))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),

            Stat::make(__("This month's commissions"), Number::currency($monthAmount, 'DOP'))
                ->description(__(':count procedures this month', ['count' => $monthCount]))
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('primary'),
        ];
    }
}
