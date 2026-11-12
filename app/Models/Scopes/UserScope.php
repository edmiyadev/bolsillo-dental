<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::hasUser()) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $module = Str::snake(Str::pluralStudly(class_basename($model)));

        if ($user->can("view_all_{$module}")) {
            return;
        }

        if (method_exists($model, 'applyUserScope')) {
            $model->applyUserScope($builder, $user);

            return;
        }

        $builder->where($model->qualifyColumn('created_by'), $user->id);
    }
}
