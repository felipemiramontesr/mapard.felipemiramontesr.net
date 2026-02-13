import { Shield } from 'lucide-react';
import Dashboard from './components/Dashboard';

function App() {
  return (
    <div className="h-screen w-screen overflow-hidden flex flex-col relative bg-ops-bg selection:bg-ops-accent selection:text-black">
      {/* Background Grid/Effect */}
      <div className="absolute inset-0 z-0 pointer-events-none bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-ops-bg_alt via-ops-bg to-black opacity-80" />

      {/* Header (Fixed) */}
      <header className="border-b border-white/10 bg-ops-bg/80 backdrop-blur-xl relative z-50 h-16 flex-none shadow-2xl">
        <div className="max-w-7xl mx-auto px-4 h-full flex items-center justify-between">
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

      {/* Main Content (Centered, Strict No Scroll) */}
      <main className="flex-grow relative z-10 flex flex-col justify-center items-center p-4 overflow-hidden">
        <Dashboard />
      </main>

      {/* Footer (Fixed Bottom) */}
      <footer className="border-t border-white/10 py-4 bg-black/60 backdrop-blur-md text-center flex-none relative z-50">
        <p className="text-white/40 text-[10px] sm:text-xs font-mono tracking-[0.2em] uppercase">
          MAPA-RD &copy; {new Date().getFullYear()} • <span className="text-ops-cyan font-bold drop-shadow-[0_0_5px_rgba(0,243,255,0.3)]">BLACK-OPS-LEVEL</span> OSINT
        </p>
      </footer>
    </div>
  );
}

export default App;
