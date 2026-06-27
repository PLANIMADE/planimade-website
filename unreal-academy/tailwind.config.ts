import type { Config } from "tailwindcss";

/**
 * Design-System "Sci-Fi / Unreal".
 * Farben referenzieren CSS-Variablen aus globals.css (Single Source of Truth),
 * damit Theming/Dark-Mode zentral steuerbar bleibt.
 */
const config: Config = {
  content: ["./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      colors: {
        // UI-Flächen
        bg: "rgb(var(--bg) / <alpha-value>)",
        surface: "rgb(var(--surface) / <alpha-value>)",
        "surface-2": "rgb(var(--surface-2) / <alpha-value>)",
        border: "rgb(var(--border) / <alpha-value>)",
        text: "rgb(var(--text) / <alpha-value>)",
        muted: "rgb(var(--muted) / <alpha-value>)",
        // Akzente
        accent: "rgb(var(--accent) / <alpha-value>)",
        "accent-2": "rgb(var(--accent-2) / <alpha-value>)",
        success: "rgb(var(--success) / <alpha-value>)",
        danger: "rgb(var(--danger) / <alpha-value>)",
        xp: "rgb(var(--xp) / <alpha-value>)",
        streak: "rgb(var(--streak) / <alpha-value>)",
        // Blueprint-Pin-Farben (an Unreal angelehnt)
        "pin-exec": "rgb(var(--pin-exec) / <alpha-value>)",
        "pin-bool": "rgb(var(--pin-bool) / <alpha-value>)",
        "pin-int": "rgb(var(--pin-int) / <alpha-value>)",
        "pin-float": "rgb(var(--pin-float) / <alpha-value>)",
        "pin-string": "rgb(var(--pin-string) / <alpha-value>)",
        "pin-object": "rgb(var(--pin-object) / <alpha-value>)",
      },
      borderRadius: {
        xl: "1rem",
        "2xl": "1.25rem",
      },
      fontFamily: {
        sans: ["var(--font-sans)", "system-ui", "sans-serif"],
        mono: ["var(--font-mono)", "ui-monospace", "monospace"],
      },
      boxShadow: {
        glow: "0 0 0 1px rgb(var(--accent) / 0.4), 0 0 24px rgb(var(--accent) / 0.25)",
        node: "0 8px 24px rgb(0 0 0 / 0.45)",
      },
      keyframes: {
        "xp-pop": {
          "0%": { transform: "scale(0.6)", opacity: "0" },
          "60%": { transform: "scale(1.15)", opacity: "1" },
          "100%": { transform: "scale(1)", opacity: "1" },
        },
        "streak-pulse": {
          "0%, 100%": { transform: "scale(1)" },
          "50%": { transform: "scale(1.12)" },
        },
        "pin-glow": {
          "0%, 100%": { boxShadow: "0 0 0 0 rgb(var(--accent) / 0.0)" },
          "50%": { boxShadow: "0 0 12px 2px rgb(var(--accent) / 0.6)" },
        },
      },
      animation: {
        "xp-pop": "xp-pop 0.5s ease-out",
        "streak-pulse": "streak-pulse 1.4s ease-in-out infinite",
        "pin-glow": "pin-glow 1.2s ease-in-out infinite",
      },
    },
  },
  plugins: [],
};

export default config;
