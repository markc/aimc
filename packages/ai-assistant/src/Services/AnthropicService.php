<?php

namespace Markc\AiAssistant\Services;

use Anthropic\Client;
use Anthropic\Messages\Message;
use Anthropic\Messages\MessageParam;
use Anthropic\Messages\Tool;
use Anthropic\Messages\Tool\InputSchema;
use Anthropic\Messages\ToolResultBlockParam;
use Anthropic\Messages\ToolUseBlock;
use Generator;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    private Client $client;

    private string $model;

    private int $maxTokens;

    private ?string $systemPrompt;

    /** @var array<Tool> */
    private array $tools = [];

    /** @var array<string, callable> */
    private array $toolHandlers = [];

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?int $maxTokens = null,
        ?string $systemPrompt = null,
    ) {
        $apiKey = $apiKey ?? config('ai-assistant.api_key');

        if (! $apiKey) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not configured');
        }

        $this->client = new Client(apiKey: $apiKey);
        $this->model = $model ?? config('ai-assistant.model', 'claude-sonnet-4-5-20250929');
        $this->maxTokens = $maxTokens ?? config('ai-assistant.max_tokens', 4096);
        $this->systemPrompt = $systemPrompt ?? config('ai-assistant.system_prompt');
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function setSystemPrompt(?string $systemPrompt): self
    {
        $this->systemPrompt = $systemPrompt;

        return $this;
    }

    /**
     * Register a tool with its handler.
     *
     * @param  array{type?: string, properties?: array, required?: array}  $inputSchema
     */
    public function registerTool(string $name, string $description, array $inputSchema, callable $handler): self
    {
        $this->tools[] = Tool::with(
            name: $name,
            description: $description,
            inputSchema: InputSchema::with(
                type: $inputSchema['type'] ?? 'object',
                properties: $inputSchema['properties'] ?? [],
                required: $inputSchema['required'] ?? null,
            ),
        );

        $this->toolHandlers[$name] = $handler;

        return $this;
    }

    /**
     * Clear all registered tools.
     */
    public function clearTools(): self
    {
        $this->tools = [];
        $this->toolHandlers = [];

        return $this;
    }

    /**
     * Send a simple message and get a response.
     *
     * @param  array<array{role: string, content: string|array}>  $messages
     * @return array{content: string, input_tokens: int, output_tokens: int, stop_reason: ?string}
     */
    public function chat(array $messages): array
    {
        $messageParams = array_map(
            fn ($m) => MessageParam::with(role: $m['role'], content: $m['content']),
            $messages
        );

        $params = [
            'maxTokens' => $this->maxTokens,
            'messages' => $messageParams,
            'model' => $this->model,
        ];

        if ($this->systemPrompt) {
            $params['system'] = $this->systemPrompt;
        }

        if (! empty($this->tools)) {
            $params['tools'] = $this->tools;
        }

        $response = $this->client->messages->create(...$params);

        $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'stop_reason' => null];
        $content = $this->processResponse($response, $messageParams, $usage);

        return [
            'content' => $content,
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'stop_reason' => $usage['stop_reason'],
        ];
    }

    /**
     * Stream a response for real-time output.
     *
     * @param  array<array{role: string, content: string|array}>  $messages
     * @return Generator<string>
     */
    public function stream(array $messages): Generator
    {
        $messageParams = array_map(
            fn ($m) => MessageParam::with(role: $m['role'], content: $m['content']),
            $messages
        );

        $params = [
            'maxTokens' => $this->maxTokens,
            'messages' => $messageParams,
            'model' => $this->model,
        ];

        if ($this->systemPrompt) {
            $params['system'] = $this->systemPrompt;
        }

        $stream = $this->client->messages->createStream(...$params);

        foreach ($stream as $event) {
            if (isset($event->delta->text)) {
                yield $event->delta->text;
            }
        }
    }

    /**
     * Process the response, handling tool calls if necessary.
     *
     * @param  array<MessageParam>  $originalMessages
     * @param  array{input_tokens: int, output_tokens: int}  $usage
     */
    private function processResponse(Message $response, array $originalMessages, array &$usage): string
    {
        $usage['input_tokens'] += $response->usage->inputTokens ?? 0;
        $usage['output_tokens'] += $response->usage->outputTokens ?? 0;
        $usage['stop_reason'] = is_string($response->stopReason) ? $response->stopReason : $response->stopReason?->value;

        $textContent = '';
        $toolUses = [];

        foreach ($response->content as $block) {
            if ($block instanceof ToolUseBlock) {
                $toolUses[] = $block;
            } elseif (isset($block->text)) {
                $textContent .= $block->text;
            }
        }

        if (empty($toolUses)) {
            return $textContent;
        }

        $toolResults = [];
        foreach ($toolUses as $toolUse) {
            $result = $this->executeTool($toolUse->name, (array) $toolUse->input);
            $toolResults[] = ToolResultBlockParam::with(
                toolUseId: $toolUse->id,
                content: is_string($result) ? $result : json_encode($result),
            );
        }

        $newMessages = $originalMessages;
        $newMessages[] = MessageParam::with(role: 'assistant', content: $response->content);
        $newMessages[] = MessageParam::with(role: 'user', content: $toolResults);

        $params = [
            'maxTokens' => $this->maxTokens,
            'messages' => $newMessages,
            'model' => $this->model,
        ];

        if ($this->systemPrompt) {
            $params['system'] = $this->systemPrompt;
        }

        if (! empty($this->tools)) {
            $params['tools'] = $this->tools;
        }

        $followUp = $this->client->messages->create(...$params);

        return $this->processResponse($followUp, $newMessages, $usage);
    }

    /**
     * Execute a registered tool.
     */
    private function executeTool(string $name, array $input): mixed
    {
        if (! isset($this->toolHandlers[$name])) {
            Log::warning("AI Assistant: Tool not found: {$name}");

            return "Error: Tool '{$name}' not found";
        }

        try {
            return ($this->toolHandlers[$name])($input);
        } catch (\Throwable $e) {
            Log::error("AI Assistant: Tool execution failed: {$name}", ['error' => $e->getMessage()]);

            return "Error executing tool: {$e->getMessage()}";
        }
    }

    /**
     * Get the underlying client for advanced usage.
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
