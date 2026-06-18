# GitHub/Linear → Discord Relay — Rebuild Requirements

**Date:** 2026-06-18
**Status:** Approved (ready for planning)

## Problem & Goal

The current app is a Laravel 10 webhook relay that forwards GitHub and Linear events to
Discord, rewriting source-platform user mentions into Discord mentions. All configuration
(user mappings, destination webhooks) lives in PHP config files and `.env`, so changing
routing or who-maps-to-whom requires a code edit and redeploy. There is no UI and no
inbound request authentication.

Rebuild it as a fresh Laravel 13 app with a lightweight React + Inertia admin GUI that lets
an operator manage Discord user mappings and configure which destination Discord webhook
each event is routed to — without touching code.

## Users & Access

- Single operator/admin. One seeded admin account (email + password from `.env` / an artisan
  command). Inertia login page. **No** registration, roles, or password-reset UI.

## Scope

### In scope
- Fresh Laravel 13 scaffold (in this repo) using the official React starter kit
  (Inertia 2 + React + TypeScript + Tailwind), trimmed to single-admin login.
- DB-backed configuration replacing `config/user_mapping.php` and the `DISCORD_WEBHOOK_URL_*`
  env vars.
- Port existing relay behavior for GitHub and Linear (see "Behavior to preserve").
- Most-specific-match destination routing with org/repo (GitHub) and team/project (Linear)
  granularity plus a global default per source.
- Optional inbound signature verification per source.
- Admin GUI: Members, Routes, Settings, Login.
- Seeder migrating current mapping data and the two existing destination webhooks.

### Outside this iteration
- Delivery/audit log and retry UI (explicitly deferred — relay continues writing to the
  `webhooks` log channel only).
- Multi-user accounts, roles, invitations.
- Event-type-based routing or per-destination event filtering (routing keys off source +
  org/repo/team/project only).
- Sources other than GitHub and Linear.

## Behavior to Preserve (port faithfully)

Inbound endpoints keep their current paths so existing GitHub/Linear webhook configs keep
working unchanged:
- `POST /github/webhook`
- `POST /linear/webhook`

**GitHub relay:** recursively rewrite `@username` → `<@discord_id>` across every string in
the payload, then forward the (modified) raw payload to the resolved Discord webhook URL with
`/github` appended, re-proxying the required `X-GitHub-*` / `Accept` / `Content-Type` /
`User-Agent` headers. Failures to reach Discord are logged, not surfaced to the sender.

**Linear relay:** build a Discord embed from scratch per event type (`Issue`, `Comment`,
`Project`, `ProjectUpdate`), mapping Linear actor/assignee UUIDs → Discord mentions; include
color-by-action and priority emoji as today. Preserve:
- 24h cache-based deduplication keyed on `type|action|data.id|createdAt`.
- The skip filter currently suppressing `issue → update` events (configurable).
- Verbose logging to the dedicated `webhooks` daily log channel.

## Routing Model

Most-specific-match (no ordered rule engine). Resolver selects the active route whose scope
most specifically matches the inbound event:

- **GitHub:** `repo` (`owner/repo`) → `org` (`owner`) → `global` default.
- **Linear:** `project` → `team` → `global` default.

If nothing matches and no global default exists for that source, log and drop the event.
GitHub destinations are Discord webhook URLs with `/github` appended at send time; Linear
destinations are plain Discord webhook URLs.

## Security

Optional per-source signature verification:
- If a signing secret is configured for the source, enforce it and reject invalid/unsigned
  requests with `401` before any relay work. GitHub: validate `X-Hub-Signature-256` (HMAC-SHA256
  of the raw body). Linear: validate the `Linear-Signature` header.
- If no secret is configured for the source, accept the request (current behavior).
- Secrets are stored encrypted and editable in the Settings screen.

## Data Model (intent, not final schema)

- `users` — single admin, seeded.
- `members` — a person/Discord user: display `name`, `discord_user_id` (Discord snowflake).
- `member_identities` — `member_id`, `source` (`github` | `linear`), `external_id`
  (GitHub username or Linear UUID); a member may have several (one current person maps from
  two GitHub usernames).
- `webhook_routes` — `source`, `scope`, `match_value` (nullable for `global`),
  `discord_webhook_url`, `label`, `is_active`.
- `settings` — encrypted per-source signing secrets (and the configurable skip-filter).

## Admin GUI

- **Login** — single admin.
- **Members** — CRUD; each member edits its Discord ID and its GitHub/Linear identities.
- **Routes** — CRUD grouped by source, including the global defaults; shows scope, match
  value, destination, active toggle.
- **Settings** — signing secrets per source; Linear skip-filter.

## Success Criteria

- An operator can add a teammate's GitHub username + Linear UUID + Discord ID and route a new
  repo/org/team to a chosen Discord channel entirely through the GUI, no redeploy.
- Existing GitHub and Linear webhooks continue to deliver to Discord with identical formatting
  after the rebuild, using seeded data.
- A forged request to a source with a configured secret is rejected with `401`; a source with
  no secret behaves as today.
- Feature tests cover: route precedence per source, signature enforce-vs-skip, GitHub mention
  rewrite + header forwarding, Linear embed transform + dedup + skip filter.

## Migration / Seeding

- Seed `members` + `member_identities` from current `config/user_mapping.php`
  (`github` and `linear` maps).
- Seed two `global` `webhook_routes` from the current `DISCORD_WEBHOOK_URL_1` (GitHub) and
  `DISCORD_WEBHOOK_URL_2` (Linear).

## Outstanding Questions

- **Linear match-value key:** default to matching `team`/`project` on the **stable Linear ID**
  (not human name) for robustness against renames; revisit if the GUI ergonomics of pasting
  IDs prove painful (could store name alongside ID for display).

## Assumptions

- PHP 8.4 / Laravel 13 / Composer available in the target environment (verified locally:
  PHP 8.4.21, Composer 2.9.5).
- SQLite remains the default datastore, consistent with the current app.
