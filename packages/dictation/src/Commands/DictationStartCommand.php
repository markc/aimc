<?php

namespace Markc\Dictation\Commands;

use Illuminate\Console\Command;
use Markc\Dictation\Services\AudioRecorder;

class DictationStartCommand extends Command
{
    protected $signature = 'dictation:start';

    protected $description = 'Start recording audio for dictation';

    public function handle(AudioRecorder $recorder): int
    {
        if ($recorder->isRecording()) {
            $this->error('Recording already in progress.');

            return self::FAILURE;
        }

        try {
            $filepath = $recorder->start();
            $this->info('Recording started.');
            $this->line("Audio file: {$filepath}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to start recording: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
