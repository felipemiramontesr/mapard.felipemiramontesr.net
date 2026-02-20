import React, { useState } from 'react';
import { ShieldAlert, ShieldCheck, ChevronDown, CheckSquare, Square } from 'lucide-react';
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
    specific_remediation: string[]; // Raw strings from API
}

interface RiskVectorState {
    id: string;
    data: Vector;
    isNeutralized: boolean;
    steps: RemediationStep[];
}

interface RiskNeutralizationProps {
    findings: Vector[];
    onClose: () => void;
}

const RiskNeutralization: React.FC<RiskNeutralizationProps> = ({ findings, onClose }) => {
    // Transform API data into local state with checkboxes
    const [vectors, setVectors] = useState<RiskVectorState[]>(() =>
        findings.map((f, i) => ({
            id: `vec-${i}`,
            data: f,
            isNeutralized: false,
            steps: f.specific_remediation.map((step, sI) => ({
                id: `step-${i}-${sI}`,
                text: step,
                completed: false
            }))
        }))
    );

    const [expandedId, setExpandedId] = useState<string | null>(vectors[0]?.id || null);

    const toggleStep = (vectorId: string, stepId: string) => {
        setVectors(prev => prev.map(v => {
            if (v.id !== vectorId) return v;

            const newSteps = v.steps.map(s =>
                s.id === stepId ? { ...s, completed: !s.completed } : s
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
        setVectors(prev => prev.map(v => {
            if (v.id !== vectorId) return v;

            // If manually reverting to Risk (false), reset all steps to unchecked
            const newSteps = newState === false
                ? v.steps.map(s => ({ ...s, completed: false }))
                : v.steps;

            return { ...v, isNeutralized: newState, steps: newSteps };
        }));
    };


    return (
        <motion.div
            initial={{ opacity: 0, y: 50 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 50 }}
            className="w-full max-w-4xl mx-auto mt-8 relative z-20 pb-10"
        >
            <div className="flex items-center justify-between mb-6 border-b border-white/10 pb-4">
                <h3 className="text-xl font-bold text-white tracking-widest uppercase flex items-center gap-3">
                    <ShieldAlert className="text-ops-radioactive" />
                    Protocolo de Neutralización
                </h3>
                <button
                    onClick={onClose}
                    className="border border-white/10 bg-black/50 px-4 py-2 rounded text-xs font-bold text-ops-text_dim hover:text-white hover:bg-white/5 hover:border-white/30 uppercase tracking-widest transition-all whitespace-nowrap"
                >
                    Cerrar Panel
                </button>
            </div>

            <div className="space-y-4">
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
                                <div className="flex items-center gap-3">
                                    <span className={`p-2 rounded-full ${vector.isNeutralized ? 'bg-ops-radioactive/10 text-ops-radioactive' : 'bg-ops-danger/10 text-ops-danger'}`}>
                                        {vector.isNeutralized ? <ShieldCheck size={20} /> : <ShieldAlert size={20} />}
                                    </span>
                                    <div>
                                        <h4 className="font-bold text-lg text-white tracking-wide">{vector.data.source_name}</h4>
                                        <p className="text-xs text-ops-text_dim uppercase tracking-wider">
                                            {vector.isNeutralized ? 'AMENAZA NEUTRALIZADA' : 'RIESGO ACTIVO DETECTADO'}
                                        </p>
                                    </div>
                                </div>

                                {/* Absolute positioned chevron */}
                                <div className={`absolute top-4 right-4 p-1 rounded-full bg-ops-cyan/10 text-ops-cyan border border-ops-cyan/20 transition-transform duration-300 ${expandedId === vector.id ? 'rotate-180' : ''}`}>
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
                                    className={`flex-1 px-4 py-3 rounded text-xs font-bold uppercase tracking-wider transition-all duration-300 max-w-[140px] flex justify-center items-center ${!vector.isNeutralized
                                        ? 'bg-ops-danger text-black shadow-[0_0_15px_rgba(255,0,80,0.6)] scale-105'
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
                                            setExpandedId(null); // Collapse when Neutralized
                                        }
                                    }}
                                    className={`flex-1 px-4 py-3 rounded text-xs font-bold uppercase tracking-wider transition-all duration-300 max-w-[140px] flex justify-center items-center ${vector.isNeutralized
                                        ? 'bg-ops-radioactive text-black shadow-[0_0_15px_rgba(57,255,20,0.6)] scale-105'
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
                                    <div className="p-6 space-y-6">
                                        {/* Info Grid */}
                                        <div className="grid md:grid-cols-2 gap-6">
                                            <div>
                                                <h5 className="text-xs font-mono text-ops-accent mb-2 uppercase">Historia del Incidente</h5>
                                                <p className="text-sm text-ops-text_dim leading-relaxed border-l-2 border-ops-accent/30 pl-3">
                                                    {vector.data.incident_story}
                                                </p>
                                            </div>
                                            <div>
                                                <h5 className="text-xs font-mono text-ops-warning mb-2 uppercase">Datos Comprometidos</h5>
                                                <p className="text-sm text-ops-text_dim leading-relaxed border-l-2 border-ops-warning/30 pl-3">
                                                    {vector.data.risk_explanation}
                                                </p>
                                            </div>
                                        </div>

                                        {/* Remediation Checklist */}
                                        <div className="bg-ops-bg_alt/30 p-4 rounded border border-white/5">
                                            <h5 className="text-xs font-mono text-white mb-4 uppercase tracking-widest flex items-center gap-2">
                                                <CheckSquare size={14} className="text-ops-cyan" />
                                                Acciones de Neutralización Requeridas
                                            </h5>
                                            <div className="space-y-3">
                                                {vector.steps.map(step => (
                                                    <div
                                                        key={step.id}
                                                        onClick={() => toggleStep(vector.id, step.id)}
                                                        className={`flex items-start gap-3 p-3 rounded cursor-pointer transition-all ${step.completed
                                                            ? 'bg-ops-radioactive/5 border border-ops-radioactive/20'
                                                            : 'hover:bg-white/5 border border-transparent'
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
