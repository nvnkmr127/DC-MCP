import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

export function usePermissions() {
    const { auth } = usePage<PageProps>().props;

    const can = (resource: string, action: string): boolean => {
        if (!auth.user) return false;
        // CEO has full access
        if (auth.user.roles.includes('ceo')) return true;
        return (auth.permissions[resource] ?? []).includes(action);
    };

    const hasRole = (...roles: string[]): boolean => {
        if (!auth.user) return false;
        return roles.some((r) => auth.user!.roles.includes(r));
    };

    const isAuthenticated = !!auth.user;

    return { can, hasRole, isAuthenticated, user: auth.user, permissions: auth.permissions };
}
