import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Shield, Lock, CheckCircle, ChevronDown, Award, Star, Target } from 'lucide-react';

interface Lesson {
    id: string;
    week: number;
    title: string;
    questions?: { q: string; a: string[]; correct: number }[];
}

interface Block {
    id: number;
    name: string;
    rank: string;
    lessons: Lesson[];
}

const CURRICULUM: Block[] = [
    {
        id: 1,
        name: "Higiene Digital Básica",
        rank: "RECLUTA",
        lessons: [
            { id: "1-1", week: 1, title: "¿Qué es la Ciberseguridad? Conceptos básicos" },
            { id: "1-2", week: 2, title: "Anatomía de una Contraseña Fuerte" },
            { id: "1-3", week: 3, title: "Gestores de Contraseñas" },
            { id: "1-4", week: 4, title: "Autenticación de Dos Pasos (2FA)" },
            { id: "1-5", week: 5, title: "Actualizaciones de Software" },
            { id: "1-6", week: 6, title: "Redes Wi-Fi Públicas y VPN" },
            { id: "1-7", week: 7, title: "Navegación Segura (HTTPS vs HTTP)" },
            { id: "1-8", week: 8, title: "Limpieza de Dispositivos y Permisos" },
            { id: "1-9", week: 9, title: "Cifrado de Extremo a Extremo" },
            { id: "1-10", week: 10, title: "Respaldo de Información (Rule 3-2-1)" },
            { id: "1-11", week: 11, title: "Seguridad Física y USBs" },
            { id: "1-12", week: 12, title: "Repaso y Test Nivel 1" },
        ]
    },
    {
        id: 2,
        name: "El Arte del Engaño",
        rank: "SOLDADO",
        lessons: [
            { id: "2-1", week: 13, title: "Phishing: Identificación de Fraude" },
            { id: "2-2", week: 14, title: "Smishing y Vishing" },
            { id: "2-3", week: 15, title: "Pretexting y Narrativas de Ataque" },
            { id: "2-4", week: 16, title: "Baiting y Descargas Gratuitas" },
            { id: "2-5", week: 17, title: "Ingeniería Social en Redes" },
            { id: "2-6", week: 18, title: "Deepfakes y Desinformación" },
            { id: "2-7", week: 19, title: "Suplantación de Identidad (Spoofing)" },
            { id: "2-8", week: 20, title: "Shoulder Surfing" },
            { id: "2-9", week: 21, title: "Malware 101: Virus, Troyanos, Gusanos" },
            { id: "2-10", week: 22, title: "Ransomware: Secuestro Digital" },
            { id: "2-11", week: 23, title: "Spyware y Keyloggers" },
            { id: "2-12", week: 24, title: "Repaso y Test Nivel 2" },
        ]
    },
    {
        id: 3,
        name: "Privacidad y Datos",
        rank: "CABO",
        lessons: [
            { id: "3-1", week: 25, title: "Huella Digital: Rastro de Datos" },
            { id: "3-2", week: 26, title: "Cookies y Rastreadores" },
            { id: "3-3", week: 27, title: "Auditoría de Privacidad (Big Tech)" },
            { id: "3-4", week: 28, title: "Derechos ARCO" },
            { id: "3-5", week: 29, title: "Navegadores Privados (Tor, Brave)" },
            { id: "3-6", week: 30, title: "Internet de las Cosas (IoT)" },
            { id: "3-7", week: 31, title: "Seguridad en la Nube" },
            { id: "3-8", week: 32, title: "Metadatos en Multimedia" },
            { id: "3-9", week: 33, title: "E-Commerce Seguro" },
            { id: "3-10", week: 34, title: "Banca en Línea Segura" },
            { id: "3-11", week: 35, title: "VPNs Avanzadas" },
            { id: "3-12", week: 36, title: "Repaso y Test Nivel 3" },
        ]
    },
    {
        id: 4,
        name: "Conceptos Avanzados",
        rank: "SARGENTO",
        lessons: [
            { id: "4-1", week: 37, title: "¿Qué es un Firewall?" },
            { id: "4-2", week: 38, title: "Dark Web vs Deep Web" },
            { id: "4-3", week: 39, title: "Gestión de Brechas de Datos" },
            { id: "4-4", week: 40, title: "Criptografía Básica" },
            { id: "4-5", week: 41, title: "Seguridad Corporativa" },
            { id: "4-6", week: 42, title: "IA en Ciberseguridad" },
            { id: "4-7", week: 43, title: "Blockchain y Criptoactivos" },
            { id: "4-8", week: 44, title: "Ética Hacker (White/Black/Gray)" },
            { id: "4-9", week: 45, title: "Leyes y Denuncia" },
            { id: "4-10", week: 46, title: "Plan de Respuesta a Incidentes" },
            { id: "4-11", week: 47, title: "Futuro: Bio y Cuántica" },
            { id: "4-12", week: 48, title: "EXAMEN FINAL CPEH/CDFE" },
        ]
    }
];

