import React, { useState } from 'react';
import { Search, Mail, Globe, User } from 'lucide-react';

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
        <div className="glass-panel p-8 max-w-2xl mx-auto transform transition-all hover:scale-[1.01]">
            <h2 className="text-2xl font-bold mb-6 text-center text-white">INICIAR ESCANEO DE INTELIGENCIA</h2>

            <form onSubmit={handleSubmit} className="space-y-6">
                <div>
                    <label className="block text-sm font-mono text-cyber-muted mb-2">NOMBRE DEL OBJETIVO</label>
                    <div className="relative">
                        <User className="absolute left-3 top-3 text-cyber-muted w-5 h-5" />
                        <input
                            type="text"
                            className="input-field pl-10"
                            placeholder="Ej. Felipe Miramontes"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="block text-sm font-mono text-cyber-muted mb-2">CORREO ELECTRÓNICO (REQUIRED)</label>
                        <div className="relative">
                            <Mail className="absolute left-3 top-3 text-cyber-muted w-5 h-5" />
                            <input
                                type="email"
                                required
                                className="input-field pl-10 border-cyber-accent/30"
                                placeholder="target@example.com"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-mono text-cyber-muted mb-2">DOMINIO (OPCIONAL)</label>
                        <div className="relative">
                            <Globe className="absolute left-3 top-3 text-cyber-muted w-5 h-5" />
                            <input
                                type="text"
                                className="input-field pl-10"
                                placeholder="empresa.com"
                                value={domain}
                                onChange={(e) => setDomain(e.target.value)}
                            />
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={isLoading}
                    className="btn-primary w-full flex items-center justify-center gap-2 group"
                >
                    {isLoading ? (
                        <span className="animate-pulse">INICIALIZANDO PROTOCOLO...</span>
                    ) : (
                        <>
                            <Search className="w-5 h-5 group-hover:rotate-90 transition-transform" />
                            EJECUTAR OSINT
                        </>
                    )}
                </button>
            </form>
        </div>
    );
};

export default ScanForm;
