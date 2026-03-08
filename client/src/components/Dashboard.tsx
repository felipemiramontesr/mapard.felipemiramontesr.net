import React, { useState, useEffect, useRef, useCallback } from 'react';
import ScanForm from './ScanForm';
import StatusTerminal from './StatusTerminal';
import RiskNeutralization, { type Vector } from './RiskNeutralization';
import FeedTerminal from './FeedTerminal';
import LoginView from './Auth/LoginView';
import VerificationView from './Auth/VerificationView';
import RescueVerificationView from './Auth/RescueVerificationView';
import RescueResetView from './Auth/RescueResetView';
import { secureStorage } from '../utils/secureStorage';
import { format } from 'date-fns';
import { Shield, Target, Lock, Fingerprint, ChevronDown, ChevronUp } from 'lucide-react';
import { motion } from 'framer-motion';
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

// Phase 4.1 Tactical Color Matrix
const getTacticalColor = (count: number, total: number) => {
    if (total === 0) return '#8a9fca'; // Default Lavender
    const ratio = (total - count) / total;

    if (ratio === 0) return '#a855f7';     // 0/5 - Púrpura (id: 1)
    if (ratio <= 0.25) return '#ef4444';   // 1/5 - Rojo (id: 2)
    if (ratio <= 0.50) return '#f97316';   // 2/5 - Naranja (id: 3)
    if (ratio <= 0.75) return '#eab308';   // 3/5 - Amarillo (id: 4)
    if (ratio < 1) return '#22c55e';       // 4/5 - Verde (id: 5)
    return '#0ea5e9';                      // 5/5 - Azul (id: 6)
};

