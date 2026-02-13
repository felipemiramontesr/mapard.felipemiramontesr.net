/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        ops: {
          bg: "#0a0e27", // Dark Navy
          bg_alt: "#1a1f3a", // Lighter Navy
          text: "#e8e8e8", // White Bone
          text_dim: "#c5cae0", // Blueish Gray

          // FORCED CYAN PROTOCOL
          accent: "#00f3ff", // RADIOACTIVE CYAN (Overrides Lavender)
          cyan: "#00f3ff",   // Explicit Cyan
          radioactive: "#39ff14", // RADIOACTIVE GREEN (Neon)
          border: "#4a5578", // UI Border (Blueish)

          success: "#00f3ff", // Success is now Cyan too for uniformity
          warning: "#ff9f43",
          danger: "#ff2a2a",
        }
      },
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
      animation: {
        'scan': 'scan 4s linear infinite',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
      keyframes: {
        scan: {
          '0%': { top: '0%', opacity: '0' },
          '10%': { opacity: '1' },
          '90%': { opacity: '1' },
          '100%': { top: '100%', opacity: '0' },
        }
      },
      backgroundImage: {
        'ops-gradient': 'linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%)',
      }
    },
  },
  plugins: [],
}
