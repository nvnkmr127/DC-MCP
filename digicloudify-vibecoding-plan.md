# Digicloudify Platform — Vibe Coding Master Plan

> **How to use this document**
> - Each phase has micro-tasks. Each micro-task has a ready-to-paste AI prompt.
> - Copy the prompt exactly. Paste into Cursor / Windsurf / Claude / ChatGPT / Copilot.
> - Label prompts with the AI you used so you can track what works.
> - MCP-specific tasks are marked with 🔌 — use the relevant MCP connector.
> - Never skip a task. Each builds on the previous.

---

## Stack Reference

| Layer | Technology |
|---|---|
| Backend | Laravel 11 |
| Database | PostgreSQL 16 |
| Frontend | React + Inertia.js |
| Queue | Redis + Laravel Horizon |
| Auth | Laravel Sanctum |
| AI | Claude API (claude-sonnet-4-20250514) |
| Storage | S3 / Minio |
| MCP integrations | Google Calendar, Gmail, Google Drive, Notion, Zoho Cliq, Meta Ads, Make |
| Dev environment | Docker Compose |
| CI/CD | GitHub Actions |

---

---

# PHASE 1 — Project Foundation & Environment

**Goal:** Working Laravel + Postgres + Docker dev environment with auth, roles, and base DB schema.

**Duration estimate:** 3–5 days

---

## TASK 1.1 — Docker Compose environment

**AI to use:** Cursor / Claude

---

**PROMPT:**

```
You are setting up a Laravel 11 project called "digicloudify-platform".

Create a complete Docker Compose development environment with these services:

1. app — PHP 8.3 + Laravel Octane (Swoole). Port 8000.
2. queue — Laravel Horizon worker. Same image as app.
3. postgres — PostgreSQL 16. Database: digicloudify_db. User: digi. Password: digipass. Port 5432.
4. redis — Redis 7 alpine. Port 6379.
5. minio — MinIO for S3-compatible local storage. Ports 9000 (API) and 9001 (console).
6. mailpit — Local email testing UI. Port 8025.

Requirements:
- Use a custom Dockerfile for the app service based on php:8.3-cli-alpine
- Install extensions: pdo_pgsql, redis, swoole, pcntl, bcmath, gd, zip, intl
- Install Composer inside the image
- Mount the Laravel project as a volume at /var/www/html
- Use a .env file for all credentials
- Create a Makefile with commands: make up, make down, make shell, make migrate, make horizon

Output:
- docker-compose.yml
- Dockerfile
- Makefile
- .env.example with all required variables filled in for Docker environment
- .dockerignore

Company context: This is a digital marketing agency platform (Digicloudify, Hyderabad) that integrates with Google, Notion, Zoho, Meta Ads, and Make via MCP.
```

---

## TASK 1.2 — Laravel project scaffold

**AI to use:** Cursor / Claude

---

**PROMPT:**

```
Inside a Laravel 11 project called digicloudify-platform, scaffold the full modular folder structure.

The system has these modules:
1. ProjectManagement — projects, sprints, milestones, tasks
2. DailyBriefing — AI-powered morning digest engine
3. Reporting — campaign and project reports
4. DataViz — dashboard and chart configuration
5. TaskEngine — role-based task auto-spawning
6. MCP — external integrations (Google, Notion, Zoho, Meta Ads, Make)
7. Notifications — multi-channel delivery (email, Zoho Cliq, WhatsApp, push)
8. Auth — users, roles, permissions, teams

Create this folder structure inside app/:

app/
  Modules/
    ProjectManagement/
      Models/
      Http/Controllers/
      Http/Requests/
      Services/
      Jobs/
      Events/
      Listeners/
    DailyBriefing/
      Models/
      Http/Controllers/
      Services/
      Jobs/
    Reporting/
      Models/
      Http/Controllers/
      Services/
      Jobs/
      Exports/
    DataViz/
      Models/
      Http/Controllers/
      Services/
    TaskEngine/
      Services/
      Jobs/
      Templates/
    MCP/
      Contracts/
      Adapters/
      Jobs/
      Sync/
    Notifications/
      Channels/
      Templates/
    Auth/
      Models/
      Http/Controllers/
      Services/
  Shared/
    Traits/
    Helpers/
    Enums/

Also create:
- A ModuleServiceProvider base class that each module extends
- Register all module service providers in config/app.php
- A BaseModel.php in Shared with SoftDeletes, UUID primary key, and audit timestamps (created_by, updated_by)
- An ApiResponse helper in Shared/Helpers/ with success(), error(), paginated() static methods

Use Laravel best practices. No Livewire. Frontend will be React + Inertia.js.
```

---

## TASK 1.3 — PostgreSQL database schema (core tables)

**AI to use:** Claude / ChatGPT (use for complex SQL)

---

**PROMPT:**

```
Generate Laravel 11 migration files for a digital marketing agency project management platform called Digicloudify.

Create migrations in this exact order (order matters for foreign keys):

MIGRATION 1 — organizations
  - id (uuid, primary)
  - name, slug (unique), logo_url, website
  - plan (enum: free, starter, pro, enterprise)
  - settings (jsonb — store org-level config)
  - is_active (boolean, default true)
  - timestamps + softDeletes

MIGRATION 2 — users
  - id (uuid, primary)
  - organization_id (uuid, FK → organizations)
  - name, email (unique), password
  - avatar_url, phone, timezone (default: Asia/Kolkata)
  - is_active, email_verified_at
  - last_active_at
  - preferences (jsonb — dashboard config, notification settings)
  - timestamps + softDeletes

MIGRATION 3 — roles
  - id (uuid), organization_id (FK), name, slug, description
  - is_system (boolean — system roles can't be deleted)
  - permissions (jsonb array)
  - timestamps

MIGRATION 4 — role_user
  - user_id, role_id, organization_id
  - assigned_by (uuid), assigned_at

MIGRATION 5 — clients
  - id (uuid), organization_id (FK)
  - name, email, phone, company, website
  - industry, tier (enum: basic, standard, premium, enterprise)
  - status (enum: active, paused, churned, prospect)
  - notes (text), metadata (jsonb)
  - assigned_to (uuid FK → users)
  - timestamps + softDeletes

MIGRATION 6 — projects
  - id (uuid), organization_id (FK), client_id (FK)
  - name, slug, description
  - type (enum: seo, social_media, performance_ads, web_dev, app_dev, content, brand, whatsapp, email_marketing, ecommerce)
  - status (enum: draft, active, on_hold, completed, cancelled)
  - priority (enum: low, medium, high, critical)
  - start_date, end_date, actual_end_date
  - budget (decimal 12,2), budget_used (decimal 12,2)
  - project_manager_id (uuid FK → users)
  - settings (jsonb), tags (jsonb array)
  - timestamps + softDeletes

MIGRATION 7 — sprints
  - id (uuid), project_id (FK)
  - name, goal (text)
  - status (enum: planning, active, completed, cancelled)
  - start_date, end_date
  - velocity_planned (integer), velocity_actual (integer)
  - timestamps

MIGRATION 8 — milestones
  - id (uuid), project_id (FK), sprint_id (nullable FK)
  - name, description
  - due_date, completed_at
  - status (enum: pending, in_progress, completed, missed)
  - timestamps

MIGRATION 9 — tasks
  - id (uuid), organization_id (FK), project_id (FK)
  - sprint_id (nullable FK), milestone_id (nullable FK)
  - parent_task_id (nullable FK → tasks — for subtasks)
  - title, description (text)
  - type (enum: feature, bug, content, design, research, review, meeting, report, campaign_setup, ad_creative, seo_audit, email_sequence, other)
  - status (enum: backlog, todo, in_progress, in_review, blocked, done, cancelled)
  - priority (enum: low, medium, high, critical)
  - assigned_to (uuid FK → users), created_by (uuid FK → users)
  - role_required (enum: ceo, project_manager, analyst, marketer, developer, designer, copywriter)
  - due_date, completed_at
  - estimated_hours (decimal 5,2), actual_hours (decimal 5,2)
  - sla_hours (integer — max hours to complete from creation)
  - sla_breached_at (timestamp)
  - tags (jsonb), meta (jsonb)
  - sort_order (integer)
  - timestamps + softDeletes

MIGRATION 10 — task_assignments (history of who was assigned)
  - id (uuid), task_id (FK), user_id (FK)
  - assigned_by (uuid), assigned_at, unassigned_at
  - timestamps

MIGRATION 11 — task_logs (activity log per task)
  - id (uuid), task_id (FK), user_id (FK)
  - action (enum: created, status_changed, assigned, commented, time_logged, attachment_added, sla_warning, sla_breached)
  - old_value, new_value (jsonb)
  - comment (text)
  - logged_at (timestamp)

MIGRATION 12 — time_entries
  - id (uuid), task_id (FK), user_id (FK), project_id (FK)
  - description, hours (decimal 5,2)
  - logged_date (date)
  - is_billable (boolean)
  - timestamps

MIGRATION 13 — attachments
  - id (uuid), attachable_type, attachable_id (polymorphic)
  - organization_id (FK)
  - filename, original_name, mime_type, size_bytes
  - storage_path, storage_disk
  - uploaded_by (uuid FK → users)
  - timestamps

MIGRATION 14 — comments
  - id (uuid), commentable_type, commentable_id (polymorphic)
  - user_id (FK), parent_id (nullable FK → comments)
  - body (text), mentions (jsonb — array of user ids)
  - is_internal (boolean)
  - timestamps + softDeletes

Use PostgreSQL-specific features where appropriate (JSONB, UUID default gen_random_uuid()). Add database-level indexes on all FK columns and commonly queried fields (status, due_date, organization_id). Write clean, readable Laravel migration syntax.
```

---

## TASK 1.4 — MCP & metrics tables

**AI to use:** Claude

---

**PROMPT:**

