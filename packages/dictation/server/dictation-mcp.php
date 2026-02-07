#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Dictation MCP Server — Local speech-to-text for Claude Code
 *
 * Exposes dictation tools via Model Context Protocol (JSON-RPC 2.0 over stdio).
 * Bootstraps Laravel to access DictationService, AudioRecorder, WhisperTranscriber.
 *
 * Protocol: JSON-RPC 2.0 over newline-delimited stdio
 * Spec: https://modelcontextprotocol.io/specification/2024-11-05
 */

ob_implicit_flush(true);
while (ob_get_level()) ob_end_flush();

// ============================================================================
// Bootstrap Laravel
// ============================================================================

// Find the Laravel app root (three levels up from packages/dictation/server/)
$basePath = dirname(__DIR__, 3);
require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ============================================================================
// Tool & Schema Helpers (same pattern as appmesh)
// ============================================================================

final readonly class Tool
{
    public function __construct(
        public string $description,
        public array $inputSchema,
        /** @var callable(array): string */
        private mixed $handler,
    ) {}

    public function execute(array $args): string
    {
        return ($this->handler)($args);
    }
}

function schema(array $properties = [], array $required = []): array
{
    return [
        'type' => 'object',
        'properties' => empty($properties) ? (object)[] : $properties,
        'required' => $required,
    ];
}

function prop(string $type, string $description, array $extra = []): array
{
    return ['type' => $type, 'description' => $description, ...$extra];
}

// ============================================================================
// Dictation Tools
// ============================================================================

$recorder = app(Markc\Dictation\Services\AudioRecorder::class);
$transcriber = app(Markc\Dictation\Services\WhisperTranscriber::class);
$injector = app(Markc\Dictation\Services\TextInjector::class);
$service = app(Markc\Dictation\Services\DictationService::class);

$tools = [

    'dictation_start' => new Tool(
        description: 'Start recording audio from the microphone for speech-to-text dictation. Returns the audio file path.',
        inputSchema: schema(),
        handler: function (array $args) use ($recorder): string {
            if ($recorder->isRecording()) {
                return 'Already recording. Use dictation_stop to stop and transcribe.';
            }
            $filepath = $recorder->start();
            return "Recording started. Audio file: {$filepath}\nSpeak now, then use dictation_stop when done.";
        }
    ),

    'dictation_stop' => new Tool(
        description: 'Stop recording and transcribe the audio to text using whisper.cpp. Returns the transcribed text. Optionally injects text into the focused window.',
        inputSchema: schema(
            ['inject' => prop('boolean', 'Inject transcribed text into focused window via clipboard paste (default: true)')],
        ),
        handler: function (array $args) use ($recorder, $transcriber, $injector): string {
            if (!$recorder->isRecording()) {
                return 'No recording in progress. Use dictation_start first.';
            }

            $audioFile = $recorder->stop();
            if (!$audioFile) {
                return 'Recording stopped but no audio was captured.';
            }

            $model = config('dictation.model', 'base.en');
            $language = config('dictation.language', 'en');

            $result = $transcriber->transcribe($audioFile, $model, $language);

            $inject = $args['inject'] ?? true;
            $injected = false;
            if ($inject && !empty($result->text)) {
                $injected = $injector->inject($result->text);
            }

            // Auto-delete audio
            if (config('dictation.auto_delete_audio', true) && file_exists($audioFile)) {
                @unlink($audioFile);
            }

            $output = "Transcription: {$result->text}\n";
            $output .= "Model: {$result->model} | Language: {$result->language} | Processing: " . round($result->processingMs / 1000, 1) . "s";
            $output .= " | Segments: " . count($result->segments);
            if ($injected) {
                $output .= "\nText injected into focused window via clipboard paste.";
            }

            return $output;
        }
    ),

    'dictation_transcribe' => new Tool(
        description: 'Transcribe an existing audio file to text using whisper.cpp.',
        inputSchema: schema(
            [
                'file' => prop('string', 'Path to audio file (WAV format)'),
                'model' => prop('string', 'Whisper model to use (default: base.en)'),
                'language' => prop('string', 'Language code (default: en)'),
            ],
            ['file']
        ),
        handler: function (array $args) use ($transcriber): string {
            $file = $args['file'] ?? '';
            if (!file_exists($file)) {
                return "File not found: {$file}";
            }

            $model = $args['model'] ?? config('dictation.model', 'base.en');
            $language = $args['language'] ?? config('dictation.language', 'en');

            $result = $transcriber->transcribe($file, $model, $language);

            $output = "Transcription: {$result->text}\n";
            $output .= "Model: {$result->model} | Language: {$result->language} | Processing: " . round($result->processingMs / 1000, 1) . "s";
            $output .= " | Segments: " . count($result->segments);

            return $output;
        }
    ),

    'dictation_status' => new Tool(
        description: 'Check if dictation is currently recording.',
        inputSchema: schema(),
        handler: function (array $args) use ($recorder): string {
            if ($recorder->isRecording()) {
                $info = $recorder->getRecordingInfo();
                return "Recording in progress for {$info['elapsed']}s.\nFile: {$info['file']}";
            }
            return 'Not recording.';
        }
    ),

    'dictation_models' => new Tool(
        description: 'List available whisper models and their installation status.',
        inputSchema: schema(),
        handler: function (array $args) use ($transcriber): string {
            $models = $transcriber->listModels();
            $current = config('dictation.model', 'base.en');
            $lines = ["Available whisper models (active: {$current}):\n"];

            foreach ($models as $name => $model) {
                $status = $model['installed'] ? 'installed' : 'not downloaded';
                $size = $model['size'] ? round($model['size'] / 1048576) . ' MB' : '';
                $active = $name === $current ? ' *' : '';
                $lines[] = "  {$name}{$active} — {$status}" . ($size ? " ({$size})" : '');
            }

            return implode("\n", $lines);
        }
    ),

    'dictation_model_download' => new Tool(
        description: 'Download a whisper model for transcription.',
        inputSchema: schema(
            ['model' => prop('string', 'Model name (e.g., tiny.en, base.en, small.en, medium.en, large)')],
            ['model']
        ),
        handler: function (array $args) use ($transcriber): string {
            $model = $args['model'] ?? '';
            if ($transcriber->modelExists($model)) {
                return "Model '{$model}' is already downloaded.";
            }
            $path = $transcriber->downloadModel($model);
            return "Model '{$model}' downloaded to: {$path}";
        }
    ),

];

