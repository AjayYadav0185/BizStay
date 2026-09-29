#!/bin/sh
# BizStay entrypoint for Render (SQLite on a persistent disk).
#
# Behaviour contract (safe to run on EVERY boot — never wipes data):
#   1. Point SQLite at the persistent disk (/var/www/storage/database).
#      On Render this path is backed by the `bizstay-data` disk declared in
#      render.yaml, so the database survives deploys and restarts.
#   2. Derive APP_URL from RENDER_EXTERNAL_URL when not explicitly set
#      (@vite emits absolute asset URLs from APP_URL).
#   3. Generate an ephemeral APP_KEY only as a last resort (set a persistent
#      APP_KEY in the Render dashboard — encrypted cookies/sessions depend on it).
#   4. `migrate --force` (never db:wipe — that would delete live tenant data
#      on every deploy), then seed ONLY when the users table is empty
#      (first boot → demo logins exist; later boots → real data untouched).
#   5. Cache config/routes/views for production, link storage, run the
#      scheduler + queue worker in the background, serve on $PORT.
set -e

APP_DIR="/var/www"
cd "$APP_DIR"

# ---- Required runtime directories & permissions -----------------------------
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/storage/framework/cache"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/app/public"
mkdir -p "$APP_DIR/storage/database"
mkdir -p "$APP_DIR/bootstrap/cache"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# ---- Database (SQLite on the Render persistent disk) -------------------------
# Render mounts the `bizstay-data` disk at /var/www/storage (see render.yaml).
# DB_DATABASE defaults to the on-disk file so SQLite is persistent by default.
# Override DB_DATABASE in the dashboard only if you know what you are doing.
DB_CONN="${DB_CONNECTION:-sqlite}"
if [ "$DB_CONN" = "sqlite" ]; then
    DB_DATABASE="${DB_DATABASE:-$APP_DIR/storage/database/database.sqlite}"
    export DB_CONNECTION=sqlite
    export DB_DATABASE
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
fi

# ---- APP_URL ------------------------------------------------------------------
# @vite generates ABSOLUTE asset URLs from APP_URL (default: http://localhost).
# Without the correct URL the browser tries to load CSS/JS from localhost and
# they appear "missing". Render injects RENDER_EXTERNAL_URL automatically, so
# use it whenever APP_URL is not explicitly set in the dashboard.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
    echo "APP_URL not set - using RENDER_EXTERNAL_URL: $APP_URL"
fi

# ---- APP_KEY -----------------------------------------------------------------
# Prefer setting APP_KEY in the Render dashboard. If it is missing, generate an
# ephemeral one so the app still boots (it changes on restart, so encrypted
# cookies/sessions won't survive a redeploy until a persistent key is set).
if [ -z "$APP_KEY" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    echo "WARNING: APP_KEY was not set; generated an ephemeral key."
    echo "         Set APP_KEY in the Render dashboard so it survives restarts."
fi

echo "Database connection: $DB_CONNECTION (${DB_DATABASE:-from env})"
echo "Queue connection:    ${QUEUE_CONNECTION:-database}"

# ---- Bootstrap ---------------------------------------------------------------
php artisan config:clear  >/dev/null 2>&1 || true
php artisan view:clear    >/dev/null 2>&1 || true

# Rebuild the package manifest against the --no-dev vendor in the image
# (drops stale dev-provider entries like laravel/pail if they linger).
rm -f "$APP_DIR/bootstrap/cache/packages.php" "$APP_DIR/bootstrap/cache/services.php"
php artisan package:discover --ansi >/dev/null 2>&1 || true

# Safe migrations: NEVER db:wipe here (that deletes live data on every boot).
echo "Running migrations..."
php artisan migrate --force

# Seed only on a fresh database (first boot). DemoSeeder + DummyDataSeeder are
# idempotent, but skipping the check entirely keeps every restart fast and
# guarantees real tenant data is never touched by seed logic.
USERS_COUNT="$(php artisan tinker --execute="echo App\Models\User::query()->count();" 2>/dev/null | tr -d '\r\n ' || echo "0")"
case "$USERS_COUNT" in
    ''|*[!0-9]*) USERS_COUNT="0" ;;
esac
if [ "$USERS_COUNT" = "0" ]; then
    echo "Empty database detected - seeding demo data..."
    php artisan db:seed --force
else
    echo "Database already has $USERS_COUNT user(s) - skipping seed."
fi

php artisan storage:link >/dev/null 2>&1 || true

# ---- Optimise ----------------------------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---- Background scheduler + queue worker --------------------------------------
# Render has no cron; the scheduler loop covers billing/overdue/late-fee runs
# defined in routes/console.php, and the queue worker drains database jobs.
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
php artisan queue:work --sleep=3 --tries=3 --timeout=120 \
    >> "$APP_DIR/storage/logs/queue-worker.log" 2>&1 &
php artisan schedule:work --sleep-time=60 \
    >> "$APP_DIR/storage/logs/schedule-worker.log" 2>&1 &

# ---- Web server (Render injects PORT; default to 10000) -----------------------
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