```
Continue the Laravel 11 migration files for Digicloudify platform. These migrations handle MCP integrations and metrics storage.

MIGRATION 15 — mcp_connections
  - id (uuid), organization_id (FK), user_id (FK — who connected it)
  - provider (enum: google_calendar, gmail, google_drive, notion, zoho_cliq, meta_ads, make, whatsapp, slack, hubspot)
  - name (human-readable label e.g. "Agency Gmail Account")
  - status (enum: active, disconnected, error, pending)
  - credentials (jsonb — encrypted, store tokens/keys)
  - scopes (jsonb array — OAuth scopes granted)
  - last_synced_at, sync_error (text)
  - settings (jsonb — provider-specific config e.g. calendar IDs, ad account IDs)
  - timestamps + softDeletes

MIGRATION 16 — mcp_sync_logs
  - id (uuid), mcp_connection_id (FK)
  - direction (enum: inbound, outbound)
  - entity_type (e.g. "calendar_event", "notion_page", "meta_campaign")
  - entity_id (external ID from the provider)
  - status (enum: success, failed, partial, skipped)
  - records_processed, records_failed (integer)
  - payload (jsonb — request/response snapshot)
  - error_message (text)
  - duration_ms (integer)
  - synced_at (timestamp)

MIGRATION 17 — mcp_webhook_events
  - id (uuid), mcp_connection_id (FK)
  - provider, event_type
  - payload (jsonb — raw webhook body)
  - signature (string — for verification)
  - status (enum: received, processing, processed, failed)
  - processed_at (timestamp)
  - timestamps

MIGRATION 18 — kpi_definitions
  - id (uuid), organization_id (FK)
  - name, slug (unique per org), description
  - category (enum: marketing, project, financial, team, client)
  - source (enum: manual, meta_ads, google_analytics, notion, internal)
  - mcp_connection_id (nullable FK — which connection provides this KPI)
  - aggregation (enum: sum, average, last_value, count, percentage)
  - unit (e.g. "INR", "clicks", "%", "hours")
  - target_value (decimal), target_direction (enum: higher_better, lower_better)
  - is_active (boolean)
  - timestamps

MIGRATION 19 — metric_snapshots
  Partitioned by month (document the PARTITION BY RANGE strategy in a comment).
  - id (uuid)
  - organization_id (FK), kpi_definition_id (FK)
  - project_id (nullable FK), client_id (nullable FK)
  - mcp_connection_id (nullable FK)
  - value (decimal 15,4)
  - dimension_1, dimension_2 (varchar — e.g. campaign_name, ad_set_name)
  - metadata (jsonb — raw source data snapshot)
  - source_external_id (varchar — ID in source system)
  - recorded_at (timestamp — when the metric occurred)
  - synced_at (timestamp — when we pulled it)
  - date_key (date — for easy date filtering)

MIGRATION 20 — reports
  - id (uuid), organization_id (FK), project_id (nullable FK), client_id (nullable FK)
  - title, type (enum: weekly, monthly, campaign, sprint, custom, client)
  - status (enum: draft, generating, ready, sent, archived)
  - template (enum: seo_report, ads_report, social_report, sprint_report, full_service)
  - date_from, date_to (date)
  - config (jsonb — which metrics, charts, sections to include)
  - generated_file_path (varchar — S3 path to PDF)
  - generated_at, sent_at
  - generated_by (uuid FK → users)
  - recipients (jsonb — array of emails)
  - timestamps + softDeletes

MIGRATION 21 — daily_briefings
  - id (uuid), organization_id (FK), user_id (FK)
  - date (date)
  - status (enum: pending, generating, ready, delivered, failed)
  - digest_raw (jsonb — structured data before AI processing)
  - digest_html, digest_text (text — AI-generated content)
  - ai_model (varchar), ai_tokens_used (integer)
  - delivered_via (jsonb array — e.g. ["email", "zoho_cliq"])
  - delivered_at
  - timestamps

MIGRATION 22 — dashboard_configs
  - id (uuid), organization_id (FK), user_id (FK)
  - role (enum: ceo, project_manager, analyst, marketer, developer)
  - name, is_default (boolean)
  - layout (jsonb — grid positions and widget configs)
  - timestamps

MIGRATION 23 — notifications_log
  - id (uuid), organization_id (FK), user_id (FK)
  - type (enum: task_assigned, sla_warning, sla_breached, report_ready, briefing_ready, campaign_alert, mention, system)
  - channel (enum: email, zoho_cliq, whatsapp, in_app, push)
  - title, body (text)
  - data (jsonb — contextual data like task_id, project_id)
  - status (enum: pending, sent, delivered, failed, read)
  - read_at, sent_at
  - timestamps

Add PostgreSQL indexes, use gen_random_uuid() defaults, and add a note in comments for the metric_snapshots partition strategy.
```

---

## TASK 1.5 — Auth, roles, and Sanctum setup

**AI to use:** Cursor / Claude

---

**PROMPT:**

```
Set up complete authentication and RBAC (Role-Based Access Control) for a Laravel 11 multi-tenant SaaS platform called Digicloudify.

Requirements:

1. AUTHENTICATION
   - Use Laravel Sanctum for API tokens (mobile/API clients) and session auth (Inertia.js frontend)
   - Registration: name, email, password, organization name (creates org + first admin user)
   - Login returns user object with roles, permissions, organization, and preferences
   - Password reset via email
   - Email verification

2. ROLES (seed these as system roles, is_system = true)
   - ceo: full access to everything in their organization
   - project_manager: manage all projects, assign tasks, view all reports
   - analyst: view all data, create reports, manage metrics
   - marketer: manage campaigns, tasks assigned to them, content calendar
   - developer: view dev tasks only, sprint boards, time logging
   - designer: view design tasks, upload assets
   - client: view-only access to their project reports (read-only portal)

3. PERMISSION SYSTEM
   Create a PermissionService that checks:
   - Can the user perform action X on resource Y?
   - Actions: view, create, update, delete, assign, export, manage_settings
   - Resources: project, task, report, client, metric, user, mcp_connection, briefing

   Store permissions as JSONB on the roles table like:
   {
     "project": ["view", "create", "update"],
     "task": ["view", "create", "update", "assign"],
     "report": ["view", "export"]
   }

4. MIDDLEWARE
   Create these middleware:
   - OrganizationScope: automatically scope all queries to the authenticated user's organization_id
   - RoleMiddleware: check if user has required role (usage: ->middleware('role:ceo,project_manager'))
   - PermissionMiddleware: check specific permission (usage: ->middleware('can:create,project'))

5. MODELS
   - User model with: hasRoles(), hasPermission(resource, action), belongsToOrganization(), currentOrganization()
   - Organization model with: users(), projects(), clients(), mcpConnections()
   - Role model with: users(), hasPermission(resource, action)

6. SEEDERS
   - OrganizationSeeder: create "Digicloudify" organization
   - RoleSeeder: seed all 7 system roles with full permission sets
   - UserSeeder: create demo users for each role with email pattern role@digicloudify.com, password: Demo@1234

Output all controllers, models, middleware, seeders, and route definitions in api.php and web.php.
```

---

## TASK 1.6 — Task templates seeder (role-based)

**AI to use:** Claude

---

**PROMPT:**

```
Create a Laravel seeder called TaskTemplateSeeder for the Digicloudify platform.

For each project type, define a set of tasks that auto-spawn when a project of that type is created. Each task has: title, description, type, role_required, estimated_hours, sla_hours, sort_order, depends_on (array of sort_order numbers).

Seed task templates for these project types:

PROJECT TYPE: seo
Tasks:
1. Initial SEO Audit (analyst, 8h, sla: 48h) — Technical audit of site
2. Keyword Research (analyst, 6h, sla: 72h) — Competitor + volume analysis
3. On-Page Optimisation Plan (analyst, 4h, sla: 48h, depends: 1,2)
4. Content Brief Creation (marketer, 3h, sla: 48h, depends: 3)
5. Content Writing (marketer/copywriter, 8h, sla: 120h, depends: 4)
6. Technical Fixes Implementation (developer, 6h, sla: 96h, depends: 3)
7. Backlink Outreach Setup (marketer, 4h, sla: 72h, depends: 3)
8. Monthly Report Setup (analyst, 2h, sla: 24h, depends: 1)
9. Client Kickoff Meeting (project_manager, 1h, sla: 24h)

PROJECT TYPE: performance_ads
Tasks:
1. Ad Account Audit (analyst, 4h, sla: 24h)
2. Audience Research (analyst, 4h, sla: 48h)
3. Campaign Strategy Document (project_manager, 3h, sla: 48h, depends: 1,2)
4. Ad Creative Brief (marketer, 2h, sla: 48h, depends: 3)
5. Creative Production (designer, 8h, sla: 96h, depends: 4)
6. Campaign Setup in Meta Ads (marketer, 4h, sla: 48h, depends: 5)
7. Pixel/Tracking Verification (developer, 2h, sla: 24h, depends: 6)
8. Launch Review (project_manager, 1h, sla: 12h, depends: 6,7)
9. Weekly Performance Report Template (analyst, 2h, sla: 24h)

PROJECT TYPE: web_dev
Tasks:
1. Requirements Gathering (project_manager, 4h, sla: 24h)
2. Wireframe Design (designer, 8h, sla: 96h, depends: 1)
3. Design Approval (project_manager, 1h, sla: 24h, depends: 2)
4. Frontend Development (developer, 40h, sla: 240h, depends: 3)
5. Backend Development (developer, 30h, sla: 240h, depends: 3)
6. Content Upload (marketer, 8h, sla: 96h, depends: 4)
7. QA Testing (developer, 8h, sla: 48h, depends: 4,5)
8. Client UAT (project_manager, 4h, sla: 48h, depends: 7)
9. Go Live (developer, 2h, sla: 24h, depends: 8)
10. Post-Launch Monitoring (developer, 2h, sla: 72h, depends: 9)

PROJECT TYPE: social_media
Tasks:
1. Brand Voice & Style Guide (marketer, 4h, sla: 48h)
2. Content Calendar — Month 1 (marketer, 4h, sla: 48h, depends: 1)
3. Graphic Templates Creation (designer, 8h, sla: 96h, depends: 1)
4. Content Writing — Week 1 (marketer, 3h, sla: 48h, depends: 2)
5. Design — Week 1 Posts (designer, 4h, sla: 48h, depends: 4)
6. Scheduling Setup (marketer, 1h, sla: 24h, depends: 5)
7. Monthly Analytics Report (analyst, 2h, sla: 24h)

PROJECT TYPE: email_marketing
Tasks:
1. Email Strategy & Sequence Plan (marketer, 4h, sla: 48h)
2. Email Template Design (designer, 6h, sla: 72h, depends: 1)
3. Copywriting — Email Sequence (marketer, 8h, sla: 96h, depends: 1)
4. ESP Setup & Integration (developer, 3h, sla: 48h, depends: 2)
5. List Segmentation (analyst, 2h, sla: 24h)
6. Test Send & QA (marketer, 1h, sla: 24h, depends: 3,4)
7. Launch & Monitor (marketer, 1h, sla: 12h, depends: 6)
8. Performance Report (analyst, 2h, sla: 24h, depends: 7)

Store templates in a task_templates table:
- id (uuid), project_type (enum), title, description, type, role_required
- estimated_hours, sla_hours, sort_order
- depends_on (jsonb array of sort_order integers)
- is_active (boolean)
- timestamps

Create the migration for task_templates and the seeder. Also create a TaskSpawnerService with a method spawnFromTemplate(Project $project): Collection that reads templates for the project type and creates all tasks, resolving dependencies, and setting initial status to backlog.
```