interface TrainingProtocolProps {
    onProgressUpdate?: (progress: number, rank: string) => void;
    isGraduated?: boolean;
}

const TrainingProtocol: React.FC<TrainingProtocolProps> = ({ onProgressUpdate, isGraduated }) => {
    const [activeBlock, setActiveBlock] = useState(1);
    const [completedLessons, setCompletedLessons] = useState<string[]>([]);
    const [isMenuOpen, setIsMenuOpen] = useState(false);

    const totalLessons = 48;
    const progress = (completedLessons.length / totalLessons) * 100;

    // Gamificación Táctica (Fase 10)
    const getRankData = (prog: number) => {
        if (isGraduated) return { name: 'COMANDANTE SUPREMO', color: '#fbbf24', glow: 'rgba(251, 191, 36, 0.6)', icon: 'Award' };
        if (prog >= 100) return { name: 'ASPIRANTE A COMANDANTE', color: '#10b981', glow: 'rgba(16, 185, 129, 0.5)', icon: 'Award' };
        if (prog > 60) return { name: 'SARGENTO', color: '#22d3ee', glow: 'rgba(34, 211, 238, 0.4)', icon: 'Star' };
        if (prog > 40) return { name: 'CABO', color: '#facc15', glow: 'rgba(250, 204, 21, 0.4)', icon: 'Target' };
        if (prog > 20) return { name: 'SOLDADO', color: '#ef4444', glow: '#ef444466', icon: 'Shield' };
        return { name: 'RECLUTA', color: '#a855f7', glow: 'rgba(168, 85, 247, 0.4)', icon: 'Shield' };
    };

    const currentRank = getRankData(progress);

    useEffect(() => {
        if (onProgressUpdate) {
            onProgressUpdate(progress, currentRank.name);
        }
    }, [progress, onProgressUpdate, currentRank.name]);

    const handleLessonComplete = (lessonId: string) => {
        if (!completedLessons.includes(lessonId)) {
            setCompletedLessons(prev => [...prev, lessonId]);
        }
    };

    return (
        <div className="w-full flex flex-col mt-6">
            <motion.div
                onClick={() => setIsMenuOpen(!isMenuOpen)}
                className="w-full border border-[rgba(74,85,120,0.55)] bg-white/[0.03] backdrop-blur-md p-6 rounded cursor-pointer hover:bg-white/[0.05] transition-colors flex flex-col items-center shadow-[0_18px_50px_rgba(0,0,0,0.18)] relative overflow-hidden group"
                style={{ borderColor: `${currentRank.color}44` }}
                whileHover={{ scale: 1.005 }}
                whileTap={{ scale: 0.99 }}
            >
                {/* Cabecera */}
                <div className="w-full pt-4 pb-2 border-b border-white/10 mb-6 px-6">
                    <div className="flex items-center justify-center gap-2">
                        <Shield className="w-4 h-4 flex-shrink-0" style={{ color: currentRank.color }} />
                        <h3 className="text-[.72rem] font-semibold tracking-[.2em] text-white uppercase text-center">PROTOCOLO DE ENTRENAMIENTO</h3>
                    </div>
                </div>

                {/* Radar Homologado 212px */}
                <div className="relative group/radar mb-4">
                    <div className="w-[212px] h-[212px] rounded-full flex items-center justify-center relative backdrop-blur-sm bg-white/[0.02]">
                        <svg className="absolute inset-0 w-full h-full -rotate-90">
                            <circle cx="106" cy="106" r="98" fill="none" stroke="rgba(255,255,255,0.05)" strokeWidth="2" />
                            <motion.circle
                                cx="106" cy="106" r="98" fill="none"
                                stroke={currentRank.color} strokeWidth="8"
                                strokeDasharray={2 * Math.PI * 98}
                                initial={{ strokeDashoffset: 2 * Math.PI * 98 }}
                                animate={{ strokeDashoffset: 2 * Math.PI * 98 * (1 - (progress / 100)) }}
                                transition={{ duration: 1.5, ease: "easeOut" }}
                                strokeLinecap="round"
                                style={{ filter: `drop-shadow(0 0 8px ${currentRank.color}88)` }}
                            />
                        </svg>
                        <div className="flex flex-col items-center">
                            <span className="text-5xl font-black font-mono tracking-tighter" style={{ color: currentRank.color, textShadow: `0 0 20px ${currentRank.glow}` }}>
                                {Math.round(progress)}%
                            </span>
                            <span className="text-[10px] text-white/40 uppercase tracking-[0.2em] font-bold mt-1">Status</span>
                        </div>
                        {/* Insignia Dinámica */}
                        <div className="absolute top-0 right-0 -mr-2 -mt-2 z-20 transition-transform duration-500 group-hover/radar:scale-110">
                            <div className="w-12 h-12 rounded-full bg-ops-bg border-2 flex items-center justify-center shadow-xl backdrop-blur-md"
                                style={{ borderColor: currentRank.color, boxShadow: `0 0 15px ${currentRank.glow}` }}>
                                {currentRank.icon === 'Shield' && <Shield className="w-6 h-6" style={{ color: currentRank.color }} />}
                                {currentRank.icon === 'Target' && <Target className="w-6 h-6" style={{ color: currentRank.color }} />}
                                {currentRank.icon === 'Star' && <Star className="w-6 h-6" style={{ color: currentRank.color }} />}
                                {currentRank.icon === 'Award' && <Award className="w-6 h-6" style={{ color: currentRank.color }} />}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Botón Detalles */}
                <div className="w-full mt-6 flex justify-center">
                    <div className="w-[240px] h-9 border border-white/10 bg-white/5 rounded flex items-center justify-center gap-2 transition-all hover:bg-white/10 overflow-hidden"
                        style={{ borderColor: `${currentRank.color}33` }}>
                        <span className="text-[0.62rem] uppercase tracking-[.3em] font-light text-white whitespace-nowrap">DETALLES CURRICULARES</span>
                        <ChevronDown className={`w-3 h-3 transition-transform duration-300 ${isMenuOpen ? 'rotate-180' : ''}`} style={{ color: currentRank.color }} />
                    </div>
                </div>
            </motion.div>

            <AnimatePresence>
                {isMenuOpen && (
                    <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden w-full">
                        <div className="pt-6 flex flex-col gap-6">
                            <div className="grid grid-cols-4 gap-2 px-1">
                                {CURRICULUM.map(block => (
                                    <button key={block.id} onClick={(e) => { e.stopPropagation(); setActiveBlock(block.id); }} className={`py-2 rounded border text-[10px] uppercase tracking-tighter transition-all ${activeBlock === block.id ? 'bg-ops-accent/20 border-ops-accent text-white font-bold' : 'bg-white/5 border-white/10 text-ops-text_dim'}`}>
                                        B{block.id}
                                    </button>
                                ))}
                            </div>
                            <div className="flex flex-col gap-3">
                                <div className="flex justify-between items-end px-2 border-l-2 border-ops-accent pl-4">
                                    <div className="flex flex-col">
                                        <span className="text-[10px] text-ops-accent font-mono uppercase tracking-widest">{CURRICULUM[activeBlock - 1].rank}</span>
                                        <h4 className="text-sm font-bold text-white uppercase tracking-wider">{CURRICULUM[activeBlock - 1].name}</h4>
                                    </div>
                                    <span className="text-[10px] text-ops-text_dim/60 font-mono">SEMANAS {CURRICULUM[activeBlock - 1].lessons[0].week}-{CURRICULUM[activeBlock - 1].lessons[11].week}</span>
                                </div>
                                <div className="grid grid-cols-1 gap-2 mt-2">
                                    {CURRICULUM[activeBlock - 1].lessons.map((lesson, idx) => {
                                        const isCompleted = completedLessons.includes(lesson.id);
                                        const isUnlocked = idx === 0 && activeBlock === 1 ? true : completedLessons.length >= (activeBlock - 1) * 12 + idx;
                                        return (
                                            <div key={lesson.id} onClick={(e) => { e.stopPropagation(); if (isUnlocked && !isCompleted) handleLessonComplete(lesson.id); }} className={`p-4 border rounded flex items-center justify-between transition-all ${isCompleted ? 'bg-ops-success/10 border-ops-success/30' : isUnlocked ? 'bg-white/5 border-white/10 hover:border-ops-accent/40' : 'bg-black/20 border-white/5 opacity-50 cursor-not-allowed'}`}>
                                                <div className="flex items-center gap-4">
                                                    <span className="text-[10px] font-mono text-ops-text_dim/40 w-6">{lesson.week}</span>
                                                    <span className={`text-xs uppercase tracking-wide font-medium ${isCompleted ? 'text-ops-success' : 'text-white/80'}`}>{lesson.title}</span>
                                                </div>
                                                {isCompleted ? <CheckCircle className="w-4 h-4 text-ops-success" /> : !isUnlocked ? <Lock className="w-3.5 h-3.5 text-white/20" /> : <div className="w-4 h-4 rounded-full border border-white/20" />}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
};

export default TrainingProtocol;
