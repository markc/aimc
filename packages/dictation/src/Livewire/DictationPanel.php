<?php

namespace Markc\Dictation\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Markc\Dictation\Models\DictationSetting;
use Markc\Dictation\Models\Transcription;
use Markc\Dictation\Services\DictationService;

class DictationPanel extends Component
{
    public bool $isRecording = false;

    public ?string $lastTranscription = null;

    public ?int $lastProcessingMs = null;

    public ?string $lastModel = null;

    // Settings
    public string $selectedModel = 'base.en';

    public string $selectedLanguage = 'en';

    public string $selectedInjector = 'wtype';

    public bool $autoInject = true;

    public bool $autoDeleteAudio = true;

    // Search
    public string $search = '';

    public function mount(): void
    {
        $settings = DictationSetting::forUser();

        $this->selectedModel = $settings->model;
        $this->selectedLanguage = $settings->language;
        $this->selectedInjector = $settings->injector;
        $this->autoInject = $settings->auto_inject;
        $this->autoDeleteAudio = $settings->auto_delete_audio;

        $service = app(DictationService::class);
        $this->isRecording = $service->isRecording();
    }

    public function toggleRecording(): void
    {
        $service = app(DictationService::class);

        if ($this->isRecording) {
            $this->stopRecording($service);
        } else {
            $this->startRecording($service);
        }
    }

    protected function startRecording(DictationService $service): void
    {
        try {
            $service->startRecording();
            $this->isRecording = true;
            $this->lastTranscription = null;
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    protected function stopRecording(DictationService $service): void
    {
        try {
            $result = $service->stopRecording();
            $this->isRecording = false;

            if ($result) {
                $this->lastTranscription = $result->text;
                $this->lastProcessingMs = $result->processingMs;
                $this->lastModel = $result->model;
            } else {
                $this->dispatch('notify', type: 'warning', message: 'No audio captured.');
            }
        } catch (\Throwable $e) {
            $this->isRecording = false;
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function reInject(int $transcriptionId): void
    {
        $transcription = Transcription::forCurrentUser()->findOrFail($transcriptionId);
        $service = app(DictationService::class);

        if ($service->injectText($transcription->text)) {
            $transcription->update(['injected' => true]);
            $this->dispatch('notify', type: 'success', message: 'Text injected.');
        } else {
            $this->dispatch('notify', type: 'danger', message: 'Injection failed.');
        }
    }

    public function copyText(int $transcriptionId): void
    {
        $transcription = Transcription::forCurrentUser()->findOrFail($transcriptionId);
        $this->dispatch('copy-to-clipboard', text: $transcription->text);
    }

    public function deleteTranscription(int $id): void
    {
        Transcription::forCurrentUser()->where('id', $id)->delete();
    }

    public function saveSettings(): void
    {
        $settings = DictationSetting::forUser();
        $settings->update([
            'model' => $this->selectedModel,
            'language' => $this->selectedLanguage,
            'injector' => $this->selectedInjector,
            'auto_inject' => $this->autoInject,
            'auto_delete_audio' => $this->autoDeleteAudio,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Settings saved.');
    }

    public function downloadModel(string $model): void
    {
        try {
            $service = app(DictationService::class);
            $service->downloadModel($model);
            $this->dispatch('notify', type: 'success', message: "Model '{$model}' downloaded.");
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function deleteModel(string $model): void
    {
        try {
            $service = app(DictationService::class);
            $service->deleteModel($model);
            $this->dispatch('notify', type: 'success', message: "Model '{$model}' deleted.");
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    #[Computed]
    public function transcriptions()
    {
        $query = Transcription::forCurrentUser()->latest();

        if ($this->search) {
            $query->where('text', 'like', '%' . $this->search . '%');
        }

        return $query->take(50)->get();
    }

    #[Computed]
    public function models()
    {
        return app(DictationService::class)->listModels();
    }

    public function getAvailableLanguages(): array
    {
        return [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            'ko' => 'Korean',
            'auto' => 'Auto-detect',
        ];
    }

    public function render()
    {
        return view('dictation::livewire.dictation-panel');
    }
}
