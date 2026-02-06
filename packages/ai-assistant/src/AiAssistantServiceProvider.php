<?php

namespace Markc\AiAssistant;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Markc\AiAssistant\Services\AnthropicService;

class AiAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-assistant.php', 'ai-assistant');

        $this->app->singleton(AnthropicService::class, function ($app) {
            return new AnthropicService(
                apiKey: config('ai-assistant.api_key'),
                model: config('ai-assistant.model'),
                maxTokens: config('ai-assistant.max_tokens'),
                systemPrompt: config('ai-assistant.system_prompt'),
            );
        });

        $this->app->alias(AnthropicService::class, 'ai-assistant');
    }

    public function boot(): void
    {
        // Register Livewire components namespace
        Livewire::addNamespace(
            namespace: 'ai-assistant',
            classNamespace: 'Markc\\AiAssistant\\Livewire',
            classPath: __DIR__.'/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
        );

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-assistant');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publishable resources
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__.'/../config/ai-assistant.php' => config_path('ai-assistant.php'),
            ], 'ai-assistant-config');

            // Views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ai-assistant'),
            ], 'ai-assistant-views');

            // Migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ai-assistant-migrations');

            // Filament Page (optional - for customization)
            $this->publishes([
                __DIR__.'/Filament/Pages/AiAssistant.php' => app_path('Filament/Pages/AiAssistant.php'),
            ], 'ai-assistant-filament');
        }
    }

    /**
     * Get the Filament page class for registration.
     */
    public static function getFilamentPage(): string
    {
        return \Markc\AiAssistant\Filament\Pages\AiAssistant::class;
    }
}
