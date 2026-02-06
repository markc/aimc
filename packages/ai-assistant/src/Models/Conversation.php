<?php

namespace Markc\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Conversation extends Model
{
    protected $table = 'ai_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'model',
        'system_prompt',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        return $this->belongsTo($userModel);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Get messages formatted for the Anthropic API.
     *
     * When a message has image attachments, builds a multi-block content array
     * using plain arrays to avoid SDK nested serialization issues with media_type.
     */
    public function getMessagesForApi(): array
    {
        return $this->messages
            ->map(function (Message $m) {
                $content = $m->content;

                if ($m->isUser() && ! empty($m->attachments)) {
                    $blocks = [];

                    foreach ($m->attachments as $attachment) {
                        $path = $attachment['path'];
                        if (Storage::disk('public')->exists($path)) {
                            $blocks[] = [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'data' => base64_encode(Storage::disk('public')->get($path)),
                                    'media_type' => $attachment['media_type'],
                                ],
                            ];
                        }
                    }

                    $blocks[] = ['type' => 'text', 'text' => $m->content];
                    $content = $blocks;
                }

                return ['role' => $m->role, 'content' => $content];
            })
            ->toArray();
    }

    /**
     * Generate a title from the first user message if not set.
     */
    public function generateTitle(): void
    {
        if ($this->title) {
            return;
        }

        $firstMessage = $this->messages()->where('role', 'user')->first();
        if ($firstMessage) {
            $this->title = str($firstMessage->content)->limit(50)->toString();
            $this->save();
        }
    }

    /**
     * Scope to get conversations for the current user.
     */
    public function scopeForCurrentUser($query)
    {
        return $query->where('user_id', auth()->id());
    }
}
