import React, { useState, useEffect, useRef, useCallback } from 'react';
import ScanForm from './ScanForm';
import StatusTerminal from './StatusTerminal';
import RiskNeutralization, { type Vector } from './RiskNeutralization';
import LoginView from './Auth/LoginView';
import VerificationView from './Auth/VerificationView';
import { secureStorage } from '../utils/secureStorage';
import { format } from 'date-fns';
import { Shield, Target, Lock, Fingerprint } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { Capacitor } from '@capacitor/core';
import { App, type AppState } from '@capacitor/app';
import { Device } from '@capacitor/device';
import { biometricService } from '../utils/biometricService';
import { BackgroundRunner } from '@capacitor/background-runner';
import { LocalNotifications } from '@capacitor/local-notifications';

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
    const [findings, setFindings] = useState<Vector[]>([]);
    const [showNeutralization, setShowNeutralization] = useState(false);

    // AUTH STATE (Phase 22)
    const [authStep, setAuthStep] = useState<'initial_check' | 'login' | 'verify' | 'dashboard'>('initial_check');
    const [userEmail, setUserEmail] = useState<string | null>(null);
    const [authError, setAuthError] = useState<string | null>(null);
    const [isAuthLoading, setIsAuthLoading] = useState(false);
    const [deltaNew, setDeltaNew] = useState<number>(0);
    const [isBiometricLocked, setIsBiometricLocked] = useState(false);
    const [deviceId, setDeviceId] = useState<string>('');
    const [isFirstAnalysisComplete, setIsFirstAnalysisComplete] = useState<boolean>(false);

    // Phase 26: Hardware Guard & Navigation Policy
    const isAuthenticating = useRef(false);

    const performHardwareChallenge = useCallback(async () => {
        if (isAuthenticating.current) return true;
        isAuthenticating.current = true;
        try {
            const success = await biometricService.authenticate();
            return success;
        } finally {
            // Delay to allow Android OS to settle before allowing another prompt
            setTimeout(() => {
                isAuthenticating.current = false;
            }, 1000);
        }
    }, []);

    const syncBackgroundContext = useCallback(async (email: string, checksum: string | null) => {
        if (!Capacitor.isNativePlatform()) return;
        try {
            await BackgroundRunner.dispatchEvent({
                label: 'com.mapard.app.check',
                event: 'setContext',
                details: { email, checksum }
            });
            console.log('Background Context Synced');
        } catch (e) {
            console.error("Error syncing background context", e);
        }
    }, []);

    useEffect(() => {
        const initHardwareGate = async () => {
            // 1. Get Device ID
            const info = await Device.getId();
            setDeviceId(info.identifier);

            // 2. Check Auth Session
            const token = await secureStorage.get('auth_token');
            const storedEmail = await secureStorage.get('target_email');

            if (token && storedEmail) {
                // 3. Hardware Challenge (Biometrics)
                const success = await performHardwareChallenge();
                if (!success) {
                    setIsBiometricLocked(true);
                }

                setUserEmail(storedEmail);
                setAuthStep('dashboard');

                // Phase 23/28: Automatic Status Retrieval with FSM
                try {
                    const statusRes = await fetch(`${API_BASE}/api/user/status?email=${storedEmail}`);
                    const statusData = await statusRes.json();

                    setIsFirstAnalysisComplete(!!statusData.is_first_analysis_complete);

                    // Phase 30: Sync state for background monitoring
                    syncBackgroundContext(storedEmail, statusData.checksum || null);

                    if (statusData.has_scans) {
                        setFindings(statusData.findings || []);
                        setLogs(statusData.logs || []);
                        setResultUrl(statusData.result_url || null);
                        setDeltaNew(statusData.delta_new || 0);

                        // Phase 24 Strict/29 FSM: Jump directly to neutralization if findings exist AND analysis is complete
                        if (statusData.findings && statusData.findings.length > 0 && statusData.is_first_analysis_complete) {
                            setShowNeutralization(true);
                            setViewMode('terminal');
                        } else {
                            setViewMode('terminal');
                        }
                    } else if (statusData.is_first_analysis_complete) {
                        // Edge case: Analysis complete but scans array is empty somehow. Force terminal.
                        setViewMode('terminal');
                    } else {
                        // Phase 29: If no scans and not complete, we are in INITIAL_SETUP
                        setViewMode('form');
                    }
                } catch (e) {
                    console.error("Error fetching initial status", e);
                }
            } else {
                setAuthStep('login');
            }
        };

        initHardwareGate();
    }, [performHardwareChallenge, syncBackgroundContext]);

    useEffect(() => {
        const prepareBackground = async () => {
            if (Capacitor.isNativePlatform()) {
                const perm = await LocalNotifications.requestPermissions();
                console.log('Notification Permission:', perm.display);
            }
        };
        prepareBackground();
    }, []);

    useEffect(() => {
        // 4. App Resume Listener (Re-lock on background)
        const resumeListener = App.addListener('appStateChange', async (state: AppState) => {
            if (state.isActive) {
                const token = await secureStorage.get('auth_token');
                if (token && !isBiometricLocked) {
                    // Small delay to prevent race conditions with window focus on Android
                    setTimeout(async () => {
                        const success = await performHardwareChallenge();
                        if (!success) setIsBiometricLocked(true);
                        else setIsBiometricLocked(false);
                    }, 500);
                }
            }
        });

        return () => {
            resumeListener.then(l => l.remove());
        };
    }, [isBiometricLocked, performHardwareChallenge]);

    const handleLoginSubmit = async (email: string, pass: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/setup`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password: pass, device_id: deviceId })
            });
            const data = await res.json();
            if (res.ok) {
                setUserEmail(email);
                setAuthStep('verify');
            } else {
                setAuthError(data.error || 'Fallo de autenticación');
            }
        } catch {
            setAuthError('Error de red al conectar con el servidor táctico');
        } finally {
            setIsAuthLoading(false);
        }
    };

    const handleVerifySubmit = async (code: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/verify`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: userEmail, code, device_id: deviceId })
            });
            const data = await res.json();
            if (res.ok) {
                await secureStorage.set('auth_token', data.token);
                await secureStorage.set('target_email', userEmail!);

                // Phase 24 Strict: Refresh status immediately after verification
                try {
                    const statusRes = await fetch(`${API_BASE}/api/user/status?email=${userEmail}`);
                    const statusData = await statusRes.json();

                    setIsFirstAnalysisComplete(!!statusData.is_first_analysis_complete);
                    syncBackgroundContext(userEmail!, statusData.checksum || null);

                    if (statusData.has_scans) {
                        setFindings(statusData.findings || []);
                        setLogs(statusData.logs || []);
                        setResultUrl(statusData.result_url || null);
                        if (statusData.findings && statusData.findings.length > 0) {
                            setShowNeutralization(true);
                            setViewMode('terminal');
                        }
                    } else {
                        setViewMode('form');
                    }
                } catch (e) {
                    console.error("Error refreshing status", e);
                }

                setAuthStep('dashboard');
            } else {
                setAuthError(data.error || 'Código inválido');
            }
        } catch {
            setAuthError('Error de validación táctica');
        } finally {
            setIsAuthLoading(false);
        }
    };

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
                        setIsFirstAnalysisComplete(true); // Phase 28/29 FSM Update

                        if (jobData.checksum) {
                            syncBackgroundContext(userEmail!, jobData.checksum);
                        }

                        // Force Completion Message 
                        addLog('Análisis Completado. Generando reporte...', 'success');

                        if (jobData.result_url) {
                            setTimeout(() => {
                                setResultUrl(jobData.result_url);
                                // Sync "Neutralizar" button appearance with "Descargar Dossier"
                                if (jobData.findings && Array.isArray(jobData.findings)) {
                                    setFindings(jobData.findings);
                                    // Phase 24 Strict: Automatic jump to neutralization
                                    if (jobData.findings.length > 0) {
                                        setTimeout(() => setShowNeutralization(true), 2000);
                                    }
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

    const handleNeutralizeUpdate = async (updatedFindings: Vector[]) => {
        // Optimistic update of local state
        setFindings(updatedFindings);

        // Persist to backend
        try {
            await fetch(`${API_BASE}/api/scan/update-findings`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: userEmail,
                    findings: updatedFindings
                })
            });
        } catch (e) {
            console.error("Error persisting tactical state", e);
        }
    };

    const refreshStatus = async () => {
        setIsScanning(true);
        addLog(`Sincronizando Dossier con el Servidor...`, 'info');
        try {
            const statusRes = await fetch(`${API_BASE}/api/user/status?email=${userEmail}`);
            const statusData = await statusRes.json();
            if (statusData.has_scans) {
                setFindings(statusData.findings || []);
                setLogs(statusData.logs || []);
                setResultUrl(statusData.result_url || null);
                setDeltaNew(statusData.delta_new || 0);

                syncBackgroundContext(userEmail!, statusData.checksum || null);

                addLog('Dossier Actualizado.', 'success');
            } else {
                addLog('No se encontraron registros activos.', 'warning');
            }
        } catch (e) {
            console.error("Error refreshing status", e);
            addLog('Error de conexión al sincronizar.', 'error');
        } finally {
            setIsScanning(false);
        }
    };

    const handleReset = () => {
        // Phase 26 Strict: For recurrent users, toggle views instead of going to form
        if (findings.length > 0) {
            // If we are in neutralization, go to logs terminal. If in logs, go to neutralization.
            setShowNeutralization(!showNeutralization);
            setViewMode('terminal');
        } else {
            setViewMode('form');
        }
    };

    return (
        <div className="min-h-screen bg-black text-white selection:bg-ops-accent/30 selection:text-white flex flex-col p-4 md:p-8">
            <AnimatePresence>
                {isBiometricLocked && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-[100] bg-black/95 backdrop-blur-xl flex flex-col items-center justify-center p-6 text-center"
                    >
                        <div className="relative mb-8">
                            <div className="absolute inset-0 bg-ops-danger/20 blur-3xl rounded-full animate-pulse" />
                            <Lock className="w-16 h-16 text-ops-danger relative z-10" />
                        </div>
                        <h2 className="text-xl font-bold tracking-[0.3em] uppercase mb-4 text-white">Terminal Bloqueada</h2>
                        <p className="text-ops-text_dim text-sm max-w-xs mb-8 font-mono">
                            Acceso restringido. Se requiere autenticación biométrica de hardware para desencriptar el dossier.
                        </p>
                        <button
                            onClick={async () => {
                                const success = await biometricService.authenticate();
                                if (success) setIsBiometricLocked(false);
                            }}
                            className="btn-ops px-8 py-4 flex items-center gap-3 group"
                        >
                            <Fingerprint className="w-5 h-5 group-hover:scale-110 transition-transform" />
                            REINTENTAR ACCESO
                        </button>
                    </motion.div>
                )}
            </AnimatePresence>

            <header className="flex flex-col mb-4 md:mb-12 relative">
                <div className="flex flex-col items-center justify-center relative z-10 w-full">
                    <div className="flex items-center justify-center gap-4 mb-2">
                        <Shield className="w-10 h-10 md:w-16 md:h-16 text-ops-cyan drop-shadow-[0_0_15px_rgba(0,243,255,0.5)]" strokeWidth={2} />
                        <h1 className="text-4xl md:text-7xl font-black text-white tracking-[0.2em] uppercase drop-shadow-[0_0_15px_rgba(255,255,255,0.15)]">
                            MAPARD
                        </h1>
                    </div>
                    <p className="text-ops-text_dim font-mono text-[10px] md:text-sm tracking-[0.3em] uppercase opacity-70">
                        INTELIGENCIA TÁCTICA Y VIGILANCIA
                    </p>
                </div>
            </header>

            <main className="flex-grow flex flex-col items-center w-full max-w-4xl mx-auto">
                {authStep === 'login' && (
                    <LoginView onLogin={handleLoginSubmit} isLoading={isAuthLoading} />
                )}

                {authStep === 'verify' && (
                    <VerificationView
                        email={userEmail || ''}
                        onVerify={handleVerifySubmit}
                        onResend={() => handleLoginSubmit(userEmail!, '')}
                        isLoading={isAuthLoading}
                        error={authError}
                    />
                )}

                {authStep === 'dashboard' && (
                    <>
                        <div className="flex items-center gap-2 border border-white/10 px-4 py-2 bg-black/40 backdrop-blur-md rounded-full mb-2 animate-in fade-in slide-in-from-top-4 duration-700">
                            <Target className="w-4 h-4 text-ops-accent" />
                            <span className="text-[10px] md:text-xs font-mono text-ops-text_dim uppercase tracking-widest">
                                TARGET LOCKED: <span className="text-white font-bold">{userEmail}</span>
                            </span>
                        </div>

                        {deltaNew > 0 && (
                            <div className="w-full max-w-lg mb-6 border border-red-500/50 bg-red-500/10 p-4 rounded-lg animate-pulse backdrop-blur-sm self-center">
                                <div className="flex items-center">
                                    <Target className="text-red-500 mr-3 h-5 w-5" />
                                    <div className="flex-1">
                                        <h3 className="text-red-500 font-bold uppercase tracking-[0.2em] text-[10px] md:text-xs">
                                            ALERTA: NUEVA FILTRACIÓN DETECTADA
                                        </h3>
                                        <p className="text-gray-400 text-[9px] md:text-[10px] mt-1 font-mono leading-relaxed">
                                            Se han detectado {deltaNew} nuevos compromisos desde el Baseline. Dossier actualizado disponible.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {findings.length > 0 ? (
                            <div className="animate-[slideUp_0.5s_ease-out] w-full px-4 flex flex-col">
                                {!showNeutralization ? (
                                    <StatusTerminal
                                        logs={logs}
                                        isVisible={true}
                                        onReset={!isScanning ? refreshStatus : undefined}
                                        resetLabel="SINCRONIZAR DOSSIER"
                                        resultUrl={resultUrl}
                                        onNeutralize={() => setShowNeutralization(true)}
                                    />
                                ) : (
                                    <RiskNeutralization
                                        findings={findings}
                                        onUpdate={handleNeutralizeUpdate}
                                        onClose={handleReset}
                                    />
                                )}
                            </div>
                        ) : (viewMode === 'form' && !isFirstAnalysisComplete) ? (
                            <div className="animate-[fadeIn_0.5s_ease-out] w-full px-4 flex flex-col">
                                <ScanForm
                                    onScan={(data) => handleStartScan({ ...data, email: userEmail! })}
                                    isLoading={isScanning}
                                    lockedEmail={userEmail}
                                />
                            </div>
                        ) : (
                            <div className="animate-[slideUp_0.5s_ease-out] w-full px-4 flex flex-col">
                                <StatusTerminal
                                    logs={logs}
                                    isVisible={true}
                                    // If analysis is complete, NEVER show the manual sync button because BackgroundRunner handles it.
                                    onReset={!isScanning && !isFirstAnalysisComplete ? handleReset : undefined}
                                    resetLabel={!isFirstAnalysisComplete ? "EJECUTAR ANÁLISIS" : undefined}
                                    resultUrl={resultUrl}
                                    onNeutralize={undefined}
                                />
                            </div>
                        )}
                    </>
                )}

                {authStep === 'initial_check' && (
                    <div className="flex items-center justify-center p-20">
                        <div className="w-10 h-10 border-4 border-ops-accent border-t-transparent rounded-full animate-spin"></div>
                    </div>
                )}
            </main>
        </div>
    );
};

export default Dashboard;
