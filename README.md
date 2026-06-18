# GitHub / Linear → Discord Relay

A Laravel 13 + Inertia (React) application that relays GitHub and Linear
webhooks to Discord, rewriting source-platform mentions into Discord mentions.
All configuration — member mappings, destination routing, and signing secrets —
lives in the database and is managed through a single-admin web GUI, so onboarding
a teammate or routing a new repo/org/team needs no code change or redeploy.

## Features

- **Member mappings** — map a person's GitHub username(s) and Linear UUID to a
  single Discord user, managed in the GUI.
- **Most-specific-match routing** — pick the destination Discord webhook by
  scope: GitHub `repo → org → global`, Linear `project → team → global`.
- **Faithful relay behavior**
  - GitHub: recursively rewrites `@username` → `<@discordId>`, forwards the raw
    payload to the destination URL with `/github` appended and the required
    `X-GitHub-*` headers re-proxied.
  - Linear: builds per-type Discord embeds (Issue / Comment / Project /
    ProjectUpdate), with 24h deduplication, color-by-action, priority emoji, and
    a configurable skip filter.
- **Optional inbound signature verification** — when a per-source signing secret
  is configured, requests are verified (HMAC-SHA256 of the raw body) and forged
  requests are rejected with `401`. With no secret configured, requests are
  accepted (original behavior).

## Inbound endpoints

Preserved from the original app so existing webhook configs keep working:

- `POST /github/webhook`
- `POST /linear/webhook`

These are registered outside the `web` middleware group (no CSRF/session).

## Requirements

- PHP 8.3+
- Composer
- Node 20+ and [pnpm](https://pnpm.io/)
- SQLite (default) or any Laravel-supported database

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Set the admin credentials and (optionally) the two default destination
# webhooks in .env: ADMIN_EMAIL, ADMIN_PASSWORD, DISCORD_WEBHOOK_URL_1/2

php artisan migrate --seed   # seeds the admin + existing mappings/routes
pnpm install
pnpm run build               # or `pnpm run dev` during development
php artisan serve
```

Log in at `/login` with the seeded admin credentials, then manage **Members**,
**Routes**, and **Relay Settings** from the sidebar.

### Resetting the admin password

```bash
php artisan app:set-admin-password
```

## Configuration model

| Concern                | Where it lives                                   |
| ---------------------- | ------------------------------------------------ |
| Members & identities   | `members` / `member_identities` tables (GUI)     |
| Destination routing    | `webhook_routes` table (GUI)                     |
| Signing secrets        | `settings` table, encrypted (Relay Settings GUI) |
| Linear skip filter     | `settings` table (Relay Settings GUI)            |

The `RelayMappingSeeder` migrates the original `config/user_mapping.php` data and
the two `DISCORD_WEBHOOK_URL_*` destinations into the database on first seed.

## Testing

```bash
php artisan test            # PHPUnit (unit + feature)
./vendor/bin/pint           # PHP formatting
pnpm run lint:check         # ESLint
pnpm run types:check        # tsc
```