---

---

# PHASE 2 — MCP Integration Layer

**Goal:** Each external tool (Google, Notion, Zoho, Meta Ads, Make) is connected via a standardised adapter. Data flows in and out reliably.

**Duration estimate:** 5–7 days

---

## TASK 2.1 — MCP adapter contract and base class

**AI to use:** Claude / Cursor

---

**PROMPT:**

```
Build the MCP (Model Context Protocol) integration layer for the Digicloudify Laravel platform.

Create the following in app/Modules/MCP/:

1. INTERFACE — Contracts/MCPAdapter.php
   Methods:
   - authenticate(McpConnection $connection): bool
   - sync(McpConnection $connection): SyncResult
   - push(McpConnection $connection, array $payload): bool
   - handleWebhook(McpConnection $connection, Request $request): WebhookResult
   - testConnection(McpConnection $connection): ConnectionTestResult
   - getAvailableScopes(): array

2. BASE CLASS — Adapters/BaseAdapter.php (implements MCPAdapter)
   - Handles retry logic (3 retries with exponential backoff)
   - Logs every request/response to mcp_sync_logs table
   - Emits events: McpSyncCompleted, McpSyncFailed, McpWebhookReceived
   - Decrypts credentials from the McpConnection model
   - Records last_synced_at after successful sync
   - Has a protected httpClient() method that returns a Guzzle client pre-configured with the connection's credentials

3. VALUE OBJECTS
   - SyncResult: success (bool), records_processed, records_failed, errors (array), duration_ms
   - WebhookResult: handled (bool), action_taken (string), entity_affected (string)
   - ConnectionTestResult: connected (bool), message, scopes (array), account_info (array)

4. MCP MODEL — Models/McpConnection.php
   - Belongs to Organization, User
   - Cast credentials to encrypted JSON (use Laravel's encrypted cast)
   - Scopes: active(), byProvider()
   - Methods: markSynced(), markError(string $message), isExpired()

5. JOBS — Jobs/SyncMcpProviderJob.php
   - Accepts McpConnection model
   - Dispatches on the mcp-sync queue
   - Max attempts: 3, retry after: 60 seconds
   - On failure: marks the connection with error status, sends in-app notification to the user who connected it

6. COMMANDS — Console/Commands/SyncAllMcpCommand.php
   - artisan mcp:sync-all
   - artisan mcp:sync --provider=google_calendar
   - artisan mcp:sync --connection-id=uuid
   - Schedule: every 15 minutes in Laravel's scheduler

7. CONTROLLER — Http/Controllers/McpConnectionController.php
   - index(): list connections for current org
   - store(): create new connection (validate provider, store encrypted credentials)
   - show(), update(), destroy()
   - test(McpConnection): runs testConnection(), returns result
   - sync(McpConnection): dispatches SyncMcpProviderJob immediately
   - webhook(string $provider, Request $request): routes to correct adapter

Include all service provider registration and route definitions.
```

---

## TASK 2.2 — Google Calendar adapter

**AI to use:** Claude

---

**PROMPT:**

```
Build the Google Calendar MCP adapter for Digicloudify (Laravel 11).

File: app/Modules/MCP/Adapters/GoogleCalendarAdapter.php
Extends: BaseAdapter

The McpConnection settings JSONB will contain:
- calendar_ids: array of Google Calendar IDs to sync
- sync_direction: "inbound" | "both"
- project_mapping: object mapping calendar_id to project_id

SYNC (inbound — Google Calendar → Digicloudify):
1. Pull all events from each configured calendar for the next 30 days
2. For each event:
   a. If it has a matching milestone by name in the linked project → update the milestone's due_date
   b. If it contains [TASK] in the title → create or update a task in the linked project
   c. Store the raw event in mcp_sync_logs
3. Return a SyncResult with counts

PUSH (outbound — Digicloudify → Google Calendar):
When a task or milestone has a due_date set, create a corresponding Google Calendar event:
- Title: [DIGI] {task.title} — {project.name}
- Description: includes task URL, assigned to, project
- Calendar: the connection's default push calendar

WEBHOOK: handle Google Calendar push notifications (X-Goog-Resource-State header)
- "sync" → trigger a full sync
- "exists" → sync just the changed resource

AUTH: OAuth2 flow using Google's PHP client. The connection's credentials will contain:
- access_token, refresh_token, token_expiry
Implement auto-refresh when token is expired.

Use Google_Client + Google_Service_Calendar. Install via composer require google/apiclient.

Include full error handling, logging, and a unit test class GoogleCalendarAdapterTest with mocked HTTP responses.
```

---

## TASK 2.3 — Gmail adapter

**AI to use:** Claude

---

**PROMPT:**

```
Build the Gmail MCP adapter for Digicloudify (Laravel 11).

File: app/Modules/MCP/Adapters/GmailAdapter.php
Extends: BaseAdapter

Use Google Gmail API via google/apiclient.

The McpConnection settings will contain:
- labels_to_watch: array of Gmail label names (e.g. ["CLIENT", "URGENT"])
- auto_create_tasks: boolean
- linked_project_id: nullable — auto-link emails to this project

SYNC (inbound):
1. Pull unread emails from watched labels in the last 24 hours
2. For each email:
   a. Parse sender, subject, body snippet, date, thread_id
   b. If auto_create_tasks = true → create a task of type "review" with the email subject as title, assigned to the project_manager role, due in 24h
   c. Log the email to mcp_sync_logs with the full payload
   d. Mark the email as read in Gmail after processing
3. Handle threading — link multiple tasks to the same Gmail thread_id

PUSH (outbound — send daily briefing emails):
Implement sendBriefingEmail(User $user, DailyBriefing $briefing): bool
- Compose and send via Gmail API using the connected account
- Track send status in notifications_log

DAILY BRIEFING PULL:
Implement getClientEmailsSummary(int $hours = 24): array
Returns: array of { from, subject, snippet, received_at, thread_id, labels }
This is called by the DailyBriefing module each morning.

WEBHOOK: Gmail push via Cloud Pub/Sub
- Handle X-Goog-Subscription header
- Decode the Pub/Sub message, extract historyId
- Trigger partial sync from that historyId

Include OAuth2 token refresh, all error handling, and a GmailAdapterTest test class.
```

---

## TASK 2.4 — Notion adapter

**AI to use:** Claude

---

**PROMPT:**

```
Build the Notion MCP adapter for Digicloudify (Laravel 11).

File: app/Modules/MCP/Adapters/NotionAdapter.php
Extends: BaseAdapter

Use Notion API v1 (https://api.notion.com/v1/). Auth via Internal Integration Token stored in credentials.

The McpConnection settings will contain:
- database_ids: object mapping Notion database ID to local entity type
  Example: { "abc123": "tasks", "def456": "projects", "ghi789": "sops" }
- push_updates: boolean — whether to push local changes back to Notion

SYNC (inbound — Notion → Digicloudify):
For each database_id:
  - If entity_type = "tasks": pull all pages, map to tasks table fields
    Map Notion properties: Title→title, Status→status, Assignee→user lookup by email, Due→due_date, Priority→priority
  - If entity_type = "sops": pull all pages, store as attachments with source="notion"
  - If entity_type = "projects": pull all pages, update project fields

PUSH (outbound — Digicloudify → Notion):
When a task status changes, push the update to the corresponding Notion page if push_updates = true.
Implement pushTaskUpdate(Task $task): bool

CONTENT PULL (for Daily Briefing):
Implement getRecentUpdates(int $hours = 24): array
Returns array of recently modified Notion pages across all connected databases.

SEARCH:
Implement search(string $query): array
Used by the frontend to search across connected Notion workspaces.

Use Guzzle for HTTP calls. Map API rate limits (3 req/sec) — use Laravel rate limiter. Handle pagination (Notion cursor-based).

Include full error handling, retry on 429 (rate limit), and a NotionAdapterTest.
```

---

## TASK 2.5 — Meta Ads adapter

**AI to use:** Claude

---

**PROMPT:**

```
Build the Meta Ads MCP adapter for Digicloudify (Laravel 11).

File: app/Modules/MCP/Adapters/MetaAdsAdapter.php
Extends: BaseAdapter

Use Meta Marketing API v20.0. Auth via long-lived User Access Token.

The McpConnection settings will contain:
- ad_account_ids: array of Meta Ad Account IDs (format: act_XXXXXXXXX)
- client_id: local client UUID — link metrics to this client
- project_id: local project UUID
- metrics_to_pull: array — e.g. ["impressions", "clicks", "spend", "reach", "cpm", "cpc", "ctr", "roas", "conversions"]
- date_range: "yesterday" | "last_7d" | "last_30d" (default: yesterday)

SYNC (inbound — Meta Ads → Digicloudify):
For each ad_account_id:
  1. Pull campaigns with fields: id, name, status, objective, daily_budget, lifetime_budget
  2. Pull ad sets with fields: id, name, campaign_id, status, targeting summary
  3. Pull insights for the configured date_range with all metrics_to_pull
  4. For each insight row (campaign level):
     - Store in metric_snapshots table with:
       kpi_definition matched by slug (e.g. "meta_ads_spend")
       dimension_1 = campaign_name, dimension_2 = ad_account_id
       value = the metric value
       recorded_at = the insight date
  5. Auto-create KPI definitions if they don't exist for this org

ANOMALY DETECTION:
After syncing, compare yesterday's spend vs 7-day average.
If spend > 2x average or < 0.5x average → create a notification of type "campaign_alert"

DAILY BRIEFING PULL:
Implement getYesterdaySummary(McpConnection $connection): array
Returns: { total_spend, total_clicks, total_impressions, top_campaign, alerts: [] }

TEST CONNECTION:
Verify the token is valid and has ads_read, ads_management permissions.
Return connected account info including account name and currency.

Use Meta Business SDK (facebook/php-business-sdk) or raw Guzzle. Handle token expiry (60-day long-lived tokens). Add a MetaAdsAdapterTest.
```

