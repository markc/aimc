# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AIMC (AI Mark Constable) is a Laravel 12 + Filament 5 application that develops and showcases the `markc/ai-assistant` package — a reusable AI chat assistant powered by Anthropic's Claude API.

The reusable package lives in `packages/ai-assistant/`. The rest of the repo is a demo app that exercises it.

## Tech Stack

- PHP 8.4+, Laravel 12, Filament 5, Livewire 4
- Anthropic PHP SDK (`anthropic-ai/sdk`)
- Tailwind CSS 4 with `@tailwindcss/typography` for prose-styled markdown
- SQLite (dev), Vite 7

## Commands

```bash
composer setup              # Install deps, generate key, migrate, build assets
composer dev                # Start server + queue + logs + Vite
composer test               # Run PHPUnit tests
npm run build               # Rebuild frontend assets
php artisan migrate         # Run migrations
php artisan migrate:fresh   # Reset database
```

## Architecture

```
packages/ai-assistant/          # The reusable package
├── config/ai-assistant.php     # Publishable config
├── database/migrations/        # ai_conversations + ai_messages tables
├── resources/views/            # Blade views (Filament page + Livewire chat)
└── src/
    ├── AiAssistantServiceProvider.php   # Service provider (auto-discovered)
    ├── Filament/Pages/AiAssistant.php   # Filament admin page
    ├── Livewire/ChatBox.php             # Chat UI component
    ├── Models/Conversation.php          # Conversation model (per-user)
    ├── Models/Message.php               # Message model (with token tracking)
    └── Services/AnthropicService.php    # Claude API wrapper
```

### Key Classes

- **AnthropicService** — singleton wrapping the Anthropic SDK. Provides `chat()`, `stream()`, `registerTool()`, `setModel()`, `setSystemPrompt()`, and token usage tracking.
- **ChatBox** (Livewire) — handles sending messages, image uploads, model switching, message deletion, and markdown export.
- **AiAssistant** (Filament Page) — admin page with conversation sidebar (last 20 chats).
- **Conversation** / **Message** — Eloquent models. Conversation scopes to authenticated user. Message stores role, content, attachments (JSON), tool calls/results, token counts, and stop reason.

## Adding the ai-assistant Package to Another Project

### 1. Copy the package

```bash
cp -r packages/ai-assistant /path/to/target-project/packages/
```

### 2. Register in composer.json

```json
{
    "repositories": [{ "type": "path", "url": "packages/ai-assistant" }],
    "require": { "markc/ai-assistant": "@dev" }
}
```

```bash
composer update markc/ai-assistant
```

The service provider auto-registers via Laravel package discovery.

### 3. Environment

```env
ANTHROPIC_API_KEY=sk-ant-api03-xxxxx
ANTHROPIC_MODEL=claude-sonnet-4-5-20250929
ANTHROPIC_MAX_TOKENS=4096
ANTHROPIC_SYSTEM_PROMPT="You are a helpful assistant."
```

### 4. Migrate and link storage

```bash
php artisan migrate
php artisan storage:link
```

### 5. Register the Filament page

In `AdminPanelProvider.php`:

```php
use Markc\AiAssistant\AiAssistantServiceProvider;

->pages([
    Pages\Dashboard::class,
    AiAssistantServiceProvider::getFilamentPage(),
])
```

### 6. Enable prose typography (for markdown rendering)

```bash
npm install -D @tailwindcss/typography
```

Add `@plugin '@tailwindcss/typography'` to `resources/css/app.css`.

Create `resources/css/filament/admin/theme.css`:

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';
@plugin '@tailwindcss/typography';

@source '../../../../app/Filament/**/*';
@source '../../../../resources/views/filament/**/*';
@source '../../../../packages/ai-assistant/resources/views/**/*';
```

Add the theme to `vite.config.js` inputs and register in AdminPanelProvider:

```php
->viteTheme('resources/css/filament/admin/theme.css')
```

Then `npm run build`.

## Environment

- `ANTHROPIC_API_KEY` — required, from https://console.anthropic.com/
- `ANTHROPIC_MODEL` — default `claude-sonnet-4-5-20250929`
- `ANTHROPIC_MAX_TOKENS` — default `4096`
- `ANTHROPIC_SYSTEM_PROMPT` — default `"You are a helpful assistant."`

## Conventions

- Package code goes in `packages/ai-assistant/`, app-level code in `app/`
- Views use Filament components (`x-filament::section`, `x-filament::button`, etc.)
- AI responses render markdown via `Str::markdown()` with `prose dark:prose-invert` classes
- Config values always via `config()`, never `env()` outside config files
- Conversations are always scoped to the authenticated user
