#!/usr/bin/env bash
# Start a production-mode preview of the built Next.js export + PHP API.
# This uses PHP's built-in server; use Apache/Nginx for .htaccess testing.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8081}"
APP_ENV="${APP_ENV:-production}"
SITE_URL="${SITE_URL:-http://127.0.0.1:${PORT}}"
PHP_BIN="${PHP_BIN:-php}"
RUN_DIR="${RUN_DIR:-$ROOT/.run}"
PID_FILE="$RUN_DIR/server.pid"
LOG_FILE="$RUN_DIR/server.log"

export APP_ENV SITE_URL

port_in_use() {
    if command -v ss >/dev/null 2>&1; then
        ss -ltn 2>/dev/null | awk 'NR > 1 { print $4 }' | grep -Eq "[:.]${PORT}$"
        return $?
    fi
    if command -v lsof >/dev/null 2>&1; then
        lsof -iTCP:"${PORT}" -sTCP:LISTEN -t >/dev/null 2>&1
        return $?
    fi
    return 1
}

if [ -f "$PID_FILE" ]; then
    old_pid="$(cat "$PID_FILE" 2>/dev/null || true)"
    if [ -n "$old_pid" ] && kill -0 "$old_pid" 2>/dev/null; then
        echo "Server is already running (pid ${old_pid}): ${SITE_URL}"
        exit 1
    fi
    rm -f "$PID_FILE"
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP topilmadi: $PHP_BIN" >&2
    exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
    echo "curl topilmadi; health-check uchun kerak." >&2
    exit 1
fi

mkdir -p "$RUN_DIR"
chmod 700 "$RUN_DIR" 2>/dev/null || true

if [ "${FORCE_BUILD:-0}" = "1" ] || [ ! -f "$ROOT/out/index.html" ] || [ ! -f "$ROOT/out/api.php" ]; then
    echo "Statik export tayyorlanmoqda..."
    npm run build
fi

if port_in_use; then
    echo "Port ${PORT} band. Boshqa port uchun PORT=... ./run_server.sh ishlating." >&2
    exit 1
fi

start_args=("$PHP_BIN" -S "${HOST}:${PORT}" -t "$ROOT/out" "$ROOT/scripts/php-server-router.php")
if command -v setsid >/dev/null 2>&1; then
    setsid "${start_args[@]}" >"$LOG_FILE" 2>&1 &
else
    "${start_args[@]}" >"$LOG_FILE" 2>&1 &
fi
pid=$!
printf '%s\n' "$pid" >"$PID_FILE"

health_url="http://127.0.0.1:${PORT}/api.php/health"
for _ in $(seq 1 40); do
    if curl -fsS "$health_url" >/dev/null 2>&1; then
        echo "Server preview: ${SITE_URL}"
        echo "APP_ENV=${APP_ENV}"
        echo "Log: ${LOG_FILE}"
        echo "To'xtatish: ./stop_server.sh"
        exit 0
    fi
    if ! kill -0 "$pid" 2>/dev/null; then
        echo "Server ishga tushmadi. Log:" >&2
        tail -40 "$LOG_FILE" >&2 || true
        rm -f "$PID_FILE"
        exit 1
    fi
    sleep 0.5
done

echo "Server health-check vaqtida javob bermadi. Log:" >&2
tail -40 "$LOG_FILE" >&2 || true
kill -TERM -- "-$pid" >/dev/null 2>&1 || kill -TERM "$pid" >/dev/null 2>&1 || true
rm -f "$PID_FILE"
exit 1
