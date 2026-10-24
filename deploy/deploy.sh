#!/usr/bin/env bash
#
# متجر برو — deploy
#
# Every step a release needs, in order, in one command:
#
#   sudo bash deploy/deploy.sh            # deploy origin/main
#   sudo bash deploy/deploy.sh --demo     # …and rebuild the theme showrooms
#
# Written after a release that pulled the new code and stopped there: the
# pages came out with the new HTML but the OLD stylesheet, because the CSS is
# built from the templates by Vite and public/build/ is not in git. Every new
# class was missing — a search icon drawn the full width of the header, an
# unstyled buy button — on every store at once. A `git pull` alone is never a
# deploy here; this script is.
#
# Safe to run again: each step is idempotent, and it stops at the first one
# that fails (nothing after a failed build touches the live site).
#
# Settings, all optional, as environment variables:
#   APP_DIR    the checkout                   (default: the folder above this one)
#   APP_USER   who php-fpm and workers run as (default: www-data)
#   REPO_USER  who owns the checkout          (default: the owner of .git)
#   BRANCH     what to deploy                 (default: main)
#   PHP_FPM    the php-fpm unit to reload     (default: php8.3-fpm)

set -Eeuo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
APP_USER="${APP_USER:-www-data}"
BRANCH="${BRANCH:-main}"
PHP_FPM="${PHP_FPM:-php8.3-fpm}"
REBUILD_DEMO=0

for arg in "$@"; do
    case "$arg" in
        --demo) REBUILD_DEMO=1 ;;
        -h|--help) sed -n '2,25p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

step() { printf '\n\033[1;32m▸ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m! %s\033[0m\n' "$*" >&2; }
trap 'printf "\n\033[1;31m✗ Deploy stopped at line %s. The site is still on the previous release for every step that had not run yet.\033[0m\n" "$LINENO" >&2' ERR

cd "$APP_DIR"

[ -d .git ] || { echo "$APP_DIR is not a git checkout." >&2; exit 1; }
[ -f .env ] || { echo ".env is missing in $APP_DIR — refusing to deploy." >&2; exit 1; }

# Two users, each doing only its own kind of work:
#
#   REPO_USER — whoever owns the checkout. Runs git, composer, npm and the
#   build, which write code and vendor/node_modules/public/build. git refuses
#   outright ("dubious ownership") to work in a repository its user does not
#   own; the first version of this script ran it as www-data and stopped there.
#
#   APP_USER — the php-fpm user. Runs artisan, which writes storage/ and
#   bootstrap/cache/. A file created there by anyone else is one the web
#   request cannot read back, and the error shows up hours later somewhere
#   unrelated.
REPO_USER="${REPO_USER:-$(stat -c %U .git)}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run it with sudo: it acts as $REPO_USER for the code and as $APP_USER for the app." >&2
    exit 1
fi

as_user() {
    local user="$1"; shift
    if [ "$user" = root ]; then "$@"; else sudo -u "$user" -H -- "$@"; fi
}
as_repo() { as_user "$REPO_USER" "$@"; }
as_app() { as_user "$APP_USER" "$@"; }
artisan() { as_app php artisan "$@"; }

echo "Checkout owned by $REPO_USER; app runs as $APP_USER."

# Said up front rather than discovered at step 4 with a stack trace: the app
# user has to be able to write these two, and nothing else.
for dir in storage bootstrap/cache; do
    if ! as_app test -w "$dir"; then
        echo "$APP_USER cannot write $dir. Fix it once with:" >&2
        echo "  sudo chown -R $APP_USER:$APP_USER $APP_DIR/storage $APP_DIR/bootstrap/cache" >&2
        exit 1
    fi
done

for tool in git php composer npm; do
    command -v "$tool" >/dev/null || { echo "$tool is not installed." >&2; exit 1; }
done

# ── 1. Code ──────────────────────────────────────────────────────────────
step "Fetching $BRANCH"

# Fast-forward only, and never over local edits: a hotfix typed straight onto
# the server would otherwise be silently merged into, or lost under, the
# release.
#
# The status is read into a variable first, so a git that fails for any other
# reason stops the script with git's own error — not, as before, with a
# misleading "you have uncommitted changes".
# Untracked files (`??`) are ignored: they are not overwritten by a pull, and
# a server always has a few (backups, a stray log).
dirty="$(as_repo git status --porcelain --untracked-files=no)"
if [ -n "$dirty" ]; then
    echo "The checkout has uncommitted changes — commit or discard them first:" >&2
    printf '%s\n' "$dirty" >&2
    exit 1
fi

OLD_REV="$(as_repo git rev-parse HEAD)"
as_repo git fetch --quiet origin "$BRANCH"
as_repo git checkout --quiet "$BRANCH"
as_repo git merge --ff-only --quiet "origin/$BRANCH"
NEW_REV="$(as_repo git rev-parse HEAD)"

if [ "$OLD_REV" = "$NEW_REV" ]; then
    echo "Already at $(as_repo git log -1 --format='%h %s'). Rebuilding anyway."
else
    as_repo git log --oneline "$OLD_REV..$NEW_REV" | sed 's/^/  /'
fi

changed() { [ "$OLD_REV" != "$NEW_REV" ] && as_repo git diff --name-only "$OLD_REV" "$NEW_REV" -- "$@" | grep -q .; }

