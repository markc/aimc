<?php

namespace Markc\Dictation\Services;

use RuntimeException;

class AudioRecorder
{
    protected string $recordingsPath;
    protected string $pidFile;
    protected string $format;
    protected int $rate;
    protected int $channels;
    protected int $maxDuration;

    public function __construct()
    {
        $this->recordingsPath = config('dictation.recordings_path');
        $this->pidFile = config('dictation.pid_file');
        $this->format = config('dictation.recorder.format', 'wav');
        $this->rate = config('dictation.recorder.rate', 16000);
        $this->channels = config('dictation.recorder.channels', 1);
        $this->maxDuration = config('dictation.max_duration', 300);
    }

    public function start(): string
    {
        if ($this->isRecording()) {
            throw new RuntimeException('Recording already in progress.');
        }

        $this->ensureDirectory($this->recordingsPath);
        $this->ensureDirectory(dirname($this->pidFile));

        $filename = 'recording_' . date('Ymd_His') . '.' . $this->format;
        $filepath = $this->recordingsPath . '/' . $filename;

        // Use exec to replace shell with pw-record (so PID matches the actual process).
        // Nohup + output redirection keeps it alive after this PHP process exits.
        $command = sprintf(
            'nohup pw-record --format=s16 --rate=%d --channels=%d %s > /dev/null 2>&1 & echo $!',
            $this->rate,
            $this->channels,
            escapeshellarg($filepath)
        );

        $pid = (int) trim(shell_exec($command) ?: '');

        if ($pid <= 0) {
            throw new RuntimeException('Failed to start pw-record.');
        }

        // Verify the process actually started
        usleep(200000); // 200ms

        if (! posix_kill($pid, 0)) {
            throw new RuntimeException('pw-record process exited immediately.');
        }

        file_put_contents($this->pidFile, json_encode([
            'pid' => $pid,
            'file' => $filepath,
            'started_at' => time(),
        ]));

        return $filepath;
    }

    public function stop(): ?string
    {
        if (! $this->isRecording()) {
            return null;
        }

        $data = json_decode(file_get_contents($this->pidFile), true);
        $pid = $data['pid'];
        $filepath = $data['file'];

        // Send SIGINT for clean WAV header finalization
        if (posix_kill($pid, SIGINT)) {
            // Wait for process to exit cleanly
            $waited = 0;
            while (posix_kill($pid, 0) && $waited < 2000) {
                usleep(50000); // 50ms
                $waited += 50;
            }

            // Force kill if still running
            if (posix_kill($pid, 0)) {
                posix_kill($pid, SIGKILL);
                usleep(100000);
            }
        }

        @unlink($this->pidFile);

        if (! file_exists($filepath) || filesize($filepath) < 100) {
            return null;
        }

        return $filepath;
    }

    public function isRecording(): bool
    {
        if (! file_exists($this->pidFile)) {
            return false;
        }

        $data = json_decode(file_get_contents($this->pidFile), true);

        if (! $data || ! isset($data['pid'])) {
            @unlink($this->pidFile);

            return false;
        }

        // Check if process is still running
        if (! posix_kill($data['pid'], 0)) {
            @unlink($this->pidFile);

            return false;
        }

        // Check max duration safety limit
        if (isset($data['started_at']) && (time() - $data['started_at']) > $this->maxDuration) {
            $this->stop();

            return false;
        }

        return true;
    }

    public function getRecordingInfo(): ?array
    {
        if (! $this->isRecording()) {
            return null;
        }

        $data = json_decode(file_get_contents($this->pidFile), true);
        $data['elapsed'] = time() - $data['started_at'];

        return $data;
    }

    protected function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
