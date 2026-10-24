#!/usr/bin/env bash
# Production operations helper for otaboyev-sardorbek.uz (cPanel account host9590).
# Run ON THE SERVER as the cPanel user. Safe to re-run; every step is idempotent.
#
#   ./scripts/production-ops.sh verify
#   ./scripts/production-ops.sh migrate
#   ./scripts/production-ops.sh deploy
#   ./scripts/production-ops.sh cron
#   ./scripts/production-ops.sh logs
#   ./scripts/production-ops.sh all
#
# Environment overrides: APP_DIR, WEB_ROOT, BACKUP_DIR, LOG_DIR, SITE_URL, NODE_ENABLE, SKIP_BUILD=1
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/apps/otaboyev-sardorbek.uz}"
WEB_ROOT="${WEB_ROOT:-$HOME/public_html}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/otaboyev-sardorbek.uz}"
LOG_DIR="${LOG_DIR:-$HOME/logs}"
SITE_URL="${SITE_URL:-https://otaboyev-sardorbek.uz}"
NODE_ENABLE="${NODE_ENABLE:-/opt/alt/alt-nodejs22/enable}"
CRON_BACKUP_TAG="# otaboyev-ops:backup"
CRON_MONITOR_TAG="# otaboyev-ops:monitor"
CRON_LOGROTATE_TAG="# otaboyev-ops:logrotate"

log() { printf '[ops] %s\n' "$*"; }
die() { printf '[ops] ERROR: %s\n' "$*" >&2; exit 1; }

config_value() {
  # config_value <dotted.key> -> prints value from $APP_DIR/config.local.php
  php -r '
    $file = $argv[1];
    $key  = $argv[2];
    if (!is_file($file)) { fwrite(STDERR, "config not found\n"); exit(1); }
    $c = require $file;
    foreach (explode(".", $key) as $part) {
      if (!is_array($c) || !array_key_exists($part, $c)) { exit(0); }
      $c = $c[$part];
    }
    echo $c;
  ' "$APP_DIR/config.local.php" "$1"
}

require_config() {
  [ -f "$APP_DIR/config.local.php" ] || die "missing $APP_DIR/config.local.php"
  chmod 600 "$APP_DIR/config.local.php" 2>/dev/null || true
}

check_build() {
  [ -f "$APP_DIR/out/api.php" ] || die "$APP_DIR/out is not a build artifact"
  [ -f "$APP_DIR/out/404.html" ] || die "$APP_DIR/out/404.html missing"
  if [ -e "$APP_DIR/out/config.local.php" ] || [ -e "$APP_DIR/out/github_keys.txt" ] \
    || [ -e "$APP_DIR/out/config.local.js" ] || [ -e "$APP_DIR/out/server_keys.txt" ]; then
    die "secret file inside out/ - refusing to deploy"
  fi
  if [ -d "$APP_DIR/out/database" ]; then
    die "database/ inside out/ - refusing to deploy"
  fi
}

do_migrate() {
  require_config
  command -v mysql >/dev/null 2>&1 || die "mysql client not found"
  local host port name user pass socket
  host="$(config_value host)"
  port="$(config_value port)"
  name="$(config_value name)"
  user="$(config_value user)"
  pass="$(config_value pass)"
  socket="$(config_value socket)"
  [ -n "$name" ] && [ -n "$user" ] || die "DB config incomplete"

  export MYSQL_PWD="$pass"
  local args=(-h "${host:-localhost}" -u "$user" "$name")
  [ -n "$port" ] && args=(-P "$port" "${args[@]}")
  [ -n "$socket" ] && args=(-S "$socket" "${args[@]}")

  for file in database/migrate-project-sections.sql database/migrate-contact-messages.sql database/migrate-bot-guards.sql database/migrate-admin-role.sql; do
    [ -f "$APP_DIR/$file" ] || { log "skip $file (absent)"; continue; }
    log "importing $file (idempotent)"
    mysql "${args[@]}" < "$APP_DIR/$file"
  done

  log "verifying guard tables"
  mysql "${args[@]}" -N -e "SHOW TABLES LIKE 'bot_updates_seen'; SHOW TABLES LIKE 'bot_rate_limit';"

  log "verifying admin role column"
  mysql "${args[@]}" -N -e "SHOW COLUMNS FROM admin_users LIKE 'role';"

  log "verifying contact rate-limit index"
  mysql "${args[@]}" -N -e "SHOW INDEX FROM contact_messages WHERE Key_name = 'idx_contact_messages_ip';"
  unset MYSQL_PWD
}

