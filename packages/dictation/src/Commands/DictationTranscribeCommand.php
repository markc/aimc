<?php

namespace Markc\Dictation\Commands;

use Illuminate\Console\Command;
use Markc\Dictation\Services\WhisperTranscriber;

class DictationTranscribeCommand extends Command
{
    protected $signature = 'dictation:transcribe
                            {file : Path to audio file}
                            {--model= : Whisper model to use}
                            {--language= : Language code}';

    protected $description = 'Transcribe an audio file';

    public function handle(WhisperTranscriber $transcriber): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $model = $this->option('model') ?? config('dictation.model');
        $language = $this->option('language') ?? config('dictation.language');

        $this->info("Transcribing with model '{$model}'...");

        try {
            $result = $transcriber->transcribe($file, $model, $language);

            $this->newLine();
            $this->line($result->text);
            $this->newLine();
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Model', $result->model],
                    ['Language', $result->language],
                    ['Processing', ($result->processingMs / 1000) . 's'],
                    ['Segments', count($result->segments)],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Transcription failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
