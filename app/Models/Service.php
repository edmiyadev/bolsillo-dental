<?php

namespace App\Models;

use App\Enums\CommissionType;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'default_price', 'default_commission', 'commission_type'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToUser, HasFactory;

    /**
     * @return HasMany<CommissionRecord, $this>
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'default_commission' => 'decimal:2',
            'commission_type' => CommissionType::class,
        ];
    }
}
