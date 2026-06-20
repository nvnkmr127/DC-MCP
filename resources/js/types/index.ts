export interface User {
    id: string;
    name: string;
    email: string;
    avatar_url: string | null;
    organization_id: string;
    timezone: string;
    preferences: Record<string, any>;
    roles: string[];
}

export interface Organization {
    id: string;
    name: string;
    slug: string;
    logo_url: string | null;
    timezone: string;
    currency: string;
    settings: Record<string, any>;
}

export interface Project {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    // DB enum: draft | planning | active | on_hold | completed | cancelled
    // 'planning' added via migration 2026_06_03_000002; 'draft' kept for legacy rows.
    status: 'planning' | 'draft' | 'active' | 'on_hold' | 'completed' | 'cancelled';
    // DB enum: low | medium | high | critical
    priority: 'low' | 'medium' | 'high' | 'critical';
    type: string;
    start_date: string | null;
    end_date: string | null;
    budget: number;
    budget_used: number;
    // DB column is NOT NULL — always a uuid, but we allow null in the TS shape
    // because the web form and some API responses expose it as nullable.
    client_id: string | null;
    project_manager_id: string | null;
    client?: Client;
    manager?: User;
    tags: string[];
    created_at: string;
}

export interface Task {
    id: string;
    project_id: string;
    title: string;
    description: string | null;
    status: 'backlog' | 'todo' | 'in_progress' | 'in_review' | 'blocked' | 'done' | 'cancelled';
    // DB enum: low | medium | high | critical  (NOT 'urgent')
    priority: 'low' | 'medium' | 'high' | 'critical';
    assigned_to: string | null;
    assignee?: User;
    due_date: string | null;
    estimated_hours: number;
    actual_hours: number;
    tags: string[];
    created_at: string;
    project?: Project;
}

export interface Sprint {
    id: string;
    project_id: string;
    name: string;
    goal: string | null;
    status: 'planning' | 'active' | 'completed' | 'cancelled';
    start_date: string | null;
    end_date: string | null;
}

export interface Client {
    id: string;
    name: string;
    // DB: NOT NULL without default — nullable in API responses when missing
    email: string | null;
    phone: string | null;
    website: string | null;
    // DB: NOT NULL without default
    company: string | null;
    industry: string | null;
    // DB enum: basic | standard | premium | enterprise  ('basic' is the DB default)
    tier: 'basic' | 'standard' | 'premium' | 'enterprise';
    // DB enum (after migration): active | inactive | prospect | churned
    status: 'active' | 'inactive' | 'prospect' | 'churned';
    notes: string | null;
    assigned_to: string | null;
    manager?: User;
    projects_count?: number;
    created_at?: string;
}

export interface Comment {
    id: string;
    commentable_type: string;
    commentable_id: string;
    user_id: string;
    user?: User;
    body: string;
    parent_id: string | null;
    replies?: Comment[];
    created_at: string;
    updated_at: string;
}

export interface Attachment {
    id: string;
    attachable_type: string;
    attachable_id: string;
    // DB column is 'uploaded_by'; model appends 'uploader' relation.
    // 'user_id' renamed to 'uploaded_by' to match DB and Attachment model.
    uploaded_by: string | null;
    uploader?: User;
    filename: string;
    original_filename: string;
    mime_type: string;
    // DB column: size_bytes (bigint); model accessor exposes it as 'size'.
    size: number;
    url: string;
    created_at: string;
}

export interface Notification {
    id: string;
    type: string;
    channel: string;
    title: string;
    body: string;
    data: Record<string, any> | null;
    status: string;
    // Computed from status === 'read' || read_at !== null — not a DB column.
    is_read: boolean;
    read_at: string | null;
    created_at: string;
}

export interface DailyBriefing {
    id: string;
    date: string;
    status: 'pending' | 'generating' | 'ready' | 'delivered' | 'failed';
    digest_html: string | null;
    digest_text: string | null;
    ai_model: string | null;
    delivered_via: string[];
    delivered_at: string | null;
}

export interface McpConnection {
    id: string;
    provider: string;
    name: string;
    // DB enum: active | disconnected | error | pending | pending_verification | token_expired | rate_limited | partially_active | suspended | quota_exceeded | pending_reauth | degraded
    status: 'pending' | 'active' | 'disconnected' | 'error' | 'pending_verification' | 'token_expired' | 'rate_limited' | 'partially_active' | 'suspended' | 'quota_exceeded' | 'pending_reauth' | 'degraded';
    last_synced_at: string | null;
    sync_error: string | null;
    // Computed server-side from credentials.expires_in + credentials.created
    is_expired: boolean;
    // Computed server-side: true when provider is not in the built-in adapter list
    is_custom: boolean;
    settings: Record<string, any>;
    scopes: string[];
}

export interface TimeEntry {
    id: string;
    task_id: string;
    user_id: string;
    project_id: string;
    hours: number;
    description: string;
    logged_date: string;
    // DB column: is_billable (original creation migration 000008)
    // A duplicate 'billable' column was added by migration 000016 — the model uses is_billable.
    is_billable: boolean;
    task?: Task;
    user?: User;
}

export interface Report {
    id: string;
    organization_id: string;
    project_id: string | null;
    client_id: string | null;
    title: string;
    // DB enum: weekly | monthly | campaign | sprint | custom | client
    type: 'weekly' | 'monthly' | 'campaign' | 'sprint' | 'custom' | 'client';
    // DB enum: draft | generating | ready | sent | archived | failed
    // 'failed' added via migration 2026_06_03_000006
    status: 'draft' | 'generating' | 'ready' | 'sent' | 'archived' | 'failed';
    template: 'seo_report' | 'ads_report' | 'social_report' | 'sprint_report' | 'full_service';
    date_from: string;
    date_to: string;
    generated_file_path: string | null;
    generated_at: string | null;
    sent_at: string | null;
    recipients: string[];
    // Computed relation — null when user is deleted
    generated_by?: { name: string } | null;
    project?: Pick<Project, 'id' | 'name'> | null;
    client?: Pick<Client, 'id' | 'name'> | null;
    created_at: string;
}

export interface PageProps extends Record<string, unknown> {
    auth: {
        user: User | null;
        permissions: Record<string, string[]>;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    app: {
        name: string;
        currency: string;
        timezone: string;
    };
}

export type PaginatedResponse<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};
