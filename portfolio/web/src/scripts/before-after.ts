/**
 * Vorher/Nachher-Vergleich: zwei übereinanderliegende Bilder, deren Trennlinie
 * per Maus, Finger oder Pfeiltasten verschoben wird.
 */

export function initBeforeAfter(): void {
  document.querySelectorAll<HTMLElement>('[data-before-after]').forEach((container) => {
    const beforeSrc = container.dataset.before;
    const afterSrc = container.dataset.after;
    if (!beforeSrc || !afterSrc) return;

    container.innerHTML = `
      <img src="${afterSrc}" alt="Nachher" class="absolute inset-0 h-full w-full object-cover" draggable="false" loading="lazy">
      <div data-clip class="absolute inset-0 overflow-hidden" style="width: 50%">
        <img src="${beforeSrc}" alt="Vorher" class="absolute inset-0 h-full w-full max-w-none object-cover" draggable="false" loading="lazy" style="width: ${container.clientWidth}px">
      </div>
      <div data-handle class="absolute inset-y-0 w-px bg-white/80" style="left: 50%">
        <span class="absolute top-1/2 left-1/2 grid h-11 w-11 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-white/60 bg-black/50 text-white backdrop-blur">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4">
            <path d="m9 7-5 5 5 5M15 7l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
        </span>
      </div>
      <span class="absolute left-4 top-4 rounded-full bg-black/50 px-2.5 py-1 font-mono text-[0.5625rem] uppercase tracking-[0.2em] text-white/80 backdrop-blur">Vorher</span>
      <span class="absolute right-4 top-4 rounded-full bg-black/50 px-2.5 py-1 font-mono text-[0.5625rem] uppercase tracking-[0.2em] text-white/80 backdrop-blur">Nachher</span>
      <input
        type="range" min="0" max="100" value="50" step="0.5"
        aria-label="Vergleich zwischen Vorher und Nachher verschieben"
        class="absolute inset-0 h-full w-full cursor-ew-resize opacity-0"
        data-range
      >`;

    const clip = container.querySelector<HTMLElement>('[data-clip]');
    const clipImage = clip?.querySelector<HTMLImageElement>('img');
    const handle = container.querySelector<HTMLElement>('[data-handle]');
    const range = container.querySelector<HTMLInputElement>('[data-range]');
    if (!clip || !clipImage || !handle || !range) return;

    const apply = (percent: number): void => {
      clip.style.width = `${percent}%`;
      handle.style.left = `${percent}%`;
      // Das innere Bild behält die volle Breite, damit es nicht gestaucht wirkt.
      clipImage.style.width = `${container.clientWidth}px`;
    };

    range.addEventListener('input', () => apply(Number(range.value)));
    window.addEventListener('resize', () => apply(Number(range.value)));
    apply(50);
  });
}