// ============================================================================
// MCP Server
// ============================================================================

final readonly class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';

    public function __construct(
        private string $name,
        private string $version,
        /** @var array<string, Tool> */
        private array $tools,
    ) {}

    public function run(): never
    {
        $this->log("Starting v{$this->version} with " . count($this->tools) . " tools");

        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line === false || ($line = trim($line)) === '') {
                continue;
            }

            $request = json_decode($line, true);
            if (!is_array($request)) {
                continue;
            }

            $this->dispatch($request);
        }

        $this->log("Shutting down");
        exit(0);
    }

    private function dispatch(array $request): void
    {
        $method = $request['method'] ?? '';
        $id = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        $this->log("Received: $method" . ($id !== null ? " (id: $id)" : " (notification)"));

        match ($method) {
            'initialize' => $this->handleInitialize($id),
            'notifications/initialized' => $this->log("Client initialized"),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $params),
            'ping' => $this->respond($id, (object)[]),
            default => $this->handleUnknown($method, $id),
        };
    }

    private function handleInitialize(mixed $id): void
    {
        $this->respond($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => (object)[]],
            'serverInfo' => [
                'name' => $this->name,
                'version' => $this->version,
            ],
        ]);
    }

    private function handleToolsList(mixed $id): void
    {
        $tools = array_map(
            fn(string $name, Tool $tool): array => [
                'name' => $name,
                'description' => $tool->description,
                'inputSchema' => $tool->inputSchema,
            ],
            array_keys($this->tools),
            array_values($this->tools)
        );

        $this->respond($id, ['tools' => $tools]);
    }

    private function handleToolsCall(mixed $id, array $params): void
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        $this->log("Calling tool: $name");

        if (!isset($this->tools[$name])) {
            $this->respondToolResult($id, "Unknown tool: $name", isError: true);
            return;
        }

        try {
            $result = $this->tools[$name]->execute($args);
            $this->respondToolResult($id, $result);
        } catch (\Throwable $e) {
            $this->log("Tool error: {$e->getMessage()}");
            $this->respondToolResult($id, "Error: {$e->getMessage()}", isError: true);
        }
    }

    private function handleUnknown(string $method, mixed $id): void
    {
        if ($id !== null) {
            $this->respondError($id, -32601, "Method not found: $method");
        }
    }

    private function respondToolResult(mixed $id, string $text, bool $isError = false): void
    {
        $result = ['content' => [['type' => 'text', 'text' => $text]]];
        if ($isError) {
            $result['isError'] = true;
        }
        $this->respond($id, $result);
    }

    private function respond(mixed $id, array|object $result): void
    {
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function respondError(mixed $id, int $code, string $message): void
    {
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }

    private function send(array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
    }

    private function log(string $msg): void
    {
        fwrite(STDERR, "[{$this->name}] $msg\n");
    }
}

// ============================================================================
// Run Server
// ============================================================================

(new McpServer('dictation', '1.0.0', $tools))->run();
