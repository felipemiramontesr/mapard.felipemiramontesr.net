import React, { useState } from 'react';
import ScanForm from './ScanForm';
import StatusTerminal from './StatusTerminal';
import RiskNeutralization, { type Vector } from './RiskNeutralization';
import { format } from 'date-fns';
import { Shield } from 'lucide-react';
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
    const [findings, setFindings] = useState<Vector[]>([]); // Store detailed analysis
    const [showNeutralization, setShowNeutralization] = useState(false);

    const addLog = (message: string, type: Log['type'] = 'info') => {
        setLogs(currentLogs => {
            // STRICT DE-DUPLICATION:
            if (currentLogs.length > 0 && currentLogs[0].message === message) {
                return currentLogs;
            }
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
        setFindings([]);
        setShowNeutralization(false);
        setViewMode('terminal');
        setLogs([]); // Clear previous session
        addLog(`Iniciando conexión segura...`, 'info');

        // FLAG: Prevent multiple completions if multiple polls return at once
        let completionHandled = false;

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
                if (completionHandled) {
                    clearInterval(pollInterval);
                    return;
                }

                try {
                    // Cache: no-store to prevent stale status on mobile
                    const statusRes = await fetch(`${API_BASE}/api/scan/${job_id}`, { cache: 'no-store' });
                    if (!statusRes.ok) {
                        return; // Ignore transient polling errors
                    }

                    const jobData = await statusRes.json();

                    // STOP if we already handled completion (Double check)
                    if (completionHandled) return;

                    if (jobData.status === 'COMPLETED') {
                        completionHandled = true; // LOCK
                        clearInterval(pollInterval);
                        setIsScanning(false);

                        // Force Completion Message 
                        addLog('Análisis Completado. Generando reporte...', 'success');

                        if (jobData.result_url) {
                            setTimeout(() => {
                                setResultUrl(jobData.result_url);
                                // Sync "Neutralizar" button appearance with "Descargar Dossier"
                                if (jobData.findings && Array.isArray(jobData.findings)) {
                                    setFindings(jobData.findings);
                                }
                                addLog('Dossier de Inteligencia Listo.', 'success');
                            }, 1500); // 1.5s delay for smooth transition
                        } else {
                            // Fallback if no result URL
                            if (jobData.findings && Array.isArray(jobData.findings)) {
                                setFindings(jobData.findings);
                            }
                        }
                    }
                    else if (jobData.status === 'FAILED') {
                        completionHandled = true; // LOCK
                        clearInterval(pollInterval);
                        setIsScanning(false);
                        addLog('Fallo en el sistema. Revise logs.', 'error');
                    }
                    else {
                        // LOGIC: Update logs from Backend ONLY if not complete
                        if (jobData.logs && Array.isArray(jobData.logs) && jobData.logs.length > 0) {
                            const lastLog = jobData.logs[jobData.logs.length - 1];
                            addLog(lastLog.message, lastLog.type as Log['type']);
                        }
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
        setFindings([]);
        setShowNeutralization(false);
    };

    return (
        <div className="w-full max-w-4xl mx-auto relative flex flex-col items-center flex-grow justify-center py-0">
            {/* Title Section: Re-designed with Logo and 40px spacing (mb-10) - STATIC (No Animation) */}
            <div className="flex flex-col items-center justify-center my-4 md:my-8 relative z-10 w-full">

                {/* Logo + Brand Container */}
                <div className="flex items-center justify-center gap-4 mb-2">
                    {/* Shield Logo */}
                    <Shield className="w-10 h-10 md:w-16 md:h-16 text-ops-cyan drop-shadow-[0_0_15px_rgba(0,243,255,0.5)]" strokeWidth={2} />

                    {/* Brand Name */}
                    <h1 className="text-4xl md:text-7xl font-black text-white tracking-[0.2em] uppercase drop-shadow-[0_0_15px_rgba(255,255,255,0.15)]">
                        MAPARD
                    </h1>
                </div>

                {/* Subtitle */}
                <p className="text-ops-text_dim font-mono text-[10px] md:text-sm tracking-[0.3em] uppercase opacity-70">
                    INTELIGENCIA TÁCTICA Y VIGILANCIA
                </p>
            </div>

            {viewMode === 'form' ? (
                <div className="animate-[fadeIn_0.5s_ease-out] w-full px-4 flex flex-col">
                    <ScanForm onScan={handleStartScan} isLoading={isScanning} />
                </div>
            ) : (
                <div className="animate-[slideUp_0.5s_ease-out] w-full px-4 flex flex-col">
                    {!showNeutralization ? (
                        <StatusTerminal
                            logs={logs}
                            isVisible={true}
                            onReset={!isScanning ? handleReset : undefined}
                            resultUrl={resultUrl}
                            onNeutralize={findings.length > 0 ? () => setShowNeutralization(true) : undefined}
                        />
                    ) : (
                        <RiskNeutralization
                            findings={findings}
                            onClose={() => setShowNeutralization(false)}
                        />
                    )}
                </div>
            )}
        </div>
    );
};

export default Dashboard;
