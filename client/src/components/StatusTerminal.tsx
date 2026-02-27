import React, { useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Zap, Activity, FileCheck, ShieldAlert } from 'lucide-react';
import { Capacitor } from '@capacitor/core';

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
    resultUrl?: string | null;
    onNeutralize?: () => void;
}

const StatusTerminal: React.FC<StatusTerminalProps> = ({ logs, isVisible, onReset, resetLabel, resultUrl, onNeutralize }) => {
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
            className="ops-card mt-4 font-mono text-xs flex flex-col w-full max-w-2xl mx-auto overflow-hidden relative min-h-[160px]"
        >
            {/* Minimal Header */}
            <div className="bg-white/5 px-4 py-1.5 flex items-center justify-between border-b border-white/5 flex-none z-10">
                <div className="flex items-center gap-2 text-ops-accent">
                    <Activity className="w-3 h-3" />
                    <span className="tracking-widest font-bold text-[9px]">ESTADO DE OPERACIÓN</span>
                </div>
                <div className="flex gap-1">
                    <div className="w-1.5 h-1.5 bg-ops-accent/50 animate-pulse" />
                </div>
            </div>

            {/* Content Area - Single Line Centered */}
            <div className="flex-grow flex flex-col items-center justify-center p-6 space-y-4 relative">
                <AnimatePresence mode="wait">
                    {logs.map((log) => (
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
            {isCompleted && (onReset || resultUrl || onNeutralize) && (
                <div className="p-4 border-t border-white/10 bg-white/5 flex-none z-20 space-y-3">
                    {(onReset && (hasError || resultUrl)) && (
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
                                            // Force Android OS to handle the PDF download natively
                                            window.open(fullUrl, '_system');
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
                                className="btn-ops flex-1 bg-transparent border-ops-border text-ops-text hover:border-ops-accent hover:text-white text-[10px] sm:text-xs md:text-sm gap-2 sm:gap-3"
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
