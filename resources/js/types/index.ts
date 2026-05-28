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
    status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
    priority: 'low' | 'medium' | 'high' | 'urgent';
    type: string;
    start_date: string | null;
    end_date: string | null;
    budget: number;
    budget_used: number;
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
    priority: 'low' | 'medium' | 'high' | 'urgent';
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
    email: string | null;
    phone: string | null;
    website: string | null;
    company: string | null;
    industry: string | null;
    tier: 'standard' | 'premium' | 'enterprise';
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
    user_id: string;
    user?: User;
    filename: string;
    original_filename: string;
    mime_type: string;
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
    status: 'pending' | 'active' | 'inactive' | 'error';
    last_synced_at: string | null;
    sync_error: string | null;
    is_expired: boolean;
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
    is_billable: boolean;
    task?: Task;
    user?: User;
}

export interface PageProps {
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
