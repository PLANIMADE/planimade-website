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
import { track } from '../lib/api';

async function boot(): Promise<void> {
  initTheme();
  initSound();
  initCursor();
  initNav();
  initMotion();
  initPalette();
  initEasterEgg();

  void initHydration();

  if (document.querySelector('[data-hero-canvas]')) {
    const { initHero } = await import('./hero');
    initHero();
  }

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

  // Seitenaufruf zählen – ohne Cookie, ohne Drittanbieter.
  track('pageview');
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => void boot());
} else {
  void boot();
}
