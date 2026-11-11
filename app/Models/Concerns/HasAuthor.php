<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasAuthor
{
    public static function bootHasAuthor(): void
    {
        static::creating(function (Model $model): void {
            if (Auth::hasUser()) {
                if ($model->getAttribute('created_by') === null) {
                    $model->setAttribute('created_by', Auth::id());
                }

                if ($model->getAttribute('updated_by') === null) {
                    $model->setAttribute('updated_by', Auth::id());
                }
            }
        });

        static::updating(function (Model $model): void {
            if (Auth::hasUser() && ! $model->isDirty('updated_by')) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
