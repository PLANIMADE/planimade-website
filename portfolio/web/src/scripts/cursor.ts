/**
 * Eigener Mauszeiger mit zwei Ebenen:
 *  – Punkt folgt 1:1 (fühlt sich präzise an)
 *  – Ring folgt gefedert nach (fühlt sich lebendig an)
 *
 * Über `data-cursor="hover|view|text"` bzw. `data-cursor-label="ANSEHEN"`
 * lässt sich das Verhalten pro Element steuern.
 */

const LERP = 0.18;

export function initCursor(): void {
  const fine = window.matchMedia('(pointer: fine)').matches;
  const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const root = document.querySelector<HTMLElement>('[data-cursor-root]');
  if (!fine || calm || !root) {
    root?.remove();
    return;
  }

  const dot = root.querySelector<HTMLElement>('[data-cursor-dot]');
  const ring = root.querySelector<HTMLElement>('[data-cursor-ring]');
  const label = root.querySelector<HTMLElement>('[data-cursor-label]');
  if (!dot || !ring || !label) return;

  let pointerX = window.innerWidth / 2;
  let pointerY = window.innerHeight / 2;
  let ringX = pointerX;
  let ringY = pointerY;
  let visible = false;

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

  document.addEventListener('pointermove', onMove, { passive: true });
  document.addEventListener('pointerdown', () => ring.style.setProperty('scale', '0.8'));
  document.addEventListener('pointerup', () => ring.style.removeProperty('scale'));
  document.addEventListener('pointerleave', () => {
    root.style.opacity = '0';
    visible = false;
  });

  // Delegiert – funktioniert damit auch für später nachgeladene Projektkarten.
  document.addEventListener(
    'pointerover',
    (event) => {
      const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
        '[data-cursor], a, button, input, textarea, select',
      );
      if (!target) {
        setState('default');
        return;
      }

      const mode = target.dataset.cursor ?? (target.matches('input, textarea, select') ? 'text' : 'hover');
      setState(mode, target.dataset.cursorLabel ?? '');
    },
    { passive: true },
  );

  requestAnimationFrame(frame);
}