---

## TASK 2.6 — Zoho Cliq adapter

**AI to use:** Claude

---

**PROMPT:**

```
Build the Zoho Cliq MCP adapter for Digicloudify (Laravel 11).

File: app/Modules/MCP/Adapters/ZohoCliqAdapter.php
Extends: BaseAdapter

Use Zoho Cliq API. Auth via OAuth2 (Zoho Accounts).

The McpConnection settings will contain:
- channels_to_watch: array of channel names to monitor
- briefing_channel: channel name for daily briefing delivery
- alert_channel: channel name for SLA alerts and system notifications
- bot_unique_name: Zoho Cliq bot name (if bot integration enabled)

PUSH METHODS (these are the primary use of this adapter):

1. sendDailyBriefing(User $user, DailyBriefing $briefing): bool
   Posts the briefing digest as a formatted card message to the briefing_channel.
   Card format:
   - Title: "📊 Daily Briefing — {date}"
   - Sections: Tasks Due Today, Campaign Alerts, Client Emails, Team Blockers
   - Action buttons: View Full Briefing, Open Dashboard

2. sendTaskAlert(Task $task, string $alertType): bool
   alertType: "assigned" | "sla_warning" | "sla_breached" | "completed"
   Sends a DM to the assigned user with task details and a deep link.

3. sendProjectAlert(Project $project, string $message): bool
   Posts to alert_channel with project context.

4. sendChannelMessage(string $channel, string $message, array $card = []): bool
   Generic message sender used by the Notifications module.

SYNC (inbound):
1. Pull recent messages from watched channels (last 24h)
2. If a message mentions "@digicloudify" or contains [TASK], parse and create a task
3. Return messages summary for Daily Briefing

AUTH: OAuth2 with Zoho. Handle refresh token rotation.

Include sendTestMessage() for connection testing and a ZohoCliqAdapterTest.
```

---

## TASK 2.7 — Make.com adapter

**AI to use:** Claude

---

**PROMPT:**

```
Build the Make.com (formerly Integromat) MCP adapter for Digicloudify (Laravel 11).

File: app/Modules/MCP/Adapters/MakeAdapter.php
Extends: BaseAdapter

Use Make.com REST API. Auth via API Key stored in credentials.

The McpConnection settings will contain:
- team_id: Make team ID
- organization_id_make: Make organization ID
- webhook_scenarios: object mapping local event → Make webhook URL
  Example: {
    "task_completed": "https://hook.make.com/xxxx",
    "report_generated": "https://hook.make.com/yyyy",
    "sla_breached": "https://hook.make.com/zzzz",
    "new_client": "https://hook.make.com/wwww"
  }

PUSH (outbound — trigger Make scenarios from Digicloudify events):
Implement triggerScenario(string $eventType, array $data): bool
- Looks up the webhook URL from webhook_scenarios
- Posts the data payload to the Make webhook URL
- Logs to mcp_sync_logs

SYNC (inbound — pull Make execution results):
Implement getRecentExecutions(int $limit = 50): array
- Calls Make API: GET /api/v2/scenarios/{scenarioId}/executions
- Returns execution status, duration, errors
- Store failed executions in mcp_sync_logs with status "failed"

AVAILABLE TRIGGERS (document these, create a getAvailableTriggers() method):
- task_created, task_completed, task_sla_breached
- project_created, project_completed
- report_generated, report_sent
- new_client_added
- campaign_alert
- daily_briefing_generated

INBOUND WEBHOOKS (Make → Digicloudify):
Implement handleInboundWebhook(Request $request): WebhookResult
Make scenarios can POST back to Digicloudify to:
- Create tasks (payload: project_id, title, assigned_role, due_date)
- Update metric snapshots (payload: kpi_slug, value, recorded_at)
- Send notifications (payload: user_id, message, channel)
Validate inbound requests using a shared secret in settings.

Include a MakeAdapterTest and instructions for configuring Make scenarios to connect to this system.
```

---

---

# PHASE 3 — Core Modules (Backend)

**Goal:** All 5 core modules fully functional as API endpoints. Tested and ready for frontend consumption.

**Duration estimate:** 7–10 days

---

## TASK 3.1 — Project Management module

**AI to use:** Cursor (best for full CRUD)

---

**PROMPT:**

```
Build the complete Project Management module for Digicloudify (Laravel 11 API).

Location: app/Modules/ProjectManagement/

Build these components:

MODELS (all extend BaseModel with UUID, SoftDeletes, org-scoped):
- Project (belongs to Organization, Client, User as PM; has many Sprints, Milestones, Tasks, TimeEntries, Attachments, Members)
- Sprint (belongs to Project; has many Tasks; scopes: active(), upcoming(), completed())
- Milestone (belongs to Project, Sprint; status transitions with events)
- Task (belongs to Project, Sprint, Milestone, User assigned_to; has many subtasks, TaskLogs, TimeEntries, Attachments, Comments; scopes: overdue(), dueSoon(), byRole(), myTasks())

SERVICES:
1. ProjectService
   - createProject(array $data): Project — creates project + spawns tasks via TaskSpawnerService
   - updateProject(Project $project, array $data): Project
   - archiveProject(Project $project): void
   - getProjectStats(Project $project): array — returns progress %, tasks by status, budget used, time logged, days remaining
   - getTeamWorkload(Organization $org): array — per user: task count, estimated hours, overdue count

2. TaskService
   - createTask(array $data): Task — validates, creates, logs, notifies assigned user
   - updateTaskStatus(Task $task, string $newStatus, User $actor): Task — validates transition, logs, checks SLA, triggers Make webhook
   - assignTask(Task $task, User $user, User $actor): Task
   - logTime(Task $task, User $user, float $hours, string $description, Carbon $date): TimeEntry
   - checkAndFlagSLABreaches(): int — called by scheduler every 30 min; returns count flagged

3. SprintService
   - createSprint(Project $project, array $data): Sprint
   - startSprint(Sprint $sprint): Sprint — sets status to active, sets actual start date
   - completeSprint(Sprint $sprint): Sprint — moves incomplete tasks to backlog or next sprint

CONTROLLERS (API Resource Controllers):
- ProjectController: index, store, show, update, destroy, stats, team-workload
- SprintController: index, store, show, update, start, complete
- MilestoneController: index, store, show, update, destroy
- TaskController: index, store, show, update, destroy, assign, log-time, move (change sprint/milestone)
- TimeEntryController: index, store, update, destroy, summary (hours by user/project/date)
- KanbanController: board(Project $project) — returns tasks grouped by status for kanban view

API RESOURCES:
Create Laravel API Resources for all models. TaskResource must include: project name, assignee name/avatar, sprint name, milestone name, subtask count, time_logged, sla_status (ok/warning/breached).

EVENTS & LISTENERS:
- TaskStatusChanged → log to task_logs, check SLA, trigger Make webhook
- TaskSLABreached → create notification, send Zoho Cliq alert
- ProjectCreated → spawn tasks from template via TaskSpawnerService

ROUTES (in routes/api.php, all under /api/v1/ prefix, auth:sanctum middleware, organization-scoped):
Full CRUD for all resources. Add these extra routes:
- GET /projects/{project}/kanban
- GET /projects/{project}/stats
- POST /tasks/{task}/assign
- POST /tasks/{task}/log-time
- POST /tasks/{task}/move
- GET /organizations/team-workload

Include Form Requests for validation on all store/update actions.
```

---

## TASK 3.2 — Daily Briefing module

**AI to use:** Claude (AI-heavy task)

---

**PROMPT:**

```
Build the Daily Briefing module for Digicloudify (Laravel 11).

Location: app/Modules/DailyBriefing/

This module generates a personalised morning digest for each user every day using AI (Claude API).

BRIEFING DATA COLLECTOR — Services/BriefingDataCollector.php
Method: collect(User $user, Carbon $date): array
Returns a structured data object by pulling from:

1. TASKS (from local DB):
   - Tasks due today assigned to the user
   - Overdue tasks assigned to the user
   - Tasks where SLA is within 4 hours of breaching
   - Tasks completed yesterday by the user

2. CALENDAR (via Google Calendar adapter):
   - Today's meetings and events for the user

3. PROJECTS (from local DB):
   - Projects where the user is PM, due in next 7 days
   - Projects with no activity in last 3 days (stale alert)

4. METRICS (from metric_snapshots, for analyst/CEO roles):
   - Yesterday's Meta Ads spend, clicks, impressions vs previous day
   - Any campaign_alert notifications from yesterday

5. EMAILS (via Gmail adapter if connected):
   - Unread client emails from last 24 hours (snippet only)

6. TEAM (for project_manager/ceo roles):
   - Team members with overdue tasks (name, count)
   - Blocked tasks count

7. REPORTS (for analyst/pm roles):
   - Reports due to be sent today

Return format:
{
  "user": { name, role },
  "date": "2026-05-21",
  "tasks": { "due_today": [], "overdue": [], "sla_warning": [], "completed_yesterday": [] },
  "calendar": { "events": [] },
  "projects": { "due_soon": [], "stale": [] },
  "metrics": { "meta_ads": {}, "alerts": [] },
  "emails": { "unread_client_emails": [] },
  "team": { "overdue_by_member": [], "blocked_count": 0 },
  "reports": { "due_today": [] }
}

AI GENERATOR — Services/BriefingGenerator.php
Method: generate(User $user, array $data): DailyBriefing

Calls Claude API (claude-sonnet-4-20250514, max_tokens: 1500) with a system prompt:
"You are a smart executive assistant for Digicloudify, a digital marketing agency in Hyderabad. 
Generate a concise, actionable daily briefing. Be direct and prioritise urgent items. 
Use clear sections. No fluff. Output in HTML format suitable for email."

User prompt: Pass the structured data as JSON and ask Claude to write:
- A one-line "Today's focus" sentence
- Priority alerts (urgent items the user must handle today)
- Task summary
- Metric highlights (if analyst/CEO)
- Quick wins (2-3 easy tasks to knock out)

Store: digest_raw (the input JSON), digest_html (Claude's output), ai_tokens_used

DELIVERY — Services/BriefingDelivery.php
Method: deliver(DailyBriefing $briefing): void
Based on user's preferences.notifications in their profile:
- Email: send via Gmail adapter or Laravel Mail (SES)
- Zoho Cliq: post to user's DM via ZohoCliqAdapter
- In-app: create notification record in notifications_log

JOB — Jobs/GenerateDailyBriefingJob.php
Queued on 'briefings' queue. Runs the full pipeline:
collect → generate → deliver → mark DailyBriefing as delivered

COMMAND — artisan briefing:generate {--date=} {--user=} {--all}
- --all: generates for all active users in all orgs
- --user=uuid: single user
- --date=YYYY-MM-DD: specific date (backfill)

SCHEDULER (in Console/Kernel.php):
Schedule briefing:generate --all at 7:00 AM Asia/Kolkata

CONTROLLER — Http/Controllers/BriefingController.php
- index(): list briefings for authenticated user (paginated)
- show(DailyBriefing $briefing): return briefing with HTML content
- regenerate(DailyBriefing $briefing): re-run generator and re-deliver
- preferences(): get/update user's briefing preferences
- preview(): generate a preview without saving or delivering

Include all routes, API resources, and a BriefingGeneratorTest that mocks the Claude API call.
```

