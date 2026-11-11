<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Models\Concerns\BelongsToUser;
use App\Observers\CommissionRecordObserver;
use Database\Factories\CommissionRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'created_by',
    'updated_by',
    'service_id',
    'price',
    'commission',
    'commission_type',
    'calculated_earnings',
    'status',
    'notes',
    'worked_at',
])]
#[ObservedBy([CommissionRecordObserver::class])]
class CommissionRecord extends Model
{
    /** @use HasFactory<CommissionRecordFactory> */
    use BelongsToUser, HasFactory, SoftDeletes;

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission' => 'decimal:2',
            'commission_type' => CommissionType::class,
            'calculated_earnings' => 'decimal:2',
            'status' => CommissionStatus::class,
            'worked_at' => 'date',
        ];
    }
}
