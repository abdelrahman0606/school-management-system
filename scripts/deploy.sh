#!/usr/bin/env bash
#
# scripts/deploy.sh — safe update sequence for an ALREADY-INSTALLED instance
# (VPS with SSH/git, or shared cPanel hosting with a Terminal that has git).
# This does not set up a new install — see docs/cpanel-deployment.md or
# docs/vps-deployment.md for that.
#
# Usage:
#   ./scripts/deploy.sh                 # fast-forward the current branch to its remote
#   ./scripts/deploy.sh origin/dev      # check out/fast-forward to a specific branch
#   ./scripts/deploy.sh v1.3.4          # deploy a specific tag
#
# What it does, in order, stopping immediately (via `set -e`) on the first
# failure — nothing after the failed step runs:
#
#   1. Record the current commit (printed on failure, so you know what to
#      manually `git checkout` back to)
#   2. Enable maintenance mode (php artisan down), with a bypass secret so
#      you can still load the site yourself while it's up for everyone else
#   3. Back up the database with mysqldump (MySQL only — see below)
#   4. Fetch + fast-forward code to the target ref (refuses to do anything
#      but a fast-forward — never a merge/rebase that could silently leave
#      the working tree in an unexpected state)
#   5. composer install --no-dev --optimize-autoloader
#   6. php artisan migrate --force
#   7. Rebuild config/route/view caches
#   8. Terminate Horizon workers if QUEUE_CONNECTION=redis, so Supervisor
#      restarts them with the freshly-deployed code (a running worker process
#      keeps the OLD code loaded in memory otherwise). No-op on shared
#      hosting's cron-driven queue — a fresh PHP process every minute picks
#      up new code automatically, nothing to restart.
#   9. Hit /api/v2/health and confirm it actually reports "ok" before
#      declaring success
#  10. Disable maintenance mode
#
# On failure, the site is left in maintenance mode on purpose — it's better
# to show the "we'll be right back" page than a half-updated app. This
# script does NOT attempt to automatically undo a failed step; it prints
# exactly what it backed up / what commit you were on, and you decide
# whether to fix forward or roll back manually:
#
#   git checkout <the printed previous commit>
#   composer install --no-dev --optimize-autoloader
#   php artisan config:clear
#   gunzip -c <the printed backup file> | mysql -h HOST -u USER -p DATABASE   # only if a migration actually ran
#   php artisan up

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

PHP_BIN="${PHP_BIN:-php}"
TARGET_REF="${1:-}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$APP_DIR/storage/app/deploy-backups"
DB_BACKUP_FILE="$BACKUP_DIR/db-$TIMESTAMP.sql.gz"
PREVIOUS_COMMIT=""

log() { printf '\n\033[1;34m==>\033[0m %s\n' "$1"; }
die() { printf '\n\033[1;31mERROR:\033[0m %s\n' "$1" >&2; exit 1; }

on_exit() {
  local code=$?
  if [ "$code" -ne 0 ]; then
    echo
    echo "────────────────────────────────────────────────────────────────"
    echo " Deploy stopped (exit code $code)."
    echo " The site is still in maintenance mode — it is NOT serving a"
    echo " half-updated app to visitors."
    [ -n "$PREVIOUS_COMMIT" ] && echo " Previous commit: $PREVIOUS_COMMIT"
    [ -f "$DB_BACKUP_FILE" ] && echo " DB backup taken before migrating: $DB_BACKUP_FILE"
    echo " Fix the problem above, then either re-run this script, or roll"
    echo " back manually and run 'php artisan up' — see the comment block"
    echo " at the top of this script for the exact rollback commands."
    echo "────────────────────────────────────────────────────────────────"
  fi
}
trap on_exit EXIT

[ -f "$APP_DIR/artisan" ] || die "artisan not found at $APP_DIR."
[ -f "$APP_DIR/.env" ] || die ".env not found — this script updates an EXISTING install. First-time setup: docs/cpanel-deployment.md or docs/vps-deployment.md."
command -v git >/dev/null 2>&1 || die "git not found on PATH."
command -v composer >/dev/null 2>&1 || die "composer not found on PATH."

