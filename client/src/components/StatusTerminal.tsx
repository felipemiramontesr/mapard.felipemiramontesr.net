import React, { useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Zap, Activity, ShieldAlert } from 'lucide-react';

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
    resetLabel?: string;
    onNeutralize?: () => void;
    findingsCount?: number;
}

const StatusTerminal: React.FC<StatusTerminalProps> = ({ logs, isVisible, onReset, resetLabel, onNeutralize, findingsCount = 0 }) => {
    // ... (existing code logs setup) ...
    const endRef = useRef<HTMLDivElement>(null);
    const isCompleted = logs.some(l =>
        l.message.includes('Listo') ||
        l.message.includes('Failed') ||
        l.message.includes('Error')
    );
    const hasError = logs.some(l => l.type === 'error');
    // ... (rest of logic)

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [logs]);

    if (!isVisible) return null;

    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            className="ops-card mt-4 font-mono text-xs flex flex-col w-full max-w-2xl mx-auto overflow-hidden relative min-h-[160px]"
        >
            {/* Minimal Header */}
            <div className={`px-4 py-2 flex items-center justify-between border-b border-white/5 flex-none z-10 ${isCompleted ? (findingsCount > 0 ? 'bg-ops-danger/10' : 'bg-[#00f3ff]/10') : 'bg-white/5'}`}>
                <div className={`flex items-center gap-2 ${isCompleted ? (findingsCount > 0 ? 'text-ops-danger' : 'text-[#00f3ff]') : 'text-ops-accent'}`}>
                    <Activity className="w-3 h-3" />
                    <span className="tracking-widest font-bold text-[9px] md:text-xs uppercase">
                        {isCompleted ? `${findingsCount} RIESGO${findingsCount !== 1 ? 'S' : ''} DETECTADOS` : 'ESTADO DE OPERACIÓN'}
                    </span>
                </div>
                <div className="flex gap-1 items-center">
                    {!isCompleted ? (
                        <div className="flex gap-1.5 opacity-80">
                            <div className="w-0.5 h-2 bg-[#00f3ff] animate-[ping_1.5s_ease-in-out_infinite]" />
                            <div className="w-0.5 h-2 bg-[#00f3ff] animate-[ping_1.5s_ease-in-out_0.2s_infinite]" />
                            <div className="w-0.5 h-2 bg-[#00f3ff] animate-[ping_1.5s_ease-in-out_0.4s_infinite]" />
                        </div>
                    ) : (
                        <div className={`w-2 h-2 rounded-full ${findingsCount > 0 ? 'bg-ops-danger shadow-[0_0_8px_rgba(255,60,60,0.8)]' : 'bg-[#00f3ff] shadow-[0_0_8px_rgba(0,243,255,0.8)]'} animate-pulse`} />
                    )}
                </div>
            </div>

            {/* Content Area - Single Line Centered */}
            <div className="flex-grow flex flex-col items-center justify-center p-6 space-y-4 relative">
                <AnimatePresence mode="wait">
                    {logs.length > 0 && [logs[0]].map((log) => (
                        <motion.div
                            key={log.id}
                            initial={{ opacity: 0, scale: 0.95 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: 1.05 }}
                            transition={{ duration: 0.2 }}
                            className={`text-center space-y-2 max-w-lg ${log.type === 'error' ? 'text-ops-danger' :
                                log.type === 'success' ? 'text-ops-success' :
                                    'text-white'
                                }`}
                        >
                            <span className="block text-xs uppercase tracking-widest opacity-50 mb-2">
                                {log.timestamp}
                            </span>
                            <span className="block text-sm md:text-base font-bold tracking-wider leading-relaxed">
                                {log.message}
                            </span>
                        </motion.div>
                    ))}
                </AnimatePresence>
            </div>

            {/* Footer de Acciones (Fijo al fondo) */}
            {isCompleted && (onReset || onNeutralize) && (
                <div className="p-4 border-t border-white/10 bg-white/5 flex-none z-20 space-y-3">
                    {(onReset && (hasError || (isCompleted && findingsCount === 0))) && (
                        <button
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                onReset();
                            }}
                            className="btn-ops w-full text-xs sm:text-sm gap-3"
                        >
                            <Zap className="w-4 h-4" />
                            {resetLabel || 'EJECUTAR ANÁLISIS'}
                        </button>
                    )}

                    <div className="flex flex-col sm:flex-row gap-3">

                        {onNeutralize && (
                            <button
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onNeutralize();
                                }}
                                className="btn-ops flex-1 text-[10px] sm:text-xs md:text-sm gap-2 sm:gap-3"
                            >
                                <ShieldAlert className="w-4 h-4 hidden sm:block" />
                                NEUTRALIZAR RIESGOS
                            </button>
                        )}
                    </div>
                </div>
            )}
        </motion.div>
    );
};

export default StatusTerminal;
