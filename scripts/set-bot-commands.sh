#!/usr/bin/env bash
# Register the bot command list with Telegram (setMyCommands).
# Run once after deploy, from anywhere:
#
#   BOT_TOKEN='<token>' ./scripts/set-bot-commands.sh
#
# The token is read from the environment and never written to disk or logs.
set -euo pipefail

BOT_TOKEN="${BOT_TOKEN:-${TELEGRAM_BOT_TOKEN:-}}"
if [ -z "$BOT_TOKEN" ]; then
  printf 'BOT_TOKEN env is required\n' >&2
  exit 1
fi

payload='{
  "commands": [
    {"command": "start",    "description": "Boshlash"},
    {"command": "help",     "description": "Yordam"},
    {"command": "whoami",   "description": "Joriy admin"},
    {"command": "messages", "description": "Contact inbox (10 ta)"},
    {"command": "unread",   "description": "O‘qilmagan xabarlar"},
    {"command": "message",  "description": "Xabarni o‘qish (id)"},
    {"command": "reply",    "description": "Email javob (id | matn)"},
    {"command": "stats",    "description": "Statistika"},
    {"command": "inbox",    "description": "IMAP email inbox"},
    {"command": "read",     "description": "Emailni o‘qish (uid)"},
    {"command": "send",     "description": "Email yuborish"}
  ]
}'

# Never echo the URL: it contains the bot token.
response="$(curl -sS --max-time 15 -X POST \
  -H 'Content-Type: application/json' \
  --data "$payload" \
  "https://api.telegram.org/bot${BOT_TOKEN}/setMyCommands")"

case "$response" in
  *'"ok":true'*) printf 'Bot commands registered.\n' ;;
  *) printf 'setMyCommands failed: %s\n' "$response" >&2; exit 1 ;;
esac
