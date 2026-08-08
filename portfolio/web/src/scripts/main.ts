/**
 * Einstiegspunkt: startet die globalen Module und lädt seitenspezifische
 * Bausteine nur dann nach, wenn das passende Element im DOM steht.
 */

import { initTheme } from './theme';
import { initSound } from './sound';
import { initCursor } from './cursor';
import { initNav } from './nav';
import { initMotion } from './motion';
import { initPalette } from './palette';
import { initHydration } from './hydrate';
import { initEasterEgg } from './easter-egg';
import { fetchSettings, track } from '../lib/api';

async function boot(): Promise<void> {
  initTheme();
  initSound();
  initCursor();
  initNav();
  initMotion();
  initPalette();
  initEasterEgg();

  void initHydration();

  await initHeroSection();

  if (document.querySelector('[data-work-grid]')) {
    const { initWorkGrid } = await import('./work-grid');
    void initWorkGrid();
  }

  if (document.querySelector('[data-project-root]')) {
    const { initProjectDetail } = await import('./project-detail');
    void initProjectDetail();
  }

  if (document.querySelector('[data-contact-form]')) {
    const { initContactForm } = await import('./contact-form');
    initContactForm();
  }

  if (document.querySelector('[data-cv]')) {
    const { initCv } = await import('./cv');
    void initCv();
  }

  if (document.querySelector('[data-resume]')) {
    const { initResume } = await import('./resume');
    void initResume();
  }

  // Seitenaufruf zählen – ohne Cookie, ohne Drittanbieter.
  track('pageview');
}

/**
 * Kopfbereich: Showreel oder WebGL-Typografie.
 *
 * Die Entscheidung fällt anhand der Einstellung aus dem Dashboard. Deshalb
 * wird hier kurz auf die Einstellungen gewartet, statt den WebGL-Hintergrund
 * zu starten und ihn gleich darauf wieder wegzuwerfen.
 */
async function initHeroSection(): Promise<void> {
  if (!document.querySelector('[data-hero-canvas]')) return;

  const settings = await fetchSettings().catch(() => null);

  if (settings?.hero?.mode === 'showreel' && settings.hero.video) {
    const { initHeroShowreel } = await import('./hero-showreel');
    if (initHeroShowreel(settings)) return;
  }

  const { initHero } = await import('./hero');
  initHero();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => void boot());
} else {
  void boot();
}