---

## TASK 3.3 — Reporting module

**AI to use:** Cursor / Claude

---

**PROMPT:**

```
Build the Reporting module for Digicloudify (Laravel 11).

Location: app/Modules/Reporting/

REPORT TEMPLATES:
Define 5 report templates as PHP classes in Templates/:

1. SeoReportTemplate — sections: Executive Summary, Keyword Rankings, Organic Traffic, Technical Health, Backlinks, Next Steps
2. AdsReportTemplate — sections: Executive Summary, Spend Overview, Campaign Performance, Top Ads, Audience Insights, Recommendations
3. SocialReportTemplate — sections: Overview, Platform Breakdown, Top Posts, Engagement Trends, Growth
4. SprintReportTemplate — sections: Sprint Goal, Velocity, Completed Tasks, Carried Over, Blockers, Next Sprint Plan
5. FullServiceReportTemplate — combines all relevant sections based on project type

Each template class has:
- getSections(): array — list of section configs
- getRequiredMetrics(): array — KPI slugs needed to generate this report
- getDefaultDateRange(): string — e.g. "last_30d"

REPORT SERVICE — Services/ReportService.php
Methods:
- createReport(array $data): Report — validates, creates record
- generateReport(Report $report): Report — main generation pipeline:
  1. Collect metric_snapshots for the report's date range and project/client
  2. Pull additional data from MCP adapters if needed (Meta Ads latest, etc.)
  3. Build a structured data array matching the template's sections
  4. Pass to ReportRenderer
  5. Update report status to "ready"

- scheduleReport(Report $report, string $cronExpression): void
- sendReport(Report $report, array $recipients): void — sends PDF via email

RENDERER — Services/ReportRenderer.php
- renderHtml(Report $report, array $data): string — generates HTML from data using Blade views
- renderPdf(Report $report): string — converts HTML to PDF using Browsershot (headless Chrome)
  Install: composer require spatie/browsershot
- Store PDF to S3: storage/reports/{org_id}/{report_id}.pdf

BLADE VIEWS (resources/views/reports/):
- layouts/report.blade.php — base layout with Digicloudify branding, logo, page numbers
- sections/executive_summary.blade.php
- sections/metrics_table.blade.php
- sections/chart_placeholder.blade.php (chart images embedded as base64)
- partials/metric_card.blade.php

SCHEDULED REPORTS:
- ReportSchedule model (report_schedules table): report_template, project_id, client_id, frequency (weekly/monthly), send_day, recipients, last_run_at, next_run_at
- artisan reports:run-scheduled — checks which scheduled reports are due, generates and sends them
- Schedule this command daily at 8 AM

CONTROLLER — Http/Controllers/ReportController.php
- index(): list reports for current org (filter by project, client, type, status)
- store(): create report
- show(): return report metadata + download URL
- generate(Report $report): trigger generation job
- download(Report $report): return signed S3 URL (expires in 1 hour)
- send(Report $report): send to recipients immediately
- schedules — nested resource for scheduled reports

JOB — Jobs/GenerateReportJob.php
Queue: reports. Timeout: 120 seconds (PDF generation is slow).
On completion: create notification, trigger Make webhook "report_generated".

Include all migrations for report_schedules, routes, and a ReportServiceTest.
```

---

## TASK 3.4 — Data Viz module

**AI to use:** Claude

---

**PROMPT:**

```
Build the Data Visualization module backend for Digicloudify (Laravel 11).

Location: app/Modules/DataViz/

This module powers the dashboard with configurable widgets. The frontend (React) will call these APIs to render charts.

QUERY ENGINE — Services/VizQueryEngine.php
Single endpoint architecture: the frontend sends a query spec, the engine returns data.

Method: query(User $user, array $spec): array
Spec format:
{
  "metric_key": "meta_ads_spend",         // KPI slug or computed metric
  "aggregation": "sum",                   // sum | avg | count | last
  "group_by": "day",                      // day | week | month | campaign | project | user
  "filters": {
    "date_from": "2026-04-01",
    "date_to": "2026-04-30",
    "project_id": "uuid",
    "client_id": "uuid",
    "dimension_1": "Campaign Name"
  },
  "compare": "previous_period",           // optional: compare to previous_period or previous_year
  "limit": 10,                            // for top-N queries
  "chart_type": "line"                    // hint for frontend
}

Returns:
{
  "data": [{ "label": "2026-04-01", "value": 12500.50, "compare_value": 10200.00 }],
  "summary": { "total": 375000, "change_pct": 22.5, "trend": "up" },
  "meta": { "unit": "INR", "chart_type": "line", "date_range": "Apr 2026" }
}

COMPUTED METRICS (built-in, sourced from local DB):
- tasks_completed_count: tasks completed in date range
- tasks_overdue_count: tasks currently overdue
- avg_task_completion_hours: average hours from created to completed
- sprint_velocity: completed story points per sprint
- time_logged_hours: total hours logged
- sla_breach_rate: percentage of tasks that breached SLA
- project_health_score: computed from: on-time tasks %, budget used %, team activity

WIDGET CONFIG — Models/DashboardWidget.php
Stored in dashboard_configs.layout (JSONB):
{
  "id": "widget-uuid",
  "type": "line_chart",              // line_chart | bar_chart | metric_card | funnel | table | heatmap | pie_chart
  "title": "Ad Spend Trend",
  "spec": { ...query spec... },
  "position": { "x": 0, "y": 0, "w": 6, "h": 4 },
  "refresh_interval": 300            // seconds
}

DASHBOARD SERVICE — Services/DashboardService.php
- getDashboard(User $user): array — returns the user's dashboard config + runs all widget queries
- saveDashboard(User $user, array $layout): DashboardConfig
- getDefaultDashboard(string $role): array — returns role-appropriate default widgets:

  CEO default widgets: Company KPI cards (revenue, active projects, total tasks), Meta Ads spend line chart, Team workload bar chart, Project status pie chart
  
  Project Manager: Active projects table, Team tasks bar chart, SLA breach rate card, Sprint velocity line chart
  
  Analyst: Metric cards (spend, clicks, ROAS), Campaign performance bar chart, KPI trend lines, Comparison table
  
  Marketer: Campaign status cards, Scheduled content calendar, Ad performance metrics
  
  Developer: Sprint board summary, My tasks table, Time logged bar chart

CONTROLLER — Http/Controllers/VizController.php
- GET /api/v1/viz/query — runs VizQueryEngine::query() with user's role-scoped permissions
- GET /api/v1/dashboards — list user's dashboards
- POST /api/v1/dashboards — create dashboard
- PUT /api/v1/dashboards/{id} — save layout
- GET /api/v1/dashboards/{id}/data — returns dashboard with all widget data in one call (parallel queries)
- GET /api/v1/viz/kpis — list available KPI definitions for this org
- GET /api/v1/viz/metric-sparkline?metric=X&days=30 — quick sparkline data

CACHING:
Cache all VizQueryEngine results in Redis with key: viz:{org_id}:{metric_key}:{hash_of_filters}
TTL: 5 minutes for real-time metrics, 1 hour for historical data.
Invalidate cache on new metric_snapshots insert.

Include routes and a VizQueryEngineTest with sample assertions.
```

---

## TASK 3.5 — Task engine (role-based auto-spawn)

**AI to use:** Claude / Cursor

---

**PROMPT:**

```
Build the Task Engine module for Digicloudify (Laravel 11). This module handles role-based task creation, auto-spawning, SLA enforcement, and task automation.

Location: app/Modules/TaskEngine/

TASK SPAWNER SERVICE — Services/TaskSpawnerService.php
This was referenced in Phase 1. Now build it fully.

Method: spawnFromTemplate(Project $project): Collection
1. Load all active task_templates for the project's type
2. Sort by sort_order
3. Resolve dependencies (depends_on array → map sort_order to actual task IDs)
4. For each template, create a Task:
   - status: backlog
   - role_required: from template
   - assigned_to: null (will be auto-assigned if matching user exists)
   - sla_hours: from template
   - estimated_hours: from template
   - meta.spawned_from_template: true
   - meta.template_sort_order: for dependency tracking
5. After all tasks created, set up dependency tracking (store in task meta)
6. Auto-assign tasks where only one user has the required role in the project team

Method: spawnCustomTask(Project $project, array $taskData, User $creator): Task
Creates a single task outside of templates, with full validation.

SLA ENGINE — Services/SlaEngine.php
Method: checkAllSLAs(): void — called every 30 minutes by scheduler
1. Find all incomplete tasks where sla_hours is set
2. For each task, calculate: sla_deadline = created_at + sla_hours
3. If sla_deadline is within 4 hours → fire SlaWarning event
4. If sla_deadline has passed and task is not done/cancelled → fire SlaBreached event, set sla_breached_at

Method: getSlaStatus(Task $task): string — returns "ok" | "warning" | "breached" | "na"

Events & Listeners:
- SlaWarning(Task $task) → SendSlaWarningNotification listener
- SlaBreached(Task $task) → SendSlaBreachedNotification listener + trigger Make webhook

ROLE ASSIGNMENT SERVICE — Services/RoleAssignmentService.php
Method: suggestAssignees(Task $task): Collection
- Based on task.role_required, return users in the same organization with that role
- Rank by: current task load (fewest open tasks first)

Method: autoAssignIfPossible(Task $task): ?User
- If exactly one user has the required role in the org → auto-assign
- If multiple → leave unassigned, notify PM to assign

TASK DEPENDENCY SERVICE — Services/TaskDependencyService.php
Method: checkDependenciesMet(Task $task): bool
- Returns true if all tasks this one depends on are in status "done"

Method: unlockDependentTasks(Task $task): Collection
- When a task is completed, find all tasks that depend on it
- If all their dependencies are now met, move them from "backlog" to "todo"
- Return the unlocked tasks

BULK OPERATIONS:
Method: bulkUpdateStatus(array $taskIds, string $status, User $actor): int
Method: bulkAssign(array $taskIds, User $assignee, User $actor): int
Method: bulkMove(array $taskIds, Sprint $sprint): int

COMMAND — artisan tasks:check-slas
Schedule: every 30 minutes.

COMMAND — artisan tasks:unlock-dependencies
Schedule: every 10 minutes. Checks all completed tasks and unlocks any dependent tasks.

Include all events, listeners, and a TaskEngineTest with SLA calculation assertions.
```

