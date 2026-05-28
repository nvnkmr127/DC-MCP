# Digicloudify Platform — Project Context & Rules

## What This Project Is

Digicloudify is a digital marketing and transformation agency based in Hyderabad, India. The company (legally registered as SAANETIX SOLUTIONS PRIVATE LIMITED) helps businesses move from offline operations to a strong online presence. The founder is Naveen Kumar Adicharla.

This document defines the rules, conventions, and context that every AI tool, every developer, and every prompt in this project must follow without exception. Treat this as the single source of truth for the entire build.

---

## The System We Are Building

We are building an internal operations platform for Digicloudify — a project management, communications, reporting, and daily briefing system purpose-built for a digital marketing agency. It is not a generic project management tool. Every decision, every data model, every UI element must reflect the reality of running a marketing agency: client campaigns, performance data, content calendars, SEO tasks, ad spend, and team coordination.

The platform integrates with external services through MCP (Model Context Protocol): Google Calendar, Gmail, Google Drive, Notion, Zoho Cliq, Meta Ads, and Make.com. These are not optional extras — they are core to how the system works.

---

## Tech Stack (Non-Negotiable)

**Backend:** Laravel 11 with Octane (Swoole driver)
**Database:** PostgreSQL 16 — no MySQL, no SQLite
**Frontend:** React with TypeScript via Inertia.js — no separate SPA, no Next.js, no Vue
**Styling:** Tailwind CSS v4
**Queue:** Redis with Laravel Horizon
**File storage:** S3-compatible (Minio locally, AWS S3 in production)
**Search:** Meilisearch
**Websockets:** Laravel Reverb
**AI:** Anthropic Claude API (claude-sonnet-4-5 model only)
**Local email:** Mailpit
**PDF generation:** Browsershot (headless Chrome)
**Testing:** Pest PHP

Do not introduce any other framework, ORM, or major library without explicit approval. If a package solves a problem cleanly, use it. If you are inventing something a package already handles well, stop and use the package.

---

## Architecture Rules

### Module Structure

All application code lives inside `app/Modules/`. Each module is self-contained with its own controllers, services, models, jobs, events, and listeners. There is no dumping of files into `app/Http/Controllers/` at the root level.

Modules:
- Auth
- ProjectManagement
- DailyBriefing
- Reporting
- DataViz
- TaskEngine
- MCP (with sub-folders: Adapters, Contracts, Jobs, Webhooks)
- Shared (for traits, base classes, shared services)

### Multi-tenancy

Every resource belongs to an organization. There are no exceptions. Every Eloquent model that holds business data must have an `organization_id` foreign key and must use the `OrganizationScope` global scope. The scope is applied automatically — no controller should ever manually filter by organization_id. If you find yourself writing `->where('organization_id', auth()->user()->organization_id)` in a controller, you are doing it wrong.

### UUID Primary Keys

All primary keys use UUID v4 (`id` column, type uuid). Auto-incrementing integers are only acceptable for lookup/pivot tables that will never be exposed in URLs or APIs.

### Soft Deletes

Projects, tasks, clients, users, and comments use soft deletes. Hard deletes are not permitted through the application interface. Background cleanup of soft-deleted records older than 90 days is handled by a scheduled command.

### API Response Format

All API responses follow this structure:

Success: `{ "data": {...}, "meta": {...} }`
Error: `{ "message": "...", "errors": {...} }`
Paginated: `{ "data": [...], "meta": { "current_page", "last_page", "per_page", "total" } }`

Never return raw Eloquent models from controllers. Always use API Resources.

### Events and Listeners

Business logic side effects are handled through Laravel events. A controller creates a project and fires `ProjectCreated`. The listener handles task spawning, Notion page creation, Zoho Cliq notification, and Google Calendar event creation. Controllers must not directly call adapters, notification services, or spawning logic. Keep controllers thin.

---

## Database Rules

### Naming Conventions

Tables: snake_case, plural (projects, task_logs, mcp_connections)
Columns: snake_case (organization_id, created_at, is_active)
Foreign keys: referenced_table_singular + _id (project_id, user_id, client_id)
Enum columns: always use a PostgreSQL enum type via Laravel's `enum()` migration method — never store raw strings that are secretly enums
Boolean columns: prefix with `is_` or `has_` (is_active, has_been_sent)
JSON columns: suffix with nothing — just name them for what they contain (settings, metadata, tags, permissions)

### Index Rules

Every foreign key column gets an index. Every status column that will be filtered gets an index. Compound indexes are created for the most common query patterns: (project_id, status), (organization_id, created_at), (user_id, status). Over-indexing is acceptable. Under-indexing is not.

### Migrations

Each migration does one thing. Do not bundle unrelated changes. Migration file names must be descriptive: `create_task_templates_table`, not `add_stuff_to_tasks`. Never modify a migration that has already been run in any environment. Always write a `down()` method.

### JSONB Columns