do_build() {
  cd "$APP_DIR"
  if [ "${SKIP_BUILD:-0}" = "1" ]; then
    log "SKIP_BUILD=1, using existing out/"
    return 0
  fi
  if [ -f "$NODE_ENABLE" ]; then
    # shellcheck disable=SC1090
    . "$NODE_ENABLE"
  fi
  command -v npm >/dev/null 2>&1 || die "npm not found (enable Node 22 first)"
  log "npm ci"
  npm ci --no-audit --no-fund
  log "npm run check"
  npm run check
  log "npm run build"
  npm run build
}

do_deploy() {
  require_config
  do_build
  check_build
  [ -d "$WEB_ROOT" ] || die "web root not found: $WEB_ROOT"

  local stamp snapshot
  stamp="$(date +%Y%m%d-%H%M%S)"
  snapshot="$BACKUP_DIR/web-$stamp"
  mkdir -p "$snapshot"
  chmod 700 "$BACKUP_DIR"

  log "snapshotting current web root to $snapshot"
  tar -C "$WEB_ROOT" --exclude='./config.local.php' --exclude='./.well-known' -cf - . 2>/dev/null \
    | tar -C "$snapshot" -xf - 2>/dev/null || log "snapshot skipped (empty web root?)"

  log "publishing out/ to $WEB_ROOT"
  tar -C "$APP_DIR/out" --exclude='./config.local.php' -cf - . | tar -C "$WEB_ROOT" -xf -

  log "restoring secret config and permissions"
  cp "$APP_DIR/config.local.php" "$WEB_ROOT/config.local.php"
  chmod 600 "$WEB_ROOT/config.local.php"
  find "$WEB_ROOT" -type d -exec chmod 755 {} +
  find "$WEB_ROOT" -type f -exec chmod 644 {} +
  chmod 600 "$WEB_ROOT/config.local.php" 2>/dev/null || true
  [ -f "$WEB_ROOT/.htaccess" ] || log "WARNING: .htaccess missing in web root"

  log "deploy complete ($stamp)"
}

