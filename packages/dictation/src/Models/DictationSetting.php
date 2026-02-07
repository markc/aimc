<?php

namespace Markc\Dictation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DictationSetting extends Model
{
    protected $table = 'dictation_settings';

    protected $fillable = [
        'user_id',
        'model',
        'language',
        'injector',
        'auto_inject',
        'auto_delete_audio',
    ];

    protected function casts(): array
    {
        return [
            'auto_inject' => 'boolean',
            'auto_delete_audio' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public static function forUser(?int $userId = null): static
    {
        $userId = $userId ?? auth()->id();

        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'model' => config('dictation.model'),
                'language' => config('dictation.language'),
                'injector' => config('dictation.injector'),
                'auto_inject' => config('dictation.auto_inject'),
                'auto_delete_audio' => config('dictation.auto_delete_audio'),
            ]
        );
    }
}
