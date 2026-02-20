import React, { useState } from 'react';
import { Fingerprint, KeyRound, ShieldAlert } from 'lucide-react';

interface VerificationViewProps {
    email: string;
    onVerify: (code: string) => void;
    onResend: () => void;
    isLoading: boolean;
    error?: string | null;
}

const VerificationView: React.FC<VerificationViewProps> = ({ email, onVerify, onResend, isLoading, error }) => {
    const [code, setCode] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (code.length === 6) {
            onVerify(code);
        }
    };

    return (
        <div className="ops-card max-w-sm mx-auto transform transition-all hover:scale-[1.005] w-full flex flex-col justify-center animate-in slide-in-from-right-10 duration-500">
            <h2 className="text-xs tall:text-sm md:text-xl font-bold mb-3 tall:mb-6 md:mb-8 text-center text-white tracking-[0.2em] uppercase border-b border-white/10 pb-2 tall:pb-4 md:pb-4 transition-all duration-300 flex items-center justify-center gap-3">
                <Fingerprint className="text-ops-radioactive w-5 h-5 md:w-6 md:h-6" />
                VALIDACIÓN DE IDENTIDAD
            </h2>

            <div className="px-6 text-center space-y-4">
                <p className="text-[10px] md:text-xs text-ops-text_dim uppercase tracking-widest leading-relaxed">
                    SE HA ENVIADO UN CÓDIGO TÁCTICO AL CORREO DEL OBJETIVO:
                    <br />
                    <span className="text-white font-bold block mt-1">[{email}]</span>
                </p>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="relative group">
                        <KeyRound className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-5 h-5 group-focus-within:text-ops-radioactive transition-colors" />
                        <input
                            type="text"
                            maxLength={6}
                            required
                            autoFocus
                            className="input-field pl-12 bg-black/40 focus:bg-ops-bg_alt/80 text-2xl text-center font-mono py-4 w-full tracking-[0.5em] transition-all duration-300 border-ops-border/30 focus:border-ops-radioactive"
                            placeholder="000000"
                            value={code}
                            onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                        />
                    </div>

                    {error && (
                        <div className="flex items-center gap-2 text-ops-danger text-[10px] bg-ops-danger/10 p-2 rounded border border-ops-danger/20 animate-pulse">
                            <ShieldAlert size={14} />
                            <span className="font-bold uppercase tracking-tighter">{error}</span>
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={isLoading || code.length !== 6}
                        className="btn-ops w-full flex items-center justify-center gap-2 group h-12 text-xs sm:text-sm md:text-base font-bold tracking-widest hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 border-ops-radioactive/50 hover:bg-ops-radioactive/10"
                    >
                        {isLoading ? (
                            <span className="animate-pulse flex items-center gap-2">
                                <div className="w-2 h-2 bg-ops-radioactive animate-ping rounded-full" />
                                VALIDANDO...
                            </span>
                        ) : (
                            <>PROCESAR TOKEN</>
                        )}
                    </button>
                </form>

                <button
                    onClick={onResend}
                    disabled={isLoading}
                    className="text-[9px] md:text-[10px] text-ops-text_dim hover:text-white uppercase tracking-widest underline decoration-ops-radioactive/30 underline-offset-4 transition-all"
                >
                    Reenviar Código de Acceso
                </button>
            </div>

            <p className="mt-8 text-[8px] md:text-[10px] text-ops-text_dim text-center uppercase tracking-[0.1em] opacity-40 px-6">
                PROTOCOLO SCORPION PROTECTED • AES-256 ENCRYPTED HANDSHAKE
            </p>
        </div>
    );
};

export default VerificationView;