---

---

# PHASE 4 — Frontend (React + Inertia.js)

**Goal:** Role-based UI with all modules wired to the API. Each role sees a tailored workspace.

**Duration estimate:** 7–10 days

---

## TASK 4.1 — Inertia.js + React setup

**AI to use:** Cursor

---

**PROMPT:**

```
Set up the React + Inertia.js frontend for the Digicloudify platform (Laravel 11 backend).

Install and configure:
- @inertiajs/react
- @vitejs/plugin-react
- Tailwind CSS 3
- Headless UI (@headlessui/react)
- Lucide React (icons)
- Recharts (data visualization)
- @dnd-kit/core + @dnd-kit/sortable (drag and drop for kanban)
- React Query (TanStack Query v5) — for server state management
- Zustand — for client state (sidebar open/closed, active filters)
- React Hook Form + Zod — for forms and validation
- date-fns — date utilities

PROJECT STRUCTURE (resources/js/):
resources/js/
  app.jsx — Inertia bootstrap
  layouts/
    AppLayout.jsx — main authenticated layout with sidebar + header
    GuestLayout.jsx — for login/register
  components/
    ui/ — reusable UI primitives (Button, Input, Select, Modal, Badge, Avatar, Tooltip, Dropdown)
    charts/ — chart wrappers (LineChart, BarChart, MetricCard, FunnelChart, PieChart)
    tasks/ — TaskCard, TaskRow, KanbanColumn, TaskForm
    projects/ — ProjectCard, ProjectList, ProjectForm
    reports/ — ReportCard, ReportPreview
    briefing/ — BriefingCard, BriefingDigest
    notifications/ — NotificationBell, NotificationList
  pages/
    Auth/ — Login.jsx, Register.jsx, ForgotPassword.jsx
    Dashboard/ — Index.jsx (role-aware dashboard)
    Projects/ — Index.jsx, Show.jsx, Create.jsx, Edit.jsx
    Tasks/ — Index.jsx, Show.jsx, Kanban.jsx
    Sprints/ — Index.jsx, Show.jsx
    Reports/ — Index.jsx, Show.jsx, Create.jsx
    Briefings/ — Index.jsx, Show.jsx
    DataViz/ — Index.jsx (dashboard builder)
    Settings/ — Organization.jsx, Profile.jsx, MCP.jsx, Team.jsx
    Admin/ — Users.jsx, Roles.jsx
  hooks/
    useAuth.js — current user, roles, permissions
    usePermission.js — hasPermission(resource, action) hook
    useMCP.js — MCP connection status
    useNotifications.js — real-time notifications via Echo
  stores/
    uiStore.js — sidebar, modals, active filters
  utils/
    formatters.js — currency (INR), dates, percentages
    api.js — Axios instance with CSRF and auth headers

ROLE-AWARE SIDEBAR:
The sidebar navigation must show different items per role:
- CEO: Dashboard, Reports, Team, Clients, Settings
- Project Manager: Dashboard, Projects, Tasks, Sprints, Team, Reports, Clients
- Analyst: Dashboard, Reports, Data Viz, Metrics, Briefings
- Marketer: Dashboard, Tasks (my tasks), Campaigns, Content Calendar, Briefings
- Developer: Dashboard, Tasks (my tasks), Sprints, Time Tracking

Implement useAuth() hook that reads the authenticated user and their roles from Inertia shared props. Implement usePermission() that calls hasPermission(resource, action) and gates UI elements.

Set up Laravel Echo with Pusher (or Soketi for local dev) for real-time notifications.

Generate:
- vite.config.js
- tailwind.config.js (with Digicloudify brand colors: primary #2563EB, accent #7C3AED)
- app.jsx with Inertia setup and React Query provider
- AppLayout.jsx with role-aware sidebar
- All hook files
- All store files
- The ui/ component library (Button, Input, Select, Modal, Badge, Avatar)
```

---

## TASK 4.2 — Dashboard page (role-aware)

**AI to use:** Cursor / Claude

---

**PROMPT:**

```
Build the Dashboard page for Digicloudify (React + Inertia.js).

File: resources/js/pages/Dashboard/Index.jsx

The dashboard is fully role-aware. Each role gets a different set of default widgets. Users can customise their layout (drag-and-drop widgets, add/remove).

DATA FETCHING:
- On load: GET /api/v1/dashboards (get user's saved dashboard or default for their role)
- GET /api/v1/dashboards/{id}/data — fetches all widget data in one call
- Use React Query with a 5-minute stale time and background refetch

WIDGET SYSTEM:
Create a WidgetRenderer.jsx component that accepts a widget config object and renders the correct chart:
- "metric_card" → MetricCard component (large number, label, trend arrow, % change)
- "line_chart" → Recharts LineChart with date X axis
- "bar_chart" → Recharts BarChart
- "pie_chart" → Recharts PieChart with legend
- "table" → Sortable table with pagination
- "task_list" → List of tasks with status badges and assignee avatars

CEO DASHBOARD (default):
Row 1: 4 metric cards — Active Projects, Open Tasks, Total Ad Spend (MTD in INR), Team Utilization %
Row 2: Line chart — Monthly revenue / project count trend (6 months)
Row 3: Bar chart — Team task completion by member | Pie chart — Projects by status

PROJECT MANAGER DASHBOARD:
Row 1: 4 cards — My Projects, Overdue Tasks, SLA Breaches Today, Unassigned Tasks
Row 2: Table — Active projects with progress bar, PM, deadline, status
Row 3: Bar chart — Team workload (tasks per person) | Sprint velocity line chart

ANALYST DASHBOARD:
Row 1: 4 cards — Ad Spend Yesterday, Total Clicks, ROAS, Active Campaigns
Row 2: Line chart — Spend trend last 30 days
Row 3: Bar chart — Campaign performance comparison | Table — Top performing ads

MARKETER DASHBOARD:
Row 1: 3 cards — My Tasks Due Today, Scheduled Posts (this week), Active Campaigns
Row 2: Table — My tasks with status, project, due date, priority badge
Row 3: Small metric cards — key campaign stats

DEVELOPER DASHBOARD:
Row 1: 3 cards — Tasks In Progress, Sprint Days Remaining, Hours Logged This Week
Row 2: Sprint board summary (mini kanban: columns todo/in_progress/in_review/done with task counts)
Row 3: Table — My tasks sorted by due date

INTERACTIVITY:
- Every metric card and chart has a refresh button
- Date range filter at top of page — filters all widgets
- Project/client filter — optional
- "Edit Dashboard" button → enters drag-and-drop mode using @dnd-kit
- In edit mode: drag widgets to reposition, click X to remove, click "Add Widget" to open widget picker
- On save: PUT /api/v1/dashboards/{id} with new layout

Use Tailwind CSS for all styling. Brand colors: primary-600 (#2563EB) for charts, accent-600 (#7C3AED) for secondary charts.
```

---

## TASK 4.3 — Projects and Kanban board

**AI to use:** Cursor

---

**PROMPT:**

```
Build the Projects module frontend for Digicloudify (React + Inertia.js).

FILES:
- resources/js/pages/Projects/Index.jsx — project list view
- resources/js/pages/Projects/Show.jsx — single project view with tabs
- resources/js/pages/Projects/Create.jsx — new project form
- resources/js/pages/Tasks/Kanban.jsx — kanban board

PROJECTS INDEX (Index.jsx):
- Grid of ProjectCards, each showing: name, client, type badge, status badge, progress bar (% tasks done), PM avatar, deadline, budget used
- Filter bar: by status, type, client, PM
- Sort by: deadline, created, name, progress
- "New Project" button (PM/CEO only) → opens Create modal or navigates to Create page
- Search input — live search by project name or client

PROJECT SHOW (Show.jsx):
Tabs: Overview | Tasks | Kanban | Sprints | Milestones | Reports | Files | Settings

Overview tab:
- Project header: name, type, status badge, priority, client name, PM avatar
- Stats row: Tasks (done/total), Budget used, Time logged, Days remaining
- Recent activity feed from task_logs
- Team members with their open task counts

Tasks tab:
- Filterable table of all tasks: title, type, assignee avatar, status badge, priority, due date, SLA indicator
- Bulk actions: change status, assign, move to sprint
- "Add Task" button

Kanban tab — Kanban.jsx:
- Columns: Backlog | To Do | In Progress | In Review | Blocked | Done
- Task cards: title, assignee avatar, priority dot, due date, SLA warning icon if applicable
- Drag and drop between columns using @dnd-kit (call PATCH /api/v1/tasks/{id} on drop)
- Collapsed backlog column (expandable)
- Filter by: assignee, sprint, priority
- "Add task" in any column

Sprints tab:
- List of sprints with status, dates, velocity
- Active sprint highlighted with progress bar
- "Start Sprint" / "Complete Sprint" buttons

TASK FORM (components/tasks/TaskForm.jsx):
Used for create and edit. Fields:
- Title (required), Description (rich text — use a simple textarea for now)
- Type (select with icons), Status, Priority
- Assigned To (user search/select, shows avatar)
- Sprint (select from project's sprints)
- Milestone (select)
- Due Date (date picker)
- Estimated Hours (number)
- SLA Hours (number, for PMs)
- Tags (multi-input)
Submit calls POST /api/v1/tasks or PATCH /api/v1/tasks/{id}.

PERMISSION GATES:
- Only PM/CEO can create projects, change sprint, manage milestones
- All roles can view (within their org)
- Task assignment: PM can assign anyone; marketer/dev/analyst can self-assign
Use the usePermission() hook to conditionally render action buttons.
```

