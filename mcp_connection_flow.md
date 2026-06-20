# Model Context Protocol (MCP) Integration Flow

This document outlines the complete working flow and connection mechanics for the Model Context Protocol (MCP) integrations within the Digicloudify platform.

## 1. Overview

The MCP module in Digicloudify connects the internal platform to external services (Google Calendar, Gmail, Notion, Zoho Cliq, Meta Ads, Make.com) to synchronize data and perform actions on behalf of the organization.

The architecture enforces strict decoupling:
*   **Controllers** never communicate directly with external APIs.
*   **Adapters** (`MCPAdapter` interface) handle all external HTTP requests and data translation.
*   **Sync Jobs** (via Laravel Horizon) manage asynchronous, scheduled data pulling.
*   **Webhooks** provide real-time updates that supplement the scheduled syncs.

## 2. Connection Flow & Setup

### A. Creating a Connection
The connection flow is managed through the `McpWebController` and the frontend settings UI.
1. **User Action:** An authorized user navigates to the MCP Settings page (`/settings/mcp`).
2. **Provider Selection:** The user selects a built-in provider (e.g., `gmail`, `meta_ads`) or configures a custom webhook/API URL.
3. **Credential Submission:** The user provides authentication credentials (API Keys, OAuth Access Tokens, or Username/Password).
4. **Storage:** 
    *   The backend validates the input.
    *   Credentials (`access_token`, `api_key`, etc.) are securely encrypted using Laravel's `Crypt::encryptString()` before saving to the database. They are **never** stored in plaintext.
    *   A new `McpConnection` database record is created, linked to the user's `organization_id`.

### B. Updating a Connection
1. When tokens expire or need refreshing (e.g., OAuth refresh token flow), the connection record is updated.
2. The `McpConnection` model handles decrypting the existing tokens, merging the new tokens, re-encrypting them, and saving the updated state.

## 3. Data Synchronization Flow

The system uses a combination of scheduled syncs and real-time webhooks.

### A. Scheduled Syncs (The Source of Truth)
1. **Trigger:** A scheduled command or a manual test (via `McpWebController::sync`) dispatches the `SyncMcpProviderJob`.
2. **Adapter Initialization:** The job instantiates the specific `MCPAdapter` required for the connection's provider.
3. **Credential Decryption:** The adapter retrieves the decrypted credentials from the `McpConnection` model (`$connection->getDecryptedAccessToken()`).
4. **Data Pull:** The adapter makes HTTP requests to the external API, handling pagination and rate limits.
    *   *Rate Limit Handling:* If a 429 response is received, the adapter waits for the `Retry-After` duration, retries once, and if it fails again, logs the failure and queues it for the next cycle.
5. **Data Ingestion:** The pulled data is normalized into Data Transfer Objects (DTOs) and saved to the local database.
6. **Logging:** The operation's result (success, affected records, duration, or error message) is strictly logged to the `mcp_sync_logs` table.

### B. Webhooks (Real-time Updates)
1. External services push real-time events to dedicated webhook endpoints.
2. **Security Verification:** The webhook controller verifies the signature (e.g., Make.com HMAC-SHA256, Zoho Shared Secret, Google Channel Token).
3. **Event Dispatch:** Valid payloads dispatch Laravel Events (e.g., `ExternalTaskCompleted`).
4. **Resilience:** If a webhook fails or is dropped, the system remains consistent because the next scheduled sync will catch up on any missed data.

## 4. Action Push Flow (Outgoing Data)

When the Digicloudify platform needs to mutate data externally (e.g., create a Notion page or a Google Calendar event):
1. A controller performs a local action and fires a Laravel Event (e.g., `ProjectCreated`).
2. A queued Event Listener catches the event.
3. The Listener instantiates the relevant `MCPAdapter`.
4. The Adapter formats the payload and makes the outgoing API request using the encrypted connection credentials.
5. The outgoing action and its result are logged in `mcp_sync_logs`.

## 5. Security & Authorization
*   **Organization Isolation:** `McpConnection` records are scoped to `organization_id` via the `OrganizationScope`. A connection can only be used to interact with resources belonging to that specific organization.
*   **Encrypted Storage:** All tokens are encrypted at rest.
*   **API Exposure:** Tokens are explicitly filtered out before returning connection data to the frontend (e.g., `array_filter` in `McpWebController`).
