import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Shield, Lock, CheckCircle, ChevronDown, Award, Star, Target, Zap } from 'lucide-react';

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
        rank: "CABO",
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
        rank: "SARGENTO",
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
        rank: "OFICIAL DE ÉLITE",
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
}

const TrainingProtocol: React.FC<TrainingProtocolProps> = ({ onProgressUpdate }) => {
    const [activeBlock, setActiveBlock] = useState(1);
    const [completedLessons, setCompletedLessons] = useState<string[]>([]);
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [showExam, setShowExam] = useState<string | null>(null);

    // Mock progress calculation
    const totalLessons = 48;
    const progress = (completedLessons.length / totalLessons) * 100;

    const getCurrentRank = () => {
        if (completedLessons.length >= 48) return "OFICIAL DE ÉLITE";
        if (completedLessons.length >= 36) return "SARGENTO";
        if (completedLessons.length >= 24) return "CABO";
        if (completedLessons.length >= 12) return "RECLUTA AVANZADO";
        return "RECLUTA";
    };

    useEffect(() => {
        if (onProgressUpdate) {
            onProgressUpdate(progress, getCurrentRank());
        }
    }, [completedLessons, onProgressUpdate, progress]);

    const handleLessonComplete = (lessonId: string) => {
        if (!completedLessons.includes(lessonId)) {
            setCompletedLessons(prev => [...prev, lessonId]);
            setShowExam(null);
        }
    };

    return (
        <div className="w-full flex flex-col mt-6">
            <motion.div
                onClick={() => setIsMenuOpen(!isMenuOpen)}
                className="w-full border border-[rgba(74,85,120,0.55)] bg-white/[0.03] backdrop-blur-md p-6 rounded cursor-pointer hover:bg-white/[0.05] transition-colors flex flex-col items-center shadow-[0_18px_50px_rgba(0,0,0,0.18)] relative overflow-hidden group"
                whileHover={{ scale: 1.005 }}
                whileTap={{ scale: 0.99 }}
            >
                {/* Cabecera Táctica */}
                <div className="w-full pt-4 pb-2 border-b border-white/10 mb-6 px-6">
                    <div className="flex items-center justify-center gap-2">
                        <Shield className="w-4 h-4 flex-shrink-0 text-ops-accent" />
                        <h3 className="text-[.72rem] font-semibold tracking-[.2em] text-white uppercase text-center">PROTOCOLO DE ENTRENAMIENTO</h3>
                    </div>
                </div>

                {/* Status de Rango */}
                <div className="w-[260px] h-10 bg-white/5 border border-white/10 rounded mb-4 flex items-center px-4 justify-between overflow-hidden relative">
                    <div className="flex items-center gap-2">
                        <Star className="w-3 h-3 text-ops-accent" />
                        <span className="text-[0.62rem] uppercase tracking-[.25em] text-[#c5cae0] font-bold">{getCurrentRank()}</span>
                    </div>
                    <span className="text-[0.62rem] font-mono text-ops-accent">{Math.round(progress)}%</span>
                    <div className="absolute bottom-0 left-0 h-[1px] bg-ops-accent/50 transition-all duration-500" style={{ width: `${progress}%` }}></div>
                </div>

                {/* Radar de Entrenamiento Central */}
                <div className="relative group/radar">
                    <div className="w-40 h-40 rounded-full border border-ops-accent/20 flex flex-col items-center justify-center relative overflow-hidden backdrop-blur-sm bg-white/[0.02] transition-all duration-500 group-hover:border-ops-accent/40">
                        <span className="text-[3.5rem] font-extralight text-white leading-none">
                            {completedLessons.length}
                        </span>
                        <span className="text-[0.5rem] uppercase tracking-[.4em] text-ops-text_dim mt-1">Semanas</span>

                        {/* Progress Ring Overlay (SVG) */}
                        <svg className="absolute inset-0 w-full h-full -rotate-90">
                            <circle
                                cx="80" cy="80" r="78"
                                fill="none"
                                stroke="rgba(138, 159, 202, 0.1)"
                                strokeWidth="2"
                            />
                            <motion.circle
                                cx="80" cy="80" r="78"
                                fill="none"
                                stroke="rgba(138, 159, 202, 0.6)"
                                strokeWidth="2"
                                strokeDasharray="490"
                                initial={{ strokeDashoffset: 490 }}
                                animate={{ strokeDashoffset: 490 - (4.9 * progress) }}
                                transition={{ duration: 1, ease: "easeOut" }}
                            />
                        </svg>
                    </div>

                    {/* Rank Icons based on progress */}
                    <div className="absolute -top-2 -right-2 w-8 h-8 rounded-full bg-ops-bg border border-ops-accent/30 flex items-center justify-center shadow-lg">
                        <Award className={`w-4 h-4 ${progress >= 100 ? 'text-yellow-400' : 'text-ops-accent/50'}`} />
                    </div>
                </div>

                {/* Botón Detalles */}
                <div className="w-full mt-8 flex justify-center">
                    <div className="w-[240px] h-9 border border-white/10 bg-white/5 rounded flex items-center justify-center gap-2 transition-all hover:bg-white/10 overflow-hidden">
                        <span className="text-[0.62rem] uppercase tracking-[.3em] font-light text-white">DESPLEGAR CURRÍCULO</span>
                        <ChevronDown className={`w-3 h-3 transition-transform duration-300 ${isMenuOpen ? 'rotate-180' : ''} text-ops-accent`} />
                    </div>
                </div>
            </motion.div>

            {/* Panel de Lecciones Expandible */}
            <AnimatePresence>
                {isMenuOpen && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        className="overflow-hidden w-full"
                    >
                        <div className="pt-6 flex flex-col gap-6">
                            {/* Bloques Trimestrales (Tabs) */}
                            <div className="grid grid-cols-4 gap-2 px-1">
                                {CURRICULUM.map(block => (
                                    <button
                                        key={block.id}
                                        onClick={() => setActiveBlock(block.id)}
                                        className={`py-2 rounded border text-[10px] uppercase tracking-tighter transition-all ${activeBlock === block.id
                                                ? 'bg-ops-accent/20 border-ops-accent text-white font-bold'
                                                : 'bg-white/5 border-white/10 text-ops-text_dim'
                                            }`}
                                    >
                                        B{block.id}
                                    </button>
                                ))}
                            </div>

                            {/* Detalle del Bloque Activo */}
                            <div className="flex flex-col gap-3">
                                <div className="flex justify-between items-end px-2 border-l-2 border-ops-accent pl-4">
                                    <div className="flex flex-col">
                                        <span className="text-[10px] text-ops-accent font-mono uppercase tracking-widest">{CURRICULUM[activeBlock - 1].rank}</span>
                                        <h4 className="text-sm font-bold text-white uppercase tracking-wider">{CURRICULUM[activeBlock - 1].name}</h4>
                                    </div>
                                    <span className="text-[10px] text-ops-text_dim/60 font-mono">SEMANAS {CURRICULUM[activeBlock - 1].lessons[0].week}-{CURRICULUM[activeBlock - 1].lessons[11].week}</span>
                                </div>

                                {/* Grid de Lecciones (Matte Tactical Style) */}
                                <div className="grid grid-cols-1 gap-2 mt-2">
                                    {CURRICULUM[activeBlock - 1].lessons.map((lesson, idx) => {
                                        const isCompleted = completedLessons.includes(lesson.id);
                                        // A lesson is unlocked if it's the first one, or the previous one is completed
                                        const isUnlocked = idx === 0 && activeBlock === 1
                                            ? true
                                            : completedLessons.length >= (activeBlock - 1) * 12 + idx;

                                        return (
                                            <div
                                                key={lesson.id}
                                                onClick={() => isUnlocked && !isCompleted && setShowExam(lesson.id)}
                                                className={`p-4 border rounded flex items-center justify-between transition-all group ${isCompleted
                                                        ? 'bg-ops-accent/5 border-ops-accent/30 opacity-100 hover:bg-ops-accent/10 cursor-default'
                                                        : isUnlocked
                                                            ? 'bg-white/[0.03] border-white/10 opacity-100 hover:border-ops-accent/50 cursor-pointer'
                                                            : 'bg-black/20 border-white/5 opacity-40 cursor-not-allowed'
                                                    }`}
                                            >
                                                <div className="flex items-center gap-4">
                                                    <div className="w-8 h-8 rounded border border-white/10 flex items-center justify-center font-mono text-[10px] text-ops-text_dim group-hover:border-ops-accent/50 group-hover:text-white transition-colors">
                                                        {lesson.week}
                                                    </div>
                                                    <div className="flex flex-col gap-0.5">
                                                        <span className={`text-xs font-bold leading-tight ${isCompleted ? 'text-ops-accent' : isUnlocked ? 'text-white' : 'text-ops-text_dim'}`}>
                                                            {lesson.title}
                                                        </span>
                                                        <span className="text-[9px] uppercase tracking-widest text-ops-text_dim/60">
                                                            S{lesson.week} • EVALUACIÓN TÁCTICA
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="flex items-center">
                                                    {isCompleted ? (
                                                        <CheckCircle className="w-4 h-4 text-ops-accent" />
                                                    ) : isUnlocked ? (
                                                        <Zap className="w-4 h-4 text-ops-accent/40 group-hover:text-ops-accent group-hover:animate-pulse transition-all" />
                                                    ) : (
                                                        <Lock className="w-3.5 h-3.5 text-white/20" />
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            {/* Modal de Examen Gamificado (The Scorpion Test) */}
            <AnimatePresence>
                {showExam && (
                    <motion.div
                        initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                        className="fixed inset-0 z-50 flex items-center justify-center p-6 backdrop-blur-xl bg-black/80"
                    >
                        <motion.div
                            initial={{ scale: 0.9, y: 20 }}
                            animate={{ scale: 1, y: 0 }}
                            className="w-full max-w-md bg-[#0a0e27] border border-ops-accent rounded p-8 shadow-[0_0_100px_rgba(138,159,202,0.15)] relative overflow-hidden"
                        >
                            {/* Scanline overlay */}
                            <div className="absolute inset-0 pointer-events-none opacity-5 bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.5)_50%)] bg-[length:100%_4px]"></div>

                            <div className="relative z-10 flex flex-col items-center text-center">
                                <div className="w-16 h-16 rounded-full border-2 border-ops-accent flex items-center justify-center mb-6">
                                    <Target className="w-8 h-8 text-ops-accent animate-pulse" />
                                </div>

                                <span className="text-[10px] font-mono text-ops-accent tracking-[.3em] uppercase mb-2">Evaluación del Operador</span>
                                <h2 className="text-xl font-bold text-white uppercase tracking-tight mb-4">Misión de Validación S{showExam.split('-')[1]}</h2>

                                <div className="w-full bg-white/5 border border-white/10 rounded p-6 mb-8 text-left">
                                    <p className="text-sm text-ops-text_secondary leading-relaxed italic">
                                        "Para desbloquear el siguiente nivel, debes demostrar dominio total sobre el tema. Un error corromperá la conexión."
                                    </p>
                                </div>

                                <div className="flex flex-col w-full gap-3">
                                    <button
                                        onClick={() => handleLessonComplete(showExam)}
                                        className="w-full py-4 bg-ops-accent text-ops-bg font-bold uppercase tracking-[.2em] text-xs rounded hover:bg-white transition-all shadow-lg"
                                    >
                                        INICIAR EXAMEN (3/3)
                                    </button>
                                    <button
                                        onClick={() => setShowExam(null)}
                                        className="w-full py-4 border border-white/10 text-ops-text_dim font-bold uppercase tracking-[.2em] text-xs rounded hover:border-white transition-all"
                                    >
                                        ABORTAR MISIÓN
                                    </button>
                                </div>
                            </div>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
};

export default TrainingProtocol;
