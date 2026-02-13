import React, { useState } from 'react';
import ScanForm from './ScanForm';
import StatusTerminal from './StatusTerminal';
import { format } from 'date-fns';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';

// Native App needs absolute URL. Web uses relative (proxy).
const API_BASE = Capacitor.isNativePlatform()
    ? 'https://mapard.felipemiramontesr.net'
    : '';

interface Log {
    id: number;
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    timestamp: string;
}

const Dashboard: React.FC = () => {
    const [logs, setLogs] = useState<Log[]>([]);
    const [isScanning, setIsScanning] = useState(false);
    const [viewMode, setViewMode] = useState<'form' | 'terminal'>('form');

    const addLog = (message: string, type: Log['type'] = 'info') => {
        setLogs(prev => [...prev, {
            id: Date.now(),
            message,
            type,
            timestamp: format(new Date(), 'HH:mm:ss')
        }]);
    };

    const handleStartScan = async (data: { name: string; email: string; domain?: string }) => {
        setIsScanning(true);
        setViewMode('terminal'); // SWAP TO TERMINAL
        setLogs([]);
        addLog(`Initiating connection to MAPA-RD Graph...`, 'info');

        try {
            // 1. Start Scan
            const response = await fetch(`${API_BASE}/api/scan`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error("API Error Response:", errorText);
                throw new Error(`Failed to start scan (${response.status}): ${errorText.substring(0, 200)}`);
            }
            const { job_id } = await response.json();
            addLog(`Scan Job Created: ${job_id}`, 'success');

            // 2. Poll for Status
            const pollInterval = setInterval(async () => {
                try {
                    const statusRes = await fetch(`${API_BASE}/api/scan/${job_id}`);
                    if (!statusRes.ok) {
                        const errText = await statusRes.text();
                        console.error("Polling Error:", errText);
                        addLog(`POLLING ERROR (${statusRes.status}): ${errText.substring(0, 150)}`, 'error');
                        return; // Keep polling? Maybe it's transient.
                    }

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

                        // AUTO DOWNLOAD / OPEN
                        if (jobData.result_url) {
                            setTimeout(async () => {
                                if (Capacitor.isNativePlatform()) {
                                    // Native: Open in System Browser (Chrome/Firefox/Samsung)
                                    // Ensure URL is absolute
                                    let fullUrl = jobData.result_url;
                                    if (fullUrl && !fullUrl.startsWith('http')) {
                                        fullUrl = `${API_BASE}${fullUrl.startsWith('/') ? '' : '/'}${fullUrl}`;
                                    }

                                    await Browser.open({ url: fullUrl });
                                    addLog(`Opening Report in Browser...`, 'info');
                                } else {
                                    // Web: Direct Download
                                    const link = document.createElement('a');
                                    link.href = jobData.result_url;
                                    link.download = `MAPA-RD_REPORT_${job_id}.pdf`;
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                    addLog(`Downloading Report: ${link.href}`, 'info');
                                }
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

        } catch (e: any) {
            console.error(e);
            addLog(`CRITICAL BACKEND ERROR: ${e.message}`, 'error');
            if (e.message.includes('500')) {
                addLog("Server Internal Error. Check api/debug.php", 'error');
            }
            setIsScanning(false);
        }
    };

    const handleReset = () => {
        setViewMode('form');
        setLogs([]);
    };

    return (
        <div className="w-full max-w-4xl mx-auto relative flex flex-col items-center justify-center h-full">
            {/* Title Section: Responsive Vertical Sizing */}
            {/* Mobile Compact (Default): text-3xl, mb-2 */}
            {/* Mobile Tall (>700px): text-5xl, mb-8 */}
            <div className="text-center mb-2 tall:mb-8 md:mb-10 relative z-10 px-4 w-full block">
                <h1 className="text-3xl tall:text-5xl md:text-6xl font-black text-white tracking-widest uppercase mb-1 tall:mb-4 md:mb-4 drop-shadow-[0_0_15px_rgba(255,255,255,0.15)] break-words transition-all duration-300">
                    MAPARD
                </h1>
                <p className="text-ops-text_dim max-w-2xl mx-auto font-mono text-[8px] tall:text-[10px] sm:text-xs md:text-sm tracking-[0.15em] sm:tracking-[0.2em] uppercase leading-relaxed text-white/60 transition-all duration-300">
                    INTELIGENCIA TÁCTICA Y VIGILANCIA PASIVA
                </p>
            </div>

            {viewMode === 'form' ? (
                <div className="animate-[fadeIn_0.5s_ease-out] w-full px-4">
                    <ScanForm onScan={handleStartScan} isLoading={isScanning} />
                </div>
            ) : (
                <div className="animate-[slideUp_0.5s_ease-out] w-full px-4">
                    <StatusTerminal
                        logs={logs}
                        isVisible={true}
                        onReset={!isScanning ? handleReset : undefined}
                        resultUrl={resultUrl}
                    />
                </div>
            )}
        </div>
    );
};

export default Dashboard;
