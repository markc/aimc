<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <livewire:ai-assistant::chat-box :conversation-id="$conversationId" :key="$conversationId ?? 'new'" />

    {{-- Conversation History --}}
    @if ($this->getConversations()->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Conversations</x-slot>
            <x-slot name="afterHeader">
                <x-filament::icon-button
                    icon="heroicon-o-plus"
                    label="New Chat"
                    wire:click="selectConversation(0)"
                    size="sm"
                />
            </x-slot>
            <div class="fi-ta-content">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($this->getConversations() as $conversation)
                            <tr
                                wire:click="selectConversation({{ $conversation->id }})"
                                @class([
                                    'cursor-pointer transition',
                                    'bg-primary-50 dark:bg-primary-400/10' => $conversationId === $conversation->id,
                                    'hover:bg-gray-50 dark:hover:bg-white/5' => $conversationId !== $conversation->id,
                                ])
                            >
                                <td class="fi-ta-cell px-3 py-3">
                                    <div class="fi-ta-text">
                                        <span @class([
                                            'text-sm',
                                            'font-medium text-primary-600 dark:text-primary-400' => $conversationId === $conversation->id,
                                            'text-gray-950 dark:text-white' => $conversationId !== $conversation->id,
                                        ])>
                                            {{ $conversation->title ?? 'Untitled' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="fi-ta-cell px-3 py-3 text-right">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $conversation->updated_at->diffForHumans() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
    </div>
</x-filament-panels::page>
