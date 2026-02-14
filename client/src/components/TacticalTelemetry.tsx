import React, { useEffect, useState } from 'react';
import { Activity, Radio, Cpu, Wifi } from 'lucide-react';

const TacticalTelemetry: React.FC = () => {
    const [scramble, setScramble] = useState('LOADING...');
    const [ping, setPing] = useState(24);

    useEffect(() => {
        const interval = setInterval(() => {
            setPing(prev => Math.max(12, Math.min(99, prev + (Math.random() > 0.5 ? 2 : -2))));
            const chars = '0123456789ABCDEF';
            setScramble(chars.split('').sort(() => 0.5 - Math.random()).join('').substring(0, 8));
        }, 1000);
        return () => clearInterval(interval);
    }, []);

    return (
        <div className="w-full max-w-2xl mx-auto mt-8 opacity-40 hover:opacity-100 transition-opacity duration-500 select-none">
            {/* Divider */}
            <div className="flex items-center gap-4 mb-4">
                <div className="h-px bg-ops-accent flex-grow opacity-30"></div>
                <span className="text-[9px] font-mono text-ops-accent tracking-[0.3em]">SYSTEM_DIAGNOSTICS</span>
                <div className="h-px bg-ops-accent flex-grow opacity-30"></div>
            </div>

            {/* Grid Data */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-[9px] tall:text-[10px] font-mono text-ops-text_dim tracking-wider">

                {/* Module 1: Uplink */}
                <div className="flex flex-col gap-1 border-l border-ops-accent/20 pl-3">
                    <div className="flex items-center gap-2 text-ops-accent">
                        <Wifi size={10} className="animate-pulse" />
                        <span>UPLINK_SECURE</span>
                    </div>
                    <span>TLS 1.3 // AES-256</span>
                    <span>PING: {ping}ms</span>
                </div>

                {/* Module 2: CPU */}
                <div className="flex flex-col gap-1 border-l border-ops-accent/20 pl-3">
                    <div className="flex items-center gap-2 text-ops-radioactive">
                        <Cpu size={10} />
                        <span>CORE_LOAD</span>
                    </div>
                    <span>THREADS: 8/8 ACT</span>
                    <span>MEM: SEGMENT_{scramble.substring(0, 4)}</span>
                </div>

                {/* Module 3: Signal */}
                <div className="flex flex-col gap-1 border-l border-ops-accent/20 pl-3">
                    <div className="flex items-center gap-2 text-ops-cyan">
                        <Radio size={10} className="animate-[spin_4s_linear_infinite]" />
                        <span>FREQ_MOD</span>
                    </div>
                    <span>BAND: 2.4Ghz-Enc</span>
                    <span>SIG: -{Math.floor(Math.random() * 20 + 40)}dBm</span>
                </div>

                {/* Module 4: Geo */}
                <div className="flex flex-col gap-1 border-l border-ops-accent/20 pl-3">
                    <div className="flex items-center gap-2 text-ops-warning">
                        <Activity size={10} />
                        <span>GEO_LOCK</span>
                    </div>
                    <span>LAT: [REDACTED]</span>
                    <span>LON: [REDACTED]</span>
                </div>

            </div>

            {/* Bottom Bar */}
            <div className="flex items-center justify-between mt-4 text-[8px] text-white/20">
                <span>ID: {scramble}</span>
                <div className="flex gap-1">
                    {[1, 2, 3, 4, 5].map(i => (
                        <div key={i} className={`w-1 h-1 rounded-full ${Math.random() > 0.5 ? 'bg-ops-accent' : 'bg-white/10'}`}></div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default TacticalTelemetry;
