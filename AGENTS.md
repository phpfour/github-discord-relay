# Repository Guidelines

## Project Structure & Modules
- Core PHP code lives in `app/` (notably `app/Http/Controllers/*WebhookController.php`).
- HTTP routes: `routes/web.php` exposes `POST /github/webhook` and `POST /linear/webhook`.
- Configuration: `config/services.php` (Discord webhooks), `config/user_mapping.php` (GitHub/Linear → Discord IDs).
- Frontend assets (if used): `resources/` with Vite config in `vite.config.js`.
- Public entrypoint: `public/`. Tests reside in `tests/Unit` and `tests/Feature`.

## Build, Test, and Run
- Install deps: `composer install`
- Setup env: `cp .env.example .env && php artisan key:generate`
- Run dev server: `php artisan serve` (defaults to `http://127.0.0.1:8000`)
- Vite dev (optional): `npm run dev`; production build: `npm run build`
- Run test suite: `php artisan test` or `./vendor/bin/phpunit`
- Example: `php artisan test --testsuite=Feature`

## Coding Style & Naming
- PHP: PSR-12 via Pint. Format with `./vendor/bin/pint`.
- Indentation: 4 spaces; LF line endings (`.editorconfig`).
- Namespaces: PSR-4 (`App\` for `app/`, `Tests\` for `tests/`).
- Controllers end with `Controller` (e.g., `GitHubWebhookController`). Routes use concise closures or controller actions.
- Config keys use snake_case; environment secrets live in `.env`.

## Testing Guidelines
- Framework: PHPUnit (Laravel 10). Place fast, isolated tests in `tests/Unit`, HTTP/flow tests in `tests/Feature`.
- File names end with `Test.php` and one assertion focus per case where possible.
- Prefer `php artisan test` with options like `--filter`, `--testsuite=Unit`.
- Suggested areas: payload transformation, user mapping, header forwarding, deduplication (Linear controller).

## Commit & PR Guidelines
- Commits: imperative mood, concise subject (e.g., “Add Linear dedup cache”). Group related changes.
- PRs: clear description, rationale, and scope. Link issues, include reproduction steps and screenshots/logs when relevant.
- Checks: CI green, tests updated/added, Pint run clean, no secrets in diffs.

## Security & Configuration Tips
- Never commit `.env`. Set `DISCORD_WEBHOOK_URL_1/2` in environment; update mappings in `config/user_mapping.php`.
- Validate inbound webhooks; required GitHub headers are proxied in `GitHubWebhookController`.
- Rotate/regenerate keys with `php artisan key:generate` for local only; do not change in production.
