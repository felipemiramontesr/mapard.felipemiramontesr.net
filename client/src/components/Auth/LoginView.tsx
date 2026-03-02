import React, { useState, useEffect } from 'react';
import { Mail, Lock, ShieldCheck, Eye, EyeOff, CheckCircle2, Circle } from 'lucide-react';

interface LoginViewProps {
    onLogin: (email: string, pass: string) => void;
    isLoading: boolean;
    error?: string | null;
}

const LOADING_MESSAGES = [
    "CIFRANDO"
];

const LoginView: React.FC<LoginViewProps> = ({ onLogin, isLoading, error }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loadingMsgIdx, setLoadingMsgIdx] = useState(0);
    const [lockoutError, setLockoutError] = useState<string | null>(null);
    const [isHardLocked, setIsHardLocked] = useState(false);
    const [isReturningUser, setIsReturningUser] = useState(false);

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        import('../../utils/secureStorage').then(({ secureStorage }) => {
            secureStorage.get('is_returning_user').then((val) => {
                if (val === 'true') setIsReturningUser(true);
            });

            secureStorage.get('biometric_lockout_until').then((lockUntil) => {
                if (lockUntil) {
                    const checkLock = () => {
                        const now = Date.now();
                        const timeList = parseInt(lockUntil, 10);
                        if (now < timeList) {
                            setIsHardLocked(true);
                            const minutes = Math.ceil((timeList - now) / 60000);
                            const formattedMin = minutes < 10 ? `0${minutes}:00` : `${minutes}:00`;
                            setLockoutError(`ACCESO RESTRINGIDO\nSe ha detectado un patrón de acceso inusual. Por seguridad, la terminal se ha bloqueado temporalmente.\nReintento disponible en: [${formattedMin}]`);
                        } else {
                            setIsHardLocked(false);
                            setLockoutError(null);
                            secureStorage.remove('biometric_lockout_until');
                            clearInterval(interval);
                        }
                    };
                    checkLock();
                    interval = setInterval(checkLock, 10000);
                } else {
                    secureStorage.get('biometric_lockout').then((isLocked) => {
                        if (isLocked === 'true') {
                            setLockoutError("SISTEMA BLOQUEADO: Límite biométrico excedido. Autenticación remota requerida.");
                            secureStorage.remove('biometric_lockout');
                        }
                    });
                }
            });
        });
        return () => { if (interval) clearInterval(interval); }
    }, []);

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
            <div className="text-center mb-8">
                <ShieldCheck className="w-12 h-12 md:w-16 md:h-16 text-[#00f3ff] mx-auto mb-4" />
                <h2 className="text-lg md:text-2xl font-bold tracking-[0.2em] mb-2 uppercase text-white">
                    {isReturningUser ? 'RECUPERACIÓN DE DISPOSITIVO DETECTADA' : 'Vinculación Táctica'}
                </h2>
                {isReturningUser && (
                    <p className="text-xs md:text-sm text-ops-text_dim uppercase tracking-widest leading-relaxed">
                        Bienvenido de nuevo, Operador. Detectamos una instancia previa en este terminal. Ingrese sus credenciales para re-sincronizar su llave de acceso con el servidor central.
                    </p>
                )}
            </div>

            {lockoutError && (
                <div className="mb-6 p-4 border border-ops-danger/50 bg-ops-danger/10 rounded break-words text-center flex flex-col items-center justify-center animate-[pulse_2s_ease-in-out_infinite]">
                    <ShieldCheck className="w-6 h-6 text-ops-danger mb-2" />
                    <span className="text-ops-danger font-mono text-[9px] md:text-xs uppercase tracking-wider whitespace-pre-line leading-relaxed">{lockoutError}</span>
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4 relative z-10 transition-all duration-300 px-2 sm:px-6">
                {isHardLocked && (
                    <div className="absolute inset-0 z-20 bg-black/50 backdrop-blur-sm cursor-not-allowed rounded" />
                )}
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
                    {!isHardLocked && (
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
                    )}
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
                        <div className="flex items-center justify-between w-full px-2 font-mono font-bold tracking-[0.15em] text-[#00f3ff]">
                            <span className="flex items-center gap-2 animate-pulse w-full justify-center">
                                <div className="w-1.5 h-1.5 bg-[#00f3ff] animate-ping rounded-full" />
                                {LOADING_MESSAGES[loadingMsgIdx]}
                                <div className="w-1.5 h-1.5 bg-[#00f3ff] animate-ping rounded-full" />
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