---

## TASK 4.4 — Daily Briefing page

**AI to use:** Claude

---

**PROMPT:**

```
Build the Daily Briefing frontend for Digicloudify (React + Inertia.js).

FILES:
- resources/js/pages/Briefings/Index.jsx — list of past briefings
- resources/js/pages/Briefings/Show.jsx — read a single briefing
- resources/js/components/briefing/BriefingDigest.jsx — renders the AI HTML digest

INDEX PAGE:
- List of briefings for the current user, sorted by date descending
- Each row: date, status badge (pending/ready/delivered/failed), delivery channels (icons for email/zoho/in-app), preview snippet
- "Generate Today's Briefing" button at top → POST /api/v1/briefings/generate → shows loading spinner → redirects to new briefing
- Filter by date range

SHOW PAGE:
- Header: "Good morning, {name}" + date formatted as "Thursday, 21 May 2026"
- Status badge + delivery timestamp
- Render the digest_html from the API inside a styled container (the HTML is already formatted by Claude — just render it safely with dangerouslySetInnerHTML in a scoped div)
- "Resend Briefing" button → POST /api/v1/briefings/{id}/resend
- "View Raw Data" toggle → shows the digest_raw JSON in a collapsible section (useful for debugging)
- Delivery status section: shows which channels it was sent to and the status (sent/failed)

BRIEFING PREFERENCES (in Settings/Profile.jsx, briefing section):
- Toggle: Enable daily briefing (on/off)
- Delivery time: time picker (default 7:00 AM)
- Delivery channels: checkboxes for email, Zoho Cliq, in-app
- Include sections: checkboxes for tasks, calendar, metrics, emails, team (filter what Claude sees)
- Submit: PUT /api/v1/briefings/preferences

REAL-TIME:
When a briefing status changes from "generating" to "ready", push a notification via Laravel Echo. On the Index page, update the list in real-time without a page refresh. Show a toast: "Your briefing for today is ready!" with a link.

Use Tailwind for styling. The briefing digest HTML is styled inside a .briefing-content CSS class (add styles in app.css for h2, h3, ul, strong, hr inside .briefing-content).
```

---

## TASK 4.5 — Reports and Data Viz page

**AI to use:** Cursor / Claude

---

**PROMPT:**

```
Build the Reports and Data Viz pages for Digicloudify (React + Inertia.js).

FILES:
- resources/js/pages/Reports/Index.jsx
- resources/js/pages/Reports/Create.jsx
- resources/js/pages/Reports/Show.jsx
- resources/js/pages/DataViz/Index.jsx — dashboard builder

REPORTS INDEX:
- Table: title, type badge, project/client, date range, status badge, generated_at, actions (view/download/send)
- Filter by: type, status, client, project, date range
- "Generate Report" button (analyst/PM only)
- "Scheduled Reports" tab — list of scheduled reports with next_run_at

REPORT CREATE:
Step 1 — Choose template: card grid showing SEO Report, Ads Report, Social Report, Sprint Report, Full Service. Click to select.
Step 2 — Configure:
  - Title (auto-filled from template + project name)
  - Project (select) + Client (auto-filled from project)
  - Date range: preset (last 7d, last 30d, last month, last quarter) or custom
  - Include sections: checklist of template sections (can exclude some)
  - Recipients: multi-email input
Step 3 — Review + Generate
  - Shows summary of what will be included
  - "Generate Now" button → POST /api/v1/reports → shows spinner → polls GET /api/v1/reports/{id} every 3 seconds until status = "ready" → shows success with download link

REPORT SHOW:
- Report header: title, type, date range, client/project
- Status banner with generated_at time
- Preview: embed the PDF in an <iframe> or provide a "Preview" button that opens the PDF URL in a new tab
- Download button (calls GET /api/v1/reports/{id}/download → redirect to signed S3 URL)
- Send button: opens modal to confirm recipients → POST /api/v1/reports/{id}/send

DATA VIZ / DASHBOARD BUILDER (DataViz/Index.jsx):
This is the advanced analytics page for analysts and CEOs.

Left panel (collapsible):
- KPI Library: list of all kpi_definitions for the org (name, unit, source icon)
- Drag a KPI from the library onto the canvas to add a widget

Center canvas:
- Grid layout (12 columns)
- Existing widgets rendered via WidgetRenderer.jsx (built in Task 4.2)
- In "Edit" mode: widgets are draggable/resizable, have a settings icon and remove button

Widget settings modal (when clicking ⚙️):
- Title (text)
- Chart type (select: line, bar, pie, metric_card, table)
- Date range (preset or custom)
- Group by (day/week/month/campaign)
- Compare to (none/previous period)
- Filters (project, client, dimension_1 filter)
- Preview button — live preview of the query result

Top bar:
- Dashboard name (editable inline)
- Date range filter (applies to all widgets simultaneously)
- "Edit" / "Save" toggle button
- "Export as PDF" button → generates a PDF screenshot of the dashboard (use browser print)
- "Share" button (PM/CEO only) → copies a link

Use Recharts for all charts. All monetary values formatted as ₹X,XX,XXX (Indian number format using Intl.NumberFormat with locale 'en-IN').
```

---

## TASK 4.6 — Settings and MCP connections UI

**AI to use:** Cursor

---

**PROMPT:**

```
Build the Settings pages for Digicloudify (React + Inertia.js).

FILES:
- resources/js/pages/Settings/Profile.jsx
- resources/js/pages/Settings/Organization.jsx
- resources/js/pages/Settings/Team.jsx
- resources/js/pages/Settings/MCP.jsx — MCP integrations manager
- resources/js/pages/Settings/Roles.jsx — role & permission manager (CEO only)

SETTINGS LAYOUT:
Left sidebar with links: Profile | Organization | Team | Integrations | Roles | Notifications | Billing

MCP INTEGRATIONS PAGE (Settings/MCP.jsx):
This is the most important settings page.

TOP SECTION — Connected integrations:
Grid of integration cards. Each card shows:
- Provider logo/icon + name (Google Calendar, Gmail, Google Drive, Notion, Zoho Cliq, Meta Ads, Make)
- Status badge: Connected (green) | Disconnected (gray) | Error (red)
- Last synced: "2 minutes ago"
- "Sync Now" button (calls POST /api/v1/mcp/{id}/sync)
- "Test Connection" button (calls POST /api/v1/mcp/{id}/test → shows result toast)
- "Settings" button → opens provider-specific settings modal
- "Disconnect" button (with confirmation)

CONNECT NEW INTEGRATION:
"Add Integration" button → opens modal with grid of all available providers
Click a provider → opens OAuth flow or API key input form, depending on provider:
- Google (Calendar/Gmail/Drive): OAuth2 → "Connect with Google" button opens popup
- Notion: Paste Internal Integration Token
- Zoho Cliq: OAuth2 → "Connect with Zoho" button
- Meta Ads: Paste Access Token + Ad Account IDs (comma-separated)
- Make: Paste API Key + Team ID

PROVIDER-SPECIFIC SETTINGS MODALS:
Google Calendar settings:
  - Which calendars to sync (multi-select from available calendars — fetched after connecting)
  - Sync direction: Inbound only / Both ways
  - Default push calendar (for task due dates)

Meta Ads settings:
  - Ad Account IDs (one per line)
  - Metrics to pull (checkboxes)
  - Date range default

Notion settings:
  - Database mappings: for each Notion database, map to a local entity type (tasks/sops/projects)
  - Push updates back to Notion: toggle

Make settings:
  - Webhook URL mappings: for each event type, paste the Make webhook URL
  - Inbound secret: a generated token for Make → Digicloudify webhooks

SYNC LOG VIEWER:
Bottom of the page: expandable table of recent sync log entries. Columns: provider, direction, status, records, date. Click a row to see the full payload in a modal (useful for debugging).

TEAM PAGE (Settings/Team.jsx):
- Table of all users: name, email, role badge, status, last active
- "Invite User" button: email + role selector → POST /api/v1/team/invite (sends email invitation)
- Change role: click role badge → dropdown to change
- Deactivate user: toggle

All pages use Tailwind. CEO-only pages check usePermission('settings', 'manage_settings') and redirect to /dashboard if unauthorized.
```

---

---

# PHASE 5 — Notifications & Real-time

**Goal:** Multi-channel notifications fully wired. Real-time updates in the UI.

**Duration estimate:** 3–4 days

---

## TASK 5.1 — Notification system

**AI to use:** Claude

---

**PROMPT:**

