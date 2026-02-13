import React, { useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Terminal, Activity, ShieldAlert } from 'lucide-react';

interface Log {
    id: number;
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    timestamp: string;
}

interface StatusTerminalProps {
    logs: Log[];
    isVisible: boolean;
    onReset?: () => void;
}

const StatusTerminal: React.FC<StatusTerminalProps> = ({ logs, isVisible, onReset }) => {
    const endRef = useRef<HTMLDivElement>(null);
    const isCompleted = logs.some(l => l.message.includes('Scan Complete') || l.message.includes('Scan Failed'));

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [logs]);

    if (!isVisible) return null;

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="ops-card mt-8 font-mono text-xs overflow-hidden border-ops-cyan shadow-none"
        >
            <div className="bg-ops-bg_alt/90 px-4 py-2 flex items-center justify-between border-b border-white/5 mb-2">
                <div className="flex items-center gap-2 text-ops-cyan animate-pulse">
                    <Activity className="w-4 h-4" />
                    <span className="tracking-widest font-bold">SYSTEM_LOG - LIVE_FEED</span>
                </div>
                <div className="flex gap-1.5">
                    <div className="w-2 h-2 rounded-none bg-ops-danger" />
                    <div className="w-2 h-2 rounded-none bg-ops-warning" />
                    <div className="w-2 h-2 rounded-none bg-ops-cyan" />
                </div>
            </div>

            <div className="p-4 h-64 overflow-y-auto bg-black/80 space-y-1 font-mono custom-scrollbar">
                <AnimatePresence>
                    {logs.map((log) => (
                        <motion.div
                            key={log.id}
                            initial={{ opacity: 0, x: -10 }}
                            animate={{ opacity: 1, x: 0 }}
                            className={`flex gap-3 ${log.type === 'error' ? 'text-ops-danger font-bold' :
                                log.type === 'success' ? 'text-ops-cyan font-bold' :
                                    log.type === 'warning' ? 'text-ops-warning' : 'text-ops-text_dim'
                                }`}
                        >
                            <span className="opacity-40 text-[10px] pt-0.5">[{log.timestamp}] ::</span>
                            <span className={log.type === 'info' ? 'text-opacity-80' : ''}>{log.message}</span>
                            {log.type === 'error' && <ShieldAlert className="w-3 h-3 inline ml-1" />}
                        </motion.div>
                    ))}
                </AnimatePresence>
                <div ref={endRef} />
            </div>

            {isCompleted && onReset && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    className="p-4 border-t border-white/5 bg-ops-bg_alt/50"
                >
                    <button
                        onClick={onReset}
                        className="w-full py-3 bg-ops-cyan text-black font-bold tracking-[0.2em] hover:bg-ops-cyan/80 transition-all uppercase text-sm flex items-center justify-center gap-2"
                    >
                        <Terminal className="w-4 h-4" />
                        EJECUTAR NUEVO ANÁLISIS
                    </button>
                </motion.div>
            )}
        </motion.div>
    );
};

export default StatusTerminal;
