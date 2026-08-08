/**
 * Vollbildansicht für die Bildstrecke einer Case-Study.
 *
 * Bedienung: Klick öffnet, Pfeiltasten blättern, Escape schließt, Klick auf
 * das Bild zoomt zwischen „ganz sichtbar" und „100 %" um. Auf Touch-Geräten
 * lässt sich wischen.
 */

interface Slide {
  url: string;
  alt: string;
  caption: string;
  kind: 'image' | 'video' | 'model';
}

export function initLightbox(root: HTMLElement): void {
  const figures = Array.from(root.querySelectorAll<HTMLElement>('[data-lightbox-item]'));
  if (figures.length === 0) return;

  const slides: Slide[] = figures.map((figure) => ({
    url: figure.dataset.full ?? '',
    alt: figure.dataset.alt ?? '',
    caption: figure.dataset.caption ?? '',
    kind: (figure.dataset.kind as Slide['kind']) ?? 'image',
  }));

  let index = 0;
  let zoomed = false;
  let overlay: HTMLElement | null = null;
  let lastFocused: HTMLElement | null = null;

  const render = (): void => {
    if (!overlay) return;

    const slide = slides[index];
    if (!slide) return;

    const stage = overlay.querySelector<HTMLElement>('[data-stage]');
    const caption = overlay.querySelector<HTMLElement>('[data-caption]');
    const counter = overlay.querySelector<HTMLElement>('[data-counter]');
    if (!stage || !caption || !counter) return;

    zoomed = false;
    stage.innerHTML =
      slide.kind === 'video'
        ? `<video src="${slide.url}" controls autoplay playsinline class="max-h-full max-w-full"></video>`
        : `<img src="${slide.url}" alt="${slide.alt}" data-zoomable class="max-h-full max-w-full cursor-zoom-in object-contain transition-transform duration-300">`;

    caption.textContent = slide.caption;
    counter.textContent = `${index + 1} / ${slides.length}`;

    stage.querySelector<HTMLImageElement>('[data-zoomable]')?.addEventListener('click', (event) => {
      const image = event.currentTarget as HTMLImageElement;
      zoomed = !zoomed;
      image.style.transform = zoomed ? 'scale(2)' : '';
      image.style.cursor = zoomed ? 'zoom-out' : 'zoom-in';
    });
  };

  const go = (step: number): void => {
    index = (index + step + slides.length) % slides.length;
    render();
  };

  const close = (): void => {
    if (!overlay) return;
    overlay.remove();
    overlay = null;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
    lastFocused?.focus();
  };

  const onKey = (event: KeyboardEvent): void => {
    if (!overlay) return;

    if (event.key === 'Escape') close();
    else if (event.key === 'ArrowRight') go(1);
    else if (event.key === 'ArrowLeft') go(-1);
  };

  const open = (start: number): void => {
    lastFocused = document.activeElement as HTMLElement | null;
    index = start;

    overlay = document.createElement('div');
    overlay.className =
      'fixed inset-0 z-[95] flex flex-col bg-black/95 opacity-0 backdrop-blur-sm transition-opacity duration-300';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Bildansicht');

    overlay.innerHTML = `
      <div class="flex items-center justify-between px-5 py-4 text-white/70">
        <span data-counter class="font-mono text-xs tracking-widest"></span>
        <button type="button" data-close aria-label="Schließen"
          class="grid h-9 w-9 place-items-center rounded-full border border-white/20 transition-colors hover:border-white/60 hover:text-white">✕</button>
      </div>

      <div data-stage class="flex flex-1 items-center justify-center overflow-hidden px-4 sm:px-16"></div>

      <div class="flex items-center justify-between gap-4 px-5 py-5">
        <button type="button" data-prev aria-label="Vorheriges Bild"
          class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-white/20 text-white/80 transition-colors hover:border-white/60 hover:text-white">←</button>
        <p data-caption class="min-w-0 flex-1 truncate text-center font-mono text-xs text-white/60"></p>
        <button type="button" data-next aria-label="Nächstes Bild"
          class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-white/20 text-white/80 transition-colors hover:border-white/60 hover:text-white">→</button>
      </div>`;

    document.body.append(overlay);
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => overlay?.classList.replace('opacity-0', 'opacity-100'));

    overlay.querySelector('[data-close]')?.addEventListener('click', close);
    overlay.querySelector('[data-prev]')?.addEventListener('click', () => go(-1));
    overlay.querySelector('[data-next]')?.addEventListener('click', () => go(1));
    overlay.querySelector<HTMLElement>('[data-stage]')?.addEventListener('click', (event) => {
      // Klick neben das Bild schließt.
      if (event.target === event.currentTarget) close();
    });

    // Wischen auf Touch-Geräten
    let touchStart = 0;
    overlay.addEventListener('touchstart', (event) => (touchStart = event.touches[0]?.clientX ?? 0), { passive: true });
    overlay.addEventListener(
      'touchend',
      (event) => {
        const delta = (event.changedTouches[0]?.clientX ?? 0) - touchStart;
        if (Math.abs(delta) > 60) go(delta < 0 ? 1 : -1);
      },
      { passive: true },
    );

    document.addEventListener('keydown', onKey);
    overlay.querySelector<HTMLButtonElement>('[data-close]')?.focus();
    render();
  };

  figures.forEach((figure, position) => {
    figure.style.cursor = 'zoom-in';
    figure.dataset.cursor = 'view';
    figure.dataset.cursorLabel = 'GRÖSSER';
    figure.addEventListener('click', () => open(position));
    figure.setAttribute('tabindex', '0');
    figure.setAttribute('role', 'button');
    figure.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        open(position);
      }
    });
  });
}
