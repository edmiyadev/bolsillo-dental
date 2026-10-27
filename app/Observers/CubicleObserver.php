<?php

namespace App\Observers;

use App\Models\Cubicle;

class CubicleObserver
{
    public function creating(Cubicle $cubicle): void
    {
        if (blank($cubicle->code) && filled($cubicle->branch_id)) {
            $cubicle->code = static::generateNextCodeForBranch((int) $cubicle->branch_id);
        }
    }

    public static function generateNextCodeForBranch(int $branchId): string
    {
        $existingCodes = Cubicle::query()
            ->where('branch_id', $branchId)
            ->where('code', 'LIKE', 'CUB-%')
            ->pluck('code');

        $maxNumber = 0;

        foreach ($existingCodes as $code) {
            if (preg_match('/^CUB-(\d+)$/i', $code, $matches)) {
                $number = (int) $matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        return sprintf('CUB-%02d', $maxNumber + 1);
    }
}
