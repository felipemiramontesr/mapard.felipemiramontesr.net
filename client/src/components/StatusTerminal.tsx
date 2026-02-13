import React, { useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Terminal } from 'lucide-react';

interface Log {
    id: number;
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    timestamp: string;
}

interface StatusTerminalProps {
    logs: Log[];
    isVisible: boolean;
}

const StatusTerminal: React.FC<StatusTerminalProps> = ({ logs, isVisible }) => {
    const endRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [logs]);

    if (!isVisible) return null;

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="ops-card mt-8 font-mono text-xs overflow-hidden border-ops-accent/30"
        >
            <div className="bg-ops-bg_alt/90 px-4 py-2 flex items-center justify-between border-b border-white/5 mb-2">
                <div className="flex items-center gap-2 text-ops-accent animate-pulse">
                    <Terminal className="w-4 h-4" />
                    <span className="tracking-widest">SYSTEM_LOG // LIVE_FEED</span>
                </div>
                <div className="flex gap-1.5">
                    <div className="w-2 h-2 rounded-none bg-ops-danger" />
                    <div className="w-2 h-2 rounded-none bg-ops-warning" />
                    <div className="w-2 h-2 rounded-none bg-ops-success" />
                </div>
            </div>

            <div className="p-4 h-64 overflow-y-auto bg-black/60 space-y-1 font-mono">
                <AnimatePresence>
                    {logs.map((log) => (
                        <motion.div
                            key={log.id}
                            initial={{ opacity: 0, x: -10 }}
                            animate={{ opacity: 1, x: 0 }}
                            className={`flex gap-3 ${log.type === 'error' ? 'text-ops-danger' :
                                log.type === 'success' ? 'text-ops-success' :
                                    log.type === 'warning' ? 'text-ops-warning' : 'text-ops-text_dim'
                                }`}
                        >
                            <span className="opacity-40">[{log.timestamp}] ::</span>
                            <span>{log.message}</span>
                        </motion.div>
                    ))}
                </AnimatePresence>
                <div ref={endRef} />
            </div>
        </motion.div>
    );
};

export default StatusTerminal;