```
Build the complete Notification system for Digicloudify (Laravel 11).

Location: app/Modules/Notifications/

NOTIFICATION TYPES (enum NotificationType):
- task_assigned: someone assigned a task to you
- task_commented: someone commented on your task
- task_mentioned: someone @mentioned you
- sla_warning: your task SLA deadline is within 4 hours
- sla_breached: your task SLA has been breached
- project_update: a project you're on was updated
- report_ready: a report you requested is ready
- briefing_ready: your daily briefing is ready
- campaign_alert: ad spend anomaly detected
- system: platform announcements

NOTIFICATION SERVICE — Services/NotificationService.php
Method: send(User $user, NotificationType $type, array $data, array $channels): void
- Creates a record in notifications_log
- Dispatches SendNotificationJob for each requested channel

CHANNELS:
1. InAppChannel: creates the notifications_log record (already done in send())
2. EmailChannel: uses Laravel Mailables, sends via configured mail driver
3. ZohoCliqChannel: uses ZohoCliqAdapter to send DM to user
4. WhatsAppChannel: calls WhatsApp Business API (configurable webhook URL in org settings)
5. PushChannel: uses Laravel broadcasting to push via Pusher/Soketi

NOTIFICATION TEMPLATES (per type, per channel):
Create Blade templates for email:
- resources/views/emails/notifications/task_assigned.blade.php
- resources/views/emails/notifications/sla_warning.blade.php
- resources/views/emails/notifications/report_ready.blade.php
- resources/views/emails/notifications/briefing_ready.blade.php

Zoho Cliq message templates (plain text with card format):
- task_assigned: "📋 New task assigned: {title}\nProject: {project}\nDue: {due_date}\n[View Task]({url})"
- sla_warning: "⚠️ SLA Warning: {title} is due in {hours}h\n[View Task]({url})"

USER PREFERENCES:
Each user has notification_preferences in their preferences JSONB:
{
  "channels": {
    "task_assigned": ["in_app", "email"],
    "sla_warning": ["in_app", "zoho_cliq", "email"],
    "sla_breached": ["in_app", "zoho_cliq", "email", "whatsapp"],
    "report_ready": ["in_app", "email"],
    "briefing_ready": ["in_app", "zoho_cliq"],
    "campaign_alert": ["in_app", "zoho_cliq"]
  },
  "quiet_hours": { "from": "22:00", "to": "07:00" },
  "timezone": "Asia/Kolkata"
}

Quiet hours: don't send email/Zoho/WhatsApp during quiet hours. In-app always goes through.

CONTROLLER — Http/Controllers/NotificationController.php
- GET /api/v1/notifications — list unread first, paginated
- POST /api/v1/notifications/{id}/read — mark read
- POST /api/v1/notifications/read-all — mark all read
- GET /api/v1/notifications/unread-count — returns { count: N } (for the bell badge)
- DELETE /api/v1/notifications/{id} — dismiss

BROADCASTING:
Create NotificationSent event that broadcasts to the user's private channel:
- Channel: private-user.{userId}
- Event: .notification.new
- Payload: { id, type, title, body, data, created_at }

Frontend listens via useNotifications() hook (using Echo) and updates the notification bell badge in real-time.

Include all jobs, listeners, routes, and a NotificationServiceTest.
```

---

---

# PHASE 6 — DevOps, Testing & Launch

**Goal:** CI/CD pipeline, production-ready deployment, and test coverage.

**Duration estimate:** 3–5 days

---

## TASK 6.1 — GitHub Actions CI/CD

**AI to use:** Claude / ChatGPT

---

**PROMPT:**

```
Create a complete GitHub Actions CI/CD pipeline for Digicloudify (Laravel 11 + React + Postgres + Redis).

FILES TO CREATE:
- .github/workflows/ci.yml — runs on every PR
- .github/workflows/deploy-staging.yml — deploys to staging on merge to develop branch
- .github/workflows/deploy-production.yml — deploys to production on merge to main (with manual approval gate)

CI WORKFLOW (.github/workflows/ci.yml):
Triggers: pull_request to main or develop

Jobs:
1. php-tests:
   - Ubuntu latest, PHP 8.3
   - Services: postgres:16, redis:7
   - Steps:
     a. Checkout
     b. Setup PHP with extensions: pdo_pgsql, redis, bcmath, gd, zip
     c. Install Composer dependencies
     d. Copy .env.testing → .env
     e. Generate app key
     f. Run migrations: php artisan migrate --database=testing
     g. Seed test data: php artisan db:seed --class=TestingSeeder
     h. Run PHPUnit: php artisan test --parallel --coverage-clover=coverage.xml
     i. Upload coverage to Codecov

2. frontend-checks:
   - Ubuntu latest, Node 20
   - Steps:
     a. Checkout
     b. Install npm dependencies
     c. Run ESLint: npm run lint
     d. Run TypeScript check (if using TS): npm run type-check
     e. Run Vitest unit tests: npm run test
     f. Build assets: npm run build (ensure no build errors)

3. code-quality:
   - Run Laravel Pint (code style): ./vendor/bin/pint --test
   - Run PHPStan level 6: ./vendor/bin/phpstan analyse

DEPLOY TO STAGING (.github/workflows/deploy-staging.yml):
Triggers: push to develop branch
- SSH into staging server (use GitHub secret STAGING_SSH_KEY, STAGING_HOST, STAGING_USER)
- Commands on server:
  cd /var/www/digicloudify-staging
  git pull origin develop
  composer install --no-dev --optimize-autoloader
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan migrate --force
  npm ci && npm run build
  php artisan horizon:terminate && php artisan horizon (restart Horizon)
  php artisan octane:reload
- Send Slack/Zoho notification on success or failure

DEPLOY TO PRODUCTION:
Same as staging but:
- Requires manual approval (use GitHub environments with required reviewers)
- Zero-downtime: use php artisan down before migrate, php artisan up after
- Tag the release automatically: git tag v$(date +%Y%m%d%H%M%S)
- Create GitHub Release with changelog

SECRETS NEEDED (document in a .github/SECRETS.md):
- APP_KEY_STAGING / APP_KEY_PRODUCTION
- DB_PASSWORD_STAGING / DB_PASSWORD_PRODUCTION
- STAGING_SSH_KEY / PRODUCTION_SSH_KEY
- AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY
- ANTHROPIC_API_KEY
- All MCP credentials as secrets

Include a .env.testing file for the test environment (SQLite or test Postgres DB).
```

---

## TASK 6.2 — Test suite

**AI to use:** Claude

---

**PROMPT:**

```
Generate a comprehensive test suite for the Digicloudify platform (Laravel 11, PHPUnit).

Create tests in tests/ directory:

FEATURE TESTS (tests/Feature/):

1. AuthTest.php
   - test_user_can_register_with_valid_data
   - test_registration_creates_organization_and_admin_role
   - test_user_can_login_with_correct_credentials
   - test_login_fails_with_wrong_password
   - test_authenticated_user_cannot_access_other_org_data (critical: multi-tenancy isolation)

2. ProjectTest.php
   - test_pm_can_create_project
   - test_project_creation_spawns_tasks_from_template
   - test_analyst_cannot_create_project
   - test_project_stats_return_correct_data
   - test_project_scoped_to_organization

3. TaskTest.php
   - test_task_can_be_created_with_valid_data
   - test_task_status_transition_is_logged
   - test_sla_warning_fired_when_near_deadline
   - test_sla_breach_recorded_when_past_deadline
   - test_task_dependency_unlocked_when_dependency_completes
   - test_bulk_status_update

4. MCPTest.php
   - test_mcp_connection_created_with_encrypted_credentials
   - test_sync_job_dispatched_on_manual_sync
   - test_sync_failure_marks_connection_as_error
   - test_webhook_routed_to_correct_adapter

5. BriefingTest.php
   - test_briefing_data_collector_returns_correct_structure
   - test_briefing_generator_calls_claude_api_with_correct_prompt
   - test_briefing_not_sent_during_quiet_hours
   - test_briefing_delivered_to_configured_channels

6. ReportTest.php
   - test_report_can_be_generated_for_seo_template
   - test_report_pdf_created_and_stored_in_s3
   - test_report_sent_to_recipients

UNIT TESTS (tests/Unit/):

1. SlaEngineTest.php
   - test_sla_status_ok_when_deadline_far
   - test_sla_status_warning_when_within_4_hours
   - test_sla_status_breached_when_past_deadline
   - test_na_returned_for_tasks_without_sla

2. TaskSpawnerTest.php
   - test_correct_number_of_tasks_spawned_for_seo_project
   - test_task_dependencies_resolved_correctly
   - test_tasks_auto_assigned_when_single_role_user

3. VizQueryEngineTest.php
   - test_metric_query_returns_correct_aggregation
   - test_date_grouping_by_day_returns_correct_labels
   - test_compare_to_previous_period_returns_change_pct

4. PermissionTest.php
   - test_analyst_has_view_permission_on_reports
   - test_analyst_cannot_create_projects
   - test_ceo_has_all_permissions
   - test_client_role_has_read_only_access

FACTORIES:
Create Laravel factories for: Organization, User, Role, Client, Project, Sprint, Task, McpConnection, MetricSnapshot, Report, DailyBriefing

TESTING HELPERS:
- TestCase.php base class: actingAsRole(string $role): TestCase method that creates a user with that role and authenticates
- ApiTestCase.php: adds assertApiSuccess(), assertApiError(), assertJsonHasStructure() helpers

Create a TestingSeeder that seeds minimal data for a complete test environment.

Use Mockery to mock the Claude API, Google Calendar API, and Meta Ads API in all tests that touch external services.
```

---

---

# APPENDIX — Prompt Toolbox

## Quick prompts for common situations

### When you hit a bug
```
I'm building Digicloudify, a Laravel 11 + React + Inertia.js platform for a digital marketing agency.

[PASTE THE ERROR]

Context:
- The error occurs in [file/location]
- I was trying to [what you were doing]
- Relevant code:

[PASTE RELEVANT CODE]

Fix this bug and explain why it happened.
```

### When you need a new API endpoint fast
```
Add a new API endpoint to the Digicloudify Laravel platform.

Endpoint: [METHOD] /api/v1/[path]
Purpose: [what it does]
Auth: Required (auth:sanctum). Role required: [role].
Input: [describe request body/params]
Output: [describe response shape]

Follow the existing patterns in the codebase:
- Use Form Request for validation
- Return via ApiResponse::success() helper
- Scope all queries to $request->user()->organization_id
- Log relevant actions to task_logs or mcp_sync_logs
```

### When you need a new React component
```
Build a React component for the Digicloudify platform.

Component: [ComponentName].jsx
Location: resources/js/components/[folder]/
Purpose: [what it does]

Props: [list props]
State: [what state it manages]
API calls: [which endpoints it hits]
Styling: Tailwind CSS. Primary color: blue-600. Accent: violet-600.
Follow the patterns of existing components (shadcn-style, clean, minimal).

Edge cases to handle: loading state, empty state, error state.
```

### When adding a new MCP provider
```
Add a new MCP adapter to Digicloudify for [PROVIDER NAME].

Base class to extend: app/Modules/MCP/Adapters/BaseAdapter.php
Interface to implement: app/Modules/MCP/Contracts/MCPAdapter.php

Provider API docs: [paste or describe the API]
Auth method: [OAuth2 / API Key / etc.]

Sync direction: [inbound / outbound / both]
What to pull: [describe entities]
What to push: [describe what we send]

Follow the exact pattern of GoogleCalendarAdapter.php.
```

---

*Document generated for Digicloudify | Saanetix Solutions Private Limited | Hyderabad*
*Founder: Naveen Kumar Adicharla*
