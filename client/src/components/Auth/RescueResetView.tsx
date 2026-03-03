import React, { useState } from 'react';
import { Lock, ShieldCheck, Eye, EyeOff, CheckCircle2, Circle, ShieldAlert } from 'lucide-react';

interface RescueResetViewProps {
    onReset: (newPassword: string) => void;
    isLoading: boolean;
    error?: string | null;
}

const RescueResetView: React.FC<RescueResetViewProps> = ({ onReset, isLoading, error }) => {
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);

    const validations = [
        { id: 'length', text: 'MÍNIMO 8 CARACTERES', passed: password.length >= 8 },
        { id: 'uppercase', text: 'MIN. 1 LETRA MAYÚSCULA', passed: /[A-Z]/.test(password) },
        { id: 'lowercase', text: 'MIN. 1 LETRA MINÚSCULA', passed: /[a-z]/.test(password) },
        { id: 'number', text: 'MIN. 1 NÚMERO', passed: /[0-9]/.test(password) },
        { id: 'special', text: 'MIN. 1 CARÁCTER ESPECIAL', passed: /[!@#$%^&*(),.?":{}|<>]/.test(password) }
    ];

    const isPasswordValid = validations.every(v => v.passed);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isPasswordValid && !isLoading) {
            onReset(password);
        }
    };

    return (
        <div className="ops-card max-w-2xl mx-auto w-full flex flex-col justify-center animate-in fade-in zoom-in duration-500 border border-[#ff3366]/30">
            <div className="text-center mb-8 border-b border-[#ff3366]/20 pb-4">
                <ShieldCheck className="w-12 h-12 md:w-16 md:h-16 text-[#ff3366] mx-auto mb-4 animate-pulse" />
                <h2 className="text-lg md:text-2xl font-bold tracking-[0.2em] mb-2 uppercase text-white">
                    NUEVA LLAVE MAESTRA
                </h2>
                <p className="text-xs md:text-sm text-[#ff3366] uppercase tracking-widest leading-relaxed">
                    SU IDENTIDAD HA SIDO CONFIRMADA. ESTABLEZCA UNA NUEVA CREDENCIAL DE ACCESO.
                </p>
            </div>

            {error && (
                <div className="mb-6 p-4 border border-ops-danger/50 bg-ops-danger/10 rounded break-words text-center flex flex-col items-center justify-center animate-[pulse_2s_ease-in-out_infinite]">
                    <ShieldAlert className="w-6 h-6 text-ops-danger mb-2" />
                    <span className="text-ops-danger font-mono text-[9px] md:text-xs uppercase tracking-wider whitespace-pre-line leading-relaxed">{error}</span>
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4 relative z-10 transition-all duration-300 px-2 sm:px-6">
                <div>
                    <label className="block text-[9px] tall:text-[10px] md:text-xs font-mono text-[#ff3366] mb-1 tracking-wider uppercase">
                        NUEVA CONTRASEÑA TÁCTICA
                    </label>
                    <div className="relative group">
                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-4 h-4 group-focus-within:text-[#ff3366] transition-colors" />
                        <input
                            type={showPassword ? "text" : "password"}
                            required
                            className="input-field pl-10 pr-10 focus:border-[#ff3366] focus:ring-[#ff3366]/20"
                            placeholder="••••••••"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-ops-border hover:text-[#ff3366] transition-colors focus:outline-none"
                        >
                            {showPassword ? (
                                <EyeOff className="w-4 h-4" />
                            ) : (
                                <Eye className="w-4 h-4" />
                            )}
                        </button>
                    </div>

                    {/* Protocol Validation Indicators */}
                    <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[9px] tall:text-[10px] font-mono tracking-wider opacity-80 pl-1 animate-in slide-in-from-top-2">
                        {validations.map((v) => (
                            <div key={v.id} className="flex items-center gap-1.5 transition-colors duration-300">
                                {v.passed ? (
                                    <CheckCircle2 className="w-3 h-3 text-[#ff3366]" />
                                ) : (
                                    <Circle className="w-3 h-3 text-ops-text_dim/40" />
                                )}
                                <span className={v.passed ? "text-white" : "text-ops-text_dim"}>
                                    {v.text}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={isLoading || !isPasswordValid}
                    className={`btn-ops w-full mt-6 h-12 text-xs sm:text-sm md:text-base gap-2 transition-all duration-300 bg-[#ff3366]/20 border-[#ff3366] hover:bg-[#ff3366]/40 text-[#ff3366]`}
                >
                    {isLoading ? (
                        <div className="flex items-center justify-between w-full px-2 font-mono font-bold tracking-[0.15em] text-[#ff3366]">
                            <span className="flex items-center gap-2 animate-pulse w-full justify-center">
                                <div className="w-1.5 h-1.5 bg-[#ff3366] animate-ping rounded-full" />
                                ENCRIPTANDO
                                <div className="w-1.5 h-1.5 bg-[#ff3366] animate-ping rounded-full" />
                            </span>
                        </div>
                    ) : (
                        <>
                            SOBRESCRIBIR CREDENCIAL
                        </>
                    )}
                </button>
            </form>
        </div>
    );
};

export default RescueResetView;
