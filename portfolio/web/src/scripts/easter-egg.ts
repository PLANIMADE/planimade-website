/**
 * Kleines Osterei: Der Konami-Code schaltet den „Wireframe-Modus" ein –
 * die Seite zeigt sich kurz so, wie ein 3D-Artist sie sieht.
 *
 * Zweiter Trigger: das Wort „render" tippen.
 */

const KONAMI = [
  'ArrowUp',
  'ArrowUp',
  'ArrowDown',
  'ArrowDown',
  'ArrowLeft',
  'ArrowRight',
  'ArrowLeft',
  'ArrowRight',
  'b',
  'a',
];

const STYLE_ID = 'wireframe-style';

function toggleWireframe(): void {
  const existing = document.getElementById(STYLE_ID);
  if (existing) {
    existing.remove();
    toast('Wireframe aus');
    return;
  }

  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = `
    * { outline: 1px solid color-mix(in srgb, var(--accent) 55%, transparent) !important; outline-offset: -1px; }
    img, video { filter: grayscale(1) contrast(1.15) brightness(0.75); }
    body::after {
      content: 'WIREFRAME MODE';
      position: fixed; left: 50%; bottom: 1.5rem; translate: -50% 0; z-index: 90;
      font-family: var(--font-mono); font-size: 0.625rem; letter-spacing: 0.3em;
      color: var(--accent); border: 1px solid var(--accent);
      padding: 0.4rem 0.9rem; border-radius: 999px; background: color-mix(in srgb, var(--bg) 80%, transparent);
    }`;
  document.head.append(style);
  toast('Wireframe an — nochmal für aus');
}

function toast(message: string): void {
  const element = document.createElement('div');
  element.className =
    'fixed bottom-20 left-1/2 z-[95] -translate-x-1/2 rounded-full border border-accent/40 bg-elevated px-5 py-2.5 font-mono text-[0.625rem] uppercase tracking-[0.2em] text-accent transition-opacity duration-300';
  element.textContent = message;
  document.body.append(element);
  window.setTimeout(() => {
    element.style.opacity = '0';
    window.setTimeout(() => element.remove(), 320);
  }, 2200);
}

export function initEasterEgg(): void {
  let konamiIndex = 0;
  let typed = '';

  document.addEventListener('keydown', (event) => {
    const target = event.target as HTMLElement | null;
    if (target?.matches('input, textarea, select')) return;

    // Konami-Code
    if (event.key === KONAMI[konamiIndex]) {
      konamiIndex += 1;
      if (konamiIndex === KONAMI.length) {
        konamiIndex = 0;
        toggleWireframe();
      }
    } else {
      konamiIndex = event.key === KONAMI[0] ? 1 : 0;
    }

    // Getipptes Codewort
    if (event.key.length === 1) {
      typed = (typed + event.key.toLowerCase()).slice(-6);
      if (typed === 'render') {
        typed = '';
        toggleWireframe();
      }
    }
  });
}
