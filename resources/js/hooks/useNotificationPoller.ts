import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';

interface NotificationResponse {
    data: any[];
}

export function useNotificationPoller(intervalMs: number = 30000) {
    const [unreadCount, setUnreadCount] = useState<number>(0);
    const knownIds = useRef<Set<string>>(new Set());

    // Creates a simple synthesized "ding" sound
    const playNotificationSound = () => {
        try {
            const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            
            const osc = ctx.createOscillator();
            const gainNode = ctx.createGain();
            
            osc.type = 'sine';
            // Play a pleasant high-pitch chime
            osc.frequency.setValueAtTime(880.00, ctx.currentTime); // A5
            osc.frequency.exponentialRampToValueAtTime(1760.00, ctx.currentTime + 0.1); // A6
            
            // Envelope
            gainNode.gain.setValueAtTime(0, ctx.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.2);
            
            osc.connect(gainNode);
            gainNode.connect(ctx.destination);
            
            osc.start();
            osc.stop(ctx.currentTime + 1.2);
        } catch (e) {
            console.warn('AudioContext error', e);
        }
    };

    const showBrowserNotification = (title: string, body: string) => {
        if (!('Notification' in window)) return;

        if (Notification.permission === 'granted') {
            new Notification(title, {
                body,
                icon: '/favicon.ico', // Update if there's a specific app icon
            });
        }
    };

    const fetchNotifications = async () => {
        try {
            // First fetch just the count to update the badge efficiently
            const countRes = await axios.get('/api/v1/notifications/unread-count');
            const newCount = countRes.data.data.count;
            setUnreadCount(newCount);
            
            // Sync with global window object and update document title immediately
            window.unreadNotificationCount = newCount;
            const currentTitle = document.title.replace(/^\(\d+\+?\)\s/, '');
            if (newCount > 0) {
                const badge = newCount > 99 ? '99+' : newCount;
                document.title = `(${badge}) ${currentTitle}`;
            } else {
                document.title = currentTitle;
            }

            if (newCount > 0) {
                // Fetch the latest unread notifications
                const listRes = await axios.get<any>('/api/v1/notifications?unread=1');
                const notifications = listRes.data.data.data; // Using the ApiResponse paginated format
                
                if (notifications && notifications.length > 0) {
                    let newItemsDetected = false;
                    const currentIds = new Set<string>();

                    for (const notif of notifications) {
                        currentIds.add(notif.id);
                        if (!knownIds.current.has(notif.id)) {
                            newItemsDetected = true;
                        }
                    }
                    
                    if (newItemsDetected) {
                        const latest = notifications[0];
                        playNotificationSound();
                        showBrowserNotification(latest.title, latest.body);
                        toast.info(`New Notification: ${latest.title}`);
                    }
                    
                    // Update our tracker to current state
                    knownIds.current = currentIds;
                }
            } else {
                knownIds.current.clear();
            }
        } catch (error) {
            console.error('Failed to poll notifications', error);
        }
    };

    // Request browser permission if requested by UI
    const requestNotificationPermission = async () => {
        if (!('Notification' in window)) {
            toast.error('This browser does not support desktop notifications');
            return false;
        }
        
        if (Notification.permission !== 'denied') {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                toast.success('Desktop notifications enabled!');
                return true;
            }
        }
        return false;
    };

    useEffect(() => {
        // Initial fetch
        fetchNotifications();

        // Start polling
        const interval = setInterval(fetchNotifications, intervalMs);
        return () => clearInterval(interval);
    }, [intervalMs]);

    return {
        unreadCount,
        requestNotificationPermission,
        permission: 'Notification' in window ? Notification.permission : 'denied',
        refetch: fetchNotifications
    };
}
