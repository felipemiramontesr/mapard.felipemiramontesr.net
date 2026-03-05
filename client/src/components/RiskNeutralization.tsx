import React, { useState } from 'react';
import { ShieldAlert, ShieldCheck, ChevronDown, CheckSquare, Square, Shield } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

interface RemediationStep {
    id: string;
    text: string;
    completed: boolean;
}

export interface Vector {
    source_name: string;
    incident_story: string;
    risk_explanation: string;
    specific_remediation: string[];
    gemini_insight?: string; // Phase 2: AI Educational Note
    isNeutralized?: boolean; // Phase 26
    steps?: RemediationStep[]; // Phase 26
}

interface RiskVectorState {
    id: string;
    data: Vector;
    isNeutralized: boolean;
    steps: RemediationStep[];
}

interface RiskNeutralizationProps {
    findings: Vector[];
    onUpdate?: (updatedFindings: Vector[]) => void; // Phase 26
}

const RiskNeutralization: React.FC<RiskNeutralizationProps> = ({ findings, onUpdate }) => {
    // Transform API data into local state with checkboxes
    const [vectors, setVectors] = useState<RiskVectorState[]>(() =>
        findings.map((f, i) => {
            // Check if backend already provided the state
            const hasPersistentState = f.steps && f.steps.length > 0;

            return {
                id: `vec-${i}`,
                data: f,
                isNeutralized: f.isNeutralized ?? false,
                steps: hasPersistentState ? (f.steps as RemediationStep[]) : f.specific_remediation.map((step, sI) => ({
                    id: `step-${i}-${sI}`,
                    text: step,
                    completed: false
                }))
            };
        })
    );

    const [expandedId, setExpandedId] = useState<string | null>(null);

    const toggleStep = (vectorId: string, stepIndex: number) => {
        setVectors(prev => prev.map(v => {
            if (v.id !== vectorId) return v;

            const newSteps = v.steps.map((s, idx) =>
                idx === stepIndex ? { ...s, completed: !s.completed } : s
            );

            // Logic: If user unchecks a box, we must revoke "Neutralized" status immediately
            // because the remediation is no longer complete.
            const allCompleted = newSteps.every(s => s.completed);
            const shouldBeNeutralized = v.isNeutralized && allCompleted;

            // Auto-scroll to neutralize button if just completed
            if (allCompleted && !v.isNeutralized && !shouldBeNeutralized) {
                setTimeout(() => {
                    document.getElementById(`action-panel-${vectorId}`)?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 300);
            }

            return {
                ...v,
                steps: newSteps,
                isNeutralized: shouldBeNeutralized // Auto-revoke if incomplete
            };
        }));
    };

    const toggleNeutralization = (vectorId: string, newState: boolean) => {
        setVectors(prev => {
            const nextState = prev.map(v => {
                if (v.id !== vectorId) return v;

                // If manually reverting to Risk (false), reset all steps to unchecked
                const newSteps = newState === false
                    ? v.steps.map(s => ({ ...s, completed: false }))
                    : v.steps;

                // Phase 2: If neutralizing, ensure card stays expanded and scroll to insight
                if (newState === true) {
                    setTimeout(() => {
                        document.getElementById(`insight-panel-${v.id}`)?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }, 300);
                }

                return { ...v, isNeutralized: newState, steps: newSteps };
            });

            // Persist ONLY when Neutralizar or En Riesgo button is clicked
            if (onUpdate) {
                const updatedFindings: Vector[] = nextState.map(v => ({
                    ...v.data,
                    isNeutralized: v.isNeutralized,
                    steps: v.steps
                }));
                onUpdate(updatedFindings);
            }

            return nextState;
        });
    };


    return (
        <motion.div
            initial={{ opacity: 0, y: 50 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 50 }}
            className="w-full max-w-4xl mx-auto mt-8 relative z-20 pb-10"
        >
            <div className="flex items-center justify-center mb-6 border-b border-white/10 pb-4 gap-4">
                <h3 className="text-[11px] min-[400px]:text-xs md:text-xl font-bold text-white tracking-widest uppercase flex items-center gap-2 md:gap-3 whitespace-nowrap">
                    <ShieldAlert className="text-ops-radioactive w-4 h-4 md:w-6 md:h-6 flex-shrink-0" />
                    <span>Protocolo de Neutralización</span>
                </h3>
            </div>

            {/* Vector List */}
            <div className="flex-grow overflow-y-auto space-y-4 md:space-y-6">
                {vectors.length === 0 && (
                    <div className="flex flex-col items-center justify-center p-12 text-ops-cyan/50 font-mono text-center border border-ops-cyan/20 bg-ops-cyan/5 shadow-[0_0_20px_rgba(0,243,255,0.1)]">
                        <Shield className="w-16 h-16 mb-4 drop-shadow-[0_0_10px_rgba(0,243,255,0.4)]" />
                        <h3 className="text-xl font-bold tracking-[0.2em] text-ops-cyan uppercase">0 Vectores de Riesgo</h3>
                        <p className="mt-2 text-sm max-w-sm font-medium tracking-wide">
                            ANÁLISIS DE BRECHAS LIMPIO. SISTEMA PERIMETRAL ASEGURADO.
                        </p>
                    </div>
                )}
                {vectors.map((vector) => (
                    <div
                        key={vector.id}
                        className={`ops-card relative overflow-hidden transition-all duration-300 hover:-translate-y-[2px] ${vector.isNeutralized ? 'border-ops-radioactive/30 hover:border-ops-radioactive/80' : 'border-ops-danger/50 hover:border-ops-danger/80'
                            }`}
                    >
                        {/* Status Bar / Header */}
                        <div
                            className="relative flex flex-col p-4 cursor-pointer transition-colors gap-4"
                            onClick={() => setExpandedId(expandedId === vector.id ? null : vector.id)}
                        >
                            {/* Top Row: Icon/Name (Left) + Chevron (Right Absolute) */}
                            <div className="flex items-start justify-between w-full pr-8">
                                <div className="flex items-center gap-3 w-full">
                                    <span className={`p-2 rounded-sm flex-shrink-0 ${vector.isNeutralized ? 'bg-ops-radioactive/10 text-ops-radioactive' : 'bg-ops-danger/10 text-ops-danger'}`}>
                                        {vector.isNeutralized ? <ShieldCheck size={20} /> : <ShieldAlert size={20} />}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <h4 className="font-bold text-sm md:text-lg text-white tracking-wide break-all leading-tight">{vector.data.source_name}</h4>
                                        <p className="text-[10px] md:text-xs text-ops-text_dim uppercase tracking-wider mt-0.5">
                                            {vector.isNeutralized ? 'AMENAZA NEUTRALIZADA' : 'RIESGO ACTIVO DETECTADO'}
                                        </p>
                                    </div>
                                </div>

                                {/* Absolute positioned chevron */}
                                <div className={`absolute top-4 right-4 p-1 rounded-sm bg-ops-accent/10 text-ops-accent border border-ops-accent/20 transition-transform duration-300 ${expandedId === vector.id ? 'rotate-180' : ''}`}>
                                    <ChevronDown size={18} />
                                </div>
                            </div>

                            {/* Bottom Row: Manual Toggle Buttons (Left Red - Right Green) */}
                            <div
                                id={`action-panel-${vector.id}`}
                                className="flex items-center justify-between w-full gap-4 mt-2"
                                onClick={(e) => e.stopPropagation()}
                            >
                                {/* Red Button (Left) */}
                                <button
                                    onClick={() => {
                                        toggleNeutralization(vector.id, false);
                                        setExpandedId(vector.id); // Expand when marking as Risk
                                    }}
                                    className={`flex-1 px-2 py-3 md:px-4 md:py-3 rounded-sm text-[10px] md:text-xs font-bold uppercase tracking-widest transition-all duration-300 max-w-[140px] flex justify-center items-center whitespace-nowrap ${!vector.isNeutralized
                                        ? 'bg-ops-danger text-white border border-ops-danger'
                                        : 'text-ops-danger border border-ops-danger/30 hover:bg-ops-danger/10'
                                        }`}
                                >
                                    EN RIESGO
                                </button>

                                {/* Green Button (Right) */}
                                <button
                                    disabled={!vector.steps.every(s => s.completed) && !vector.isNeutralized}
                                    onClick={() => {
                                        if (vector.steps.every(s => s.completed)) {
                                            toggleNeutralization(vector.id, true);
                                            // DO NOT COLPAPSE. We want them to read the insight.
                                            setExpandedId(vector.id);
                                        }
                                    }}
                                    className={`flex-1 px-2 py-3 md:px-4 md:py-3 rounded-sm text-[10px] md:text-xs font-bold uppercase tracking-widest transition-all duration-300 max-w-[140px] flex justify-center items-center whitespace-nowrap ${vector.isNeutralized
                                        ? 'bg-ops-radioactive text-white border border-ops-radioactive'
                                        : vector.steps.every(s => s.completed)
                                            ? 'text-ops-radioactive border border-ops-radioactive/50 hover:bg-ops-radioactive/10 animate-pulse'
                                            : 'bg-white/5 text-white/40 border border-white/10 cursor-not-allowed hover:bg-white/10 transition-colors'
                                        }`}
                                >
                                    {vector.isNeutralized
                                        ? 'NEUTRALIZADO'
                                        : vector.steps.every(s => s.completed)
                                            ? 'NEUTRALIZAR'
                                            : 'PENDIENTE'
                                    }
                                </button>
                            </div>
                        </div>

                        {/* Expandable Content (Details + Checklist) */}
                        <AnimatePresence>
                            {expandedId === vector.id && (
                                <motion.div
                                    initial={{ height: 0, opacity: 0 }}
                                    animate={{ height: 'auto', opacity: 1 }}
                                    exit={{ height: 0, opacity: 0 }}
                                    className="border-t border-white/5 bg-black/20"
                                >
                                    <div className="p-4 md:p-6 space-y-6">
                                        {/* Info Stack */}
                                        <div className="flex flex-col gap-4">
                                            <div>
                                                <h5 className="text-[10px] md:text-xs font-mono text-ops-accent flex items-center gap-2 mb-1.5 uppercase tracking-wider">
                                                    Historia del Incidente
                                                </h5>
                                                <p className="text-xs md:text-sm text-ops-text_dim leading-relaxed border-l-2 border-ops-accent/30 pl-3">
                                                    {vector.data.incident_story}
                                                </p>
                                            </div>
                                            <div>
                                                <h5 className="text-[10px] md:text-xs font-mono text-ops-warning flex items-center gap-2 mb-1.5 uppercase tracking-wider">
                                                    Datos Comprometidos
                                                </h5>
                                                <p className="text-xs md:text-sm text-ops-text_dim leading-relaxed border-l-2 border-ops-warning/30 pl-3">
                                                    {vector.data.risk_explanation}
                                                </p>
                                            </div>
                                        </div>

                                        {/* Remediation Checklist - Flatter */}
                                        <div className="pt-2">
                                            <h5 className="text-[10px] md:text-xs font-mono text-white mb-3 uppercase tracking-widest flex items-center gap-2">
                                                <CheckSquare size={14} className="text-ops-cyan" />
                                                Acciones de Neutralización Requeridas
                                            </h5>
                                            <div className="space-y-2">
                                                {vector.steps.map((step, sIdx) => (
                                                    <div
                                                        key={step.id || sIdx}
                                                        onClick={() => toggleStep(vector.id, sIdx)}
                                                        className={`flex items-start gap-3 p-2.5 rounded cursor-pointer transition-all ${step.completed
                                                            ? 'bg-ops-radioactive/5 border border-ops-radioactive/20'
                                                            : 'bg-black/20 hover:bg-white/5 border border-white/5'
                                                            }`}
                                                    >
                                                        <div className={`mt-0.5 transition-colors ${step.completed ? 'text-ops-radioactive' : 'text-white/30'}`}>
                                                            {step.completed ? <CheckSquare size={18} /> : <Square size={18} />}
                                                        </div>
                                                        <span className={`text-sm ${step.completed ? 'text-white line-through opacity-50' : 'text-ops-text'}`}>
                                                            {step.text}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        {/* Phase 2: Gemini Insight Embedded Panel (Only shows when neutralized) */}
                                        <AnimatePresence>
                                            {vector.isNeutralized && (
                                                <motion.div
                                                    id={`insight-panel-${vector.id}`}
                                                    initial={{ height: 0, opacity: 0, marginTop: 0 }}
                                                    animate={{ height: 'auto', opacity: 1, marginTop: 24 }}
                                                    exit={{ height: 0, opacity: 0, marginTop: 0 }}
                                                    className="overflow-hidden"
                                                >
                                                    <div className="border border-ops-accent/30 bg-ops-accent/10 rounded-lg p-5 relative shadow-[0_0_20px_rgba(138,159,202,0.1)]">
                                                        <div className="absolute top-0 left-0 w-1 h-full bg-ops-accent rounded-l-lg"></div>
                                                        <h5 className="text-[10px] md:text-xs font-bold text-white mb-3 uppercase tracking-widest flex items-center gap-2">
                                                            <Shield className="w-4 h-4 text-ops-accent" />
                                                            Nota Directiva Táctica
                                                        </h5>
                                                        <p className="text-sm text-ops-text_dim font-light leading-relaxed mb-6">
                                                            {vector.data.gemini_insight || "Módulo de Instrucción: Revise sus protocolos de higiene digital para prevenir futuras vulneraciones en este perímetro."}
                                                        </p>

                                                        <div className="flex justify-end">
                                                            <button
                                                                onClick={() => setExpandedId(null)}
                                                                className="px-6 py-2 bg-ops-accent/20 hover:bg-ops-accent/40 text-white border border-ops-accent/50 rounded text-xs font-bold uppercase tracking-widest transition-colors flex items-center gap-2"
                                                            >
                                                                ENTENDIDO <ShieldCheck className="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </motion.div>
                                            )}
                                        </AnimatePresence>
                                    </div>
                                </motion.div>
                            )}
                        </AnimatePresence>
                    </div>
                ))}
            </div>
        </motion.div>
    );
};

export default RiskNeutralization;
