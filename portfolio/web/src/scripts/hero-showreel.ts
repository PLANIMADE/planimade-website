/**
 * Showreel-Variante des Kopfbereichs.
 *
 * Steht im Dashboard „Showreel" statt „Typografie", ersetzt ein stumm
 * laufendes Video den WebGL-Hintergrund. Der Ton lässt sich zuschalten –
 * automatisch mit Ton startet hier nichts.
 *
 * Das Video läuft von selbst, auch wenn das System „weniger Bewegung"
 * meldet. Vorher tat es das nicht – dann stand oben ein Standbild und man
 * musste erst auf „Ton an" drücken, damit sich etwas rührte. Das Showreel
 * ist aber der Inhalt der Seite und keine Verzierung. Damit die Regel
 * trotzdem gewahrt bleibt, dass sich Bewegung anhalten lassen muss, sitzt
 * neben dem Ton-Schalter ein Schalter zum Anhalten.
 */

import type { Settings } from '../lib/types';
import { track } from '../lib/api';

export function initHeroShowreel(settings: Settings): boolean {
  const hero = settings.hero;
  const container = document.querySelector<HTMLElement>('[data-hero-media]');

  if (!container || hero.mode !== 'showreel' || !hero.video) {
    return false;
  }

  const poster = hero.poster?.url ?? '';
  const overlay = Math.min(90, Math.max(0, Number(hero.overlay ?? 55))) / 100;

  container.innerHTML = `
    <video
      data-hero-video
      class="absolute inset-0 h-full w-full object-cover"
      ${poster ? `poster="${poster}"` : ''}
      muted loop playsinline autoplay preload="auto"
      aria-label="Showreel"
    >
      <source src="${hero.video.url}" type="${hero.video.mime}">
    </video>
    <div class="absolute inset-0" style="background: rgb(0 0 0 / ${overlay})"></div>
    <!-- Nach unten dunkel auslaufen lassen, nicht in die Seitenfarbe:
         Sonst stünde heller Text im hellen Farbschema auf hellem Grund. -->
    <div class="absolute inset-x-0 bottom-0 h-72" style="background: linear-gradient(to top, rgb(0 0 0 / 0.88), transparent)"></div>`;

  const video = container.querySelector<HTMLVideoElement>('[data-hero-video]');
  if (!video) return true;

  // Der WebGL-Hintergrund wird nicht gebraucht, wenn ein Video läuft.
  document.querySelector('[data-hero-canvas]')?.remove();
  document.querySelector('[data-hero-fallback]')?.remove();

  // Schrift auf Hell umstellen – über dem abgedunkelten Video wäre dunkler
  // Text im hellen Farbschema kaum lesbar.
  container.closest('section')?.setAttribute('data-hero-on-video', '');

  // Auch die Kopfzeile schwebt über dem Video. Sie steht außerhalb dieses
  // Abschnitts, erbt die Umstellung also nicht – deshalb eine Markierung am
  // Wurzelelement, an der sich das Stylesheet bedienen kann.
  document.documentElement.dataset.heroVideo = '';

  // Von Hand angehalten? Dann soll es auch angehalten bleiben – weder der
  // Beobachter unten noch ein Tabwechsel dürfen es wieder anwerfen.
  const stand = { pausiert: false };

  const play = (): void => {
    if (stand.pausiert) return;
    void video.play().catch(() => undefined);
  };

  play();
  // Manche Browser blocken den ersten Versuch – nach der ersten
  // Interaktion klappt es zuverlässig.
  document.addEventListener('pointerdown', play, { once: true });

  addControls(container, video, stand);
  addPauseOnHidden(video, play);

  return true;
}

/** Anhalten und Ton – unten rechts im Hero. */
function addControls(container: HTMLElement, video: HTMLVideoElement, stand: { pausiert: boolean }): void {
  const leiste = document.createElement('div');
  leiste.className = 'absolute bottom-6 right-6 z-20 flex items-center gap-2';

  leiste.append(addPlayToggle(video, stand), addSoundToggle(video));
  container.append(leiste);
}

