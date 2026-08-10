/**
 * Eigener Mauszeiger mit zwei Ebenen:
 *  – Punkt folgt 1:1 (fühlt sich präzise an)
 *  – Ring folgt gefedert nach (fühlt sich lebendig an)
 *
 * Über `data-cursor="hover|view|text"` bzw. `data-cursor-label="ANSEHEN"`
 * lässt sich das Verhalten pro Element steuern.
 *
 * Wichtigste Regel hier: Der System-Zeiger wird erst versteckt, wenn der
 * eigene tatsächlich läuft – und sofort wieder freigegeben, sobald er das
 * nicht mehr tut. Vorher setzte das Kopf-Skript `data-cursor="custom"` blind,
 * bevor überhaupt feststand, ob es einen Ersatz gibt. Wer „Bewegung
 * reduzieren" eingeschaltet hatte oder bei wem das Skript nicht ankam, hatte
 * danach gar keinen Mauszeiger mehr.
 */

const LERP = 0.18;
const STORAGE_KEY = 'dm-cursor';

let stop: (() => void) | null = null;

/** Zeigt wieder den Zeiger des Systems. */
function systemZeiger(): void {
  delete document.documentElement.dataset.cursor;
}

function starte(root: HTMLElement): boolean {
  const dot = root.querySelector<HTMLElement>('[data-cursor-dot]');
  const ring = root.querySelector<HTMLElement>('[data-cursor-ring]');
  const label = root.querySelector<HTMLElement>('[data-cursor-label]');
  if (!dot || !ring || !label) return false;

  let pointerX = window.innerWidth / 2;
  let pointerY = window.innerHeight / 2;
  let ringX = pointerX;
  let ringY = pointerY;
  let visible = false;
  let laeuft = true;

  const onMove = (event: PointerEvent): void => {
    pointerX = event.clientX;
    pointerY = event.clientY;

    if (!visible) {
      visible = true;
      ringX = pointerX;
      ringY = pointerY;
      root.style.opacity = '1';
    }

    dot.style.transform = `translate3d(${pointerX}px, ${pointerY}px, 0)`;
    label.style.transform = `translate3d(${pointerX}px, ${pointerY}px, 0)`;
  };

  const frame = (): void => {
    if (!laeuft) return;
    ringX += (pointerX - ringX) * LERP;
    ringY += (pointerY - ringY) * LERP;
    ring.style.transform = `translate3d(${ringX}px, ${ringY}px, 0)`;
    requestAnimationFrame(frame);
  };

  const setState = (state: string, text = ''): void => {
    ring.dataset.state = state;
    label.textContent = text;
    label.classList.toggle('is-visible', text !== '');
  };

  const onDown = (): void => {
    ring.style.setProperty('scale', '0.8');
  };
  const onUp = (): void => {
    ring.style.removeProperty('scale');
  };
  const onLeave = (): void => {
    root.style.opacity = '0';
    visible = false;
  };

  // Delegiert – funktioniert damit auch für später nachgeladene Projektkarten.
  const onOver = (event: PointerEvent): void => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      '[data-cursor], a, button, input, textarea, select',
    );
    if (!target) {
      setState('default');
      return;
    }

    const mode = target.dataset.cursor ?? (target.matches('input, textarea, select') ? 'text' : 'hover');
    setState(mode, target.dataset.cursorLabel ?? '');
  };

  document.addEventListener('pointermove', onMove, { passive: true });
  document.addEventListener('pointerdown', onDown);
  document.addEventListener('pointerup', onUp);
  document.addEventListener('pointerleave', onLeave);
  document.addEventListener('pointerover', onOver, { passive: true });
  requestAnimationFrame(frame);

  // Erst jetzt darf der System-Zeiger verschwinden. Sichtbar wird der eigene
  // mit der ersten Mausbewegung – sonst klebte er kurz in der linken oberen
  // Ecke, wo die Maus gar nicht ist.
  root.style.opacity = '0';
  root.hidden = false;
  document.documentElement.dataset.cursor = 'custom';

  stop = (): void => {
    laeuft = false;
    document.removeEventListener('pointermove', onMove);
    document.removeEventListener('pointerdown', onDown);
    document.removeEventListener('pointerup', onUp);
    document.removeEventListener('pointerleave', onLeave);
    document.removeEventListener('pointerover', onOver);
    root.hidden = true;
    root.style.opacity = '0';
    systemZeiger();
    stop = null;
  };

  return true;
}

/** Ist der eigene Zeiger auf diesem Gerät überhaupt sinnvoll? */
function moeglich(): boolean {
  // Ohne Maus gibt es nichts zu ersetzen, und wer weniger Bewegung möchte,
  // will keinen nachfedernden Ring.
  return (
    window.matchMedia('(pointer: fine)').matches &&
    !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

/** Merkt sich die Wahl des Besuchers – unabhängig von der Einstellung im Dashboard. */
export function cursorEnabled(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) !== 'off';
  } catch {
    return true;
  }
}

/**
 * Schaltet den eigenen Zeiger an oder aus.
 *
 * `merken` ist aus, wenn die Einstellung aus dem Dashboard kommt: Die soll
 * die Wahl des Besuchers nicht überschreiben.
 */
export function setCursor(on: boolean, merken = true): boolean {
  if (merken) {
    try {
      localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off');
    } catch {
      // Kein Speicher, kein Drama – die Wahl gilt dann nur für diesen Besuch.
    }
  }

  const root = document.querySelector<HTMLElement>('[data-cursor-root]');
  if (!root) return false;

  if (!on || !moeglich()) {
    stop?.();
    return false;
  }

  return stop !== null || starte(root);
}

export function toggleCursor(): boolean {
  return setCursor(!(stop !== null));
}

export function initCursor(): void {
  const root = document.querySelector<HTMLElement>('[data-cursor-root]');
  if (!root) {
    systemZeiger();
    return;
  }

  try {
    if (!cursorEnabled() || !setCursor(true, false)) {
      systemZeiger();
    }
  } catch {
    // Geht hier irgendetwas schief, ist ein sichtbarer System-Zeiger allemal
    // besser als gar keiner.
    systemZeiger();
  }
}
