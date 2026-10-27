<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class BackAction
{
    public static function make(): Action
    {
        return Action::make('back')
            ->label(__('Back'))
            ->color('gray')
            ->icon(Heroicon::ArrowLeft)
            ->url(fn (Page $livewire): string => $livewire->getResource()::getUrl('index'));
    }
}
