import React, { useState, useEffect, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShieldAlert, Globe, Clock, CheckCircle, ChevronDown } from 'lucide-react';
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
    tacticalColor?: string;
}

const FeedTerminal: React.FC<FeedTerminalProps> = ({ email, tacticalColor }) => {
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
            case 'CRITICAL': return 'border-ops-danger/40 text-ops-danger';
            case 'HIGH': return 'border-ops-warning/40 text-ops-warning';
            case 'LOW':
            case 'MEDIUM':
            default: return 'border-ops-border/40 text-ops-accent';
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
                className="w-full border border-[rgba(74,85,120,0.55)] bg-white/[0.03] backdrop-blur-md p-6 rounded cursor-pointer hover:bg-white/[0.05] transition-colors flex flex-col items-center shadow-[0_18px_50px_rgba(0,0,0,0.18)] relative overflow-hidden group"
                style={{ borderColor: `${tacticalColor || '#eab308'}44` }}
                whileHover={{ scale: 1.005 }}
                whileTap={{ scale: 0.99 }}
            >
                {/* Nivel 1: Título Homologado */}
                <div className="w-full pt-4 pb-2 border-b border-white/10 mb-6 flex items-center justify-center gap-2 overflow-hidden">
                    <Globe className="w-4 h-4 flex-shrink-0" style={{ color: tacticalColor || '#eab308' }} />
                    <h3 className="text-[11px] font-bold tracking-[.15em] text-white text-center uppercase whitespace-nowrap">INTELIGENCIA TÁCTICA</h3>
                </div>

                {/* Nivel 2: Status Box Homologado */}
                <div className="w-[240px] h-9 bg-white/5 border border-white/10 rounded-sm mb-4 flex items-center justify-center overflow-hidden">
                    <span className="text-[9px] uppercase tracking-[.25em] text-white/70 font-light text-center">ALERTAS GLOBALES</span>
                </div>

                {/* Nivel 3: Radar 212px (Color Sincronizado) */}
                <div className="relative group/radar mb-4">
                    <div className="w-[212px] h-[212px] rounded-full flex items-center justify-center relative backdrop-blur-sm bg-white/[0.02]">
                        <svg className="absolute inset-0 w-full h-full -rotate-90">
                            <circle
                                cx="106" cy="106" r="98"
                                fill="none" stroke="rgba(255,255,255,0.05)" strokeWidth="2"
                            />
                            <motion.circle
                                cx="106" cy="106" r="98"
                                fill="none"
                                stroke={tacticalColor || '#fb923c'}
                                strokeWidth="8"
                                strokeDasharray={2 * Math.PI * 98}
                                initial={{ strokeDashoffset: 2 * Math.PI * 98 }}
                                animate={{ strokeDashoffset: 2 * Math.PI * 98 * (1 - (feed.length > 0 ? 0.35 : 0)) }}
                                transition={{ duration: 1.5, ease: "easeOut" }}
                                strokeLinecap="round"
                                style={{ filter: `drop-shadow(0 0 8px ${tacticalColor || '#fb923c'}88)` }}
                            />
                        </svg>

                        <div className="flex flex-col items-center">
                            <span className="text-5xl font-black font-mono tracking-tighter" style={{ color: tacticalColor || '#fb923c', textShadow: `0 0 20px ${tacticalColor || '#fb923c'}66` }}>
                                {feed.length}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Nivel 4: Botón Detalles Homologado */}
                <div className="w-full mt-6 flex justify-center">
                    <div className="w-[240px] h-9 border border-white/10 bg-white/5 rounded flex items-center justify-center gap-2 transition-all hover:bg-white/10 overflow-hidden" style={{ borderColor: `${tacticalColor || '#eab308'}33` }}>
                        <span className="text-[9px] uppercase tracking-[.25em] font-light text-white whitespace-nowrap text-center">DETALLES</span>
                        <ChevronDown className={`w-3 h-3 transition-transform duration-300 ${isFeedOpen ? 'rotate-180' : ''}`} style={{ color: tacticalColor || '#eab308' }} />
                    </div>
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
                                        className={`relative z-10 w-full p-5 border rounded bg-white/[0.02] backdrop-blur-md cursor-grab active:cursor-grabbing transition-all duration-300 ${styleClass}`}
                                        whileTap={{ scale: 0.98 }}
                                    >
                                        <div className="flex justify-between items-center mb-4">
                                            <div className="flex items-center gap-2">
                                                <ShieldAlert className="w-4 h-4 flex-shrink-0" />
                                                <span className="text-[10px] font-bold tracking-[.15em] uppercase">{item.source}</span>
                                            </div>
                                            <div className="flex items-center gap-1.5 text-[10px] text-ops-text_dim/60 font-mono">
                                                <Clock className="w-3.5 h-3.5" />
                                                <span className="uppercase tracking-tight">en {formatDistanceToNow(new Date(item.timestamp), { locale: es })}</span>
                                            </div>
                                        </div>
                                        <a href={item.url} target="_blank" rel="noreferrer" className="block group/link mb-3">
                                            <h4 className="text-white text-base font-bold leading-tight tracking-tight group-hover/link:text-ops-accent transition-colors">
                                                {item.title}
                                            </h4>
                                        </a>
                                        <p className="text-[13px] text-ops-text_dim font-light leading-relaxed">
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
