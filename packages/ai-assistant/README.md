# AI Assistant for Laravel/Filament

A reusable AI chat assistant component for Laravel applications using Anthropic's Claude API.

## Features

- Chat interface with conversation history
- Image attachment support (vision) — JPEG, PNG, GIF, WebP
- Model switching (Haiku 4.5, Sonnet 4.5, Opus 4.6)
- Token usage and stop reason tracking
- Message deletion with attachment cleanup
- Chat export to Markdown
- Livewire-powered real-time updates
- Filament admin panel integration
- Tool/function calling support
- Streaming responses (optional)
- Easy to customize and extend

## Requirements

- PHP 8.1+
- Laravel 10+ / 11+ / 12+
- Filament 4+ / 5+
- Livewire 3+ / 4+

## Installation

### Option 1: Copy Package (Recommended for customization)

1. Copy the `packages/ai-assistant` directory to your project:

```bash
cp -r packages/ai-assistant /path/to/your-project/packages/
```

2. Add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/ai-assistant"
        }
    ],
    "require": {
        "markc/ai-assistant": "*"
    }
}
```

3. Run composer update:

```bash
composer update markc/ai-assistant
```

### Option 2: Install Dependencies Manually

If not using the package structure, install the Anthropic SDK:

```bash
composer require anthropic-ai/sdk
```

Then copy the source files to your app directory.

## Configuration

1. Add your Anthropic API key to `.env`:

```env
ANTHROPIC_API_KEY=sk-ant-api03-xxxxx
ANTHROPIC_MODEL=claude-sonnet-4-5-20250929
ANTHROPIC_MAX_TOKENS=4096
ANTHROPIC_SYSTEM_PROMPT="You are a helpful assistant."
```

2. Publish the config (optional):

```bash
php artisan vendor:publish --tag=ai-assistant-config
```

3. Run migrations:

```bash
php artisan migrate
```

4. Create the storage symlink (required for image attachments):

```bash
php artisan storage:link
```

## Filament Integration

Register the page in your Filament Panel Provider:

```php
// app/Providers/Filament/AdminPanelProvider.php

use Markc\AiAssistant\AiAssistantServiceProvider;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->pages([
            AiAssistantServiceProvider::getFilamentPage(),
        ]);
}
```

Or publish and customize the Filament page:

```bash
php artisan vendor:publish --tag=ai-assistant-filament
```

## Using the Chat Component Standalone

You can use the chat component anywhere in your Blade views:

```blade
<livewire:ai-assistant::chat-box />
```

Or with a specific conversation:

```blade
<livewire:ai-assistant::chat-box :conversation-id="$conversationId" />
```

## Using the Service Directly

```php
use Markc\AiAssistant\Services\AnthropicService;

$ai = app(AnthropicService::class);

// Simple chat
$response = $ai->chat([
    ['role' => 'user', 'content' => 'Hello, how are you?']
]);

// With custom system prompt
$ai->setSystemPrompt('You are a helpful coding assistant.');
$response = $ai->chat($messages);

// Register tools
$ai->registerTool(
    name: 'get_weather',
    description: 'Get the current weather for a location',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'location' => ['type' => 'string', 'description' => 'City name'],
        ],
        'required' => ['location'],
    ],
    handler: fn($input) => "Weather in {$input['location']}: Sunny, 22°C"
);

$response = $ai->chat([
    ['role' => 'user', 'content' => 'What is the weather in Sydney?']
]);
```

## Customization

### Custom System Prompt

Set in `.env` or config:

```env
ANTHROPIC_SYSTEM_PROMPT="You are a customer support assistant for Acme Corp. Be helpful and professional."
```

### Navigation Configuration

In `config/ai-assistant.php`:

```php
'navigation' => [
    'icon' => 'heroicon-o-chat-bubble-left-right',
    'label' => 'Chat with AI',
    'group' => 'Tools',
    'sort' => 50,
],
```

### Custom Styling

Publish views and modify:

```bash
php artisan vendor:publish --tag=ai-assistant-views
```

Views will be in `resources/views/vendor/ai-assistant/`.

## File Structure

```
packages/ai-assistant/
├── composer.json
├── config/
│   └── ai-assistant.php
├── database/
│   └── migrations/
│       └── 2024_01_01_000000_create_ai_assistant_tables.php
├── resources/
│   └── views/
│       ├── filament/pages/ai-assistant.blade.php
│       └── livewire/chat-box.blade.php
├── src/
│   ├── AiAssistantServiceProvider.php
│   ├── Filament/Pages/AiAssistant.php
│   ├── Livewire/ChatBox.php
│   ├── Models/
│   │   ├── Conversation.php
│   │   └── Message.php
│   └── Services/
│       └── AnthropicService.php
└── README.md
```

## License

MIT
