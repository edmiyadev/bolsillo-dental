<?php

namespace App\Models;

use App\ValueObjects\WeeklySchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'branch_id',
    'user_id',
    'schedule',
    'is_active',
])]
class BranchUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'branch_user';

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule' => WeeklySchedule::class,
            'is_active' => 'boolean',
        ];
    }
}
