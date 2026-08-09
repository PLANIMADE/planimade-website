/**
 * Dezente Interface-Sounds – komplett synthetisiert über die Web Audio API.
 * Kein einziges Audio-File, damit nichts nachgeladen werden muss.
 *
 * Standardmäßig aus: Ton ohne Zutun des Besuchers ist übergriffig.
 */

const STORAGE_KEY = 'dm-sound';

let context: AudioContext | null = null;
let master: GainNode | null = null;
let enabled = false;

function ensureContext(): AudioContext | null {
  if (context) return context;
  const Ctor = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
  if (!Ctor) return null;

  context = new Ctor();
  master = context.createGain();
  master.gain.value = 0.05;
  master.connect(context.destination);

  return context;
}

interface ToneOptions {
  frequency: number;
  duration: number;
  type?: OscillatorType;
  gain?: number;
  glideTo?: number;
}

function tone({ frequency, duration, type = 'sine', gain = 1, glideTo }: ToneOptions): void {
  const ctx = ensureContext();
  if (!ctx || !master || !enabled) return;
  if (ctx.state === 'suspended') void ctx.resume();

  const oscillator = ctx.createOscillator();
  const envelope = ctx.createGain();

  oscillator.type = type;
  oscillator.frequency.setValueAtTime(frequency, ctx.currentTime);
  if (glideTo !== undefined) {
    oscillator.frequency.exponentialRampToValueAtTime(glideTo, ctx.currentTime + duration);
  }

  // Weiche Hüllkurve – harte Kanten klingen nach Fehler-Piep.
  envelope.gain.setValueAtTime(0, ctx.currentTime);
  envelope.gain.linearRampToValueAtTime(gain, ctx.currentTime + 0.008);
  envelope.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + duration);

  oscillator.connect(envelope);
  envelope.connect(master);
  oscillator.start();
  oscillator.stop(ctx.currentTime + duration + 0.02);
}

export const sound = {
  hover: () => tone({ frequency: 880, duration: 0.06, type: 'sine', gain: 0.35 }),
  click: () => tone({ frequency: 520, duration: 0.12, type: 'triangle', glideTo: 300 }),
  open: () => tone({ frequency: 320, duration: 0.22, type: 'sine', glideTo: 640, gain: 0.6 }),
  close: () => tone({ frequency: 540, duration: 0.18, type: 'sine', glideTo: 220, gain: 0.5 }),
  success: () => {
    tone({ frequency: 523.25, duration: 0.16, type: 'sine' });
    window.setTimeout(() => tone({ frequency: 783.99, duration: 0.28, type: 'sine' }), 90);
  },
};

export function isSoundEnabled(): boolean {
  return enabled;
}

function syncButtons(): void {
  document.querySelectorAll<HTMLButtonElement>('[data-sound-toggle]').forEach((button) => {
    button.setAttribute('aria-pressed', String(enabled));
    const wave = button.querySelector<SVGPathElement>('[data-sound-wave]');
    if (wave) wave.style.opacity = enabled ? '1' : '0.25';
  });
}

/**
 * Ton an oder aus.
 *
 * Erreichbar über die Schnellsuche (⌘K). In der Kopfzeile stand dafür lange
 * ein Lautsprecher – für eine Einstellung, die kaum jemand sucht, war das
 * ein zu prominenter Platz.
 */
export function toggleSound(): boolean {
  enabled = !enabled;
  try {
    localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off');
  } catch {
    /* egal */
  }
  syncButtons();
  if (enabled) sound.open();

  return enabled;
}

export function soundEnabled(): boolean {
  return enabled;
}

export function initSound(): void {
  try {
    enabled = localStorage.getItem(STORAGE_KEY) === 'on';
  } catch {
    enabled = false;
  }
  syncButtons();

  document.querySelectorAll<HTMLButtonElement>('[data-sound-toggle]').forEach((button) => {
    button.addEventListener('click', () => toggleSound());
  });

  // Hover- und Klickgeräusche an allen interaktiven Elementen.
  document.addEventListener(
    'pointerenter',
    (event) => {
      const target = event.target as HTMLElement | null;
      if (target?.closest?.('a, button, [data-cursor="hover"]')) sound.hover();
    },
    true,
  );

  document.addEventListener('click', (event) => {
    const target = event.target as HTMLElement | null;
    if (target?.closest?.('a, button')) sound.click();
  });
}
