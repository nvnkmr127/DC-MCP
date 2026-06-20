import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, timeAgo } from '@/lib/utils';
import type { Notification, PaginatedResponse } from '@/types';
import { Bell, CheckCheck, AlertTriangle, MessageSquare, Clock, Zap, AlarmClock } from 'lucide-react';

interface Props {
    notifications: PaginatedResponse<Notification>;
    unread_count: number;
}

const TYPE_CONFIG: Record<string, { icon: React.ComponentType<any>; bg: string; color: string }> = {
    task_assigned:  { icon: CheckCheck,    bg: 'bg-indigo-50',  color: 'text-indigo-600' },
    sla_warning:    { icon: AlertTriangle, bg: 'bg-yellow-50',  color: 'text-yellow-600' },
    sla_breached:   { icon: AlertTriangle, bg: 'bg-red-50',     color: 'text-red-600' },
    mention:        { icon: MessageSquare, bg: 'bg-blue-50',    color: 'text-blue-600' },
    briefing_ready: { icon: Zap,           bg: 'bg-emerald-50', color: 'text-emerald-600' },
    report_ready:   { icon: Clock,         bg: 'bg-purple-50',  color: 'text-purple-600' },
    campaign_alert: { icon: Bell,          bg: 'bg-orange-50',  color: 'text-orange-600' },
    system:         { icon: Bell,          bg: 'bg-gray-50',    color: 'text-gray-600' },
};

export default function NotificationsIndex({ notifications, unread_count }: Props) {
    function markRead(id: string) {
        router.post(`/notifications/${id}/read`, {}, { preserveScroll: true });
    }

    function snooze(id: string, e: React.MouseEvent) {
        e.stopPropagation();
        router.post(`/notifications/${id}/snooze`, { hours: 1 }, { preserveScroll: true });
    }

    function markAllRead() {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    }

    return (
        <AppLayout title="Notifications">
            <Head title="Notifications" />

            {/* ── Header bar ── */}
            <div className="flex items-center justify-between mb-5">
                <div className="flex items-center gap-2.5">
                    {unread_count > 0 && (
                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-100 text-indigo-700 text-[12px] font-semibold rounded-full">
                            <span className="w-1.5 h-1.5 rounded-full bg-indigo-500" />
                            {unread_count} unread
                        </span>
                    )}
                </div>
                {unread_count > 0 && (
                    <button
                        onClick={markAllRead}
                        className="flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors"
                    >
                        <CheckCheck size={13} /> Mark all as read
                    </button>
                )}
            </div>

            {/* ── List ── */}
            <div className="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                {notifications.data.length === 0 ? (
                    <div className="p-16 text-center">
                        <div className="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-3">
                            <Bell size={20} className="text-gray-300" />
                        </div>
                        <p className="text-[13px] font-medium text-gray-600 mb-1">You're all caught up</p>
                        <p className="text-[12px] text-gray-400">No notifications at the moment</p>
                    </div>
                ) : (
                    <div className="divide-y divide-gray-50">
                        {notifications.data.map((notif) => {
                            const cfg = TYPE_CONFIG[notif.type] ?? TYPE_CONFIG.system;
                            const Icon = cfg.icon;
                            return (
                                <div
                                    key={notif.id}
                                    className={cn(
                                        'flex gap-4 px-5 py-4 hover:bg-gray-50/80 transition-colors cursor-pointer group',
                                        !notif.is_read && 'bg-indigo-50/30',
                                    )}
                                    onClick={() => !notif.is_read && markRead(notif.id)}
                                >
                                    <div className={cn('w-9 h-9 rounded-xl flex items-center justify-center shrink-0', cfg.bg)}>
                                        <Icon size={16} className={cfg.color} />
                                    </div>

                                    <div className="flex-1 min-w-0">
                                        <p className={cn('text-[13px] text-gray-900 leading-snug', !notif.is_read ? 'font-semibold' : 'font-medium')}>
                                            {notif.title}
                                        </p>
                                        <p className="text-[12px] text-gray-500 mt-0.5 leading-snug">{notif.body}</p>
                                        <p className="text-[11px] text-gray-400 mt-1.5">{timeAgo(notif.created_at)}</p>
                                    </div>

                                    {!notif.is_read && (
                                        <div className="flex items-center gap-3 pt-1 shrink-0">
                                            <button
                                                onClick={(e) => snooze(notif.id, e)}
                                                className="hidden group-hover:flex items-center gap-1 text-[11px] font-medium text-gray-500 hover:text-indigo-600 transition-colors bg-white px-2 py-1 rounded-md border border-gray-200 shadow-sm"
                                                title="Snooze for 1 hour"
                                            >
                                                <AlarmClock size={12} /> Snooze 1h
                                            </button>
                                            <div className="w-2 h-2 rounded-full bg-indigo-500" />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Pagination */}
                {notifications.meta && notifications.meta.last_page > 1 && (
                    <div className="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
                        <p className="text-[12px] text-gray-400">
                            Page {notifications.meta.current_page} of {notifications.meta.last_page}
                        </p>
                        <div className="flex gap-1">
                            {Array.from({ length: notifications.meta.last_page }, (_, i) => i + 1).map((page) => (
                                <button
                                    key={page}
                                    onClick={() => router.get('/notifications', { page }, { preserveState: true })}
                                    className={cn(
                                        'w-8 h-8 rounded-lg text-[12px] font-medium transition-colors',
                                        page === notifications.meta.current_page
                                            ? 'bg-indigo-600 text-white'
                                            : 'text-gray-500 hover:bg-gray-100',
                                    )}
                                >
                                    {page}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
