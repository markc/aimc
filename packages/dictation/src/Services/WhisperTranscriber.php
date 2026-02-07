<?php

namespace Markc\Dictation\Services;

use Codewithkyrian\Whisper\ModelLoader;
use Codewithkyrian\Whisper\Whisper;
use Codewithkyrian\Whisper\WhisperFullParams;
use RuntimeException;

class WhisperTranscriber
{
    protected string $modelsPath;
    protected int $threads;
    protected string $language;
    protected string $model;

    public function __construct()
    {
        $this->modelsPath = config('dictation.models_path');
        $this->threads = config('dictation.threads', 4);
        $this->language = config('dictation.language', 'en');
        $this->model = config('dictation.model', 'base.en');
    }

    public function transcribe(string $audioFile, ?string $model = null, ?string $language = null): TranscriptionResult
    {
        $model = $model ?? $this->model;
        $language = $language ?? $this->language;

        $this->ensureModelExists($model);

        $startTime = hrtime(true);

        $params = WhisperFullParams::default()
            ->withLanguage($language);

        $whisper = Whisper::fromPretrained($model, baseDir: $this->modelsPath, params: $params);

        $segments = $whisper->transcribe($audioFile, $this->threads);

        $processingMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        $text = '';
        $segmentData = [];

        foreach ($segments as $segment) {
            $text .= $segment->text;
            $segmentData[] = [
                'start' => $segment->startTimestamp,
                'end' => $segment->endTimestamp,
                'text' => $segment->text,
            ];
        }

        $text = trim($text);

        // Estimate audio duration from file size (16kHz, 16-bit, mono = 32000 bytes/sec)
        $fileSize = filesize($audioFile);
        $durationMs = $fileSize > 44 ? (int) (($fileSize - 44) / 32) : null;

        return new TranscriptionResult(
            text: $text,
            segments: $segmentData,
            audioFile: $audioFile,
            model: $model,
            language: $language,
            durationMs: $durationMs,
            processingMs: $processingMs,
        );
    }

    public function downloadModel(string $model): string
    {
        $this->ensureDirectory($this->modelsPath);

        return ModelLoader::loadModel($model, $this->modelsPath);
    }

    public function deleteModel(string $model): bool
    {
        $path = $this->getModelPath($model);

        if ($path && file_exists($path)) {
            return unlink($path);
        }

        return false;
    }

    public function listModels(): array
    {
        $available = [
            'tiny.en', 'tiny', 'base.en', 'base',
            'small.en', 'small', 'medium.en', 'medium',
            'large',
        ];

        $installed = [];

        foreach ($available as $name) {
            $path = $this->getModelPath($name);
            $installed[$name] = [
                'name' => $name,
                'installed' => $path !== null && file_exists($path),
                'path' => $path,
                'size' => ($path && file_exists($path)) ? filesize($path) : null,
            ];
        }

        return $installed;
    }

    public function modelExists(string $model): bool
    {
        $path = $this->getModelPath($model);

        return $path !== null && file_exists($path);
    }

    protected function ensureModelExists(string $model): void
    {
        if (! $this->modelExists($model)) {
            throw new RuntimeException(
                "Whisper model '{$model}' not found. Run: php artisan dictation:model download {$model}"
            );
        }
    }

    protected function getModelPath(string $model): ?string
    {
        $this->ensureDirectory($this->modelsPath);

        // whisper.php stores models as ggml-{model}.bin
        $path = $this->modelsPath . '/ggml-' . $model . '.bin';

        return file_exists($path) ? $path : null;
    }

    protected function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
