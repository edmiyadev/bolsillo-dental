<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Clinic;
use DomainException;
use Illuminate\Validation\ValidationException;

class BranchObserver
{
    public function creating(Branch $branch): void
    {
        if (blank($branch->clinic_id)) {
            $branch->clinic_id = Clinic::query()->value('id');
        }
    }

    public function saving(Branch $branch): void
    {
        if ($branch->exists && ! $branch->is_active && $branch->getOriginal('is_active', true) && $branch->isOnlyActiveBranch()) {
            throw ValidationException::withMessages([
                'is_active' => __('You cannot deactivate the only active branch.'),
            ]);
        }

        if ($branch->is_main && filled($branch->clinic_id)) {
            Branch::query()
                ->where('clinic_id', $branch->clinic_id)
                ->whereKeyNot($branch->getKey())
                ->update(['is_main' => false]);
        }
    }

    public function deleting(Branch $branch): void
    {
        $count = $branch->isForceDeleting()
            ? $branch->clinic?->branches()->withTrashed()->count()
            : $branch->clinic?->branches()->count();

        if ($count !== null && $count <= 1) {
            throw new DomainException(__('You cannot delete the only branch of the clinic.'));
        }
    }
}
