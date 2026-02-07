<?php

namespace Markc\Dictation\Services;

class TranscriptionResult
{
    public function __construct(
        public readonly string $text,
        public readonly array $segments,
        public readonly string $audioFile,
        public readonly string $model,
        public readonly string $language,
        public readonly ?int $durationMs = null,
        public readonly ?int $processingMs = null,
    ) {}

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'segments' => $this->segments,
            'audio_file' => $this->audioFile,
            'model' => $this->model,
            'language' => $this->language,
            'duration_ms' => $this->durationMs,
            'processing_ms' => $this->processingMs,
        ];
    }
}
