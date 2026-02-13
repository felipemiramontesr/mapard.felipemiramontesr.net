import React, { useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Terminal, Activity, ShieldAlert, Download } from 'lucide-react';
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
}

const StatusTerminal: React.FC<StatusTerminalProps> = ({ logs, isVisible, onReset, resultUrl }) => {
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
            className="ops-card mt-8 font-mono text-xs border-ops-cyan shadow-none flex flex-col h-full"
        >
            <div className="bg-ops-bg_alt/90 px-3 sm:px-4 py-2 flex items-center justify-between border-b border-white/5 mb-2 flex-none">
                <div className="flex items-center gap-2 text-ops-cyan animate-pulse">
                    <Activity className="w-3 h-3 sm:w-4 sm:h-4" />
                    <span className="tracking-widest font-bold text-[10px] sm:text-xs">SYSTEM_LOG :: LIVE</span>
                </div>
                <div className="flex gap-1.5 hidden sm:flex">
                    <div className="w-2 h-2 rounded-none bg-ops-danger" />
                    <div className="w-2 h-2 rounded-none bg-ops-warning" />
                    <div className="w-2 h-2 rounded-none bg-ops-cyan" />
                </div>
            </div>

            <div className="p-4 overflow-y-auto bg-black/80 space-y-1 font-mono custom-scrollbar flex-grow min-h-[200px]">
                <AnimatePresence>
                    {logs.map((log) => (
                        <motion.div
                            key={log.id}
                            initial={{ opacity: 0, x: -10 }}
                            animate={{ opacity: 1, x: 0 }}
                            className={`flex gap-3 break-all whitespace-pre-wrap ${log.type === 'error' ? 'text-ops-danger font-bold' :
                                log.type === 'success' ? 'text-ops-cyan font-bold' :
                                    log.type === 'warning' ? 'text-ops-warning' : 'text-ops-text_dim'
                                }`}
                        >
                            <span className="opacity-40 text-[10px] pt-0.5 flex-none">[{log.timestamp}] ::</span>
                            <span className={log.type === 'info' ? 'text-opacity-80' : ''}>{log.message}</span>
                            {log.type === 'error' && <ShieldAlert className="w-3 h-3 inline ml-1 flex-none" />}
                        </motion.div>
                    ))}
                </AnimatePresence>
                <div ref={endRef} />
            </div>

            {isCompleted && onReset && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    className="p-4 border-t border-white/5 bg-ops-bg_alt/50 flex-none z-20 relative"
                >
                    <button
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onReset();
                        }}
                        className="w-full py-3 bg-ops-cyan text-black font-bold tracking-[0.2em] hover:bg-ops-cyan/80 transition-all uppercase text-sm flex items-center justify-center gap-2 cursor-pointer active:scale-95"
                    >
                        <Terminal className="w-4 h-4" />
                        EJECUTAR NUEVO ANÁLISIS
                    </button>

                    {resultUrl && (
                        <button
                            onClick={async (e) => {
                                e.preventDefault();
                                e.stopPropagation();

                                // Logic copied from previous auto-download
                                if (Capacitor.isNativePlatform()) {
                                    const API_BASE = 'https://mapard.felipemiramontesr.net'; // Hardcoded backup or passed prop
                                    let fullUrl = resultUrl;
                                    if (fullUrl && !fullUrl.startsWith('http')) {
                                        fullUrl = `${API_BASE}${fullUrl.startsWith('/') ? '' : '/'}${fullUrl}`;
                                    }
                                    await Browser.open({ url: fullUrl });
                                } else {
                                    const link = document.createElement('a');
                                    link.href = resultUrl;
                                    link.download = `MAPA-RD_DOSSIER.pdf`;
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                }
                            }}
                            className="w-full mt-3 py-3 border border-ops-radioactive/50 text-ops-radioactive font-bold tracking-[0.2em] hover:bg-ops-radioactive/10 transition-all uppercase text-sm flex items-center justify-center gap-2 cursor-pointer active:scale-95"
                        >
                            <Download className="w-4 h-4" />
                            DESCARGAR DOSSIER
                        </button>
                    )}
                </motion.div>
            )}
        </motion.div>
    );
};

export default StatusTerminal;