const KNOPF =
  'flex items-center gap-2 rounded-full border border-white/25 bg-black/40 px-4 py-2.5 font-mono text-[0.625rem] uppercase tracking-[0.2em] text-white/90 backdrop-blur transition-colors hover:border-white/60';

/**
 * Anhalten und weiterlaufen lassen.
 *
 * Ohne diesen Schalter dürfte das Video nicht von selbst laufen: Bewegung,
 * die länger als ein paar Sekunden dauert und von allein startet, muss sich
 * anhalten lassen.
 */
function addPlayToggle(video: HTMLVideoElement, stand: { pausiert: boolean }): HTMLButtonElement {
  const button = document.createElement('button');
  button.type = 'button';
  button.dataset.cursor = 'hover';
  button.className = KNOPF;
  button.setAttribute('aria-label', 'Showreel anhalten');

  const label = document.createElement('span');
  label.textContent = 'Pause';
  button.append(playIcon(true), label);

  button.addEventListener('click', () => {
    stand.pausiert = !video.paused;

    if (stand.pausiert) {
      video.pause();
    } else {
      void video.play().catch(() => undefined);
    }

    label.textContent = stand.pausiert ? 'Abspielen' : 'Pause';
    button.setAttribute('aria-label', stand.pausiert ? 'Showreel abspielen' : 'Showreel anhalten');
    button.replaceChild(playIcon(!stand.pausiert), button.firstChild!);
  });

  return button;
}

/** Ton-Schalter unten rechts im Hero. */
function addSoundToggle(video: HTMLVideoElement): HTMLButtonElement {
  const button = document.createElement('button');
  button.type = 'button';
  button.dataset.cursor = 'hover';
  button.className = KNOPF;
  button.setAttribute('aria-pressed', 'false');

  const label = document.createElement('span');
  label.textContent = 'Ton an';
  button.append(icon(false), label);

  button.addEventListener('click', () => {
    video.muted = !video.muted;
    button.setAttribute('aria-pressed', String(!video.muted));
    label.textContent = video.muted ? 'Ton an' : 'Ton aus';
    button.replaceChild(icon(!video.muted), button.firstChild!);

    if (!video.muted) {
      void video.play().catch(() => undefined);
      track('showreel_play');
    }
  });

  return button;
}

function playIcon(laeuft: boolean): SVGSVGElement {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'currentColor');
  svg.setAttribute('class', 'h-3.5 w-3.5');
  svg.innerHTML = laeuft
    ? '<rect x="7" y="5" width="3.5" height="14" rx="1"/><rect x="13.5" y="5" width="3.5" height="14" rx="1"/>'
    : '<path d="M8 5.5v13l11-6.5z"/>';

  return svg;
}

function icon(on: boolean): SVGSVGElement {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '1.6');
  svg.setAttribute('class', 'h-3.5 w-3.5');
  svg.innerHTML = on
    ? '<path d="M11 5 6 9H3v6h3l5 4V5Z" stroke-linejoin="round"/><path d="M15.5 8.5a5 5 0 0 1 0 7" stroke-linecap="round"/>'
    : '<path d="M11 5 6 9H3v6h3l5 4V5Z" stroke-linejoin="round"/><path d="m16 9 5 6m0-6-5 6" stroke-linecap="round"/>';

  return svg;
}

/**
 * Im Hintergrund-Tab oder außerhalb des Bildes nicht weiterlaufen lassen.
 *
 * `play` prüft selbst, ob von Hand angehalten wurde – sonst würde ein
 * Tabwechsel die Pause des Besuchers übergehen.
 */
function addPauseOnHidden(video: HTMLVideoElement, play: () => void): void {
  const observer = new IntersectionObserver(([entry]) => {
    if (entry?.isIntersecting) {
      play();
    } else {
      video.pause();
    }
  });
  observer.observe(video);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      video.pause();
    } else {
      play();
    }
  });
}