Use JSONB for: settings (org-level configuration), metadata (adapter-specific external IDs), tags (array of strings), permissions (role permission map), channels_sent (delivery receipts). Do not use JSONB as a way to avoid designing proper relational data. If you find yourself querying deeply into a JSONB column in a WHERE clause, the data belongs in a proper column.

---

## MCP Integration Rules

### Adapter Contract

Every MCP integration implements the `MCPAdapter` interface. No direct API calls outside of adapters. If a controller or service needs data from Google Calendar, it does not call the Google API — it calls the adapter.

### Sync vs Real-time

Sync jobs run on a schedule (Horizon). They are the source of truth for pulled data. Webhooks provide real-time updates on top of scheduled syncs. If a webhook fails, the next scheduled sync catches up. The system must never be in a broken state because a webhook failed.

### Token Security

OAuth access tokens and refresh tokens are always stored encrypted using Laravel's `encrypt()`. They are never logged, never returned in API responses, never stored in JSONB as plaintext. The `MCPConnection` model provides `getDecryptedAccessToken()` as the only way to access tokens.

### Sync Logging

Every sync operation — success or failure — is logged to `mcp_sync_logs`. This is non-negotiable. Operations teams need to know what synced, when, and what broke. Include: direction (pull/push), status, records affected, error message if failed, duration.

### Rate Limits

All adapters must handle rate limiting. The pattern is: make request → if 429 received → wait for the `Retry-After` header duration → retry once → if fails again → log failure and queue for next sync cycle. Never hammer an external API on failure.

---

## Authentication and Authorization Rules

### Role System

There are exactly six roles: `ceo`, `project_manager`, `analyst`, `marketer`, `developer`, `client`. These are stored as an enum in the database. There is no role-creation feature for end users. New roles require a code change and migration.

### Permission Checking

All permission checks go through the `hasPermission(string $permission)` method on the User model. Gates and Policies are registered from this. Never check roles directly in controllers or views — always check permissions. This means: not `if ($user->role === 'ceo')`, but `if ($user->hasPermission('manage_billing'))`.

### Client Role Isolation

Users with the `client` role can only see their own project data. They cannot see other clients, other projects, team member details, or internal task notes. The `OrganizationScope` handles organization isolation, but there is a second `ClientScope` for users with the client role that further restricts access to only their linked client's data.

### Session and Auth

Authentication uses Laravel Sanctum in SPA mode (cookie-based, not token-based). API tokens are not issued to the frontend. All CSRF protection is active. The `EnsureOrganizationContext` middleware runs on every authenticated route and sets the organization in the application context.

---

## Frontend Rules

### Component Architecture

Pages live in `resources/js/Pages/`. Shared components live in `resources/js/Components/`. Layouts live in `resources/js/Layouts/`. Each module has its own sub-folder: `Pages/Projects/`, `Pages/Reporting/`, etc.

### Inertia.js Patterns

Use `useForm()` from Inertia for all forms. Use `router.visit()` for programmatic navigation. Never use `axios` directly in page components — all data comes from Inertia page props or React Query for background refreshes. Pass only the data the page needs from the controller — no over-fetching.

### TypeScript

All files use TypeScript. No `any` types unless absolutely unavoidable and commented with a justification. Define shared types in `resources/js/types/`. Model types mirror the database schema. All API response types are defined and used.

### UI Component Library

Use shadcn/ui for form elements, dialogs, tabs, badges, avatars, progress bars, dropdowns, and tooltips. Do not build these from scratch. For data tables: TanStack Table. For drag-and-drop: @dnd-kit. For charts: Recharts. For rich text: Tiptap. For dates: date-fns (not moment, not dayjs).

### Role-Based UI

Every role has a distinct dashboard layout. The navigation items, visible modules, and data shown change based on the authenticated user's role. This is not done with `if (role === 'ceo')` scattered everywhere — it is handled by a `usePermissions()` hook that checks the permissions array shared from the backend via Inertia's shared data. Components that require a permission wrap themselves with `<CanDo permission="view_reports">`.

### Loading States

Every data-loading UI must show a skeleton loader, not a spinner. Spinners are acceptable only for button loading states (after clicking submit). Empty states must include a helpful message and a CTA action. Error states must show a human-readable message and a retry option.

---

## AI Integration Rules (Claude API)

### When to Call the Claude API

The Claude API is called in exactly three places: daily briefing summary generation, report section commentary generation, and task anomaly detection (optional, phase 2). It is not called for real-time UI interactions. It is always called from a queued job, never from a controller directly.

### Prompt Structure

Every Claude API call uses a system prompt stored in `config/ai.php` as a named prompt. Prompts are not hardcoded in service classes. System prompts are versioned — if a system prompt changes, the old version is kept in config with a version suffix.

### Token Budget

Set `max_tokens: 1000` for briefing summaries. Set `max_tokens: 500` for report section commentary. Never request more tokens than needed. Track token usage in the `daily_briefings` table (prompt_tokens, completion_tokens columns) for cost monitoring.

### Fallback

