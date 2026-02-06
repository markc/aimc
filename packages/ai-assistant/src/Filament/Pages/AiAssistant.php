<?php

namespace Markc\AiAssistant\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Markc\AiAssistant\Models\Conversation;

class AiAssistant extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = null;

    protected static ?string $title = 'AI Assistant';

    protected static ?int $navigationSort = 100;

    protected string $view = 'ai-assistant::filament.pages.ai-assistant';

    public ?int $conversationId = null;

    public static function getNavigationLabel(): string
    {
        return config('ai-assistant.navigation.label', 'AI Assistant');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return config('ai-assistant.navigation.icon', 'heroicon-o-sparkles');
    }

    public static function getNavigationSort(): ?int
    {
        return config('ai-assistant.navigation.sort', 100);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('ai-assistant.navigation.group');
    }

    public function mount(): void
    {
        $recent = Conversation::where('user_id', auth()->id())
            ->latest()
            ->first();

        $this->conversationId = $recent?->id;
    }

    public function getConversations(): \Illuminate\Database\Eloquent\Collection
    {
        return Conversation::where('user_id', auth()->id())
            ->latest()
            ->limit(20)
            ->get();
    }

    public function selectConversation(int $id): void
    {
        $this->conversationId = $id ?: null;
    }
}
