/**
 * Showreel-Variante des Kopfbereichs.
 *
 * Steht im Dashboard „Showreel" statt „Typografie", ersetzt ein stumm
 * laufendes Video den WebGL-Hintergrund. Der Ton lässt sich zuschalten –
 * automatisch mit Ton startet hier nichts.
 *
 * Wer weniger Bewegung eingestellt hat, sieht nur das Standbild.
 */

import type { Settings } from '../lib/types';
import { track } from '../lib/api';

export function initHeroShowreel(settings: Settings): boolean {
  const hero = settings.hero;
  const container = document.querySelector<HTMLElement>('[data-hero-media]');

  if (!container || hero.mode !== 'showreel' || !hero.video) {
    return false;
  }

  const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const poster = hero.poster?.url ?? '';
  const overlay = Math.min(90, Math.max(0, Number(hero.overlay ?? 55))) / 100;

  container.innerHTML = `
    <video
      data-hero-video
      class="absolute inset-0 h-full w-full object-cover"
      ${poster ? `poster="${poster}"` : ''}
      muted loop playsinline preload="metadata"
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

  if (!calm) {
    const play = (): void => {
      void video.play().catch(() => undefined);
    };
    play();
    // Manche Browser blocken den ersten Versuch – nach der ersten
    // Interaktion klappt es zuverlässig.
    document.addEventListener('pointerdown', play, { once: true });
  }

  addSoundToggle(container, video);
  addPauseOnHidden(video, calm);

  return true;
}

/** Ton-Schalter unten rechts im Hero. */
function addSoundToggle(container: HTMLElement, video: HTMLVideoElement): void {
  const button = document.createElement('button');
  button.type = 'button';
  button.dataset.cursor = 'hover';
  button.className =
    'absolute bottom-6 right-6 z-20 flex items-center gap-2 rounded-full border border-white/25 bg-black/40 px-4 py-2.5 font-mono text-[0.625rem] uppercase tracking-[0.2em] text-white/90 backdrop-blur transition-colors hover:border-white/60';
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

  container.append(button);
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

/** Im Hintergrund-Tab oder außerhalb des Bildes nicht weiterlaufen lassen. */
function addPauseOnHidden(video: HTMLVideoElement, calm: boolean): void {
  if (calm) return;

  const observer = new IntersectionObserver(([entry]) => {
    if (entry?.isIntersecting) {
      void video.play().catch(() => undefined);
    } else {
      video.pause();
    }
  });
  observer.observe(video);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      video.pause();
    } else {
      void video.play().catch(() => undefined);
    }
  });
}
