import React, { useState } from 'react';
import { ShieldAlert, ShieldCheck, ChevronRight, CheckSquare, Square } from 'lucide-react';
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
            className="w-full max-w-4xl mx-auto mt-8 relative z-20"
        >
            <div className="flex items-center justify-between mb-6 border-b border-white/10 pb-4">
                <h3 className="text-xl font-bold text-white tracking-widest uppercase flex items-center gap-3">
                    <ShieldAlert className="text-ops-radioactive" />
                    Protocolo de Neutralización
                </h3>
                <button
                    onClick={onClose}
                    className="border border-white/10 bg-black/50 px-4 py-2 rounded text-xs font-bold text-ops-text_dim hover:text-white hover:bg-white/5 hover:border-white/30 uppercase tracking-widest transition-all"
                >
                    [ Cerrar Panel ]
                </button>
            </div>

            <div className="space-y-4">
                {vectors.map((vector) => (
                    <div
                        key={vector.id}
                        className={`ops-card relative overflow-hidden transition-all duration-300 ${vector.isNeutralized ? 'border-ops-radioactive/30' : 'border-ops-danger/50'
                            }`}
                    >
                        {/* Status Bar / Header */}
                        <div
                            className="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 cursor-pointer hover:bg-white/5 transition-colors"
                            onClick={() => setExpandedId(expandedId === vector.id ? null : vector.id)}
                        >
                            <div className="flex items-center gap-3 flex-grow">
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

                            {/* Manual Toggle Buttons */}
                            <div className="flex items-center gap-2 bg-black/50 p-1 rounded-lg border border-white/10" onClick={(e) => e.stopPropagation()}>
                                <button
                                    onClick={() => toggleNeutralization(vector.id, false)}
                                    className={`px-4 py-2 rounded text-xs font-bold uppercase tracking-wider transition-all duration-300 ${!vector.isNeutralized
                                        ? 'bg-ops-danger text-black shadow-[0_0_15px_rgba(255,0,80,0.6)] scale-105'
                                        : 'text-ops-danger opacity-50 hover:opacity-100 hover:bg-ops-danger/10'
                                        }`}
                                >
                                    EN RIESGO
                                </button>
                                <button
                                    disabled={!vector.steps.every(s => s.completed) && !vector.isNeutralized}
                                    onClick={() => {
                                        if (vector.steps.every(s => s.completed)) {
                                            toggleNeutralization(vector.id, true);
                                        }
                                    }}
                                    className={`px-4 py-2 rounded text-xs font-bold uppercase tracking-wider transition-all duration-300 ${vector.isNeutralized
                                        ? 'bg-ops-radioactive text-black shadow-[0_0_15px_rgba(57,255,20,0.6)] scale-105'
                                        : vector.steps.every(s => s.completed)
                                            ? 'text-ops-radioactive border border-ops-radioactive/50 hover:bg-ops-radioactive/10 animate-pulse'
                                            : 'text-gray-500 opacity-20 cursor-not-allowed border border-white/5'
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

                            <ChevronRight className={`transition-transform duration-300 ${expandedId === vector.id ? 'rotate-90' : ''}`} />
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
