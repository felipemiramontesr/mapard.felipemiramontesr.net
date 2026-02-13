import React, { useEffect, useRef } from 'react';
import { Shield } from 'lucide-react';

const MatrixLoader: React.FC = () => {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const katakana = 'アァカサタナハマヤャラワガザダバパイィキシチニヒミリヰギジヂビピウゥクスツヌフムユュルグズブヅプエェケセテネヘメレヱゲゼデベペオォコソトノホモヨョロヲゴゾドボポ1234567890';
        const latin = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const nums = '0123456789';
        const alphabet = katakana + latin + nums;

        const fontSize = 16;
        const columns = canvas.width / fontSize;
        const drops: number[] = [];

        for (let x = 0; x < columns; x++) {
            drops[x] = 1;
        }

        const draw = () => {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.font = fontSize + 'px monospace';

            for (let i = 0; i < drops.length; i++) {
                const text = alphabet.charAt(Math.floor(Math.random() * alphabet.length));

                // Randomly switch between Cyan and Radioactive Green
                if (Math.random() > 0.5) {
                    ctx.fillStyle = '#00f3ff'; // Ops Cyan
                } else {
                    ctx.fillStyle = '#39ff14'; // Ops Radioactive
                }

                ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        };

        const interval = setInterval(draw, 30);

        const handleResize = () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        };

        window.addEventListener('resize', handleResize);

        return () => {
            clearInterval(interval);
            window.removeEventListener('resize', handleResize);
        };
    }, []);

    return (
        <div className="fixed inset-0 z-[100] bg-black flex flex-col items-center justify-center font-mono overflow-hidden">
            <canvas ref={canvasRef} className="absolute inset-0 opacity-60" />

            <div className="relative z-10 flex flex-col items-center gap-6 animate-pulse">
                <div className="relative">
                    <Shield className="w-24 h-24 text-ops-cyan drop-shadow-[0_0_15px_rgba(0,243,255,0.8)]" />
                    <div className="absolute inset-0 bg-ops-cyan/20 blur-xl rounded-full" />
                </div>

                <div className="flex flex-col items-center gap-2">
                    <h1 className="text-3xl font-black tracking-[0.3em] text-white drop-shadow-[0_0_10px_rgba(255,255,255,0.5)]">
                        MAPARD
                    </h1>
                    <div className="flex items-center gap-2 bg-black/80 px-4 py-1 border border-ops-radioactive/50 rounded-sm">
                        <div className="w-2 h-2 bg-ops-radioactive rounded-full animate-bounce" />
                        <span className="text-ops-radioactive text-xs tracking-widest font-bold">
                            INITIALIZING SYSTEM...
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default MatrixLoader;