If the Claude API call fails for any reason (timeout, API error, rate limit), the system falls back to a template-based generator. The user receives a briefing either way. The failure is logged and alerted to the developer Zoho channel, but it does not block the briefing delivery.

---

## Code Quality Rules

### No Business Logic in Controllers

Controllers do one thing: receive a request, validate it, call a service, return a response. All business logic lives in service classes. A controller method should rarely exceed 15 lines.

### Service Classes

Services are plain PHP classes in `app/Modules/{Module}/Services/`. They are injected via the constructor. They do not extend anything. They are not static. They do not know about HTTP requests — they receive plain PHP values.

### DTOs

Data Transfer Objects are used for complex data passing between layers. Use PHP 8.3 `readonly` classes for DTOs. Never pass raw arrays between service layers where the shape matters.

### Enums

Use PHP 8.1+ backed enums for all status values, types, and categories. Enum cases match database enum values exactly. Never use string constants or magic strings — always reference the enum case.

### Error Handling

Use Laravel's exception handler. Create custom exception classes for domain errors: `ProjectNotFoundException`, `SLABreachException`, `MCPAuthenticationException`. These are thrown from services and caught in the exception handler, which formats the correct response.

### No N+1 Queries

All controller queries must eager-load relationships that will be used. Run `php artisan telescope:clear && php artisan serve` in development and check Telescope for N+1 warnings. Any PR that introduces an N+1 query is rejected.

---

## Naming Conventions Summary

**Laravel classes:** PascalCase — `ProjectService`, `TaskSpawnJob`, `MCPConnection`
**Methods:** camelCase — `getDecryptedToken()`, `spawnTasksForProject()`
**Variables:** camelCase — `$projectService`, `$syncResult`
**Database tables:** snake_case plural — `task_templates`, `mcp_sync_logs`
**Database columns:** snake_case — `organization_id`, `is_active`, `created_at`
**Routes:** kebab-case — `/projects/{id}/task-templates`
**React components:** PascalCase — `TaskCard.tsx`, `KanbanBoard.tsx`
**React hooks:** camelCase with `use` prefix — `usePermissions`, `useBriefingData`
**TypeScript types:** PascalCase — `ProjectStatus`, `BriefingPayload`
**Environment variables:** SCREAMING_SNAKE_CASE — `ANTHROPIC_API_KEY`, `META_APP_SECRET`
**Events:** past tense — `ProjectCreated`, `TaskStatusChanged`, `BriefingDelivered`
**Jobs:** imperative — `SpawnProjectTasksJob`, `GenerateDailyBriefingJob`, `SyncMetaAdsJob`

---

## Security Rules

Tokens are encrypted at rest. User passwords are hashed with bcrypt (Laravel default). No sensitive data (tokens, passwords, personal client data) is ever logged to application logs. API rate limiting is enforced: 60 requests per minute for general endpoints, 10 per minute for auth endpoints, 5 per minute for AI-powered endpoints.

All user input is validated using Laravel Form Requests before touching the database. SQL injection is prevented by using Eloquent and the query builder exclusively — no raw SQL queries with user input.

Webhook endpoints verify signatures before processing. Google uses channel tokens, Make uses HMAC-SHA256, Zoho uses a shared secret header.

The `client` role is the most restricted. Assume that client users will attempt to access data they should not see. Test this explicitly.

---

## India-Specific Defaults

Default timezone: `Asia/Kolkata` (IST, UTC+5:30)
Default currency: Indian Rupee (₹), stored as decimal in the database, displayed with ₹ symbol
Business hours for SLA calculation: 9:00 AM to 6:00 PM IST, Monday to Saturday
Date format displayed in UI: DD MMM YYYY (e.g. 15 Jan 2025)
Phone numbers: stored as plain strings, displayed with +91 prefix
GST is tracked for billing purposes but invoicing is out of scope for this build

---

## What Is Out of Scope

The following are explicitly not part of this build:

- Client-facing portal (clients use the internal system with restricted access — no separate public portal)
- Billing and invoicing (tracked manually, not automated)
- Mobile app (the web app must be responsive, but no native app)
- Social media posting scheduler (we track tasks, not auto-post)
- Email marketing sending (we manage campaigns as tasks, we do not send mass emails through this platform)
- Recruitment or HR features beyond basic team management

If a feature is not listed in the phase plan and not listed here as out of scope, bring it up before building it.

---

## Definition of Done

A task is complete when:

1. The feature works as described in the prompt
2. There is at least one Pest test covering the happy path
3. TypeScript compiles with no errors
4. No N+1 queries introduced (verified via Telescope)
5. The relevant MCP sync or push action has been tested locally with the real MCP
6. Inertia shared data is updated if new role-based visibility rules were added
7. A migration exists for any new tables or columns, with a working `down()` method
8. The feature respects the organization scope — it cannot leak data across organizations

---

*Last updated: May 2026*
*Project: Digicloudify Internal Platform*
*Company: SAANETIX SOLUTIONS PRIVATE LIMITED*
*Contact: info@digicloudify.com*
