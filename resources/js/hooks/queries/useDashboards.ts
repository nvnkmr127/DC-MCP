import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';

export interface Widget {
    id: string;
    title: string;
    type: 'metric_card' | 'line_chart' | 'bar_chart' | 'pie_chart';
    spec: {
        metric_key: string;
        aggregation: string;
        group_by?: string;
        filters?: Record<string, any>;
    };
    position: { x: number; y: number; w: number; h: number };
}

export interface DashboardConfig {
    id: string;
    name: string;
    layout: Widget[];
    is_default: boolean;
}

// Fetch all dashboard configs
export function useDashboardsQuery() {
    return useQuery<DashboardConfig[]>({
        queryKey: ['dashboards'],
        queryFn: async () => {
            const { data } = await axios.get('/api/v1/dashboards');
            return data.data ?? [];
        },
    });
}

// Fetch specific dashboard data
export function useDashboardDataQuery(id: string | undefined, enabled: boolean = true) {
    return useQuery<Record<string, any>>({
        queryKey: ['dashboardData', id],
        queryFn: async () => {
            if (!id) return {};
            const { data } = await axios.get(`/api/v1/dashboards/${id}/data`);
            return data.data?.widgets ?? {};
        },
        enabled: enabled && !!id,
        staleTime: 15_000, // 15 seconds stale time
    });
}

// Save dashboard layout
export function useSaveDashboardMutation() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, layout }: { id: string; layout: Widget[] }) => {
            const { data } = await axios.put(`/api/v1/dashboards/${id}`, { layout });
            return data;
        },
        onSuccess: (data, variables) => {
            // Invalidate cache to trigger refetch
            queryClient.invalidateQueries({ queryKey: ['dashboards'] });
            queryClient.invalidateQueries({ queryKey: ['dashboardData', variables.id] });
        },
    });
}

// Query custom KPI metrics
export function useQueryWidgetDataMutation() {
    return useMutation({
        mutationFn: async (spec: Widget['spec']) => {
            const { data } = await axios.post('/api/v1/viz/query', spec);
            return data.data;
        },
    });
}
