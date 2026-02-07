<?php

namespace Markc\Dictation\Services;

use RuntimeException;

class TextInjector
{
    protected string $injector;

    public function __construct()
    {
        $this->injector = config('dictation.injector', 'wtype');
    }

    public function isCliContext(): bool
    {
        return app()->runningInConsole();
    }

    public function inject(string $text): bool
    {
        if (empty(trim($text))) {
            return false;
        }

        // Desktop injection only works from CLI (artisan commands / toggle script)
        if (! $this->isCliContext()) {
            return false;
        }

        return match ($this->injector) {
            'wtype' => $this->injectViaWtype($text),
            'wl-paste' => $this->injectViaClipboard($text),
            'ydotool' => $this->injectViaYdotool($text),
            default => throw new RuntimeException("Unknown injector: {$this->injector}"),
        };
    }

    public function isAvailable(): bool
    {
        return match ($this->injector) {
            'wtype' => $this->commandExists('wtype'),
            'wl-paste' => $this->commandExists('wl-copy') && $this->commandExists('xdotool'),
            'ydotool' => $this->commandExists('ydotool'),
            default => false,
        };
    }

    public function getInjector(): string
    {
        return $this->injector;
    }

    protected function injectViaWtype(string $text): bool
    {
        // wtype types text character by character into the focused window
        $command = sprintf('wtype -- %s', escapeshellarg($text));

        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    protected function injectViaClipboard(string $text): bool
    {
        // Copy text to clipboard via wl-copy, then simulate Ctrl+V via xdotool
        $copyCmd = sprintf('wl-copy -- %s', escapeshellarg($text));
        exec($copyCmd, $output, $exitCode);

        if ($exitCode !== 0) {
            return false;
        }

        // Small delay to ensure clipboard is set
        usleep(50000); // 50ms

        exec('xdotool key ctrl+v', $output, $exitCode);

        return $exitCode === 0;
    }

    protected function injectViaYdotool(string $text): bool
    {
        $command = sprintf('ydotool type -- %s', escapeshellarg($text));

        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    protected function commandExists(string $command): bool
    {
        exec("command -v {$command} 2>/dev/null", $output, $exitCode);

        return $exitCode === 0;
    }
}
