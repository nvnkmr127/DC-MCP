import { useState, useEffect, useRef } from 'react';

export function useStopwatch(taskId: string) {
    const [timerRunning, setTimerRunning] = useState(false);
    const [timerSeconds, setTimerSeconds] = useState(0);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Load initial state from sessionStorage
    useEffect(() => {
        const savedSecs = sessionStorage.getItem(`stopwatch_${taskId}_secs`);
        const savedRunning = sessionStorage.getItem(`stopwatch_${taskId}_running`);
        const savedTime = sessionStorage.getItem(`stopwatch_${taskId}_timestamp`);

        if (savedSecs) {
            let secs = parseInt(savedSecs, 10);
            if (savedRunning === 'true' && savedTime) {
                const diff = Math.floor((Date.now() - parseInt(savedTime, 10)) / 1000);
                secs += diff;
                setTimerRunning(true);
            }
            setTimerSeconds(secs);
        } else {
            setTimerSeconds(0);
            setTimerRunning(false);
        }
    }, [taskId]);

    // Handle interval ticks
    useEffect(() => {
        if (timerRunning) {
            sessionStorage.setItem(`stopwatch_${taskId}_running`, 'true');
            sessionStorage.setItem(`stopwatch_${taskId}_timestamp`, Date.now().toString());

            timerRef.current = setInterval(() => {
                setTimerSeconds((prev) => {
                    const next = prev + 1;
                    sessionStorage.setItem(`stopwatch_${taskId}_secs`, next.toString());
                    sessionStorage.setItem(`stopwatch_${taskId}_timestamp`, Date.now().toString());
                    return next;
                });
            }, 1000);
        } else {
            sessionStorage.setItem(`stopwatch_${taskId}_running`, 'false');
            if (timerRef.current) {
                clearInterval(timerRef.current);
            }
        }

        return () => {
            if (timerRef.current) {
                clearInterval(timerRef.current);
            }
        };
    }, [timerRunning, taskId]);

    const start = () => setTimerRunning(true);
    
    const pause = () => setTimerRunning(false);

    const stop = () => {
        setTimerRunning(false);
        return timerSeconds;
    };

    const reset = () => {
        setTimerRunning(false);
        setTimerSeconds(0);
        sessionStorage.removeItem(`stopwatch_${taskId}_secs`);
        sessionStorage.removeItem(`stopwatch_${taskId}_running`);
        sessionStorage.removeItem(`stopwatch_${taskId}_timestamp`);
    };

    function formatTimer(s: number) {
        const h = Math.floor(s / 3600).toString().padStart(2, '0');
        const m = Math.floor((s % 3600) / 60).toString().padStart(2, '0');
        const sec = (s % 60).toString().padStart(2, '0');
        return `${h}:${m}:${sec}`;
    }

    return {
        timerRunning,
        timerSeconds,
        formattedTime: formatTimer(timerSeconds),
        start,
        pause,
        stop,
        reset,
    };
}
