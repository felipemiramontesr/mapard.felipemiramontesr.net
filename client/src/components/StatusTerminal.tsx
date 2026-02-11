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
            className="glass-panel mt-8 font-mono text-sm overflow-hidden border-cyber-accent/20"
        >
            <div className="bg-cyber-dark/80 px-4 py-2 flex items-center justify-between border-b border-white/5">
                <div className="flex items-center gap-2 text-cyber-accent">
                    <Terminal className="w-4 h-4" />
                    <span>REAL-TIME EXECUTION LOG</span>
                </div>
                <div className="flex gap-1.5">
                    <div className="w-3 h-3 rounded-full bg-red-500/50" />
                    <div className="w-3 h-3 rounded-full bg-yellow-500/50" />
                    <div className="w-3 h-3 rounded-full bg-green-500/50" />
                </div>
            </div>

            <div className="p-4 h-64 overflow-y-auto bg-black/40 space-y-1.5">
                <AnimatePresence>
                    {logs.map((log) => (
                        <motion.div
                            key={log.id}
                            initial={{ opacity: 0, x: -10 }}
                            animate={{ opacity: 1, x: 0 }}
                            className={`flex gap-3 ${log.type === 'error' ? 'text-cyber-danger' :
                                    log.type === 'success' ? 'text-cyber-success' :
                                        log.type === 'warning' ? 'text-cyber-warning' : 'text-cyber-text'
                                }`}
                        >
                            <span className="opacity-50 text-xs">[{log.timestamp}]</span>
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
