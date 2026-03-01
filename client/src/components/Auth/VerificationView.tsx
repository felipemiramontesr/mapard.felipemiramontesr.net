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
        <div className="ops-card max-w-2xl mx-auto w-full flex flex-col justify-center animate-in slide-in-from-right-10 duration-500">
            <h2 className="text-xs tall:text-sm md:text-xl font-bold mb-3 tall:mb-6 md:mb-8 text-center text-white tracking-[0.2em] uppercase border-b border-white/10 pb-2 tall:pb-4 md:pb-4 transition-all duration-300 flex items-center justify-center gap-3">
                <Fingerprint className="text-ops-accent w-5 h-5 md:w-6 md:h-6" />
                VALIDACIÓN DE IDENTIDAD
            </h2>

            <div className="px-6 text-center space-y-4">
                <p className="text-[10px] md:text-xs text-ops-text_dim uppercase tracking-widest leading-relaxed">
                    SE HA ENVIADO UN CÓDIGO TÁCTICO AL CORREO DEL OBJETIVO:
                    <br />
                    <span className="text-[#00f3ff] font-bold block mt-1 tracking-wider lowercase">{email.toLowerCase()}</span>
                </p>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="relative group">
                        <KeyRound className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-5 h-5 group-focus-within:text-[#00f3ff] transition-colors" />
                        <input
                            type="text"
                            maxLength={6}
                            required
                            autoFocus
                            className="input-field pl-10 md:pl-12 text-lg md:text-2xl text-center font-mono py-4 tracking-[0.2em] md:tracking-[0.5em] text-[#00f3ff] drop-shadow-[0_0_8px_rgba(0,243,255,0.4)]"
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
                        className="btn-ops w-full h-12 text-xs sm:text-sm md:text-base gap-2"
                    >
                        {isLoading ? (
                            <span className="animate-pulse flex items-center gap-2">
                                <div className="w-2 h-2 bg-white animate-ping rounded-full" />
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
                    className="text-[9px] md:text-[10px] text-ops-text_dim hover:text-white uppercase tracking-widest underline decoration-ops-accent/30 underline-offset-4 transition-all"
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
