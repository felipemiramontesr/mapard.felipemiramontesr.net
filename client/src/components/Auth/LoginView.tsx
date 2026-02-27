import React, { useState, useEffect } from 'react';
import { Mail, Lock, ShieldCheck, Eye, EyeOff, CheckCircle2, Circle } from 'lucide-react';

interface LoginViewProps {
    onLogin: (email: string, pass: string) => void;
    isLoading: boolean;
    error?: string | null;
}

const LOADING_MESSAGES = [
    "INICIALIZANDO PROTOCOLO",
    "VERIFICANDO HARDWARE_ID",
    "ENCRIPTANDO CANAL SR-71",
    "TRANSMITIENDO PAYLOAD"
];

const LoginView: React.FC<LoginViewProps> = ({ onLogin, isLoading, error }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loadingMsgIdx, setLoadingMsgIdx] = useState(0);

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        if (isLoading) {
            interval = setInterval(() => {
                setLoadingMsgIdx((prev) => (prev + 1) % LOADING_MESSAGES.length);
            }, 600);
        }
        return () => {
            if (interval) clearInterval(interval);
        };
    }, [isLoading]);

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
        if (!email || !isPasswordValid) return;
        onLogin(email, password);
    };

    return (
        <div className="ops-card max-w-2xl mx-auto w-full flex flex-col justify-center animate-in fade-in zoom-in duration-500">
            <h2 className="text-xs tall:text-sm md:text-xl font-bold mb-3 tall:mb-6 md:mb-8 text-center text-white tracking-[0.2em] border-b border-white/10 pb-2 tall:pb-4 md:pb-4 transition-all duration-300 flex items-center justify-center gap-3">
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
                            className="input-field pl-10"
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
                            type={showPassword ? "text" : "password"}
                            required
                            className="input-field pl-10 pr-10"
                            placeholder="••••••••"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-ops-border hover:text-ops-accent transition-colors focus:outline-none"
                        >
                            {showPassword ? (
                                <EyeOff className="w-4 h-4" />
                            ) : (
                                <Eye className="w-4 h-4" />
                            )}
                        </button>
                    </div>

                    {/* Protocol Validation Indicators */}
                    <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[9px] tall:text-[10px] font-mono tracking-wider opacity-80 pl-1">
                        {validations.map((v) => (
                            <div key={v.id} className="flex items-center gap-1.5 transition-colors duration-300">
                                {v.passed ? (
                                    <CheckCircle2 className="w-3 h-3 text-[#00f3ff]" />
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

                {error && (
                    <div className="mt-4 p-3 border border-ops-danger/50 bg-ops-danger/10 rounded break-words text-center flex items-center justify-center animate-in fade-in">
                        <span className="text-ops-danger font-mono text-[10px] md:text-sm uppercase tracking-wider">{error}</span>
                    </div>
                )}

                <button
                    type="submit"
                    disabled={isLoading || !isPasswordValid || !email}
                    className={`btn-ops w-full mt-4 h-12 text-xs sm:text-sm md:text-base gap-2 transition-all duration-300 ${isLoading ? 'bg-black/50 border-ops-accent shadow-[inset_0_0_15px_rgba(138,159,202,0.3)]' : ''}`}
                >
                    {isLoading ? (
                        <div className="flex items-center justify-between w-full px-2 font-mono font-bold tracking-[0.15em] text-ops-accent">
                            <span className="flex items-center gap-2 animate-pulse w-full justify-center">
                                <div className="w-1.5 h-1.5 bg-ops-accent animate-ping rounded-full" />
                                [ {LOADING_MESSAGES[loadingMsgIdx]} ]
                                <div className="w-1.5 h-1.5 bg-ops-accent animate-ping rounded-full" />
                            </span>
                        </div>
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
