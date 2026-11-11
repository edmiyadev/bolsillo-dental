<?php

namespace App\Models;

use App\Models\Concerns\HasAuthor;
use Database\Factories\ClinicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'email', 'phone', 'rnc', 'address', 'created_by', 'updated_by'])]
class Clinic extends Model
{
    /** @use HasFactory<ClinicFactory> */
    use HasAuthor, HasFactory;

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasOne<Branch, $this>
     */
    public function mainBranch(): HasOne
    {
        return $this->hasOne(Branch::class)->where('is_main', true);
    }
}
