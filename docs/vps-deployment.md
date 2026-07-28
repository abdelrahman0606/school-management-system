# Deploying to a VPS (with cPanel/WHM or plain SSH)

This covers the case `docs/cpanel-deployment.md` doesn't: full root/SSH
access, where you can install Redis, run Horizon under Supervisor, and
automate updates with real git + a deploy script instead of a File Manager
zip upload. If you're on budget shared hosting with no SSH, use
`docs/cpanel-deployment.md` instead — most of the first-time setup steps
below (`.env`, migrations, `storage:link`, PHP limits) are identical there
too, just without shell access to run them yourself.

## First-time setup

1. **Web server + PHP 8.3** — Nginx or Apache, with PHP-FPM 8.3 and the same
   extension list as the shared-hosting doc (`mbstring`, `pdo_mysql`, `gd`,
   `zip`, `curl`, `bcmath`, etc.).
2. **Point the document root at `public/`, not the app root.** Unlike
   shared hosting's addon-domain restrictions, a VPS vhost can always be
   configured correctly the first time — set `root` (Nginx) or
   `DocumentRoot` (Apache) to the absolute path of the app's `public/`
   directory. Never point it at the app root itself; `app/`, `.env`,
   `vendor/`, etc. must not be web-reachable.
3. **Clone the repo** and install dependencies:
   ```bash
   git clone <your-repo-url> /var/www/school-management-backend
   cd /var/www/school-management-backend
   composer install --no-dev --optimize-autoloader
   ```
4. **`.env`** — copy `.env.cpanel.example` as a starting point (database
   cache/queue/sessions, no Redis) if you want the simpler cron-driven setup
   described in the shared-hosting doc, or set `SESSION_DRIVER=redis`,
   `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis` if you're installing Redis
   (see below) — either is fully supported, pick based on whether you want
   Horizon's dashboard/throughput or the simpler no-extra-services setup.
   Then `php artisan key:generate`.
5. **Database, migrations, storage symlink** — same as the shared-hosting
   doc:
   ```bash
   php artisan migrate --force
   php artisan storage:link
   ```
6. **Cron** — same scheduler line either way:
   ```cron
   * * * * * php /var/www/school-management-backend/artisan schedule:run >> /dev/null 2>&1
   ```
   Add the queue-worker cron line too **unless** you're running Horizon
   under Supervisor instead (see below) — don't run both, they'll double-process jobs.

## Optional: Redis + Horizon under Supervisor

Only worth it once cron-based `queue:work --stop-when-empty` (every minute)
isn't fast enough — e.g. a large school generating ID card batches or
sending SMS batches frequently and wanting near-real-time processing instead
of up-to-a-minute latency.

```bash
apt install redis-server   # or your distro's equivalent
```

Set `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`,
and the `REDIS_*` vars in `.env`. `predis/predis` is already a Composer
dependency, so `REDIS_CLIENT=predis` works even without the `redis` PECL
extension compiled — set `REDIS_CLIENT=phpredis` instead if you do have it
(faster).

