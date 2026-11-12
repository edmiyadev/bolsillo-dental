<?php

namespace App\Models\Concerns;

use App\Models\Scopes\UserScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    use HasAuthor;

    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserScope);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @param  Builder<$this>  $builder
     */
    public function applyUserScope(Builder $builder, User $user): void
    {
        $builder->where($this->qualifyColumn('created_by'), $user->id);
    }
}