do_logs() {
  mkdir -p "$LOG_DIR"
  local conf="$HOME/.config/otaboyev-ops/logrotate.conf"
  mkdir -p "$(dirname "$conf")"
  cat > "$conf" <<EOF
$LOG_DIR/*.log {
    weekly
    rotate 8
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
}
EOF
  log "logrotate config written to $conf"

  local cron_line="17 4 * * * $(command -v logrotate || echo /usr/sbin/logrotate) -s $HOME/.config/otaboyev-ops/logrotate.state $conf >/dev/null 2>&1 $CRON_LOGROTATE_TAG"
  install_cron "$CRON_LOGROTATE_TAG" "$cron_line"
}

do_cron() {
  require_config
  mkdir -p "$BACKUP_DIR" "$LOG_DIR"
  chmod 700 "$BACKUP_DIR"

  local backup_script="$HOME/.config/otaboyev-ops/backup.sh"
  mkdir -p "$(dirname "$backup_script")"
  cat > "$backup_script" <<EOF
#!/usr/bin/env bash
set -euo pipefail
umask 077
APP_DIR="$APP_DIR"
WEB_ROOT="$WEB_ROOT"
BACKUP_DIR="$BACKUP_DIR"
LOG_DIR="$LOG_DIR"
stamp=\$(date +%Y%m%d-%H%M%S)
mkdir -p "\$BACKUP_DIR" "\$LOG_DIR"

php_config() {
  php -r '\$c = require \$argv[1]; \$v = \$c; foreach (explode(".", \$argv[2]) as \$p) { \$v = \$v[\$p] ?? ""; } echo \$v;' "\$APP_DIR/config.local.php" "\$1"
}

MYSQL_PWD="\$(php_config pass)" mysqldump \
  -h "\$(php_config host)" -u "\$(php_config user)" "\$(php_config name)" \
  --single-transaction --quick --no-tablespaces 2>/dev/null \
  | gzip -9 > "\$BACKUP_DIR/db-\$stamp.sql.gz"

tar -C "\$WEB_ROOT" --exclude='./config.local.php' -czf "\$BACKUP_DIR/web-\$stamp.tar.gz" .

find "\$BACKUP_DIR" -maxdepth 1 -type f -name 'db-*.sql.gz'  -mtime +14 -delete
find "\$BACKUP_DIR" -maxdepth 1 -type f -name 'web-*.tar.gz' -mtime +14 -delete
find "\$BACKUP_DIR" -maxdepth 1 -type d -name 'web-*'       -mtime +3  -exec rm -rf {} + 2>/dev/null || true
echo "\$(date -Is) backup ok" >> "\$LOG_DIR/backup.log"
EOF
  chmod 700 "$backup_script"

  local monitor_script="$HOME/.config/otaboyev-ops/monitor.sh"
  cat > "$monitor_script" <<EOF
#!/usr/bin/env bash
set -euo pipefail
umask 077
APP_DIR="$APP_DIR"
SITE_URL="$SITE_URL"
LOG_DIR="$LOG_DIR"
STATE="$HOME/.config/otaboyev-ops/monitor.state"
BOT_STATE="$HOME/.config/otaboyev-ops/bot.state"
SECRET_STATE="$HOME/.config/otaboyev-ops/bot-secret.state"
DB_STATE="$HOME/.config/otaboyev-ops/db.state"
mkdir -p "\$LOG_DIR"

TOKEN=\$(php -r '\$c = require \$argv[1]; echo \$c["admin"]["telegram_bot_token"] ?? "";' "\$APP_DIR/config.local.php")
CHAT=\$(php -r '\$c = require \$argv[1]; echo \$c["admin"]["telegram_id"] ?? "";' "\$APP_DIR/config.local.php")

notify() {
  [ -n "\$TOKEN" ] && [ -n "\$CHAT" ] || return 0
  curl -fsS --max-time 10 "https://api.telegram.org/bot\$TOKEN/sendMessage" \
    --data-urlencode "chat_id=\$CHAT" --data-urlencode "text=\$1" >/dev/null 2>&1 || true
}

code=\$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "\$SITE_URL/api.php/health" || echo 000)
if [ "\$code" = "200" ]; then
  if [ -f "\$STATE" ]; then rm -f "\$STATE"; notify "✅ \$SITE_URL yana ishlayapti"; fi
else
  if [ ! -f "\$STATE" ]; then
    date -Is > "\$STATE"
    notify "🚨 \$SITE_URL health=\$code ($(hostname))"
  fi
  echo "\$(date -Is) health=\$code" >> "\$LOG_DIR/monitor.log"
  exit 1
fi

# Bot bridge: config presence + Telegram webhook backlog.
bot=\$(curl -sS --max-time 15 "\$SITE_URL/api.php/bot/health" 2>/dev/null || echo '')
case "\$bot" in
  *'"status":"ok"'*) ;;
  *)
    # Endpoint unreachable (old deploy, 404/5xx): report once and skip the
    # per-field checks instead of firing three alerts.
    if [ ! -f "\$BOT_STATE" ]; then
      date -Is > "\$BOT_STATE"
      notify "🚨 Bot health endpoint javob bermayapti (\$SITE_URL/api.php/bot/health)"
    fi
    echo "\$(date -Is) bot_health=endpoint_down" >> "\$LOG_DIR/monitor.log"
    bot=''
    ;;
esac

if [ -n "\$bot" ]; then
  case "\$bot" in
    *'"configured":true'*)
      if [ -f "\$BOT_STATE" ]; then rm -f "\$BOT_STATE"; notify "✅ Telegram bot yana ishlayapti"; fi ;;
    *)
      if [ ! -f "\$BOT_STATE" ]; then
        date -Is > "\$BOT_STATE"
        notify "🚨 Telegram bot sozlanmagan (token yoki admin ID yo‘q)"
      fi
      echo "\$(date -Is) bot_health=unconfigured" >> "\$LOG_DIR/monitor.log" ;;
  esac

  # A missing webhook secret means Telegram rejects every update, so the
  # bridge is effectively off even when configured=true.
  case "\$bot" in
    *'"webhook_secret":true'*)
      if [ -f "\$SECRET_STATE" ]; then rm -f "\$SECRET_STATE"; notify "✅ Bot webhook secret yana sozlangan"; fi ;;
    *)
      if [ ! -f "\$SECRET_STATE" ]; then
        date -Is > "\$SECRET_STATE"
        notify "🚨 Bot webhook_secret sozlanmagan (kamida 32 belgi kerak)"
      fi
      echo "\$(date -Is) bot_health=no_webhook_secret" >> "\$LOG_DIR/monitor.log" ;;
  esac

  # The content API and the bot share one database. Report an outage once and
  # clear the flag when it recovers.
  case "\$bot" in
    *'"database":true'*)
      if [ -f "\$DB_STATE" ]; then rm -f "\$DB_STATE"; notify "✅ Baza yana javob bermoqda"; fi ;;
    *)
      if [ ! -f "\$DB_STATE" ]; then
        date -Is > "\$DB_STATE"
        notify "🚨 Baza javob bermayapti: /api.php/bot/health database=false"
      fi
      echo "\$(date -Is) bot_health=database_down" >> "\$LOG_DIR/monitor.log" ;;
  esac
fi

if [ -n "\$TOKEN" ]; then
  info=\$(curl -sS --max-time 15 "https://api.telegram.org/bot\$TOKEN/getWebhookInfo" 2>/dev/null || echo '')
  pending=\$(printf '%s' "\$info" | php -r '\$d = json_decode(stream_get_contents(STDIN), true); echo isset(\$d["result"]["pending_update_count"]) ? (int) \$d["result"]["pending_update_count"] : -1;')
  if [ "\$pending" -lt 0 ]; then
    notify "🚨 getWebhookInfo javob bermadi (\$(hostname))"
    echo "\$(date -Is) webhook_info=failed" >> "\$LOG_DIR/monitor.log"
  elif [ "\$pending" -gt 5 ]; then
    notify "🚨 bot pending_update_count=\$pending (\$(hostname))"
    echo "\$(date -Is) pending_update_count=\$pending" >> "\$LOG_DIR/monitor.log"
  fi
fi
EOF
  chmod 700 "$monitor_script"

  install_cron "$CRON_BACKUP_TAG" "10 3 * * * $backup_script >/dev/null 2>&1 $CRON_BACKUP_TAG"
  install_cron "$CRON_MONITOR_TAG" "*/5 * * * * $monitor_script >/dev/null 2>&1 $CRON_MONITOR_TAG"

  log "backup cron: daily 03:10, retention 14 days, dir $BACKUP_DIR"
  log "monitor cron: every 5 minutes with Telegram alert"
}