Supervisor config (`/etc/supervisor/conf.d/horizon.conf`):

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/school-management-backend/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/school-management-backend/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start horizon:*
```

Horizon's dashboard is then at `/horizon` (admin-only, gated by
`app/Providers/HorizonServiceProvider`'s `viewHorizon` gate — add real
admin emails there before relying on it, the shipped gate allows nobody).

## Updating an existing installation

**This is the part that goes wrong if done carelessly** — "paste the new
version over the old one" sounds simple, but naively overwriting files on a
live app risks several real failure modes:

- **Losing uploaded files.** `storage/app/public` and `storage/app/private`
  (student/staff photos, ID card batches, admission photos, website media,
  generated PDFs) are **not** in git. If your update process is "delete the
  old folder, extract a fresh zip of the repo," you will delete every file a
  user has ever uploaded. `git pull` doesn't have this problem — it only
  touches files git tracks — but a wholesale directory replace does.
- **Overwriting `.env`.** Same category of mistake — `.env` holds this
  install's actual secrets/config and is deliberately not in git
  (`.env.example`/`.env.cpanel.example` are templates, not the real file).
  Overwriting it with a template wipes your DB password, `APP_KEY`, mail
  credentials, everything.
- **Serving a torn build mid-update.** File writes during a plain `git
  pull` or zip extraction are not atomic across the whole codebase — a
  request that lands mid-update can load some files from the old version and
  some from the new one, which is a real source of "class not found" or
  subtly wrong-behavior errors that are hard to reproduce after the fact.
  Wrapping the update in Laravel's maintenance mode (`php artisan down` /
  `up`) closes this window — visitors see a "be right back" page instead of
  a half-updated app, for however many seconds the update actually takes.
- **Stale caches.** `php artisan config:cache`/`route:cache`/`view:cache`
  (recommended for production) mean a plain code update, even a perfect one,
  silently keeps serving the OLD config/routes/views until you clear and
  rebuild those caches. This is the single most common "I deployed but
  nothing changed" report.
- **Schema drift.** New code can reference columns/tables that don't exist
  yet if migrations haven't run, and running migrations from the OLD
  code against the NEW database schema (i.e. the wrong order) is equally
  broken. The order is always: update code first, then migrate — migrations
  are themselves part of the new code.
- **Stale worker code.** If you're running Horizon under Supervisor, its
  worker processes are long-running PHP processes that already have the OLD
  application code loaded into memory — updating files on disk doesn't
  change what a currently-running process has loaded. They need to be
  explicitly restarted (`artisan horizon:terminate`, which Supervisor then
  respawns) to pick up new code. Shared hosting's cron-driven queue doesn't
  have this problem — every cron tick is a brand-new PHP process, so it
  always loads whatever is currently on disk.

### The safe order of operations

1. Note the current commit (`git rev-parse HEAD`) — your rollback point.
2. `php artisan down` — maintenance mode on.
3. Back up the database.
4. Update code — `git pull`/`git checkout`, never a directory replace.
5. `composer install --no-dev --optimize-autoloader`.
6. `php artisan migrate --force`.
7. `php artisan config:clear && config:cache && route:cache && view:cache`.
8. Restart Horizon if you're running it (`artisan horizon:terminate`) — skip
   if you're on the cron-driven queue, there's nothing to restart.
9. Smoke-test — hit `/api/v2/health` and confirm it reports `"status":"ok"`.
10. `php artisan up` — maintenance mode off.

### `scripts/deploy.sh` automates exactly this sequence

```bash
./scripts/deploy.sh                 # fast-forward the current branch to its remote
./scripts/deploy.sh origin/dev      # deploy a specific branch
./scripts/deploy.sh v1.3.4          # deploy a specific tag
```

It stops on the first failure (via `set -e`) and leaves the site in
maintenance mode rather than trying to guess its way back to a working
state automatically — the script prints the previous commit and the backup
file path it took, and the exact manual rollback commands are in the
comment block at the top of the script itself. Read that comment block
before your first real run. Requires `git`, `composer`, and (for the
automatic DB backup step) `mysqldump` on `PATH`.

### On shared cPanel hosting without git

You genuinely cannot script this the same way without SSH/git — see
`docs/cpanel-deployment.md`'s own notes, but the short version: extract the
new code into a **separate, temporary folder** (never directly over the
live app), copy your live `.env` into it, `rsync`/copy `storage/app/public`
and `storage/app/private` over from the live install (never let a fresh
extract's empty `storage/` replace one with real uploads in it), then swap
the temporary folder in for the live one — ideally by renaming directories,
which is much closer to atomic than overwriting files one-by-one in place.
If your host's Terminal app does have `git` available, `scripts/deploy.sh`
works there identically to a VPS.
