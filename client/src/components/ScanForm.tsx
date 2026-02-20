import React, { useState } from 'react';
import { Mail, Rocket } from 'lucide-react';

interface ScanFormProps {
    onScan: (data: { name: string; email: string; domain: string }) => void;
    isLoading: boolean;
}

const ScanForm: React.FC<ScanFormProps> = ({ onScan, isLoading }) => {
    // Removed unused name/domain state
    const [email, setEmail] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!email) return;
        // Defaults: Name="Usuario", Domain="" (Hidden fields)
        onScan({ name: "Usuario", email, domain: "" });
    };

    return (
        <div className="ops-card max-w-xl mx-auto transform transition-all hover:scale-[1.005] w-full flex flex-col justify-center">
            <div className="absolute top-0 right-0 p-2 opacity-50">
                <div className="w-16 h-16 border-t-2 border-r-2 border-ops-accent rounded-tr-xl"></div>
            </div>

            <h2 className="text-xs tall:text-sm md:text-xl font-bold mb-3 tall:mb-6 md:mb-8 text-center text-white tracking-[0.2em] uppercase border-b border-white/10 pb-2 tall:pb-4 md:pb-4 transition-all duration-300">
                INICIAR SECUENCIA DE VIGILANCIA
            </h2>

            <form onSubmit={handleSubmit} className="space-y-4 relative z-10 transition-all duration-300 px-2 sm:px-6">

                {/* Hidden Fields Logic Kept in State but removed from UI
                    Name defaults to 'Usuario'
                    Domain defaults to '' 
                */}

                <div>
                    <label className="block text-[9px] tall:text-[10px] md:text-xs font-mono text-ops-accent mb-1 tracking-wider uppercase">
                        Correo Electrónico
                    </label>
                    <div className="relative group">
                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-ops-border w-4 h-4 group-focus-within:text-ops-accent transition-colors" />
                        <input
                            type="email"
                            required
                            className="input-field pl-10 bg-black/40 focus:bg-ops-bg_alt/80 text-sm py-3 w-full transition-all duration-300 border-ops-border/30 focus:border-ops-accent"
                            placeholder="ejemplo@gmail.com"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={isLoading}
                    className="btn-ops w-full flex items-center justify-center gap-2 group mt-4 h-12 text-xs sm:text-sm md:text-base font-bold tracking-widest hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 whitespace-nowrap overflow-hidden"
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
        </div>
    );
};

export default ScanForm;
