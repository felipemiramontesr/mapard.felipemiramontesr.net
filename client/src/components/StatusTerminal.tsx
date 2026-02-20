import React, { useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Zap, Activity, FileCheck, ShieldAlert } from 'lucide-react';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';

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
    resultUrl?: string | null;
    onNeutralize?: () => void;
}

const StatusTerminal: React.FC<StatusTerminalProps> = ({ logs, isVisible, onReset, resultUrl, onNeutralize }) => {
    // ... (existing code logs setup) ...
    const endRef = useRef<HTMLDivElement>(null);
    const isCompleted = logs.some(l =>
        l.message.includes('Completado') ||
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
            className="ops-card mt-4 font-mono text-xs border-ops-cyan shadow-none flex flex-col w-full max-w-2xl mx-auto overflow-hidden relative bg-black min-h-[160px]"
        >
            {/* Minimal Header */}
            <div className="bg-ops-bg_alt/50 px-4 py-1.5 flex items-center justify-between border-b border-white/5 flex-none z-10">
                <div className="flex items-center gap-2 text-ops-cyan/70">
                    <Activity className="w-3 h-3" />
                    <span className="tracking-widest font-bold text-[9px]">ESTADO DE OPERACIÓN</span>
                </div>
                <div className="flex gap-1">
                    <div className="w-1.5 h-1.5 bg-ops-cyan/50 animate-pulse" />
                </div>
            </div>

            {/* Content Area - Single Line Centered */}
            <div className="flex-grow flex flex-col items-center justify-center p-6 space-y-4 relative bg-black/50">
                <AnimatePresence mode="wait">
                    {logs.map((log) => (
                        <motion.div
                            key={log.id}
                            initial={{ opacity: 0, scale: 0.95 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: 1.05 }}
                            transition={{ duration: 0.2 }}
                            className={`text-center space-y-2 max-w-lg ${log.type === 'error' ? 'text-ops-danger' :
                                log.type === 'success' ? 'text-ops-cyan' :
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
            {(isCompleted && onReset && (hasError || resultUrl)) && (
                <div className="p-4 border-t border-white/10 bg-ops-bg_alt flex-none z-20 space-y-3">
                    <button
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onReset();
                        }}
                        className="w-full py-3 bg-ops-cyan text-black font-bold tracking-[0.15em] hover:bg-ops-cyan/80 transition-all uppercase text-xs sm:text-sm flex items-center justify-center gap-3 shadow-[0_0_15px_rgba(0,243,255,0.3)] active:scale-[0.98]"
                    >
                        <Zap className="w-4 h-4" />
                        EJECUTAR ANÁLISIS
                    </button>

                    <div className="flex gap-3">
                        {resultUrl && (
                            <button
                                onClick={async (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    console.log("Attempting download:", resultUrl);
                                    try {
                                        if (Capacitor.isNativePlatform()) {
                                            const API_BASE = 'https://mapard.felipemiramontesr.net';
                                            let fullUrl = resultUrl;
                                            if (fullUrl && !fullUrl.startsWith('http')) {
                                                fullUrl = `${API_BASE}${fullUrl.startsWith('/') ? '' : '/'}${fullUrl}`;
                                            }
                                            await Browser.open({ url: fullUrl });
                                        } else {
                                            const link = document.createElement('a');
                                            link.href = resultUrl;
                                            link.download = `MAPARD_DOSSIER.pdf`;
                                            document.body.appendChild(link);
                                            link.click();
                                            document.body.removeChild(link);
                                        }
                                    } catch (err) {
                                        console.error("Download Error", err);
                                        alert("Error al abrir documento: " + JSON.stringify(err));
                                    }
                                }}
                                className="flex-1 py-3 border border-ops-radioactive text-ops-radioactive font-bold tracking-[0.15em] hover:bg-ops-radioactive/10 transition-all uppercase text-[10px] sm:text-xs md:text-sm flex items-center justify-center gap-2 sm:gap-3 shadow-[0_0_10px_rgba(57,255,20,0.2)] active:scale-[0.98] whitespace-nowrap"
                            >
                                <FileCheck className="w-4 h-4 hidden sm:block" />
                                DESCARGAR DOSSIER
                            </button>
                        )}

                        {onNeutralize && (
                            <button
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onNeutralize();
                                }}
                                className="flex-1 py-3 border border-ops-radioactive text-ops-radioactive font-bold tracking-[0.15em] hover:bg-ops-radioactive/10 transition-all uppercase text-[10px] sm:text-xs md:text-sm flex items-center justify-center gap-2 sm:gap-3 shadow-[0_0_10px_rgba(0,255,255,0.2)] active:scale-[0.98] whitespace-nowrap"
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
