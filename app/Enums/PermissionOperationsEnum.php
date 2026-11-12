<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PermissionOperationsEnum: string implements HasLabel
{
    case View = 'view';
    case ViewAll = 'view_all';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'force_delete';

    public function getLabel(): string
    {
        return match ($this) {
            self::View => __('View'),
            self::ViewAll => __('View All'),
            self::Create => __('Create'),
            self::Update => __('Update'),
            self::Delete => __('Delete'),
            self::Restore => __('Restore'),
            self::ForceDelete => __('Force Delete'),
        };
    }
}
