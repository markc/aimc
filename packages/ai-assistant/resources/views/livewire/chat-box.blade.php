<div
    style="display: flex; flex-direction: column; gap: 1.5rem;"
    x-data
    @ai-assistant:download.window="
        const blob = new Blob([$event.detail.content], { type: 'text/markdown' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = $event.detail.filename;
        a.click();
        URL.revokeObjectURL(url);
    "
>
    {{-- Input Section --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ $this->conversation?->title ?? 'New Chat' }}
        </x-slot>
        <x-slot name="afterHeader">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-filament::icon-button
                    icon="heroicon-o-plus-circle"
                    label="New Chat"
                    wire:click="newConversation"
                    size="xl"
                    color="success"
                />
                @if ($this->conversationId)
                    <x-filament::icon-button
                        icon="heroicon-o-arrow-down-tray"
                        label="Export chat"
                        wire:click="exportChat"
                        size="xl"
                        color="gray"
                    />
                @endif
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="selectedModel">
                        @foreach ($this->getAvailableModels() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        {{-- Attachment Preview --}}
        @if ($attachment)
            <div style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                <div style="position: relative; display: inline-block;">
                    <img
                        src="{{ $attachment->temporaryUrl() }}"
                        alt="Attachment preview"
                        style="height: 4rem; width: 4rem; object-fit: cover; border-radius: 0.5rem; border: 1px solid rgb(209 213 219);"
                    />
                    <button
                        type="button"
                        wire:click="removeAttachment"
                        style="position: absolute; top: -0.375rem; right: -0.375rem; background: rgb(239 68 68); color: white; border-radius: 9999px; width: 1.25rem; height: 1.25rem; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; border: none; cursor: pointer; line-height: 1;"
                        title="Remove attachment"
                    >&times;</button>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $attachment->getClientOriginalName() }}
                </span>
            </div>
        @endif

        {{-- Upload Error --}}
        @error('attachment')
            <div style="margin-bottom: 0.75rem;" class="text-sm text-danger-600 dark:text-danger-400">
                {{ $message }}
            </div>
        @enderror

        <form wire:submit="send">
            <input
                type="file"
                wire:model="attachment"
                accept="image/jpeg,image/png,image/gif,image/webp"
                style="display: none;"
                id="ai-attachment-input"
            />
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button
                    type="button"
                    x-on:click="document.getElementById('ai-attachment-input').click()"
                    @disabled($isLoading)
                    style="display: flex; align-items: center; justify-content: center; padding: 0.5rem; color: rgb(107 114 128); border: none; background: none; cursor: pointer;"
                    title="Attach image"
                >
                    <x-filament::icon icon="heroicon-o-paper-clip" class="h-5 w-5" />
                </button>
                <div style="flex: 1;">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="input"
                            placeholder="Type your message and press Enter..."
                            :disabled="$isLoading"
                            x-on:keydown.enter.prevent="$wire.send()"
                        />
                    </x-filament::input.wrapper>
                </div>
                <div wire:loading.remove wire:target="send">
                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-paper-airplane"
                    >
                        Send
                    </x-filament::button>
                </div>
                <div wire:loading wire:target="send">
                    <x-filament::button
                        type="button"
                        disabled
                        color="gray"
                    >
                        <x-filament::loading-indicator class="h-5 w-5" />
                        Sending...
                    </x-filament::button>
                </div>
            </div>
        </form>

        {{-- Upload Loading --}}
        <div wire:loading wire:target="attachment" style="margin-top: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-filament::loading-indicator class="h-4 w-4 text-primary-500" />
                <span class="text-xs text-gray-500 dark:text-gray-400">Uploading image...</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Loading Indicator --}}
    @if ($isLoading)
        <x-filament::section>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                <span class="text-sm text-gray-500 dark:text-gray-400">AI is thinking...</span>
            </div>
        </x-filament::section>
    @endif

    {{-- Messages (newest first) --}}
    @foreach ($this->chatMessages->reverse() as $message)
        <x-filament::section>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    @if ($message->isUser())
                        <x-filament::icon icon="heroicon-o-user" class="h-5 w-5 text-gray-400" />
                        <span>You</span>
                    @else
                        <x-filament::icon icon="heroicon-o-sparkles" class="h-5 w-5 text-primary-500" />
                        <span>AI Assistant</span>
                    @endif
                </div>
            </x-slot>
            <x-slot name="afterHeader">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $message->created_at->format('g:i A') }}
                        @if ($message->isAssistant() && $message->input_tokens)
                            &middot; &#x2193;{{ number_format($message->input_tokens) }} &#x2191;{{ number_format($message->output_tokens) }}
                        @endif
                        @if ($message->isAssistant() && $message->stop_reason)
                            &middot; {{ $message->stop_reason }}
                        @endif
                    </span>
                    <x-filament::icon-button
                        icon="heroicon-o-trash"
                        color="danger"
                        size="sm"
                        label="Delete message"
                        wire:click="deleteMessage({{ $message->id }})"
                        wire:confirm="Delete this message?"
                    />
                </div>
            </x-slot>

            {{-- Image Attachments --}}
            @if ($message->isUser() && $message->attachments)
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                    @foreach ($message->attachments as $att)
                        <a href="{{ Storage::disk('public')->url($att['path']) }}" target="_blank">
                            <img
                                src="{{ Storage::disk('public')->url($att['path']) }}"
                                alt="{{ $att['original_name'] ?? 'Attachment' }}"
                                style="max-height: 12rem; max-width: 20rem; border-radius: 0.5rem; border: 1px solid rgb(209 213 219); cursor: pointer;"
                            />
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($message->isUser())
                <p class="text-sm text-gray-950 dark:text-white">{{ $message->content }}</p>
            @else
                <div class="fi-in-rich-text prose max-w-none text-sm dark:prose-invert">
                    {!! \Illuminate\Support\Str::markdown($message->content) !!}
                </div>
            @endif
        </x-filament::section>
    @endforeach

    {{-- Empty State --}}
    @if ($this->chatMessages->isEmpty() && !$isLoading)
        <x-filament::section>
            <div style="text-align: center; padding: 1.5rem 0;">
                <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">No messages yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Type a message above to start chatting with the AI assistant.
                </p>
            </div>
        </x-filament::section>
    @endif
</div>
