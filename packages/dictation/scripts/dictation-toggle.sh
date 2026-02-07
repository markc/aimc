#!/bin/bash
#
# dictation-toggle.sh — Toggle dictation recording via KDE shortcut (Meta+D)
#
# Setup:
#   1. System Settings → Shortcuts → Custom Shortcuts → Add
#   2. Trigger: Meta+D
#   3. Action: /path/to/packages/dictation/scripts/dictation-toggle.sh
#

APP_DIR="${DICTATION_APP_DIR:-$HOME/.gh/aimc}"
PID_FILE="${DICTATION_PID_FILE:-$APP_DIR/storage/app/dictation/recording.pid}"
PHP="${DICTATION_PHP:-php}"

cd "$APP_DIR" || exit 1

if [ -f "$PID_FILE" ]; then
    # Currently recording — stop and transcribe
    $PHP artisan dictation:stop 2>&1 | tail -1
    notify-send -i microphone-sensitivity-muted "Dictation" "Recording stopped — transcribing..."
else
    # Not recording — start
    $PHP artisan dictation:start 2>&1
    notify-send -i audio-input-microphone "Dictation" "Recording started..."
fi
