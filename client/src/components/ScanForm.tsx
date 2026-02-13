import React, { useState } from 'react';
import { Mail, Globe, User, Rocket } from 'lucide-react';

interface ScanFormProps {
    onScan: (data: { name: string; email: string; domain: string }) => void;
    isLoading: boolean;
}

const ScanForm: React.FC<ScanFormProps> = ({ onScan, isLoading }) => {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [domain, setDomain] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!email) return;
        onScan({ name, email, domain });
    };


    return (
    return (
        <div className="ops-card max-w-2xl mx-auto transform transition-all hover:scale-[1.005] w-full">
            <div className="absolute top-0 right-0 p-2 opacity-50">
                <div className="w-16 h-16 border-t-2 border-r-2 border-ops-accent rounded-tr-xl"></div>
            </div>

            <h2 className="text-sm md:text-xl font-bold mb-4 md:mb-8 text-center text-white tracking-[0.2em] uppercase border-b border-white/10 pb-2 md:pb-4">
                INICIAR SECUENCIA DE VIGILANCIA
            </h2>

            <form onSubmit={handleSubmit} className="space-y-3 md:space-y-6 relative z-10">
                <div>
                    <label className="block text-[10px] md:text-xs font-mono text-ops-accent mb-1 md:mb-2 tracking-wider">TARGET_NAME</label>
                    <div className="relative group">
                        <User className="absolute left-3 top-2.5 md:top-3 text-ops-border w-4 h-4 md:w-5 md:h-5 group-focus-within:text-ops-accent transition-colors" />
                        <input
                            type="text"
                            className="input-field pl-9 md:pl-10 bg-black/20 focus:bg-ops-bg_alt/80 text-sm md:text-sm py-2 md:py-3" // Thinner inputs on mobile
                            placeholder="IMPUT_OBJETIVO"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-6">
                    <div>
                        <label className="block text-[10px] md:text-xs font-mono text-ops-accent mb-1 md:mb-2 tracking-wider">EMAIL_ADDRESS (REQUIRED)</label>
                        <div className="relative group">
                            <Mail className="absolute left-3 top-2.5 md:top-3 text-ops-border w-4 h-4 md:w-5 md:h-5 group-focus-within:text-ops-accent transition-colors" />
                            <input
                                type="email"
                                required
                                className="input-field pl-9 md:pl-10 text-sm md:text-sm py-2 md:py-3" // Thinner inputs
                                placeholder="target@domain.com"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-[10px] md:text-xs font-mono text-ops-accent mb-1 md:mb-2 tracking-wider">DOMAIN_URL (OPTIONAL)</label>
                        <div className="relative group">
                            <Globe className="absolute left-3 top-2.5 md:top-3 text-ops-border w-4 h-4 md:w-5 md:h-5 group-focus-within:text-ops-accent transition-colors" />
                            <input
                                type="text"
                                className="input-field pl-9 md:pl-10 text-sm md:text-sm py-2 md:py-3" // Thinner inputs
                                placeholder="www.domain.com"
                                value={domain}
                                onChange={(e) => setDomain(e.target.value)}
                            />
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={isLoading}
                    className="btn-ops w-full flex items-center justify-center gap-3 group mt-2 md:mt-4 h-12 md:h-12 text-xs md:text-base touch-manipulation hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 ease-out"
                >
                    {isLoading ? (
                        <span className="animate-pulse flex items-center gap-2">
                            <div className="w-2 h-2 bg-ops-accent animate-ping rounded-full" />
                            ESTABLECIENDO ENLACE...
                        </span>
                    ) : (
                        <>
                            <Rocket className="w-4 h-4 group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform duration-300" />
                            INICIAR
                        </>
                    )}
                </button>
            </form>
        </div>
    );
};

export default ScanForm;
