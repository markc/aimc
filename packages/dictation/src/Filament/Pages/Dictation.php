<?php

namespace Markc\Dictation\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class Dictation extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-microphone';

    protected static ?string $navigationLabel = null;

    protected static ?string $title = 'Dictation';

    protected static ?int $navigationSort = 110;

    protected string $view = 'dictation::filament.pages.dictation';

    public static function getNavigationLabel(): string
    {
        return config('dictation.navigation.label', 'Dictation');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return config('dictation.navigation.icon', 'heroicon-o-microphone');
    }

    public static function getNavigationSort(): ?int
    {
        return config('dictation.navigation.sort', 110);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('dictation.navigation.group');
    }
}
