<?php

namespace Markc\Dictation\Commands;

use Illuminate\Console\Command;
use Markc\Dictation\Services\DictationService;

class DictationStopCommand extends Command
{
    protected $signature = 'dictation:stop
                            {--no-inject : Do not inject text into focused window}';

    protected $description = 'Stop recording and transcribe audio';

    public function handle(DictationService $service): int
    {
        if (! $service->isRecording()) {
            $this->error('No recording in progress.');

            return self::FAILURE;
        }

        $this->info('Stopping recording...');

        try {
            $result = $service->stopRecording();

            if (! $result) {
                $this->warn('Recording stopped but no audio was captured.');

                return self::FAILURE;
            }

            $this->info('Transcription complete.');
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

            if (! $this->option('no-inject') && config('dictation.auto_inject')) {
                $this->line('Text injected into focused window.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Transcription failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
