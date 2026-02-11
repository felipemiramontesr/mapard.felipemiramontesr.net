import { Shield } from 'lucide-react';
import Dashboard from './components/Dashboard';

function App() {
  return (
    <div className="min-h-screen flex flex-col">
      {/* Header */}
      <header className="border-b border-white/10 bg-cyber-dark/80 backdrop-blur-md sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Shield className="w-8 h-8 text-cyber-accent" />
            <span className="text-xl font-bold tracking-wider text-white">MAPA-RD <span className="text-cyber-accent text-sm ml-1">INTEL</span></span>
          </div>

          <nav className="hidden md:flex gap-6 text-cyber-muted">
            <a href="#" className="hover:text-cyber-accent transition-colors">Dashboard</a>
            <a href="#" className="hover:text-cyber-accent transition-colors">Historial</a>
            <a href="#" className="hover:text-cyber-accent transition-colors">API Keys</a>
          </nav>

          <div className="flex items-center gap-2">
            <div className="w-2 h-2 rounded-full bg-cyber-success animate-pulse" />
            <span className="text-xs font-mono text-cyber-success">SYSTEM ONLINE</span>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-grow pt-8 pb-16 px-4">
        <Dashboard />
      </main>

      {/* Footer */}
      <footer className="border-t border-white/5 py-8 bg-black/20 text-center">
        <p className="text-cyber-muted text-sm font-mono">
          MAPA-RD &copy; {new Date().getFullYear()} • NSA-LEVEL OSINT ENGINE
        </p>
      </footer>
    </div>
  );
}

export default App;
