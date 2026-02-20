import React, { useState } from 'react';
import { Mail, Lock, ShieldCheck } from 'lucide-react';

interface LoginViewProps {
    onLogin: (email: string, pass: string) => void;
    isLoading: boolean;
}

const LoginView: React.FC<LoginViewProps> = ({ onLogin, isLoading }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!email || !password) return;
        onLogin(email, password);
    };

    return (
        <div className="ops-card max-w-sm mx-auto transform transition-all hover:scale-[1.005] w-full flex flex-col justify-center animate-in fade-in zoom-in duration-500">
            <div className="absolute top-0 right-0 p-2 opacity-50">
                <div className="w-16 h-16 border-t-2 border-r-2 border-ops-accent rounded-tr-xl"></div>
            </div>

            <h2 className="text-xs tall:text-sm md:text-xl font-bold mb-3 tall:mb-6 md:mb-8 text-center text-white tracking-[0.2em] uppercase border-b border-white/10 pb-2 tall:pb-4 md:pb-4 transition-all duration-300 flex items-center justify-center gap-3">
                <ShieldCheck className="text-ops-accent w-5 h-5 md:w-6 md:h-6" />
                VINCULACIÓN TÁCTICA
            </h2>

            <form onSubmit={handleSubmit} className="space-y-4 relative z-10 transition-all duration-300 px-2 sm:px-6">
                <div>
                    <label className="block text-[9px] tall:text-[10px] md:text-xs font-mono text-ops-accent mb-1 tracking-wider uppercase">
                        Email Target
                    </label>
                    <div className="relative group">
                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-4 h-4 group-focus-within:text-ops-accent transition-colors" />
                        <input
                            type="email"
                            required
                            className="input-field pl-10 bg-black/40 focus:bg-ops-bg_alt/80 text-sm py-3 w-full transition-all duration-300 border-ops-border/30 focus:border-ops-accent"
                            placeholder="felipe@ejemplo.com"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-[9px] tall:text-[10px] md:text-xs font-mono text-ops-accent mb-1 tracking-wider uppercase">
                        Contraseña de Operador
                    </label>
                    <div className="relative group">
                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-4 h-4 group-focus-within:text-ops-accent transition-colors" />
                        <input
                            type="password"
                            required
                            className="input-field pl-10 bg-black/40 focus:bg-ops-bg_alt/80 text-sm py-3 w-full transition-all duration-300 border-ops-border/30 focus:border-ops-accent"
                            placeholder="••••••••"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={isLoading}
                    className="btn-ops w-full flex items-center justify-center gap-2 group mt-4 h-12 text-xs sm:text-sm md:text-base font-bold tracking-widest hover:scale-[1.02] active:scale-[0.98] transition-all duration-200"
                >
                    {isLoading ? (
                        <span className="animate-pulse flex items-center gap-2">
                            <div className="w-2 h-2 bg-ops-accent animate-ping rounded-full" />
                            ESTABLECIENDO ENLACE...
                        </span>
                    ) : (
                        <>
                            ESTABLECER CONEXIÓN SCORPION
                        </>
                    )}
                </button>
            </form>

            <p className="mt-6 text-[8px] md:text-[10px] text-ops-text_dim text-center uppercase tracking-[0.1em] opacity-60 px-4">
                ADVERTENCIA: Este dispositivo se vinculará permanentemente al email especificado.
            </p>
        </div>
    );
};

export default LoginView;