install_cron() {
  local tag="$1" line="$2" current
  current="$(crontab -l 2>/dev/null | grep -vF "$tag" || true)"
  {
    [ -n "$current" ] && printf '%s\n' "$current"
    printf '%s\n' "$line"
  } | crontab -
  log "cron updated: $tag"
}

do_maildns() {
  local domain="${MAIL_DOMAIN:-otaboyev-sardorbek.uz}"
  local resolver="${DNS_RESOLVER:-1.1.1.1}"
  local failures=0

  txt() {
    if command -v dig >/dev/null 2>&1; then
      dig +short "@$resolver" TXT "$1" 2>/dev/null | tr -d '"' | tr '\n' ' '
    else
      php -r '$r = @dns_get_record($argv[1], DNS_TXT); if (!$r) { exit(0); } foreach ($r as $row) { echo $row["txt"], " "; }' "$1"
    fi
  }

  log "mail DNS checks for $domain (resolver $resolver)"

  local spf dmarc dkim_default dkim_mail
  spf="$(txt "$domain")"
  case "$spf" in
    *v=spf1*) if printf '%s' "$spf" | grep -q -- '-all'; then
        printf '  ok   %-22s %s\n' "SPF" "hard fail present"
      else
        printf '  WARN %-22s %s\n' "SPF" "no -all qualifier"; failures=$((failures + 1))
      fi ;;
    *) printf '  FAIL %-22s %s\n' "SPF" "missing"; failures=$((failures + 1)) ;;
  esac

  dkim_default="$(txt "default._domainkey.$domain")"
  dkim_mail="$(txt "mail._domainkey.$domain")"
  if printf '%s' "$dkim_default$dkim_mail" | grep -qE 'p=[A-Za-z0-9+/]{20,}'; then
    printf '  ok   %-22s %s\n' "DKIM" "public key published"
  else
    printf '  FAIL %-22s %s\n' "DKIM" "no default._domainkey / mail._domainkey"; failures=$((failures + 1))
  fi

  dmarc="$(txt "_dmarc.$domain")"
  case "$dmarc" in
    *v=DMARC1*)
      if printf '%s' "$dmarc" | grep -qE 'p=(quarantine|reject)'; then
        printf '  ok   %-22s %s\n' "DMARC" "$(printf '%s' "$dmarc" | grep -oE 'p=[a-z]+')"
      else
        printf '  WARN %-22s %s\n' "DMARC" "p=none — tighten after DKIM works"
      fi ;;
    *) printf '  FAIL %-22s %s\n' "DMARC" "missing"; failures=$((failures + 1)) ;;
  esac

  [ "$failures" -eq 0 ] && log "mail DNS checks passed" \
    || die "mail DNS checks failed - fix SPF/DKIM in cPanel (Email Deliverability) or Eskiz DNS"
}

