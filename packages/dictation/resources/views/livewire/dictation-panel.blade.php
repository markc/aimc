<div
    style="display: flex; flex-direction: column; gap: 1.5rem;"
    x-data
    @copy-to-clipboard.window="
        navigator.clipboard.writeText($event.detail.text).then(() => {
            $dispatch('notify', { type: 'success', message: 'Copied to clipboard' });
        });
    "
>
    {{-- Recording Controls --}}
    <x-filament::section>
        <x-slot name="heading">Recording</x-slot>

        <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 1rem 0;">
            {{-- Mic Toggle Button --}}
            <button
                type="button"
                wire:click="toggleRecording"
                @class([
                    'rounded-full p-6 transition-all duration-300 border-none cursor-pointer',
                    'bg-danger-500 hover:bg-danger-600 text-white shadow-lg shadow-danger-500/25 animate-pulse' => $isRecording,
                    'bg-primary-500 hover:bg-primary-600 text-white shadow-lg shadow-primary-500/25' => !$isRecording,
                ])
                style="width: 5rem; height: 5rem; display: flex; align-items: center; justify-content: center;"
            >
                @if ($isRecording)
                    <x-filament::icon icon="heroicon-s-stop" class="h-8 w-8" />
                @else
                    <x-filament::icon icon="heroicon-s-microphone" class="h-8 w-8" />
                @endif
            </button>

            <span @class([
                'text-sm font-medium',
                'text-danger-600 dark:text-danger-400' => $isRecording,
                'text-gray-500 dark:text-gray-400' => !$isRecording,
            ])>
                {{ $isRecording ? 'Recording... Click to stop' : 'Click to start recording' }}
            </span>

            {{-- Last Transcription Result --}}
            @if ($lastTranscription)
                <div style="width: 100%; margin-top: 0.5rem;">
                    <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-4">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Last transcription</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $lastModel }} &middot; {{ round($lastProcessingMs / 1000, 1) }}s
                            </span>
                        </div>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $lastTranscription }}</p>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Settings --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Settings</x-slot>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem;">
            {{-- Model Selector --}}
            <div>
                <label class="text-sm font-medium text-gray-950 dark:text-white" style="display: block; margin-bottom: 0.25rem;">Model</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="selectedModel">
                        @foreach ($this->models as $name => $model)
                            <option value="{{ $name }}" @disabled(!$model['installed'])>
                                {{ $name }}{{ !$model['installed'] ? ' (not downloaded)' : '' }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            {{-- Language Selector --}}
            <div>
                <label class="text-sm font-medium text-gray-950 dark:text-white" style="display: block; margin-bottom: 0.25rem;">Language</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="selectedLanguage">
                        @foreach ($this->getAvailableLanguages() as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            {{-- Injector Selector --}}
            <div>
                <label class="text-sm font-medium text-gray-950 dark:text-white" style="display: block; margin-bottom: 0.25rem;">Injector</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="selectedInjector">
                        <option value="wl-paste">wl-paste (Clipboard)</option>
                        <option value="wtype">wtype (Wayland)</option>
                        <option value="ydotool">ydotool</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div style="display: flex; gap: 2rem; margin-top: 1rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <x-filament::input.checkbox wire:model="autoInject" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Auto-inject text</span>
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <x-filament::input.checkbox wire:model="autoDeleteAudio" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Auto-delete audio</span>
            </label>
        </div>

        <div style="margin-top: 1rem;">
            <x-filament::button wire:click="saveSettings" size="sm">
                Save Settings
            </x-filament::button>
        </div>

        {{-- Model Management --}}
        <div style="margin-top: 1.5rem; border-top: 1px solid rgb(229 231 235); padding-top: 1rem;" class="dark:border-white/10">
            <h4 class="text-sm font-medium text-gray-950 dark:text-white" style="margin-bottom: 0.75rem;">Models</h4>
            <div class="fi-ta-content">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr>
                            <th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Name</th>
                            <th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                            <th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Size</th>
                            <th class="fi-ta-header-cell px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($this->models as $name => $model)
                            <tr>
                                <td class="fi-ta-cell px-3 py-2 text-sm text-gray-950 dark:text-white">{{ $name }}</td>
                                <td class="fi-ta-cell px-3 py-2">
                                    @if ($model['installed'])
                                        <span class="inline-flex items-center rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20">Installed</span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="fi-ta-cell px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $model['size'] ? number_format($model['size'] / 1048576) . ' MB' : '-' }}
                                </td>
                                <td class="fi-ta-cell px-3 py-2 text-right">
                                    @if ($model['installed'])
                                        <x-filament::icon-button
                                            icon="heroicon-o-trash"
                                            color="danger"
                                            size="sm"
                                            label="Delete model"
                                            wire:click="deleteModel('{{ $name }}')"
                                            wire:confirm="Delete model {{ $name }}?"
                                        />
                                    @else
                                        <x-filament::icon-button
                                            icon="heroicon-o-arrow-down-tray"
                                            color="primary"
                                            size="sm"
                                            label="Download model"
                                            wire:click="downloadModel('{{ $name }}')"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>

    {{-- Transcription History --}}
    <x-filament::section>
        <x-slot name="heading">History</x-slot>
        <x-slot name="afterHeader">
            <div style="width: 16rem;">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search transcriptions..."
                    />
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        @if ($this->transcriptions->isNotEmpty())
            <div class="fi-ta-content">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr>
                            <th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Text</th>
                            <th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Duration</th>
                            <th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Model</th>
                            <th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="fi-ta-header-cell px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($this->transcriptions as $transcription)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="fi-ta-cell px-3 py-2">
                                    <span class="text-sm text-gray-950 dark:text-white" style="max-width: 30rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $transcription->text }}
                                    </span>
                                </td>
                                <td class="fi-ta-cell px-3 py-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $transcription->duration_formatted }}
                                </td>
                                <td class="fi-ta-cell px-3 py-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $transcription->model }}
                                </td>
                                <td class="fi-ta-cell px-3 py-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $transcription->created_at->diffForHumans() }}
                                </td>
                                <td class="fi-ta-cell px-3 py-2 text-right whitespace-nowrap">
                                    <div style="display: flex; justify-content: flex-end; gap: 0.25rem;">
                                        <x-filament::icon-button
                                            icon="heroicon-o-clipboard-document"
                                            size="sm"
                                            color="gray"
                                            label="Copy text"
                                            wire:click="copyText({{ $transcription->id }})"
                                        />
                                        <x-filament::icon-button
                                            icon="heroicon-o-arrow-uturn-right"
                                            size="sm"
                                            color="primary"
                                            label="Re-inject text"
                                            wire:click="reInject({{ $transcription->id }})"
                                        />
                                        <x-filament::icon-button
                                            icon="heroicon-o-trash"
                                            size="sm"
                                            color="danger"
                                            label="Delete"
                                            wire:click="deleteTranscription({{ $transcription->id }})"
                                            wire:confirm="Delete this transcription?"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align: center; padding: 1.5rem 0;">
                <x-filament::icon icon="heroicon-o-microphone" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">No transcriptions yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Click the microphone button above to start dictating.
                </p>
            </div>
        @endif
    </x-filament::section>
</div>
