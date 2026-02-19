import React, { useState } from 'react';
import ScanForm from './ScanForm';
import StatusTerminal from './StatusTerminal';
import TacticalTelemetry from './TacticalTelemetry';
import { format } from 'date-fns';
import { Capacitor } from '@capacitor/core';

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
    const [resultUrl, setResultUrl] = useState<string | null>(null);

    const addLog = (message: string, type: Log['type'] = 'info') => {
        setLogs(currentLogs => {
            // STRICT DE-DUPLICATION:
            // If the last message is identical, do absolutely nothing.
            // This prevents ID regeneration and re-renders.
            if (currentLogs.length > 0 && currentLogs[0].message === message) {
                return currentLogs;
            }
            // Otherwise, replace with new log
            return [{
                id: Date.now(),
                message,
                type,
                timestamp: format(new Date(), 'HH:mm:ss')
            }];
        });
    };

    const handleStartScan = async (data: { name: string; email: string; domain?: string }) => {
        setIsScanning(true);
        setResultUrl(null);
        setViewMode('terminal');
        setLogs([]); // Clear previous session
        addLog(`Iniciando conexión segura...`, 'info');

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

            // 2. Poll for Status
            const pollInterval = setInterval(async () => {
                try {
                    const statusRes = await fetch(`${API_BASE}/api/scan/${job_id}`);
                    if (!statusRes.ok) {
                        return; // Ignore transient polling errors
                    }

                    const jobData = await statusRes.json();

                    // LOGIC: Update logs from Backend
                    if (jobData.logs && Array.isArray(jobData.logs) && jobData.logs.length > 0) {
                        const lastLog = jobData.logs[jobData.logs.length - 1];
                        addLog(lastLog.message, lastLog.type as Log['type']);
                    }

                    if (jobData.status === 'COMPLETED') {
                        clearInterval(pollInterval);
                        setIsScanning(false);

                        // Force Completion Message (Checked by addLog for dupes)
                        addLog('Análisis Completado. Generando reporte...', 'success');

                        if (jobData.result_url) {
                            setTimeout(() => {
                                setResultUrl(jobData.result_url);
                                addLog('Dossier de Inteligencia Listo.', 'success');
                            }, 1000); // 1s delay for dramatic effect
                        }
                    } else if (jobData.status === 'FAILED') {
                        clearInterval(pollInterval);
                        setIsScanning(false);
                        addLog('Fallo en el sistema. Revise logs.', 'error');
                    }

                } catch (e) {
                    console.error("Polling error", e);
                }
            }, 2000);

        } catch (e: unknown) {
            console.error(e);
            const errorMessage = e instanceof Error ? e.message : 'Unknown Error';
            addLog(`CRITICAL BACKEND ERROR: ${errorMessage}`, 'error');
            setIsScanning(false);
        }
    };

    const handleReset = () => {
        setViewMode('form');
        setLogs([]);
        setResultUrl(null);
    };

    return (
        <div className="w-full max-w-4xl mx-auto relative flex flex-col items-center flex-grow justify-between py-0">
            {/* Title Section: Responsive Vertical Sizing */}
            {/* Mobile Compact (Default): text-3xl, mb-2 */}
            {/* Mobile Tall (>700px): text-5xl, mb-8 */}
            <div className="text-center mb-2 tall:mb-6 md:mb-6 relative z-10 px-4 w-full block">
                <h1 className="text-3xl tall:text-5xl md:text-6xl font-black text-white tracking-widest uppercase mb-1 tall:mb-3 md:mb-3 drop-shadow-[0_0_15px_rgba(255,255,255,0.15)] break-words transition-all duration-300">
                    MAPARD
                </h1>
                <p className="text-ops-text_dim max-w-4xl mx-auto font-mono text-[8px] tall:text-[10px] sm:text-xs md:text-sm tracking-[0.15em] sm:tracking-[0.2em] uppercase leading-relaxed text-white/60 transition-all duration-300 whitespace-nowrap overflow-hidden text-ellipsis">
                    INTELIGENCIA TÁCTICA Y VIGILANCIA
                </p>
            </div>

            {viewMode === 'form' ? (
                <div className="animate-[fadeIn_0.5s_ease-out] w-full px-4 flex-grow flex flex-col justify-center">
                    <ScanForm onScan={handleStartScan} isLoading={isScanning} />
                </div>
            ) : (
                <div className="animate-[slideUp_0.5s_ease-out] w-full px-4 flex-grow flex flex-col justify-center">
                    <StatusTerminal
                        logs={logs}
                        isVisible={true}
                        onReset={!isScanning ? handleReset : undefined}
                        resultUrl={resultUrl}
                    />
                </div>
            )}
            {/* Tactical Telemetry Module (Fills Void) */}
            <div className="w-full px-4 mt-2">
                <TacticalTelemetry />
            </div>
        </div>
    );
};

export default Dashboard;
