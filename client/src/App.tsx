import { Shield } from 'lucide-react';
import Dashboard from './components/Dashboard';

function App() {
  return (
    <div className="min-h-screen flex flex-col">
      {/* Header */}
      <header className="border-b border-ops-border/30 bg-ops-bg/90 backdrop-blur-md sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Shield className="w-8 h-8 text-ops-cyan animate-pulse" />
            <span className="text-2xl font-black tracking-widest text-white">MAPARD</span>
          </div>

          <div className="flex items-center gap-2 border border-ops-radioactive/30 px-3 py-1 rounded bg-ops-radioactive/5">
            <div className="w-2 h-2 rounded-full bg-ops-radioactive animate-[pulse_1.5s_ease-in-out_infinite]" />
            <span className="text-xs font-mono font-bold text-ops-radioactive tracking-widest">SYSTEM ONLINE</span>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-grow pt-8 pb-16 px-4">
        <Dashboard />
      </main>

      {/* Footer */}
      <footer className="border-t border-ops-border/30 py-8 bg-black/40 text-center">
        <p className="text-ops-text_dim text-xs font-mono tracking-widest">
          MAPA-RD &copy; {new Date().getFullYear()} • <span className="text-ops-cyan">BLACK-OPS-LEVEL</span> OSINT ENGINE
        </p>
      </footer>
    </div>
  );
}

export default App;
