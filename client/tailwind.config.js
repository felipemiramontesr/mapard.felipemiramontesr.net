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
        cyber: {
          dark: "#0a0e27",
          darker: "#050714",
          light: "#1a234a",
          accent: "#00f0ff", // Neon Cyan
          danger: "#ff2a2a", // Critical Red
          warning: "#ff9f43", // High Orange
          success: "#00ff85", // Secure Green
          text: "#e8e8e8",
          muted: "#8a9fca",
        }
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
      animation: {
        'scan': 'scan 2s linear infinite',
      },
      keyframes: {
        scan: {
          '0%': { top: '0%' },
          '100%': { top: '100%' },
        }
      }
    },
  },
  plugins: [],
}
