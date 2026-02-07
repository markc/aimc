<?php

namespace Markc\Dictation\Commands;

use Illuminate\Console\Command;
use Markc\Dictation\Services\WhisperTranscriber;

class DictationModelCommand extends Command
{
    protected $signature = 'dictation:model
                            {action : Action to perform (list, download, delete)}
                            {model? : Model name (e.g., base.en, small, medium)}';

    protected $description = 'Manage whisper models';

    public function handle(WhisperTranscriber $transcriber): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listModels($transcriber),
            'download' => $this->downloadModel($transcriber),
            'delete' => $this->deleteModel($transcriber),
            default => $this->invalidAction($action),
        };
    }

    protected function listModels(WhisperTranscriber $transcriber): int
    {
        $models = $transcriber->listModels();
        $currentModel = config('dictation.model');

        $rows = [];
        foreach ($models as $model) {
            $status = $model['installed'] ? 'Installed' : '-';
            $size = $model['size'] ? $this->formatBytes($model['size']) : '-';
            $active = $model['name'] === $currentModel ? ' (active)' : '';

            $rows[] = [
                $model['name'] . $active,
                $status,
                $size,
            ];
        }

        $this->table(['Model', 'Status', 'Size'], $rows);

        return self::SUCCESS;
    }

    protected function downloadModel(WhisperTranscriber $transcriber): int
    {
        $model = $this->argument('model');

        if (! $model) {
            $this->error('Please specify a model name.');

            return self::FAILURE;
        }

        if ($transcriber->modelExists($model)) {
            $this->info("Model '{$model}' is already downloaded.");

            return self::SUCCESS;
        }

        $this->info("Downloading model '{$model}'...");

        try {
            $path = $transcriber->downloadModel($model);
            $this->info("Model downloaded to: {$path}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Download failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function deleteModel(WhisperTranscriber $transcriber): int
    {
        $model = $this->argument('model');

        if (! $model) {
            $this->error('Please specify a model name.');

            return self::FAILURE;
        }

        if (! $transcriber->modelExists($model)) {
            $this->warn("Model '{$model}' is not installed.");

            return self::SUCCESS;
        }

        if ($transcriber->deleteModel($model)) {
            $this->info("Model '{$model}' deleted.");

            return self::SUCCESS;
        }

        $this->error("Failed to delete model '{$model}'.");

        return self::FAILURE;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("Unknown action: {$action}");
        $this->line('Available actions: list, download, delete');

        return self::FAILURE;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
