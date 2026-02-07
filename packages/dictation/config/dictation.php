<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Whisper Model
    |--------------------------------------------------------------------------
    |
    | The whisper.cpp model to use for transcription. Available models:
    | tiny.en, tiny, base.en, base, small.en, small, medium.en, medium, large
    |
    */

    'model' => env('DICTATION_MODEL', 'base.en'),

    /*
    |--------------------------------------------------------------------------
    | Models Path
    |--------------------------------------------------------------------------
    |
    | Directory where whisper models are stored. Models are downloaded
    | automatically on first use via the dictation:model command.
    |
    */

    'models_path' => env('DICTATION_MODELS_PATH', storage_path('app/dictation/models')),

    /*
    |--------------------------------------------------------------------------
    | Recordings Path
    |--------------------------------------------------------------------------
    |
    | Temporary directory for audio recordings captured by pw-record.
    |
    */

    'recordings_path' => env('DICTATION_RECORDINGS_PATH', storage_path('app/dictation/recordings')),

    /*
    |--------------------------------------------------------------------------
    | PID File
    |--------------------------------------------------------------------------
    |
    | Location of the PID file used to track the pw-record process.
    |
    */

    'pid_file' => env('DICTATION_PID_FILE', storage_path('app/dictation/recording.pid')),

    /*
    |--------------------------------------------------------------------------
    | CPU Threads
    |--------------------------------------------------------------------------
    |
    | Number of CPU threads to use for whisper inference.
    |
    */

    'threads' => env('DICTATION_THREADS', 4),

    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    |
    | Language code for transcription. Use 'en' for English.
    |
    */

    'language' => env('DICTATION_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Recorder Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for pw-record (PipeWire audio capture).
    |
    */

    'recorder' => [
        'format' => 'wav',
        'rate' => 16000,
        'channels' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Text Injector
    |--------------------------------------------------------------------------
    |
    | Tool used to inject transcribed text into the focused window.
    | Options: 'wl-paste' (clipboard via wl-copy + Ctrl+V, works on KDE),
    |          'wtype' (Wayland virtual keyboard), 'ydotool' (X11/Wayland).
    |
    */

    'injector' => env('DICTATION_INJECTOR', 'wl-paste'),

    /*
    |--------------------------------------------------------------------------
    | Auto Inject
    |--------------------------------------------------------------------------
    |
    | Automatically inject transcribed text into the focused window
    | after transcription completes.
    |
    */

    'auto_inject' => env('DICTATION_AUTO_INJECT', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Delete Audio
    |--------------------------------------------------------------------------
    |
    | Automatically delete audio files after successful transcription.
    |
    */

    'auto_delete_audio' => env('DICTATION_AUTO_DELETE_AUDIO', true),

    /*
    |--------------------------------------------------------------------------
    | Max Duration
    |--------------------------------------------------------------------------
    |
    | Maximum recording duration in seconds (safety limit).
    |
    */

    'max_duration' => env('DICTATION_MAX_DURATION', 300),

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Filament sidebar navigation configuration.
    |
    */

    'navigation' => [
        'icon' => 'heroicon-o-microphone',
        'label' => 'Dictation',
        'group' => null,
        'sort' => 110,
    ],

];
