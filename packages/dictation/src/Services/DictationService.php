<?php

namespace Markc\Dictation\Services;

use Illuminate\Support\Facades\Auth;
use Markc\Dictation\Models\DictationSetting;
use Markc\Dictation\Models\Transcription;

class DictationService
{
    public function __construct(
        protected AudioRecorder $recorder,
        protected WhisperTranscriber $transcriber,
        protected TextInjector $injector,
    ) {}

    public function startRecording(): string
    {
        return $this->recorder->start();
    }

    public function stopRecording(): ?TranscriptionResult
    {
        $audioFile = $this->recorder->stop();

        if (! $audioFile) {
            return null;
        }

        return $this->transcribe($audioFile);
    }

    public function transcribe(string $audioFile, ?string $model = null, ?string $language = null): TranscriptionResult
    {
        $settings = $this->getUserSettings();
        $model = $model ?? $settings?->model ?? config('dictation.model');
        $language = $language ?? $settings?->language ?? config('dictation.language');

        $result = $this->transcriber->transcribe($audioFile, $model, $language);

        // Save transcription to database
        $this->saveTranscription($result);

        // Auto-inject if enabled
        $autoInject = $settings?->auto_inject ?? config('dictation.auto_inject');
        $injected = false;

        if ($autoInject && ! empty($result->text)) {
            $injected = $this->injector->inject($result->text);
        }

        // Auto-delete audio if enabled
        $autoDelete = $settings?->auto_delete_audio ?? config('dictation.auto_delete_audio');

        if ($autoDelete && file_exists($audioFile)) {
            @unlink($audioFile);
        }

        return $result;
    }

    public function injectText(string $text): bool
    {
        return $this->injector->inject($text);
    }

    public function isRecording(): bool
    {
        return $this->recorder->isRecording();
    }

    public function getRecordingInfo(): ?array
    {
        return $this->recorder->getRecordingInfo();
    }

    public function downloadModel(string $model): string
    {
        return $this->transcriber->downloadModel($model);
    }

    public function deleteModel(string $model): bool
    {
        return $this->transcriber->deleteModel($model);
    }

    public function listModels(): array
    {
        return $this->transcriber->listModels();
    }

    public function modelExists(string $model): bool
    {
        return $this->transcriber->modelExists($model);
    }

    protected function saveTranscription(TranscriptionResult $result): Transcription
    {
        return Transcription::create([
            'user_id' => Auth::id(),
            'text' => $result->text,
            'audio_file' => $result->audioFile,
            'model' => $result->model,
            'language' => $result->language,
            'duration_ms' => $result->durationMs,
            'processing_ms' => $result->processingMs,
            'segments' => $result->segments,
            'injected' => false,
            'metadata' => [],
        ]);
    }

    protected function getUserSettings(): ?DictationSetting
    {
        if (! Auth::check()) {
            return null;
        }

        return DictationSetting::where('user_id', Auth::id())->first();
    }
}
