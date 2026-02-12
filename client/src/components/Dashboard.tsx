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

    const runMockSimulation = (data: { email: string }) => {
        addLog("Backend unreachable. Initiating OFF-GRID SIMULATION protocol...", "warning");

        const steps = [
            { msg: `Target confirmed: ${data.email}`, t: 1000 },
            { msg: "Bypassing server connection... Running local heuristics.", t: 2000 },
            { msg: "Querying public breach databases (Simulated)...", t: 3000, type: 'warning' },
            { msg: "Found 4 compromised credentials.", t: 4500, type: 'error' },
            { msg: "Analyzing Dark Web metadata...", t: 6000 },
            { msg: "Generating Risk Report...", t: 7500 },
            { msg: "SCAN COMPLETE. Risk Level: HIGH.", t: 8500, type: 'success' }
        ];

        steps.forEach(step => {
            setTimeout(() => {
                addLog(step.msg, (step.type as Log['type']) || 'info');
            }, step.t);
        });

        setTimeout(() => {
            setIsScanning(false);
            // Simulate Download Link
            addLog("Report ready for download (Mock).", "success");
        }, 9000);
    };

    const handleStartScan = async (data: { name: string; email: string; domain?: string }) => {
        setIsScanning(true);
        setLogs([]);
        addLog(`Initiating connection to MAPA-RD Graph...`, 'info');

        try {
            // 1. Start Scan
            const response = await fetch('/api/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (!response.ok) throw new Error('Failed to start scan');
            const { job_id } = await response.json();
            addLog(`Scan Job Created: ${job_id}`, 'success');

            // 2. Poll for Status
            const pollInterval = setInterval(async () => {
                try {
                    const statusRes = await fetch(`/api/scan/${job_id}`);
                    if (!statusRes.ok) return;

                    const jobData = await statusRes.json();

                    // Update Logs (Diffing logic could be better, but replacing for now is safe)
                    // In a real app we'd append only new ones. For now, trusting backend array.
                    // However, backend sends "logs" as a list of dicts.
                    // We map them to our frontend format.
                    if (jobData.logs && Array.isArray(jobData.logs)) {
                        // We reconstruct logs from backend source of truth
                        const backendLogs = jobData.logs.map((l: any, idx: number) => ({
                            id: idx, // Simple index as ID
                            message: l.message,
                            type: l.type as Log['type'],
                            timestamp: l.timestamp
                        }));
                        setLogs(backendLogs);
                    }

                    if (jobData.status === 'COMPLETED') {
                        clearInterval(pollInterval);
                        setIsScanning(false);
                        addLog('Scan Complete. Report ready.', 'success');

                        // AUTO DOWNLOAD TRIGGER (Optional) or show button
                        if (jobData.result_url) {
                            // Small delay to ensure FS sync
                            setTimeout(() => {
                                const link = document.createElement('a');
                                // Construct URL. If result_url is relative like /reports/mock.pdf, api/reports mount handles it?
                                // Backend wrapper says: result_path="/reports/mock.pdf"
                                // Main.py mounts /api/reports.
                                // So we need to be careful with paths.
                                // Let's assume wrapper returns "mock.pdf" or we fix the path.
                                // Actually wrapper.py line 80: result_path="/reports/mock.pdf"
                                // If we mount /api/reports, the URL should be /api/reports/mock.pdf

                                // Quick fix: user wrapper returns "/reports/mock.pdf", but that might not map to /api/reports unless we proxy/rewrite.
                                // Let's force the correct path for now based on our knowledge.
                                link.href = `/api${jobData.result_url}`;
                                link.download = `MAPA-RD_REPORT_${job_id}.pdf`;
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                addLog(`Downloading Report: ${link.href}`, 'info');
                            }, 1000);
                        }
                    } else if (jobData.status === 'FAILED') {
                        clearInterval(pollInterval);
                        setIsScanning(false);
                        addLog('Scan Failed. Check system logs.', 'error');
                    }

                } catch (e) {
                    console.error("Polling error", e);
                }
            }, 2000); // Poll every 2s

        } catch (e) {
            console.error(e);
            // FALLBACK TO SIMULATION
            runMockSimulation(data);
        }
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
