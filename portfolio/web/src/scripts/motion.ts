/**
 * Bewegung: weiches Scrollen (Lenis), Einblend-Effekte, Parallax und
 * magnetische Buttons (GSAP).
 *
 * Alles ist optional – bei `prefers-reduced-motion` bleibt die Seite statisch
 * und trotzdem vollständig bedienbar.
 */

import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const calm = (): boolean => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let lenis: Lenis | null = null;

export function getLenis(): Lenis | null {
  return lenis;
}

function initSmoothScroll(): void {
  if (calm()) return;

  lenis = new Lenis({
    duration: 1.05,
    easing: (t: number) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    smoothWheel: true,
    touchMultiplier: 1.6,
  });

  const raf = (time: number): void => {
    lenis?.raf(time);
    requestAnimationFrame(raf);
  };
  requestAnimationFrame(raf);

  lenis.on('scroll', ScrollTrigger.update);
  ScrollTrigger.refresh();
}

/** Blendet Elemente beim Hereinscrollen ein – gestaffelt innerhalb einer Gruppe. */
function initReveals(): void {
  const targets = document.querySelectorAll<HTMLElement>('[data-reveal], .line-mask');
  if (targets.length === 0) return;

  if (calm() || !('IntersectionObserver' in window)) {
    targets.forEach((element) => element.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const element = entry.target as HTMLElement;
        const delay = Number(element.dataset.revealDelay ?? 0);
        window.setTimeout(() => element.classList.add('is-visible'), delay);
        observer.unobserve(element);
      });
    },
    { rootMargin: '0px 0px -12% 0px', threshold: 0.15 },
  );

  targets.forEach((element) => observer.observe(element));
}

/** Sanfter Tiefeneffekt: `data-parallax="0.2"` = 20 % der Scrollstrecke. */
function initParallax(): void {
  if (calm()) return;

  document.querySelectorAll<HTMLElement>('[data-parallax]').forEach((element) => {
    const strength = Number(element.dataset.parallax ?? 0.15);
    gsap.to(element, {
      yPercent: strength * 100,
      ease: 'none',
      scrollTrigger: {
        trigger: element.dataset.parallaxTrigger
          ? (element.closest(element.dataset.parallaxTrigger) as Element | null) ?? element
          : element,
        start: 'top bottom',
        end: 'bottom top',
        scrub: true,
      },
    });
  });
}

/** Buttons, die den Cursor „anziehen". */
function initMagnetic(): void {
  if (calm() || !window.matchMedia('(pointer: fine)').matches) return;

  document.querySelectorAll<HTMLElement>('[data-magnetic]').forEach((element) => {
    const strength = Number(element.dataset.magnetic ?? 0.35);
    const inner = element.querySelector<HTMLElement>('[data-magnetic-inner]') ?? element;

    element.addEventListener('pointermove', (event) => {
      const bounds = element.getBoundingClientRect();
      const x = (event.clientX - bounds.left - bounds.width / 2) * strength;
      const y = (event.clientY - bounds.top - bounds.height / 2) * strength;
      gsap.to(inner, { x, y, duration: 0.6, ease: 'power3.out' });
    });

    element.addEventListener('pointerleave', () => {
      gsap.to(inner, { x: 0, y: 0, duration: 0.8, ease: 'elastic.out(1, 0.4)' });
    });
  });
}

/** Kopfzeile verdichten + Fortschrittsbalken füllen. */
function initNavState(): void {
  const nav = document.querySelector<HTMLElement>('[data-nav]');
  const progress = document.querySelector<HTMLElement>('[data-scroll-progress]');

  const update = (): void => {
    const y = window.scrollY;
    if (nav) {
      const scrolled = y > 24;
      nav.classList.toggle('bg-bg/80', scrolled);
      nav.classList.toggle('backdrop-blur-xl', scrolled);
      nav.classList.toggle('border-b', scrolled);
      nav.classList.toggle('border-line', scrolled);
    }
    if (progress) {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      progress.style.transform = `scaleX(${max > 0 ? Math.min(1, y / max) : 0})`;
    }
  };

  update();
  window.addEventListener('scroll', update, { passive: true });
}

/** Anker-Links über Lenis laufen lassen, damit das Scrollen einheitlich wirkt. */
function initAnchors(): void {
  document.addEventListener('click', (event) => {
    const link = (event.target as HTMLElement | null)?.closest<HTMLAnchorElement>('a[href^="#"]');
    if (!link) return;

    const id = link.getAttribute('href');
    if (!id || id === '#') return;

    const target = document.querySelector(id);
    if (!target) return;

    event.preventDefault();
    if (lenis) {
      lenis.scrollTo(target as HTMLElement, { offset: -80 });
    } else {
      target.scrollIntoView({ behavior: calm() ? 'auto' : 'smooth', block: 'start' });
    }
  });
}

export function initMotion(): void {
  initSmoothScroll();
  initReveals();
  initParallax();
  initMagnetic();
  initNavState();
  initAnchors();

  // Nach dem Laden aller Bilder stimmen die Trigger-Positionen erst wirklich.
  window.addEventListener('load', () => ScrollTrigger.refresh());
}

/** Für nachgeladene Inhalte (z. B. Projektkarten) erneut aufrufbar. */
export function refreshMotion(): void {
  initReveals();
  initMagnetic();
  ScrollTrigger.refresh();
}
