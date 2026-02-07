<?php

namespace Markc\Dictation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transcription extends Model
{
    protected $table = 'dictation_transcriptions';

    protected $fillable = [
        'user_id',
        'text',
        'audio_file',
        'model',
        'language',
        'duration_ms',
        'processing_ms',
        'segments',
        'injected',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'injected' => 'boolean',
            'metadata' => 'array',
            'duration_ms' => 'integer',
            'processing_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function scopeForCurrentUser($query)
    {
        return $query->where('user_id', auth()->id());
    }

    public function getDurationFormattedAttribute(): string
    {
        if (! $this->duration_ms) {
            return '--';
        }

        $seconds = $this->duration_ms / 1000;

        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        return floor($seconds / 60) . 'm ' . round($seconds % 60) . 's';
    }

    public function getProcessingFormattedAttribute(): string
    {
        if (! $this->processing_ms) {
            return '--';
        }

        $seconds = $this->processing_ms / 1000;

        return round($seconds, 1) . 's';
    }
}
