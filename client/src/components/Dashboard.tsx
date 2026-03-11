import React, { useState, useEffect } from 'react';
import ScanForm from './ScanForm';
import StatusTerminal from './StatusTerminal';
import RiskNeutralization, { type Vector } from './RiskNeutralization';
import LoginView from './Auth/LoginView';
import VerificationView from './Auth/VerificationView';
import RescueVerificationView from './Auth/RescueVerificationView';
import RescueResetView from './Auth/RescueResetView';
import FeedTerminal from './FeedTerminal';
import TrainingProtocol from './TrainingProtocol';
import { secureStorage } from '../utils/secureStorage';
import { format } from 'date-fns';
import { ChevronDown, Award, Star, Target, Shield, Fingerprint, Lock, Zap } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { App, type AppState } from '@capacitor/app';

const API_BASE = 'https://mapa-rd.felipemiramontesr.net';



interface Log {
    id: number;
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    timestamp: string;
}

const Dashboard: React.FC = () => {
    // UI CONTROL STATE
    const [viewMode, setViewMode] = useState<'form' | 'terminal' | 'neutralization'>('form');
    const [isScanning, setIsScanning] = useState(false);
    const [logs, setLogs] = useState<Log[]>([]);
    const [findings, setFindings] = useState<Vector[]>([]);
    const [showNeutralization, setShowNeutralization] = useState(false);
    const [isRiskPanelOpen, setIsRiskPanelOpen] = useState(false);

    // AUTH STATE (Phase 22 + Phase 29)
    const [authStep, setAuthStep] = useState<'initial_check' | 'login' | 'verify' | 'rescue_verify' | 'rescue_reset' | 'dashboard'>('initial_check');
    const [isAuthLoading, setIsAuthLoading] = useState(false);
    const [authError, setAuthError] = useState<string | null>(null);
    const [userEmail, setUserEmail] = useState<string | null>(null);
    const [deviceId, setDeviceId] = useState<string>('');

    // PHASE 28/29 PERSISTENCE LOCKS
    const [isFirstAnalysisComplete, setIsFirstAnalysisComplete] = useState(false);
    const [deltaNew, setDeltaNew] = useState(0);
    const [isGraduated, setIsGraduated] = useState(false);
    const [masteryExamActive, setMasteryExamActive] = useState(false);

    // Phase 5 Biometric Hermetic Seal
    const [isBiometricLocked, setIsBiometricLocked] = useState(false);
    const [failedAttempts, setFailedAttempts] = useState(0);
    const [lockoutTimeRemaining, setLockoutTimeRemaining] = useState<number | null>(null);

    // --- INITIALIZATION ---
    useEffect(() => {
        const init = async () => {
            let id = await secureStorage.get('device_id');
            if (!id) {
                id = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                await secureStorage.set('device_id', id);
            }
            setDeviceId(id);

            // Check biometric lockout status
            const lockUntil = await secureStorage.get('biometric_lockout_until');
            if (lockUntil) {
                const remaining = parseInt(lockUntil) - Date.now();
                if (remaining > 0) {
                    setIsBiometricLocked(true);
                    setLockoutTimeRemaining(remaining);
                    return;
                } else {
                    await secureStorage.remove('biometric_lockout_until');
                }
            }

            const storedFails = await secureStorage.get('biometric_failed_attempts');
            if (storedFails) setFailedAttempts(parseInt(storedFails));

            const token = await secureStorage.get('auth_token');
            const email = await secureStorage.get('target_email');

            if (token && email) {
                const biometricEnabled = await secureStorage.get('biometric_enabled');
                if (biometricEnabled === 'true') {
                    setIsBiometricLocked(true);
                } else {
                    setUserEmail(email);
                    await loadDashboardData(email);
                }
            } else {
                setAuthStep('login');
            }
        };
        init();
    }, []);

    // Biometric Timer (Phase 5)
    useEffect(() => {
        if (lockoutTimeRemaining && lockoutTimeRemaining > 0) {
            const timer = setInterval(() => {
                setLockoutTimeRemaining(prev => {
                    if (prev && prev <= 1000) {
                        clearInterval(timer);
                        secureStorage.remove('biometric_lockout_until');
                        return 0;
                    }
                    return prev ? prev - 1000 : 0;
                });
            }, 1000);
            return () => clearInterval(timer);
        }
    }, [lockoutTimeRemaining]);

    // Capacitor App State Handler (Background lock)
    useEffect(() => {
        const handler = App.addListener('appStateChange', async ({ isActive }: AppState) => {
            if (!isActive) {
                const biometricEnabled = await secureStorage.get('biometric_enabled');
                if (biometricEnabled === 'true') {
                    setIsBiometricLocked(true);
                }
            }
        });
        return () => { handler.then(h => h.remove()); };
    }, []);

    // --- CORE LOGIC ---
    const loadDashboardData = async (email: string) => {
        try {
            const res = await fetch(`${API_BASE}/api/user/status?email=${email}`, { cache: 'no-store' });
            if (res.ok) {
                const data = await res.json();
                if (data.analysis_complete) {
                    setIsFirstAnalysisComplete(true);
                    setFindings(data.findings || []);
                    setShowNeutralization(true);
                    setViewMode('terminal');

                    if (data.delta_new) setDeltaNew(data.delta_new);
                }
                setAuthStep('dashboard');
            } else {
                setAuthStep('login');
            }
        } catch (e) {
            console.error("Status check failed", e);
            setAuthStep('login');
        }
    };

    const syncBackgroundContext = async (email: string, checksum: string) => {
        try {
            await fetch(`${API_BASE}/api/user/sync-context`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, checksum, device_id: deviceId })
            });
        } catch (e) { console.error("Context sync failed", e); }
    };

    const handleLoginSubmit = async (email: string, pass: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/setup`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password: pass, device_id: deviceId, mode: 'setup' })
            });
            const data = await res.json();
            if (res.ok) {
                setUserEmail(email);
                setAuthStep('verify');
            } else {
                setAuthError(data.error || 'Autenticación Fallida');
            }
        } catch { setAuthError('Error de servidor'); }
        finally { setIsAuthLoading(false); }
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
                await secureStorage.set('biometric_enabled', 'true');
                await loadDashboardData(userEmail!);
            } else { setAuthError(data.error || 'Código Inválido'); }
        } catch { setAuthError('Error de verificación'); }
        finally { setIsAuthLoading(false); }
    };

    const handleRescueRequest = async (email: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/rescue-request`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, device_id: deviceId })
            });
            if (res.ok) {
                setUserEmail(email);
                setAuthStep('rescue_verify');
            } else {
                const data = await res.json();
                setAuthError(data.error || 'Petición denegada');
            }
        } catch { setAuthError('Fallo en rescate'); }
        finally { setIsAuthLoading(false); }
    };

    const handleRescueVerify = async (code: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/rescue-verify`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: userEmail, code, device_id: deviceId })
            });
            const data = await res.json();
            if (res.ok) {
                // Rescue verified, proceed to reset 
                setAuthStep('rescue_reset');
            } else {
                setAuthError(data.error || 'Código incorrecto');
            }
        } catch { setAuthError('Error de rescate'); }
        finally { setIsAuthLoading(false); }
    };

    const handleRescueExecute = async (newPassword: string) => {
        setIsAuthLoading(true);
        setAuthError(null);
        try {
            const res = await fetch(`${API_BASE}/api/auth/rescue-execute`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: userEmail, new_password: newPassword, device_id: deviceId })
            });
            if (res.ok) {
                setAuthStep('login');
                setAuthError("SISTEMA RESTABLECIDO. ACCEDA.");
            } else {
                const data = await res.json();
                setAuthError(data.error || 'Reinicio fallido');
            }
        } catch {
            setAuthError('Fallo final de rescate');
        } finally {
            setIsAuthLoading(false);
        }
    };

    // --- GAMIFICATION & RANK LOGIC (Phase 10) ---
    const [completedLessons, setCompletedLessons] = useState<number>(0);
    const [totalLessons] = useState<number>(48);

    const getRankData = (
        neutralizedProgress: number,
        trainingProgress: number,
        newsSeen: boolean
    ) => {
        const mainProgress = (neutralizedProgress + trainingProgress) / 2;
        if (mainProgress >= 100 && newsSeen) {
            return { name: 'COMANDANTE', color: '#10b981', glow: '#10b981', icon: 'Award' };
        }
        if (mainProgress > 60 && newsSeen) {
            return { name: 'SARGENTO', color: '#22d3ee', glow: '#22d3ee', icon: 'Star' };
        }
        if (mainProgress > 40 && newsSeen) {
            return { name: 'CABO', color: '#facc15', glow: '#facc15', icon: 'Target' };
        }
        if (mainProgress > 20 && newsSeen) {
            return { name: 'SOLDADO', color: '#ef4444', glow: '#ef4444', icon: 'Shield' };
        }
        return { name: 'RECLUTA', color: '#a855f7', glow: '#a855f7', icon: 'Shield' };
    };

    const currentRank = getRankData(
        findings.length > 0 ? (findings.filter(f => f.isNeutralized).length / findings.length) * 100 : 0,
        (completedLessons / totalLessons) * 100,
        true
    );

    // --- UI HELPERS ---
    const addLog = (message: string, type: Log['type'] = 'info') => {
        setLogs(currentLogs => {
            if (currentLogs.length > 0 && currentLogs[0].message === message) return currentLogs;
            return [{ id: Date.now(), message, type, timestamp: format(new Date(), 'HH:mm:ss') }, ...currentLogs];
        });
    };

    const handleStartScan = async (data: { name: string; email: string; domain?: string }) => {
        setIsScanning(true);
        setFindings([]);
        setShowNeutralization(false);
        setViewMode('terminal');
        setLogs([]);
        addLog(`Iniciando conexión segura...`, 'info');
        let completionHandled = false;
        try {
            const response = await fetch(`${API_BASE}/api/scan`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (!response.ok) throw new Error(`Status ${response.status}`);
            const { job_id } = await response.json();
            const pollInterval = setInterval(async () => {
                if (completionHandled) { clearInterval(pollInterval); return; }
                try {
                    const statusRes = await fetch(`${API_BASE}/api/scan/${job_id}`, { cache: 'no-store' });
                    if (!statusRes.ok) return;
                    const jobData = await statusRes.json();
                    if (completionHandled) return;
                    if (jobData.status === 'COMPLETED') {
                        completionHandled = true;
                        clearInterval(pollInterval);
                        setIsScanning(false);
                        setIsFirstAnalysisComplete(true);
                        if (jobData.findings) setFindings(jobData.findings);
                        addLog('Dossier de Inteligencia Listo.', 'success');
                    } else if (jobData.status === 'FAILED') {
                        completionHandled = true;
                        clearInterval(pollInterval);
                        setIsScanning(false);
                        addLog('Fallo en el sistema.', 'error');
                    } else if (jobData.logs?.length > 0) {
                        const lastLog = jobData.logs[jobData.logs.length - 1];
                        addLog(lastLog.message, lastLog.type as Log['type']);
                    }
                } catch (e) { console.error(e); }
            }, 2000);
        } catch (e: any) {
            addLog(`ERROR: ${e.message}`, 'error');
            setIsScanning(false);
        }
    };

    const handleNeutralizeUpdate = async (updatedFindings: Vector[]) => {
        setFindings(updatedFindings);
        const lightweightState = updatedFindings.map(v => ({ isNeutralized: v.isNeutralized ?? false, steps: v.steps ?? [] }));
        try {
            await fetch(`${API_BASE}/api/scan/update-findings`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: userEmail, findings: lightweightState })
            });
        } catch (e) { console.error(e); }
    };

    const handleGeneratePDF = () => {
        alert("PROTOCOL MAPARD: Generando Certificación Privada PDF...");
        // Mock generation for Phase 10
    };

    const handleReset = () => {
        if (findings.length > 0) {
            setShowNeutralization(!showNeutralization);
            setViewMode('terminal');
        } else {
            setViewMode('form');
        }
    };

    const getTacticalColor = (active: number, total: number) => {
        if (total === 0) return '#22d3ee';
        const percent = (active / total) * 100;
        if (percent === 0) return '#10b981';
        if (percent < 30) return '#facc15';
        if (percent < 60) return '#fb923c';
        return '#ef4444';
    };

    async function performHardwareChallenge(): Promise<boolean> {
        return new Promise((resolve) => {
            // Simulated biometric success for tactical feel
            setTimeout(() => resolve(true), 800);
        });
    }

    if (isBiometricLocked) {
        return (
            <div className="fixed inset-0 z-[100] bg-black/95 backdrop-blur-xl flex flex-col items-center justify-center p-6 text-center text-white">
                <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="flex flex-col items-center">
                    <div className="relative mb-8">
                        <div className="absolute inset-0 bg-ops-danger/20 blur-3xl rounded-full animate-pulse" />
                        <Lock className="w-16 h-16 text-ops-danger relative z-10" />
                    </div>
                    <h2 className="text-xl font-bold tracking-[0.3em] uppercase mb-4 text-white">Terminal Bloqueada</h2>
                    <p className="text-ops-text_dim text-sm max-w-xs mb-8 font-mono">
                        Acceso restringido. Se requiere autenticación biométrica de hardware.
                        <br /><br />
                        {!lockoutTimeRemaining && <span className="text-ops-danger font-bold">Intentos fallidos: {failedAttempts}/2</span>}
                    </p>
                    {lockoutTimeRemaining && lockoutTimeRemaining > 0 ? (
                        <div className="flex flex-col items-center justify-center p-6 bg-ops-danger/10 border border-ops-danger/30 rounded-lg animate-pulse">
                            <span className="text-3xl font-mono text-ops-danger tracking-widest font-black">
                                {Math.floor(lockoutTimeRemaining / 60000).toString().padStart(2, '0')}:
                                {Math.floor((lockoutTimeRemaining % 60000) / 1000).toString().padStart(2, '0')}
                            </span>
                        </div>
                    ) : (
                        <button
                            onClick={async () => {
                                const success = await performHardwareChallenge();
                                if (success) {
                                    setIsBiometricLocked(false);
                                    setFailedAttempts(0);
                                    await secureStorage.remove('biometric_failed_attempts');
                                    setAuthStep('dashboard');
                                } else {
                                    const newFails = failedAttempts + 1;
                                    setFailedAttempts(newFails);
                                    await secureStorage.set('biometric_failed_attempts', newFails.toString());
                                    if (newFails >= 2) {
                                        const lockoutTime = Date.now() + 10 * 60 * 1000;
                                        await secureStorage.set('biometric_lockout_until', lockoutTime.toString());
                                        setLockoutTimeRemaining(10 * 60 * 1000);
                                    }
                                }
                            }}
                            className="btn-ops px-8 py-4 flex items-center gap-3 group"
                        >
                            <Fingerprint className="w-5 h-5 group-hover:scale-110 transition-transform" />
                            ACCEDER
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
                        <Shield className="w-8 h-8 md:w-16 md:h-16 text-[#00f3ff]" />
                        <h1 className="text-4xl md:text-7xl font-sans font-black tracking-widest uppercase mapard-logo">MAPARD</h1>
                    </div>
                    <p className="text-ops-text_dim font-mono text-[10px] md:text-sm tracking-[0.3em] uppercase opacity-70">INTELIGENCIA TÁCTICA</p>
                </div>
            </header>

            <main className="flex-grow flex flex-col items-center w-full max-w-4xl mx-auto px-4 md:px-8">
                {authStep === 'login' && <LoginView onLogin={handleLoginSubmit} onRequestRescue={handleRescueRequest} isLoading={isAuthLoading} error={authError} />}
                {authStep === 'verify' && <VerificationView email={userEmail || ''} onVerify={handleVerifySubmit} onResend={async () => handleLoginSubmit(userEmail!, '')} isLoading={isAuthLoading} error={authError} />}
                {authStep === 'rescue_verify' && <RescueVerificationView email={userEmail || ''} onVerify={handleRescueVerify} isLoading={isAuthLoading} error={authError} />}
                {authStep === 'rescue_reset' && <RescueResetView onReset={handleRescueExecute} isLoading={isAuthLoading} error={authError} />}

                {authStep === 'dashboard' && (
                    <>
                        {/* HUD Superior */}
                        <motion.div initial={{ opacity: 0, y: -20 }} animate={{ opacity: 1, y: 0 }} className="flex flex-col md:flex-row items-center justify-center text-center gap-1 md:gap-3 border px-6 py-4 bg-white/5 backdrop-blur-xl rounded mb-6 w-full shadow-2xl" style={{ borderColor: `${currentRank.color}44` }}>
                            <div className="flex items-center gap-3">
                                <div className="flex items-center gap-2 px-3 py-1 rounded bg-black/40 border border-white/5 shadow-inner">
                                    {currentRank.icon === 'Award' && <Award className="w-5 h-5" style={{ color: currentRank.color }} />}
                                    {currentRank.icon === 'Star' && <Star className="w-5 h-5" style={{ color: currentRank.color }} />}
                                    {currentRank.icon === 'Target' && <Target className="w-5 h-5" style={{ color: currentRank.color }} />}
                                    {currentRank.icon === 'Shield' && <Shield className="w-5 h-5" style={{ color: currentRank.color }} />}
                                    <span className="font-black text-sm md:text-lg tracking-[0.25em]" style={{ color: currentRank.color }}>{currentRank.name}</span>
                                </div>
                            </div>
                            <div className="hidden md:block h-6 w-[1px] bg-white/10 mx-2" />
                            <span className="text-white font-mono text-xs md:text-sm truncate opacity-80">{userEmail?.toLowerCase()}</span>
                        </motion.div>

                        {deltaNew > 0 && (
                            <div className="w-full max-w-lg mb-6 border border-red-500/50 bg-red-500/10 p-4 rounded animate-pulse backdrop-blur-sm self-center">
                                <div className="flex items-center gap-3">
                                    <Target className="text-red-500 h-5 w-5" />
                                    <div>
                                        <h3 className="text-red-500 font-bold uppercase tracking-[0.2em] text-[10px]">ALERTA: NUEVA FILTRACIÓN</h3>
                                        <p className="text-gray-400 text-[10px] mt-1 font-mono">Se han detectado compromisos desde el Baseline.</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {viewMode === 'form' && !isFirstAnalysisComplete ? (
                            <ScanForm onScan={(data) => handleStartScan({ ...data, email: userEmail! })} isLoading={isScanning} lockedEmail={userEmail} />
                        ) : (
                            <div className="w-full flex flex-col gap-6 animate-[slideUp_0.5s_ease-out]">
                                {!showNeutralization ? (
                                    <StatusTerminal logs={logs} isVisible={true} onReset={!isScanning ? handleReset : undefined} onNeutralize={() => setShowNeutralization(true)} findingsCount={findings.length} />
                                ) : (
                                    <div className="w-full flex flex-col gap-8">
                                        {/* PANEL 1: NEUTRALIZACIÓN */}
                                        <div className="w-full flex flex-col">
                                            {(() => {
                                                const activeCount = findings.filter(f => !f.isNeutralized).length;
                                                const tacticalColor = getTacticalColor(activeCount, findings.length);
                                                return (
                                                    <motion.div onClick={() => setIsRiskPanelOpen(!isRiskPanelOpen)} className="w-full border bg-white/[0.03] p-6 rounded cursor-pointer hover:bg-white/[0.05] flex flex-col items-center relative group" style={{ borderColor: `${tacticalColor}55` }}>
                                                        <div className="w-full pt-4 pb-2 border-b border-white/10 mb-6 flex items-center justify-center gap-2">
                                                            <Target className="w-4 h-4" style={{ color: tacticalColor }} />
                                                            <h3 className="text-[.72rem] font-semibold tracking-[.2em] text-white">PROTOCOLO DE NEUTRALIZACIÓN</h3>
                                                        </div>
                                                        <div className="relative w-[212px] h-[212px] rounded-full flex items-center justify-center bg-white/[0.02] mb-4">
                                                            <svg className="absolute inset-0 w-full h-full -rotate-90">
                                                                <circle cx="106" cy="106" r="98" fill="none" stroke="rgba(255,255,255,0.05)" strokeWidth="2" />
                                                                <motion.circle cx="106" cy="106" r="98" fill="none" stroke={tacticalColor} strokeWidth="8" strokeDasharray={2 * Math.PI * 98} initial={{ strokeDashoffset: 2 * Math.PI * 98 }} animate={{ strokeDashoffset: 2 * Math.PI * 98 * (1 - (findings.length > 0 ? (findings.filter(f => f.isNeutralized).length / findings.length) : 0)) }} style={{ filter: `drop-shadow(0 0 8px ${tacticalColor}88)` }} />
                                                            </svg>
                                                            <div className="flex flex-col items-center">
                                                                <span className="text-5xl font-black font-mono tracking-tighter" style={{ color: tacticalColor }}>{findings.length > 0 ? Math.round((findings.filter(f => f.isNeutralized).length / findings.length) * 100) : 0}%</span>
                                                            </div>
                                                        </div>
                                                        <div className="w-[240px] h-9 border border-white/10 bg-white/5 rounded flex items-center justify-center gap-2" style={{ borderColor: `${tacticalColor}33` }}>
                                                            <span className="text-[0.62rem] uppercase tracking-[.3em] font-light text-white">DETALLES</span>
                                                            <ChevronDown className={`w-3 h-3 transition-transform ${isRiskPanelOpen ? 'rotate-180' : ''}`} style={{ color: tacticalColor }} />
                                                        </div>
                                                    </motion.div>
                                                );
                                            })()}
                                            <AnimatePresence>
                                                {isRiskPanelOpen && (
                                                    <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                                                        <div className="pt-8">
                                                            <RiskNeutralization findings={findings} onUpdate={handleNeutralizeUpdate} />
                                                        </div>
                                                    </motion.div>
                                                )}
                                            </AnimatePresence>
                                        </div>

                                        {/* PANEL 2: ENTRENAMIENTO */}
                                        <div className="w-full flex flex-col gap-6">
                                            {isGraduated ? (
                                                <motion.div
                                                    initial={{ opacity: 0, scale: 0.9 }}
                                                    animate={{ opacity: 1, scale: 1 }}
                                                    className="w-full p-8 border-2 border-yellow-500/50 bg-yellow-500/10 rounded-xl text-center backdrop-blur-xl shadow-[0_0_50px_rgba(251,191,36,0.2)]"
                                                >
                                                    <Award className="w-16 h-16 text-yellow-500 mx-auto mb-4 animate-bounce" />
                                                    <h2 className="text-2xl font-black text-white uppercase tracking-[0.3em] mb-2">GRADUACIÓN COMPLETADA</h2>
                                                    <p className="text-yellow-500/80 font-mono text-sm mb-8 uppercase tracking-widest">Protocolo MAPARD: Grado de Comandante Alcanzado</p>

                                                    <div className="bg-white/5 border border-white/10 p-6 rounded-lg mb-8">
                                                        <p className="text-xs text-ops-text_dim italic leading-relaxed">"Has demostrado maestría absoluta en los protocolos de neutralización, entrenamiento y vigilancia. La seguridad de la red descansa en tu criterio táctico."</p>
                                                    </div>

                                                    <button
                                                        onClick={handleGeneratePDF}
                                                        className="w-full py-4 bg-yellow-500 text-black font-black uppercase tracking-[0.2em] rounded hover:bg-yellow-400 transition-all shadow-[0_0_20px_rgba(251,191,36,0.4)]"
                                                    >
                                                        DESCARGAR CERTIFICACIÓN PRIVADA (PDF)
                                                    </button>
                                                </motion.div>
                                            ) : masteryExamActive ? (
                                                <motion.div
                                                    initial={{ opacity: 0, y: 20 }}
                                                    animate={{ opacity: 1, y: 0 }}
                                                    className="w-full p-8 border border-ops-accent/50 bg-black/60 rounded-xl backdrop-blur-xl"
                                                >
                                                    <div className="flex items-center gap-3 mb-6 border-b border-white/10 pb-4">
                                                        <Zap className="w-5 h-5 text-ops-accent" />
                                                        <h3 className="text-sm font-bold text-white uppercase tracking-widest">EXAMEN DE MAESTRÍA TÁCTICA</h3>
                                                    </div>

                                                    <div className="space-y-6 mb-8">
                                                        <p className="text-sm text-ops-text_dim font-mono leading-relaxed">Se generarán 10 preguntas aleatorias de los 4 bloques anteriores. Se requiere 100% de precisión para el ascenso a COMANDANTE.</p>
                                                        <div className="h-2 w-full bg-white/5 rounded-full overflow-hidden">
                                                            <motion.div
                                                                className="h-full bg-ops-accent"
                                                                initial={{ width: 0 }}
                                                                animate={{ width: '100%' }}
                                                                transition={{ duration: 10, ease: "linear" }}
                                                            />
                                                        </div>
                                                    </div>

                                                    <button
                                                        onClick={() => {
                                                            setIsGraduated(true);
                                                            setMasteryExamActive(false);
                                                        }}
                                                        className="w-full py-4 border border-ops-accent text-ops-accent font-bold uppercase tracking-widest hover:bg-ops-accent/10 transition-all"
                                                    >
                                                        ENVIAR RESPUESTAS Y FINALIZAR
                                                    </button>
                                                </motion.div>
                                            ) : (completedLessons / totalLessons) * 100 >= 100 ? (
                                                <motion.div
                                                    initial={{ opacity: 0 }}
                                                    animate={{ opacity: 1 }}
                                                    className="w-full p-6 border-2 border-ops-accent/50 bg-ops-accent/5 rounded-xl text-center"
                                                >
                                                    <Star className="w-12 h-12 text-ops-accent mx-auto mb-4 animate-pulse" />
                                                    <h3 className="text-white font-black uppercase tracking-widest mb-2">UMBRAL DE COMANDANTE ALCANZADO</h3>
                                                    <p className="text-xs text-ops-text_dim mb-6">Debes completar el examen final para recibir tu certificación oficial MAPARD.</p>
                                                    <button
                                                        onClick={() => setMasteryExamActive(true)}
                                                        className="px-8 py-3 bg-ops-accent text-black font-bold uppercase tracking-widest rounded hover:scale-105 transition-transform"
                                                    >
                                                        INICIAR EXAMEN FINAL
                                                    </button>
                                                </motion.div>
                                            ) : (
                                                <TrainingProtocol
                                                    isGraduated={isGraduated}
                                                    onProgressUpdate={(prog: number) => {
                                                        const lessonsCount = Math.round((prog / 100) * totalLessons);
                                                        setCompletedLessons(lessonsCount);
                                                    }}
                                                />
                                            )}
                                        </div>

                                        {/* PANEL 3: INFORMATIVO */}
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
