import React, { useState } from 'react';
import { KeyRound, ShieldAlert } from 'lucide-react';

interface RescueVerificationViewProps {
    email: string;
    onVerify: (code: string) => void;
    isLoading: boolean;
    error?: string | null;
}

const RescueVerificationView: React.FC<RescueVerificationViewProps> = ({ email, onVerify, isLoading, error }) => {
    const [code, setCode] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (code.length === 6) {
            onVerify(code);
        }
    };

    return (
        <div className="ops-card max-w-2xl mx-auto w-full flex flex-col justify-center animate-in slide-in-from-right-10 duration-500 border border-[#ff3366]/30">
            <h2 className="text-xs tall:text-sm md:text-xl font-bold mb-3 tall:mb-6 md:mb-8 text-center text-white tracking-[0.2em] uppercase border-b border-[#ff3366]/20 pb-2 tall:pb-4 md:pb-4 transition-all duration-300 flex items-center justify-center gap-3">
                <ShieldAlert className="text-[#ff3366] w-5 h-5 md:w-6 md:h-6 animate-pulse" />
                PROTOCOLO DE RESCATE
            </h2>

            <div className="px-6 text-center space-y-4">
                <div className="py-6 space-y-3">
                    <p className="text-[10px] md:text-xs text-ops-text_dim uppercase tracking-widest leading-relaxed">
                        SE HA ENVIADO UN CÓDIGO DE EMERGENCIA AL CORREO DEL OBJETIVO:
                    </p>
                    <span className="text-ops-accent font-sans text-[clamp(10px,3.5vw,14px)] font-bold block tracking-widest whitespace-nowrap lowercase py-1">
                        {email.toLowerCase()}
                    </span>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="relative group w-[180px] md:w-[260px] mx-auto">
                        <KeyRound className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-5 h-5 group-focus-within:text-[#ff3366] transition-colors" />
                        <input
                            type="text"
                            maxLength={6}
                            required
                            autoFocus
                            className="input-field pl-12 md:pl-16 text-lg md:text-2xl text-left font-mono py-4 tracking-[0.2em] md:tracking-[0.5em] text-[#ff3366] drop-shadow-[0_0_8px_rgba(255,51,102,0.4)] focus:border-[#ff3366]"
                            placeholder="000000"
                            value={code}
                            onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                        />
                    </div>

                    {error && (
                        <div className="flex items-center gap-2 text-[#ff3366] text-[10px] bg-[#ff3366]/10 p-2 rounded border border-[#ff3366]/20 animate-pulse">
                            <ShieldAlert size={14} />
                            <span className="font-bold uppercase tracking-tighter">{error}</span>
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={isLoading || code.length !== 6}
                        className="btn-ops w-full h-12 text-xs sm:text-sm md:text-base gap-2 bg-[#ff3366]/20 border-[#ff3366] hover:bg-[#ff3366]/40 text-[#ff3366]"
                    >
                        {isLoading ? (
                            <span className="animate-pulse flex items-center gap-2">
                                <div className="w-2 h-2 bg-white animate-ping rounded-full" />
                                VERIFICANDO...
                            </span>
                        ) : (
                            <>AUTORIZAR RESCATE</>
                        )}
                    </button>
                </form>
            </div>

            <p className="mt-8 text-[8px] md:text-[10px] text-ops-text_dim text-center uppercase tracking-[0.1em] opacity-40 px-6">
                PROTOCOLO DE EMERGENCIA • CÓDIGO DE UN SOLO USO
            </p>
        </div>
    );
};

export default RescueVerificationView;