const Dashboard: React.FC = () => {
    const [logs, setLogs] = useState<Log[]>([]);
    const [isScanning, setIsScanning] = useState(false);
    const [viewMode, setViewMode] = useState<'form' | 'terminal'>('form');
    const [findings, setFindings] = useState<Vector[]>([]);
    const [showNeutralization, setShowNeutralization] = useState(false);
    const [isRiskPanelOpen, setIsRiskPanelOpen] = useState(false);

    // AUTH STATE (Phase 22 + Phase 29)
    const [authStep, setAuthStep] = useState<'initial_check' | 'login' | 'verify' | 'rescue_verify' | 'rescue_reset' | 'dashboard'>('initial_check');
    const [userEmail, setUserEmail] = useState<string | null>(null);
    const [authError, setAuthError] = useState<string | null>(null);
    const [isAuthLoading, setIsAuthLoading] = useState(false);
    const [rescueToken, setRescueToken] = useState<string | null>(null);
    const [deltaNew, setDeltaNew] = useState<number>(0);
    const [isBiometricLocked, setIsBiometricLocked] = useState(false);
    const [deviceId, setDeviceId] = useState<string>('');
    const [isFirstAnalysisComplete, setIsFirstAnalysisComplete] = useState<boolean>(false);
    const [failedAttempts, setFailedAttempts] = useState(0);
    const [lockoutTimeRemaining, setLockoutTimeRemaining] = useState<number | null>(null);

    // Phase 26: Hardware Guard & Navigation Policy
    const isAuthenticating = useRef(false);

    const performHardwareChallenge = useCallback(async () => {
        if (isAuthenticating.current) return false; // CRITICAL FIX: Never bypass securely by returning true
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

    const loadDashboardData = useCallback(async (email: string) => {
        try {
            const statusRes = await fetch(`${API_BASE}/api/user/status?email=${email}`);
            const statusData = await statusRes.json();

            setIsFirstAnalysisComplete(!!statusData.is_first_analysis_complete);

            // Phase 30: Sync state for background monitoring
            syncBackgroundContext(email, statusData.checksum || null);

            if (statusData.has_scans) {
                setFindings(statusData.findings || []);
                setLogs(statusData.logs || []);
                setDeltaNew(statusData.delta_new || 0);

                // User Sequence: Jump directly to neutralization panel on subsequent entries
                if (statusData.is_first_analysis_complete) {
                    setShowNeutralization(true);
                }
                setViewMode('terminal');
            } else if (statusData.is_first_analysis_complete) {
                // Edge case: Analysis complete but scans array is empty somehow.
                setShowNeutralization(true);
                setViewMode('terminal');
            } else {
                // Phase 29: If no scans and not complete, we are in INITIAL_SETUP
                setViewMode('form');
            }
        } catch (e) {
            console.error("Error fetching initial status", e);
        }
    }, [syncBackgroundContext]);

    useEffect(() => {
        const initHardwareGate = async () => {
            // 1. Get Device ID
            const info = await Device.getId();
            setDeviceId(info.identifier);

            // 2. Check Auth Session
            const token = await secureStorage.get('auth_token');
            const storedEmail = await secureStorage.get('target_email');

            if (token && storedEmail) {
                setUserEmail(storedEmail); // Set email early so background dashboard shows the target

                // Check for existing lockout before biometrics
                const lockUntilStr = await secureStorage.get('biometric_lockout_until');
                if (lockUntilStr) {
                    const lockUntilMs = parseInt(lockUntilStr, 10);
                    if (Date.now() < lockUntilMs) {
                        setLockoutTimeRemaining(lockUntilMs - Date.now());
                        setIsBiometricLocked(true);
                        setAuthStep('dashboard'); // Turn off spinner
                        return; // Halt process
                    } else {
                        // Time served, BURN THE SESSION as requested
                        await secureStorage.remove('auth_token');
                        await secureStorage.remove('target_email');
                        await secureStorage.remove('biometric_lockout_until');
                        await secureStorage.remove('biometric_failed_attempts');
                        setAuthStep('login');
                        return;
                    }
                }

                // Check persistent failed attempts
                const storedAttempts = await secureStorage.get('biometric_failed_attempts');
                if (storedAttempts) {
                    setFailedAttempts(parseInt(storedAttempts, 10));
                }

                // Initial boot ONLY: hardware challenge
                setIsBiometricLocked(true);
                setAuthStep('dashboard'); // Guarantee spinner turns off so they see the lock screen
            } else {
                setAuthStep('login');
            }
        };

        initHardwareGate();
    }, []);

    useEffect(() => {
        const prepareBackground = async () => {
            if (Capacitor.isNativePlatform()) {
                const perm = await LocalNotifications.requestPermissions();
                console.log('Notification Permission:', perm.display);
            }
        };
        prepareBackground();
    }, []);

    // Interval for Lockout timer
    useEffect(() => {
        let timerId: ReturnType<typeof setInterval>;
        if (lockoutTimeRemaining !== null && lockoutTimeRemaining > 0) {
            timerId = setInterval(() => {
                setLockoutTimeRemaining(prev => {
                    if (prev === null) return null;
                    const next = prev - 1000;
                    if (next <= 0) {
                        clearInterval(timerId);
                        // Time served, BURN THE SESSION as requested
                        // Sync UI update ensures an immediate visual transition to Login
                        setAuthStep('login');
                        setIsBiometricLocked(false);

                        Promise.all([
                            secureStorage.remove('auth_token'),
                            secureStorage.remove('target_email'),
                            secureStorage.remove('biometric_lockout_until'),
                            secureStorage.remove('biometric_failed_attempts')
                        ]).catch(e => console.error("Error wiping session", e));

                        return null;
                    }
                    return next;
                });
            }, 1000);
        }
        return () => {
            if (timerId) clearInterval(timerId);
        };
    }, [lockoutTimeRemaining]);

    useEffect(() => {
        // 4. App Resume Listener (Re-lock on background)
        const resumeListener = App.addListener('appStateChange', async (state: AppState) => {
            const token = await secureStorage.get('auth_token');
            if (!token) return;

            if (!state.isActive) {
                // Phase 5 Anti-Leak: Instantly lock the app when backgrounded so it's hidden
                // from the OS task switcher and securely locked before they resume.
                setIsBiometricLocked(true);
                return;
            }
            // By design: We DO NOT auto-prompt biometrics here anymore.
            // The user must click "REINTENTAR ACCESO" to trigger performHardwareChallenge.
        });

        return () => {
            resumeListener.then(l => l.remove());
        };
    }, []);

    const handleLoginSubmit = async (email: string, pass: string, mode: 'login' | 'signup') => {
        setIsAuthLoading(true);
        setAuthError(null);

        // Force minimum 2.4s delay for the tactical animation to finish
        const animationPromise = new Promise(resolve => setTimeout(resolve, 2400));

        try {
            const resPromise = fetch(`${API_BASE}/api/auth/setup`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password: pass, device_id: deviceId, mode })
            });

            const [res] = await Promise.all([resPromise, animationPromise]);
            const data = await res.json();

            if (res.ok) {
                setUserEmail(email);
                setAuthStep('verify');
            } else {
                setAuthError(data.error || 'Fallo de autenticación');
            }
        } catch {
            await animationPromise; // Ensure animation finishes even on network crash
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
                await secureStorage.set('is_returning_user', 'true'); // Flag returning identity

                // Phase 24 Strict: Refresh status immediately after verification
                try {
                    const statusRes = await fetch(`${API_BASE}/api/user/status?email=${userEmail}`);
                    const statusData = await statusRes.json();

                    setIsFirstAnalysisComplete(!!statusData.is_first_analysis_complete);
                    syncBackgroundContext(userEmail!, statusData.checksum || null);

                    if (statusData.has_scans) {
                        setFindings(statusData.findings || []);
                        setLogs(statusData.logs || []);
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
                setAuthError(data.message || 'Código táctico inválido');
                if (data.error === 'MAX_ATTEMPTS_REACHED') {
                    // Kick out if tactically compromised
                    setTimeout(() => setAuthStep('login'), 3000);
                }
            }
        } catch {
            setAuthError('Error de validación táctica');
        } finally {
            if (authError !== 'MAX_ATTEMPTS_REACHED') setIsAuthLoading(false);
        }
    };

    // Phase 29: Rescue Flow Handlers
    const handleRescueRequest = async (email: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/rescue-request`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
            const data = await res.json();
            if (res.ok) {
                setUserEmail(email);
                setAuthStep('rescue_verify');
            } else {
                setAuthError(data.error || 'Fallo al inicializar rescate');
            }
        } catch {
            setAuthError('Error de red durante protocolo de rescate');
        } finally {
            setIsAuthLoading(false);
        }
    };

    const handleRescueVerify = async (code: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/rescue-verify`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: userEmail, code })
            });
            const data = await res.json();
            if (res.ok && data.rescue_token) {
                setRescueToken(data.rescue_token);
                setAuthStep('rescue_reset');
            } else {
                setAuthError(data.message || data.error || 'Código de rescate inválido');
                if (data.error === 'MAX_ATTEMPTS_REACHED' || data.error === 'EXPIRED_CODE') {
                    setTimeout(() => setAuthStep('login'), 3000);
                }
            }
        } catch {
            setAuthError('Error validando código de rescate');
        } finally {
            setIsAuthLoading(false);
        }
    };

    const handleRescueExecute = async (newPassword: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/rescue-execute`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: userEmail, rescue_token: rescueToken, new_password: newPassword, device_id: deviceId })
            });
            const data = await res.json();
            if (res.ok) {
                // Success! Force them to login normally now with the new password.
                setAuthError(data.message); // Temporarily show success message in the login view
                setAuthStep('login');
                setRescueToken(null);
            } else {
                setAuthError(data.error || 'Fallo al sobrescribir credencial');
            }
        } catch {
            setAuthError('Error de red al actualizar credencial');
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
                        addLog('Análisis Completado. Generando inteligencia táctica...', 'success');

                        setTimeout(() => {
                            if (jobData.findings && Array.isArray(jobData.findings)) {
                                setFindings(jobData.findings);
                            }
                            addLog('Dossier de Inteligencia Listo.', 'success');
                        }, 1500); // 1.5s delay for smooth transition
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

        // Persist only the minimal differential state to avoid 413 Payload Too Large
        const lightweightState = updatedFindings.map(v => ({
            isNeutralized: v.isNeutralized ?? false,
            steps: v.steps ?? []
        }));

        try {
            const response = await fetch(`${API_BASE}/api/scan/update-findings`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: userEmail,
                    findings: lightweightState
                })
            });
            if (!response.ok) {
                console.error("Persistence failed with status:", response.status);
                addLog('Error de sincronización con el servidor táctico.', 'warning');
            }
        } catch (e) {
            console.error("Network error during tactical persistence", e);
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
    // Phase 5 Hermetic Seal: If biometric is locked, return ONLY the lock screen
    if (isBiometricLocked) {
        return (
            <div className="fixed inset-0 z-[100] bg-black/95 backdrop-blur-xl flex flex-col items-center justify-center p-6 text-center text-white selection:bg-ops-accent/30 selection:text-white">
                <motion.div
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    className="flex flex-col items-center"
                >
                    <div className="relative mb-8">
                        <div className="absolute inset-0 bg-ops-danger/20 blur-3xl rounded-full animate-pulse" />
                        <Lock className="w-16 h-16 text-ops-danger relative z-10" />
                    </div>
                    <h2 className="text-xl font-bold tracking-[0.3em] uppercase mb-4 text-white">Terminal Bloqueada</h2>
                    <p className="text-ops-text_dim text-sm max-w-xs mb-8 font-mono">
                        Acceso restringido. Se requiere autenticación biométrica de hardware para desencriptar el dossier.
                        <br /><br />
                        {!lockoutTimeRemaining && <span className="text-ops-danger font-bold">Intentos fallidos: {failedAttempts}/2</span>}
                    </p>

                    {lockoutTimeRemaining && lockoutTimeRemaining > 0 ? (
                        <div className="flex flex-col items-center justify-center p-6 bg-ops-danger/10 border border-ops-danger/30 rounded-lg animate-pulse">
                            <span className="text-xs uppercase tracking-widest text-ops-danger mb-2 font-bold">Cuarentena Táctica Activa</span>
                            <span className="text-3xl font-mono text-ops-danger tracking-widest font-black">
                                {Math.floor(lockoutTimeRemaining / 60000).toString().padStart(2, '0')}:
                                {Math.floor((lockoutTimeRemaining % 60000) / 1000).toString().padStart(2, '0')}
                            </span>
                            <span className="text-[10px] text-ops-danger/70 mt-3 font-mono text-center">PRIVILEGIO DE BIOMETRÍA REVOCADO<br />TIEMPO RESTANTE PARA RESET DE SESIÓN</span>
                        </div>
                    ) : (
                        <button
                            onClick={async () => {
                                const success = await performHardwareChallenge();
                                if (success) {
                                    setIsBiometricLocked(false);
                                    setFailedAttempts(0);
                                    await secureStorage.remove('biometric_failed_attempts');

                                    setAuthStep('initial_check'); // Show spinner briefly
                                    const storedEmail = await secureStorage.get('target_email');
                                    if (storedEmail) {
                                        setUserEmail(storedEmail);
                                        await loadDashboardData(storedEmail);
                                    }
                                    setAuthStep('dashboard');
                                } else {
                                    const newFails = failedAttempts + 1;
                                    setFailedAttempts(newFails);
                                    await secureStorage.set('biometric_failed_attempts', newFails.toString());

                                    if (newFails >= 2) {
                                        const lockDuration = 10 * 60 * 1000; // 10 minutes
                                        const lockoutTime = Date.now() + lockDuration;
                                        await secureStorage.set('biometric_lockout_until', lockoutTime.toString());
                                        setLockoutTimeRemaining(lockDuration);
                                    }
                                }
                            }}
                            className="btn-ops px-8 py-4 flex items-center gap-3 group"
                        >
                            <Fingerprint className="w-5 h-5 group-hover:scale-110 transition-transform" />
                            REINTENTAR ACCESO
                        </button>
                    )}
                </motion.div>
            </div>
        );
    }

    return (
        <div className="w-full my-auto text-white selection:bg-ops-accent/30 selection:text-white flex flex-col py-4 md:py-8">

            <header className="flex flex-col mb-4 md:mb-12 relative">
                <div className="flex flex-col items-center justify-center relative z-10 w-full">
                    <div className="flex items-center justify-center gap-3 md:gap-4 mb-2">
                        <Shield className="w-8 h-8 md:w-16 md:h-16 text-[#00f3ff]" strokeWidth={2} />
                        <h1 className="text-4xl md:text-7xl font-sans font-black tracking-widest uppercase mapard-logo">
                            MAPARD
                        </h1>
                    </div>
                    <p className="text-ops-text_dim font-mono text-[10px] md:text-sm tracking-[0.3em] uppercase opacity-70">
                        INTELIGENCIA TÁCTICA Y VIGILANCIA
                    </p>
                </div>
            </header>

            <main className="flex-grow flex flex-col items-center w-full max-w-4xl mx-auto px-4 md:px-8">
                {authStep === 'login' && (
                    <LoginView onLogin={handleLoginSubmit} onRequestRescue={handleRescueRequest} isLoading={isAuthLoading} error={authError} />
                )}

                {authStep === 'verify' && (
                    <VerificationView
                        email={userEmail || ''}
                        onVerify={handleVerifySubmit}
                        onResend={() => handleLoginSubmit(userEmail!, '', 'signup')}
                        isLoading={isAuthLoading}
                        error={authError}
                    />
                )}

                {authStep === 'rescue_verify' && (
                    <RescueVerificationView
                        email={userEmail || ''}
                        onVerify={handleRescueVerify}
                        isLoading={isAuthLoading}
                        error={authError}
                    />
                )}

                {authStep === 'rescue_reset' && (
                    <RescueResetView
                        onReset={handleRescueExecute}
                        isLoading={isAuthLoading}
                        error={authError}
                    />
                )}

                {authStep === 'dashboard' && (
                    <>
                        <div className="flex flex-col md:flex-row items-center justify-center text-center gap-1 md:gap-3 border border-ops-border/50 px-5 py-3 md:py-2.5 bg-white/5 backdrop-blur-md rounded mb-4 animate-in fade-in slide-in-from-top-4 duration-700 w-full">
                            <div className="flex items-center gap-2">
                                <Target className="w-4 h-4 text-ops-text_dim flex-shrink-0" />
                                <span className="text-xs font-medium text-ops-text_dim uppercase tracking-wider">
                                    TARGET LOCKED:
                                </span>
                            </div>
                            <span className="text-white font-semibold text-[13px] md:text-sm truncate max-w-full tracking-wide">
                                {userEmail?.toLowerCase()}
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

                        {viewMode === 'form' && !isFirstAnalysisComplete ? (
                            <div className="animate-[fadeIn_0.5s_ease-out] w-full flex flex-col">
                                <ScanForm
                                    onScan={(data) => handleStartScan({ ...data, email: userEmail! })}
                                    isLoading={isScanning}
                                    lockedEmail={userEmail}
                                />
                            </div>
                        ) : (
                            <div className="animate-[slideUp_0.5s_ease-out] w-full flex flex-col">
                                {!showNeutralization ? (
                                    <StatusTerminal
                                        logs={logs}
                                        isVisible={true}
                                        onReset={!isScanning && !isFirstAnalysisComplete ? handleReset : undefined}
                                        resetLabel={!isFirstAnalysisComplete ? "EJECUTAR ANÁLISIS" : undefined}
                                        onNeutralize={isFirstAnalysisComplete ? () => { setShowNeutralization(true); setIsRiskPanelOpen(true); } : undefined}
                                        findingsCount={findings.length}
                                    />
                                ) : (
                                    <div className="w-full flex flex-col gap-6">
                                        {/* PANEL 1: PROTOCOLO DE NEUTRALIZACIÓN */}
                                        <div className="w-full flex flex-col">
                                            {(() => {
                                                const activeCount = findings.filter(f => !f.isNeutralized).length;
                                                const tacticalColor = getTacticalColor(activeCount, findings.length);
                                                return (
                                                    <motion.div
                                                        onClick={() => setIsRiskPanelOpen(!isRiskPanelOpen)}
                                                        className="w-full border bg-white/[0.03] backdrop-blur-md p-6 cursor-pointer hover:bg-white/[0.05] transition-colors flex flex-col items-center shadow-[0_18px_50px_rgba(0,0,0,0.18)] relative overflow-hidden group"
                                                        style={{ borderColor: `${tacticalColor}55` }}
                                                        whileHover={{ scale: 1.005 }}
                                                        whileTap={{ scale: 0.99 }}
                                                    >
                                                        <div className="absolute top-4 right-4 flex items-center justify-center w-8 h-8 border border-white/10 bg-white/[0.03] backdrop-blur-sm rounded-md transition-all group-hover:border-white/20 group-hover:bg-white/[0.08]">
                                                            {isRiskPanelOpen ? <ChevronUp className="w-4 h-4" style={{ color: tacticalColor }} /> : <ChevronDown className="w-4 h-4" style={{ color: tacticalColor }} />}
                                                        </div>

                                                        <div className="flex items-center gap-2 mb-2 w-full justify-center px-8">
                                                            <Target className="w-4 h-4 flex-shrink-0" style={{ color: tacticalColor }} />
                                                            <h3 className="text-[.72rem] font-semibold tracking-[.15em] text-white uppercase whitespace-nowrap">PROTOCOLO DE NEUTRALIZACIÓN</h3>
                                                        </div>

                                                        <div className="flex flex-col items-center justify-center my-6">
                                                            <span className="text-[4.5rem] font-extralight leading-none transition-colors duration-500" style={{ color: tacticalColor, textShadow: `0 0 20px ${tacticalColor}44` }}>
                                                                {activeCount}
                                                            </span>
                                                            <span className="text-[.98rem] font-light text-[#c5cae0] uppercase mt-4 tracking-widest text-center">
                                                                {activeCount > 0 ? 'AMENAZAS ACTIVAS' : 'NINGUNA AMENAZA ACTIVA'}
                                                            </span>
                                                        </div>
                                                    </motion.div>
                                                );
                                            })()}

                                            <motion.div
                                                initial={{ height: 0, opacity: 0 }}
                                                animate={{ height: isRiskPanelOpen ? 'auto' : 0, opacity: isRiskPanelOpen ? 1 : 0 }}
                                                transition={{ duration: 0.4, ease: [0.04, 0.62, 0.23, 0.98] }}
                                                className="overflow-hidden w-full flex flex-col"
                                            >
                                                <div className="pt-2">
                                                    <RiskNeutralization
                                                        findings={findings}
                                                        onUpdate={handleNeutralizeUpdate}
                                                    />
                                                </div>
                                            </motion.div>
                                        </div>

                                        {/* PANEL 2: PROTOCOLO DE ENTRENAMIENTO (MOCK) */}
                                        <motion.div
                                            className="w-full border border-[rgba(74,85,120,0.55)] bg-white/[0.03] backdrop-blur-md p-6 flex flex-col items-center shadow-[0_18px_50px_rgba(0,0,0,0.18)] relative overflow-hidden opacity-60 pointer-events-none"
                                        >
                                            <div className="absolute top-4 right-4 flex items-center justify-center w-8 h-8 border border-white/10 bg-white/[0.03] rounded-md opacity-30">
                                                <ChevronDown className="w-4 h-4 text-[#8a9fca]" />
                                            </div>
                                            <div className="flex items-center gap-2 mb-2 w-full justify-center px-8">
                                                <Shield className="w-4 h-4 text-[#8a9fca] flex-shrink-0" />
                                                <h3 className="text-[.72rem] font-semibold tracking-[.15em] text-white uppercase whitespace-nowrap">PROTOCOLO DE ENTRENAMIENTO</h3>
                                            </div>

                                            <div className="flex flex-col items-center justify-center my-6">
                                                <span className="text-[4.5rem] font-extralight leading-none text-[#6b7490]">
                                                    0
                                                </span>
                                                <span className="text-[.98rem] font-light text-[#c5cae0] uppercase mt-4 tracking-widest text-center">
                                                    DE 12 LECTURAS
                                                </span>
                                            </div>
                                        </motion.div>

                                        {/* PANEL 3: PROTOCOLO INFORMATIVO (Statefull Tactical Feed) */}
                                        <FeedTerminal email={userEmail!} />
                                    </div>
                                )}
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