# ── 2. PHP dependencies ──────────────────────────────────────────────────
step "Installing PHP packages"

# --no-scripts, then package discovery as the app user: composer's own
# post-install hook would run artisan as the checkout owner and write
# bootstrap/cache/ under the wrong user.
as_repo composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts
artisan package:discover --ansi

# ── 3. Front-end build ───────────────────────────────────────────────────
step "Building CSS and JS"

# A leftover `public/hot` makes Laravel point every page at a Vite dev server
# that is not running — the site loses all its styles. It only exists on a
# development machine, so on a server it is always a mistake.
rm -f public/hot

# A cache folder of the checkout owner's own: their home may not be writable
# (www-data's is /var/www, owned by root), and npm fails outright when it
# cannot write its default cache.
NPM_CACHE="${NPM_CACHE:-/tmp/matgarpro-npm-cache-$REPO_USER}"
npm_() { as_repo env "npm_config_cache=$NPM_CACHE" "$@"; }

npm_ npm ci --no-audit --no-fund

# Built beside the live folder and swapped in at the end, so the site never
# serves a half-written build. The previous release's hashed files are copied
# across first: a page rendered a second before the swap still asks for them,
# and browsers cache /build/ for a year (deploy/Caddyfile).
rm -rf public/build.next
npm_ npx vite build --outDir public/build.next --emptyOutDir
[ -f public/build.next/manifest.json ] || { echo "The build produced no manifest.json." >&2; exit 1; }
if [ -d public/build/assets ]; then
    as_repo cp -n public/build/assets/* public/build.next/assets/ 2>/dev/null || true
fi
rm -rf public/build.prev
if [ -d public/build ]; then mv public/build public/build.prev; fi
mv public/build.next public/build
rm -rf public/build.prev

# ── 4. Database ──────────────────────────────────────────────────────────
step "Migrating the database"
artisan migrate --force

# ── 5. Caches ────────────────────────────────────────────────────────────
step "Refreshing caches"
# The link lives in public/, which belongs to the checkout owner.
[ -L public/storage ] || as_repo php artisan storage:link
artisan optimize:clear
artisan optimize

# ── 6. Showrooms ─────────────────────────────────────────────────────────
# Rebuilt when the demo catalogue or its photos changed in this release, or
# when asked. They are rebuilt from scratch, so this is never run by default.
if [ "$REBUILD_DEMO" -eq 1 ] || changed config/demo.php resources/demo app/Services/Demo app/Console/Commands/SeedDemoStores.php; then
    step "Rebuilding the theme showrooms"
    artisan demo:stores
fi

# ── 7. Background processes ──────────────────────────────────────────────
step "Restarting workers"

# Long-running PHP keeps the code it booted with: workers have to be told to
# restart or they keep running the previous release.
artisan queue:restart

if [ "$(id -u)" -eq 0 ] && command -v systemctl >/dev/null; then
    # Install or refresh the unit files that ship in deploy/, so a release
    # that adds one (like the AI worker) is actually started by deploying it.
    units_changed=0
    for unit in deploy/*.service deploy/*.timer; do
        target="/etc/systemd/system/$(basename "$unit")"
        if ! cmp -s "$unit" "$target"; then
            cp "$unit" "$target"
            echo "  installed $(basename "$unit")"
            units_changed=1
        fi
    done
    if [ "$units_changed" -eq 1 ]; then systemctl daemon-reload; fi

    systemctl enable --now --quiet matgarpro-worker matgarpro-ai-worker matgarpro-scheduler.timer
    systemctl restart matgarpro-worker matgarpro-ai-worker

    # OPcache keeps compiled PHP in memory; a reload makes php-fpm read the
    # new files without dropping requests in flight.
    if systemctl list-unit-files "$PHP_FPM.service" >/dev/null 2>&1; then
        systemctl reload "$PHP_FPM"
    else
        warn "$PHP_FPM not found — reload php-fpm yourself so OPcache picks up the new code."
    fi
else
    warn "Not root: install/restart the systemd units and reload php-fpm yourself (see deploy/*.service)."
fi

# ── 8. Check ─────────────────────────────────────────────────────────────
# The exact failure this script exists for: a page whose stylesheet is not the
# one that was just built. Fetch the home page and the CSS it links to.
step "Checking the live site"
APP_URL="$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"')"

if [ -n "$APP_URL" ] && command -v curl >/dev/null; then
    html="$(curl -fsSL --max-time 20 "$APP_URL/")" || { echo "The home page did not load: $APP_URL" >&2; exit 1; }
    css="$(printf '%s' "$html" | grep -oE '/build/assets/[^"]+\.css' | head -1)"
    if [ -z "$css" ]; then
        echo "The home page links no built stylesheet — check public/hot and APP_ENV." >&2
        exit 1
    fi
    if ! grep -q "\"${css#/build/}\"" public/build/manifest.json; then
        echo "The page links $css, which is not in this build — a cache is serving an old page." >&2
        exit 1
    fi
    curl -fsS --max-time 20 -o /dev/null "$APP_URL$css" || { echo "$css does not load." >&2; exit 1; }
    echo "  $APP_URL is up and serving $css"
else
    warn "APP_URL is not set (or curl is missing) — check the site by hand."
fi

trap - ERR
printf '\n\033[1;32m✓ Deployed %s\033[0m\n' "$(as_repo git log -1 --format='%h %s')"
