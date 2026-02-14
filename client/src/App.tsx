import { Shield } from 'lucide-react';
import Dashboard from './components/Dashboard';
import MatrixLoader from './components/MatrixLoader';
import { useEffect, useState } from 'react';
import { StatusBar } from '@capacitor/status-bar';

function App() {
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Fake loading time for splash effect
    const timer = setTimeout(() => {
      setIsLoading(false);
    }, 8000); // 8 seconds loading

    const enableImmersive = async () => {
      try {
        await StatusBar.setOverlaysWebView({ overlay: true });
        await StatusBar.hide();
      } catch (e) {
        console.log('Non-native env or StatusBar error', e);
      }
    };
    enableImmersive();

    return () => clearTimeout(timer);
  }, []);

  if (isLoading) {
    return <MatrixLoader />;
  }

  return (
    <div className="fixed inset-0 w-full flex flex-col relative bg-ops-bg selection:bg-ops-accent selection:text-black overflow-hidden">
      {/* Background Grid/Effect */}
      <div className="absolute inset-0 z-0 pointer-events-none bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-ops-bg_alt via-ops-bg to-black opacity-80" />

      {/* Header (Fixed) */}
      <header className="border-b border-white/10 bg-ops-bg/80 backdrop-blur-xl relative z-50 flex-none shadow-2xl pt-[env(safe-area-inset-top)] transition-all duration-300">
        <div className="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
          <div className="flex items-center gap-2 sm:gap-3">
            <Shield className="w-6 h-6 sm:w-8 sm:h-8 text-ops-cyan animate-pulse drop-shadow-[0_0_8px_rgba(0,243,255,0.5)]" />
            <span className="text-xl sm:text-2xl font-black tracking-widest text-white drop-shadow-md">MAPARD</span>
          </div>

          <div className="flex items-center gap-2 border border-ops-radioactive/30 px-2 sm:px-3 py-1 rounded-sm bg-ops-radioactive/5 backdrop-blur-sm">
            <div className="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-ops-radioactive animate-[pulse_1.5s_ease-in-out_infinite] shadow-[0_0_10px_#39ff14]" />
            <span className="text-[10px] sm:text-xs font-mono font-bold text-ops-radioactive tracking-widest drop-shadow-[0_0_5px_rgba(57,255,20,0.5)]">SYSTEM ONLINE</span>
          </div>
        </div>
      </header>

      {/* Main Content (Centered, Allow Scroll) */}
      <main className="flex-grow relative z-10 flex flex-col justify-center items-center p-4 overflow-y-auto w-full">
        <Dashboard />
      </main>

      {/* Footer (Fixed Bottom) */}
      <footer className="border-t border-white/10 h-16 flex items-center justify-center bg-black/60 backdrop-blur-md flex-none relative z-50">
        <p className="text-white/40 text-[10px] sm:text-xs font-mono tracking-[0.2em] uppercase">
          MAPARD &copy; {new Date().getFullYear()} • <span className="text-ops-cyan font-bold drop-shadow-[0_0_5px_rgba(0,243,255,0.3)]">BLACK-OPS-LEVEL</span> OSINT
        </p>
      </footer>
    </div>
  );
}

export default App;
