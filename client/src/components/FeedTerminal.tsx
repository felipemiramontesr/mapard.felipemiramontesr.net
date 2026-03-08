import React, { useState, useEffect, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShieldAlert, Globe, Clock, CheckCircle, ChevronUp, ChevronDown } from 'lucide-react';
import { Capacitor } from '@capacitor/core';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';

const API_BASE = Capacitor.isNativePlatform()
    ? 'https://mapard.felipemiramontesr.net'
    : '';

export interface FeedItem {
    id: number;
    title: string;
    gemini_summary: string;
    severity: string;
    source: string;
    url: string;
    status: string;
    timestamp: string;
}

interface FeedTerminalProps {
    email: string;
}

const FeedTerminal: React.FC<FeedTerminalProps> = ({ email }) => {
    const [feed, setFeed] = useState<FeedItem[]>([]);
    const [isLoading, setIsLoading] = useState(true);

    const loadFeed = useCallback(async () => {
        try {
            const res = await fetch(`${API_BASE}/api/intelligence/sync?email=${encodeURIComponent(email)}`);
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                setFeed(data.feed);
            }
        } catch (error) {
            console.error('Feed error:', error);
        } finally {
            setIsLoading(false);
        }
    }, [email]);

    useEffect(() => {
        loadFeed();
        // Polling every 5 minutes
        const interval = setInterval(loadFeed, 300000);
        return () => clearInterval(interval);
    }, [loadFeed]);

    const handleArchive = async (id: number) => {
        // Optimistic UI update
        setFeed(prev => prev.filter(item => item.id !== id));

        try {
            await fetch(`${API_BASE}/api/intelligence/sync`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, id, status: 'ARCHIVED' })
            });
        } catch (error) {
            console.error('Error archiving feed item:', error);
        }
    };

    const getSeverityColor = (severity: string) => {
        switch (severity.toUpperCase()) {
            case 'CRITICAL': return 'text-ops-danger border-ops-danger shadow-[0_0_10px_rgba(255,51,102,0.3)] bg-ops-danger/10';
            case 'HIGH': return 'text-ops-warning border-ops-warning shadow-[0_0_10px_rgba(255,170,0,0.2)] bg-ops-warning/10';
            case 'LOW':
            case 'MEDIUM':
            default: return 'text-ops-accent border-ops-accent bg-ops-accent/5';
        }
    };

    const [isFeedOpen, setIsFeedOpen] = useState(false);

    if (isLoading && feed.length === 0) {
        return (
            <div className="w-full flex flex-col items-center justify-center py-8 opacity-50 border border-[#4a5578]/50 bg-white/[0.03] rounded-lg relative overflow-hidden">
                <div className="absolute top-0 left-0 w-1 h-full bg-[#4a5578]"></div>
                <div className="w-6 h-6 border-2 border-[#8a9fca] border-t-transparent rounded-full animate-spin"></div>
                <span className="text-[10px] text-[#8a9fca] uppercase tracking-widest mt-4">Conectando con Servidor Táctico...</span>
            </div>
        );
    }

    return (
        <div className="w-full flex flex-col">
            <motion.div
                onClick={() => setIsFeedOpen(!isFeedOpen)}
                className="w-full border border-[rgba(74,85,120,0.55)] bg-white/[0.03] backdrop-blur-md p-6 cursor-pointer hover:bg-white/[0.05] transition-colors flex flex-col items-center shadow-[0_18px_50px_rgba(0,0,0,0.18)] relative overflow-hidden group"
                whileHover={{ scale: 1.005 }}
                whileTap={{ scale: 0.99 }}
            >
                <div className="absolute top-4 right-4 flex items-center justify-center w-8 h-8 border border-white/10 bg-white/[0.03] backdrop-blur-sm rounded-md transition-all group-hover:border-white/20 group-hover:bg-white/[0.08]">
                    {isFeedOpen ? <ChevronUp className="w-4 h-4 text-ops-warning" /> : <ChevronDown className="w-4 h-4 text-ops-warning" />}
                </div>

                <div className="flex items-center gap-2 mb-2 w-full justify-center px-12">
                    <Globe className="w-4 h-4 text-ops-warning flex-shrink-0" />
                    <h3 className="text-[.72rem] font-semibold tracking-[.15em] text-white uppercase whitespace-nowrap">PROTOCOLO INFORMATIVO</h3>
                </div>

                <div className="flex flex-col items-center justify-center my-6">
                    <span className={`text-[4.5rem] font-extralight leading-none ${feed.length > 0 ? 'text-ops-warning drop-shadow-[0_0_15px_rgba(255,170,0,0.4)]' : 'text-[#6b7490]'}`}>
                        {feed.length}
                    </span>
                    <span className="text-[.98rem] font-light text-[#c5cae0] uppercase mt-4 tracking-widest text-center whitespace-nowrap">
                        DE {feed.length} ALERTAS GLOBALES ACTIVAS
                    </span>
                </div>
            </motion.div>

            <motion.div
                initial={{ height: 0, opacity: 0 }}
                animate={{ height: isFeedOpen ? 'auto' : 0, opacity: isFeedOpen ? 1 : 0 }}
                transition={{ duration: 0.4, ease: [0.04, 0.62, 0.23, 0.98] }}
                className="overflow-hidden w-full flex flex-col"
            >
                <div className="pt-4 flex flex-col gap-3">
                    <AnimatePresence>
                        {feed.length === 0 && (
                            <motion.div
                                initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                                className="flex flex-col items-center justify-center py-10 text-ops-text_dim text-center border border-white/5 bg-white/[0.02] rounded"
                            >
                                <CheckCircle className="w-10 h-10 mb-3 text-ops-success/50" />
                                <p className="text-xs uppercase tracking-widest text-[#e8e8e8]">Bandeja Despejada</p>
                                <p className="text-[10px] mt-1 font-mono text-[#8a9fca]">Sin alertas globales activas por el momento.</p>
                            </motion.div>
                        )}

                        {feed.map(item => {
                            const styleClass = getSeverityColor(item.severity);
                            return (
                                <motion.div
                                    key={item.id}
                                    layout
                                    initial={{ opacity: 0, scale: 0.95 }}
                                    animate={{ opacity: 1, scale: 1 }}
                                    exit={{ opacity: 0, x: -100, scale: 0.9 }}
                                    transition={{ duration: 0.2 }}
                                    drag="x"
                                    dragConstraints={{ left: 0, right: 0 }}
                                    dragElastic={0.8}
                                    onDragEnd={(_e, { offset, velocity }) => {
                                        if (offset.x > 80 || offset.x < -80 || velocity.x > 400 || velocity.x < -400) {
                                            handleArchive(item.id);
                                        }
                                    }}
                                    className="relative w-full group touch-pan-y block"
                                >
                                    {/* Background clear indicator */}
                                    <div className="absolute inset-0 bg-ops-success/20 border border-ops-success/30 rounded flex items-center justify-between px-6 z-0">
                                        <span className="text-ops-success font-bold text-xs uppercase tracking-widest">Archivar</span>
                                        <span className="text-ops-success font-bold text-xs uppercase tracking-widest">Archivar</span>
                                    </div>

                                    <motion.div
                                        className={`relative z-10 w-full p-4 border rounded backdrop-blur-md bg-ops-bg cursor-grab active:cursor-grabbing ${styleClass}`}
                                        whileTap={{ scale: 0.98 }}
                                    >
                                        <div className="flex justify-between items-start mb-2">
                                            <div className="flex items-center gap-2">
                                                <ShieldAlert className="w-4 h-4" />
                                                <span className="text-[10px] font-bold tracking-widest uppercase">{item.source}</span>
                                            </div>
                                            <div className="flex items-center gap-1 text-[9px] text-[#e8e8e8]/50 font-mono">
                                                <Clock className="w-3 h-3" />
                                                {formatDistanceToNow(new Date(item.timestamp), { addSuffix: true, locale: es })}
                                            </div>
                                        </div>
                                        <a href={item.url} target="_blank" rel="noreferrer" className="block mt-1">
                                            <h4 className="text-white text-sm font-semibold leading-tight hover:underline mb-2">{item.title}</h4>
                                        </a>
                                        <p className="text-xs text-[#8a9fca] leading-relaxed font-light">
                                            {item.gemini_summary}
                                        </p>
                                    </motion.div>
                                </motion.div>
                            );
                        })}
                    </AnimatePresence>

                    {feed.length > 0 && (
                        <p className="text-[9px] text-center text-[#8a9fca] font-mono mt-2 opacity-50 uppercase tracking-widest">
                            Desliza una alerta horizontalmente para archivarla
                        </p>
                    )}
                </div>
            </motion.div>
        </div>
    );
};

export default FeedTerminal;
