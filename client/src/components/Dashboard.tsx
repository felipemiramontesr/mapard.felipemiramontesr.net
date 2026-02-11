import React, { useState } from 'react';
import ScanForm from './ScanForm';
import StatusTerminal from './StatusTerminal';
import { format } from 'date-fns';

interface Log {
    id: number;
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    timestamp: string;
}

const Dashboard: React.FC = () => {
    const [logs, setLogs] = useState<Log[]>([]);
    const [isScanning, setIsScanning] = useState(false);

    const addLog = (message: string, type: Log['type'] = 'info') => {
        setLogs(prev => [...prev, {
            id: Date.now(),
            message,
            type,
            timestamp: format(new Date(), 'HH:mm:ss')
        }]);
    };

    const handleStartScan = async (data: { name: string; email: string }) => {
        setIsScanning(true);
        setLogs([]);

        // MOCK SIMULATION FOR PHASE 1
        const steps = [
            { msg: `Initializing MAPA-RD protocol for target: ${data.email}...`, t: 500 },
            { msg: "Connecting to SpiderFoot Engine...", t: 1500 },
            { msg: "Module: haveibeenpwned loaded.", t: 2000 },
            { msg: "Searching public breaches...", t: 2500, type: 'warning' },
            { msg: "Found 3 potential credential leaks.", t: 3500, type: 'error' },
            { msg: "Analyzing Dark Web marketplaces...", t: 4500 },
            { msg: "Calculating Risk Score...", t: 5500 },
            { msg: "Generating PDF Report...", t: 6500 },
            { msg: "SCAN COMPLETE. Report ready for download.", t: 7500, type: 'success' }
        ];

        for (const step of steps) {
            setTimeout(() => {
                addLog(step.msg, (step.type as Log['type']) || 'info');
            }, step.t);
        }

        setTimeout(() => {
            setIsScanning(false);
        }, 8000);
    };

    return (
        <div className="max-w-4xl mx-auto">
            <div className="text-center mb-12">
                <h1 className="text-4xl md:text-5xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-cyber-muted mb-4">
                    INTELIGENCIA DIGITAL
                </h1>
                <p className="text-cyber-muted max-w-2xl mx-auto">
                    Plataforma de vigilancia pasiva y detección de vulnerabilidades.
                    Genera reportes ejecutivos de exposición en minutos.
                </p>
            </div>

            <ScanForm onScan={handleStartScan} isLoading={isScanning} />

            <StatusTerminal logs={logs} isVisible={logs.length > 0} />
        </div>
    );
};

export default Dashboard;
