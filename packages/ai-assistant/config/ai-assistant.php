<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anthropic API Key
    |--------------------------------------------------------------------------
    |
    | Your Anthropic API key for accessing Claude models.
    |
    */
    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default Claude model to use for requests.
    | Options: claude-sonnet-4-5-20250929, claude-opus-4-6, etc.
    |
    */
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929'),

    /*
    |--------------------------------------------------------------------------
    | Max Tokens
    |--------------------------------------------------------------------------
    |
    | Default maximum tokens for responses.
    |
    */
    'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    |
    | Default system prompt for the assistant. Customize this to give
    | the AI context about your application.
    |
    */
    'system_prompt' => env('ANTHROPIC_SYSTEM_PROMPT', 'You are a helpful assistant.'),

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Configure how the AI Assistant appears in Filament navigation.
    |
    */
    'navigation' => [
        'icon' => 'heroicon-o-sparkles',
        'label' => 'AI Assistant',
        'group' => null,
        'sort' => 100,
    ],
];