do_verify() {
  local code failures=0
  check() {
    local label="$1" expect="$2" url="$3"
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$url" || echo 000)"
    if [ "$code" = "$expect" ]; then
      printf '  ok   %-28s %s\n' "$label" "$code"
    else
      printf '  FAIL %-28s %s (want %s)\n' "$label" "$code" "$expect"
      failures=$((failures + 1))
    fi
  }

  log "live checks against $SITE_URL"
  check "health" 200 "$SITE_URL/api.php/health"
  check "bot health" 200 "$SITE_URL/api.php/bot/health"
  check "uz home" 200 "$SITE_URL/uz/"
  check "en home" 200 "$SITE_URL/en/"
  check "ru home" 200 "$SITE_URL/ru/"
  check "projects list" 200 "$SITE_URL/uz/projects/"
  check "project detail" 200 "$SITE_URL/uz/projects/gitinsight/"
  check "unknown locale 404" 404 "$SITE_URL/de/"
  check "config blocked" 403 "$SITE_URL/config.local.php"
  check "backend blocked" 403 "$SITE_URL/backend/config.php"
  check "database blocked" 403 "$SITE_URL/database/schema.sql"

  local urls
  urls="$(curl -s --max-time 15 "$SITE_URL/sitemap.xml" | grep -c '<loc>' || true)"
  if [ "$urls" -ge 15 ]; then printf '  ok   %-28s %s urls\n' "sitemap" "$urls"; else
    printf '  FAIL %-28s %s urls\n' "sitemap" "$urls"; failures=$((failures + 1))
  fi

  local cfg
  cfg="$(curl -s --max-time 15 "$SITE_URL/api.php/admin/config" || true)"
  case "$cfg" in
    *'"enabled":true'*) printf '  ok   %-28s enabled\n' "admin auth" ;;
    *) printf '  FAIL %-28s not enabled\n' "admin auth"; failures=$((failures + 1)) ;;
  esac

  local bot
  bot="$(curl -s --max-time 15 "$SITE_URL/api.php/bot/health" || true)"
  case "$bot" in
    *'"configured":true'*) printf '  ok   %-28s configured\n' "telegram bot" ;;
    *) printf '  FAIL %-28s not configured\n' "telegram bot"; failures=$((failures + 1)) ;;
  esac
  case "$bot" in
    *'"webhook_secret":true'*) printf '  ok   %-28s secret set\n' "bot webhook secret" ;;
    *) printf '  FAIL %-28s secret missing/short\n' "bot webhook secret"; failures=$((failures + 1)) ;;
  esac
  case "$bot" in
    *'"database":true'*) printf '  ok   %-28s reachable\n' "bot database" ;;
    *) printf '  FAIL %-28s unreachable\n' "bot database"; failures=$((failures + 1)) ;;
  esac

  [ "$failures" -eq 0 ] && log "all checks passed" || die "$failures check(s) failed"
}

case "${1:-}" in
  migrate) do_migrate ;;
  build)   do_build ;;
  deploy)  do_deploy ;;
  cron)    do_cron ;;
  logs)    do_logs ;;
  maildns) do_maildns ;;
  verify)  do_verify ;;
  all)     do_migrate; do_deploy; do_logs; do_cron; do_verify ;;
  *)
    cat <<EOF
usage: $0 {migrate|build|deploy|cron|logs|maildns|verify|all}

  migrate  import pending migrations (project-sections, contact-messages, bot-guards, admin-role)
  build    npm ci + npm run check + npm run build on the server
  deploy   build (unless SKIP_BUILD=1) and publish out/ to the web root
  cron     install daily backup + 5-minute health monitor
  logs     install user-level logrotate config + daily cron
  maildns  check SPF / DKIM / DMARC for MAIL_DOMAIN
  verify   run live HTTP checks against SITE_URL
  all      migrate + deploy + logs + cron + verify
EOF
    exit 1 ;;
esac
