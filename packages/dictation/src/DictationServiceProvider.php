<?php

namespace Markc\Dictation;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Markc\Dictation\Commands\DictationModelCommand;
use Markc\Dictation\Commands\DictationStartCommand;
use Markc\Dictation\Commands\DictationStopCommand;
use Markc\Dictation\Commands\DictationTranscribeCommand;
use Markc\Dictation\Filament\Pages\Dictation;
use Markc\Dictation\Services\AudioRecorder;
use Markc\Dictation\Services\DictationService;
use Markc\Dictation\Services\TextInjector;
use Markc\Dictation\Services\WhisperTranscriber;

class DictationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dictation.php', 'dictation');

        $this->app->singleton(AudioRecorder::class);
        $this->app->singleton(WhisperTranscriber::class);
        $this->app->singleton(TextInjector::class);
        $this->app->singleton(DictationService::class);
    }

    public function boot(): void
    {
        Livewire::addNamespace(
            namespace: 'dictation',
            classNamespace: 'Markc\\Dictation\\Livewire',
            classPath: __DIR__ . '/Livewire',
            classViewPath: __DIR__ . '/../resources/views/livewire',
        );

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dictation');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DictationStartCommand::class,
                DictationStopCommand::class,
                DictationTranscribeCommand::class,
                DictationModelCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/dictation.php' => config_path('dictation.php'),
            ], 'dictation-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/dictation'),
            ], 'dictation-views');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'dictation-migrations');
        }
    }

    public static function getFilamentPage(): string
    {
        return Dictation::class;
    }
}
