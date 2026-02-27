import React, { useState } from 'react';
import { Mail, Rocket } from 'lucide-react';

interface ScanFormProps {
    onScan: (data: { name: string; email: string; domain: string }) => void;
    isLoading: boolean;
    lockedEmail?: string | null;
}

const ScanForm: React.FC<ScanFormProps> = ({ onScan, isLoading, lockedEmail }) => {
    // Default to lockedEmail if provided, otherwise empty
    const [email, setEmail] = useState(lockedEmail || '');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const finalEmail = lockedEmail || email;
        if (!finalEmail) return;
        // Defaults: Name="Usuario", Domain="" (Hidden fields)
        onScan({ name: "Usuario", email: finalEmail, domain: "" });
    };

    return (
        <div className="ops-card max-w-2xl mx-auto w-full flex flex-col justify-center">
            <h2 className="text-xs tall:text-sm md:text-xl font-bold mb-3 tall:mb-6 md:mb-8 text-center text-white tracking-[0.2em] border-b border-white/10 pb-2 tall:pb-4 md:pb-4 transition-all duration-300">
                INICIAR SECUENCIA DE VIGILANCIA
            </h2>

            <form onSubmit={handleSubmit} className="space-y-4 relative z-10 transition-all duration-300 px-2 sm:px-6">

                {/* Hidden Fields Logic Kept in State but removed from UI
                    Name defaults to 'Usuario'
                    Domain defaults to '' 
                */}

                {!lockedEmail && (
                    <div className="animate-in fade-in duration-500">
                        <label className="block text-[9px] tall:text-[10px] md:text-xs font-mono text-ops-accent mb-1 tracking-wider uppercase">
                            Correo Electrónico
                        </label>
                        <div className="relative group">
                            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-4 h-4 group-focus-within:text-ops-accent transition-colors" />
                            <input
                                type="email"
                                required
                                className="input-field pl-10"
                                placeholder="ejemplo@gmail.com"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                            />
                        </div>
                    </div>
                )}

                <button
                    type="submit"
                    disabled={isLoading}
                    className="btn-ops w-full mt-4 h-12"
                >
                    {isLoading ? (
                        <span className="animate-pulse flex items-center gap-2">
                            <div className="w-2 h-2 bg-ops-accent animate-ping rounded-full" />
                            EJECUTANDO...
                        </span>
                    ) : (
                        <>
                            <Rocket className="w-4 h-4 group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform duration-300 flex-shrink-0" />
                            EJECUTAR ANÁLISIS
                        </>
                    )}
                </button>
            </form>
        </div >
    );
};

export default ScanForm;
