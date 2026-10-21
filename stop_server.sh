#!/usr/bin/env bash
# Stop the server started by run_server.sh.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUN_DIR="${RUN_DIR:-$ROOT/.run}"
PID_FILE="$RUN_DIR/server.pid"

if [ ! -f "$PID_FILE" ]; then
    echo "Server is not running."
    exit 0
fi

pid="$(cat "$PID_FILE" 2>/dev/null || true)"
if [ -z "$pid" ] || ! kill -0 "$pid" 2>/dev/null; then
    rm -f "$PID_FILE"
    echo "Server is not running."
    exit 0
fi

if command -v ps >/dev/null 2>&1; then
    cmdline="$(ps -p "$pid" -o args= 2>/dev/null || true)"
    case "$cmdline" in
        *php*-S*)
            ;;
        *)
            echo "PID ${pid} serverga tegishli emas; PID fayl tozalandi." >&2
            rm -f "$PID_FILE"
            exit 1
            ;;
    esac
fi

kill -TERM -- "-$pid" >/dev/null 2>&1 || kill -TERM "$pid" >/dev/null 2>&1 || true
for _ in $(seq 1 20); do
    if ! kill -0 "$pid" 2>/dev/null; then
        break
    fi
    sleep 0.25
done
if kill -0 "$pid" 2>/dev/null; then
    kill -KILL -- "-$pid" >/dev/null 2>&1 || kill -KILL "$pid" >/dev/null 2>&1 || true
fi
rm -f "$PID_FILE"
echo "Server stopped."