env_get() { grep -E "^${1}=" "$APP_DIR/.env" | head -n1 | cut -d '=' -f2- | tr -d '"'; }

mkdir -p "$BACKUP_DIR"

log "Recording current commit"
PREVIOUS_COMMIT="$(git rev-parse HEAD)"
echo "   $PREVIOUS_COMMIT"

log "Enabling maintenance mode"
$PHP_BIN artisan down --secret="deploy-$TIMESTAMP" --retry=60
echo "   Bypass while updating: $(env_get APP_URL)/deploy-$TIMESTAMP"

DB_CONNECTION="$(env_get DB_CONNECTION)"
if [ "$DB_CONNECTION" = "mysql" ]; then
  log "Backing up database -> $DB_BACKUP_FILE"
  DB_HOST="$(env_get DB_HOST)"
  DB_PORT="$(env_get DB_PORT)"
  DB_DATABASE="$(env_get DB_DATABASE)"
  DB_USERNAME="$(env_get DB_USERNAME)"
  DB_PASSWORD="$(env_get DB_PASSWORD)"
  MYSQL_PWD="$DB_PASSWORD" mysqldump -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" \
    --single-transaction --quick "$DB_DATABASE" | gzip > "$DB_BACKUP_FILE"
else
  log "Skipping automatic DB backup — DB_CONNECTION=$DB_CONNECTION, this script only knows mysqldump. Back it up yourself first if that matters to you."
fi

log "Fetching latest code"
git fetch --all --tags

if [ -n "$TARGET_REF" ]; then
  log "Checking out $TARGET_REF"
  git checkout "$TARGET_REF"
else
  CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
  [ "$CURRENT_BRANCH" != "HEAD" ] || die "Detached HEAD and no ref given — run as: $0 <branch-or-tag>"
  log "Fast-forwarding $CURRENT_BRANCH to origin/$CURRENT_BRANCH"
  git merge --ff-only "origin/$CURRENT_BRANCH" \
    || die "$CURRENT_BRANCH has diverged from origin/$CURRENT_BRANCH — resolve manually. This script only ever fast-forwards, on purpose, so it can never silently create a merge commit or leave conflict markers in a live deploy."
fi

log "Installing Composer dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

log "Running database migrations"
$PHP_BIN artisan migrate --force

log "Rebuilding config/route/view caches"
$PHP_BIN artisan config:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

QUEUE_CONNECTION="$(env_get QUEUE_CONNECTION)"
if [ "$QUEUE_CONNECTION" = "redis" ]; then
  log "Terminating Horizon workers so Supervisor restarts them with the new code"
  $PHP_BIN artisan horizon:terminate || true
else
  log "Queue is $QUEUE_CONNECTION (cron-driven) — next scheduled run picks up the new code automatically, nothing to restart"
fi

log "Checking /api/v2/health"
HEALTH_URL="$(env_get APP_URL)/api/v2/health"
HEALTH="$(curl -fsS "$HEALTH_URL")" || die "Health check request to $HEALTH_URL failed."
echo "   $HEALTH"
echo "$HEALTH" | grep -q '"status":"ok"' || die "Health check did not report status ok."
echo "$HEALTH" | grep -q '"db":"connected"' || die "Health check reports DB not connected."
echo "$HEALTH" | grep -q '"cache":"connected"' || die "Health check reports cache not connected."
# version_verified:false means the VERSION file doesn't actually match a
# real tagged commit at or before HEAD — a strong signal something is
# wrong with what just got deployed (a stray hand-edit, a checkout that
# landed on the wrong ref, ...). version_verified:null just means git
# isn't checkable here and is not itself a failure — written as an `if`,
# not a `grep ... && die`, so a non-matching (good) grep result can never
# trip `set -e` on this line.
if echo "$HEALTH" | grep -q '"version_verified":false'; then
  die "Health check reports version_verified:false — the deployed VERSION does not match any tagged commit at or before HEAD. Investigate before trusting this deploy: php artisan version:verify"
fi

log "Disabling maintenance mode"
$PHP_BIN artisan up

log "Deploy complete — now on $(git rev-parse --short HEAD)."
