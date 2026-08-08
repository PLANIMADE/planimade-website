/** Hell/Dunkel-Umschalter. Die Wahl bleibt im localStorage erhalten. */

const STORAGE_KEY = 'dm-theme';

export type Theme = 'dark' | 'light';

export function currentTheme(): Theme {
  return document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
}

export function setTheme(theme: Theme): void {
  document.documentElement.dataset.theme = theme;
  try {
    localStorage.setItem(STORAGE_KEY, theme);
  } catch {
    /* Privater Modus: dann gilt die Wahl eben nur für diese Sitzung. */
  }
  syncIcon();
  window.dispatchEvent(new CustomEvent('dm:theme', { detail: theme }));
}

export function toggleTheme(): void {
  setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

/** Sonne im hellen Modus, Mondsichel im dunklen. */
function syncIcon(): void {
  const isLight = currentTheme() === 'light';
  document.querySelectorAll<SVGElement>('[data-theme-sun]').forEach((sun) => {
    sun.style.display = isLight ? '' : 'none';
  });
  document.querySelectorAll<SVGElement>('[data-theme-moon]').forEach((moon) => {
    moon.style.display = isLight ? 'none' : '';
  });
}

export function initTheme(): void {
  syncIcon();
  document.querySelectorAll<HTMLButtonElement>('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => toggleTheme());
  });
}
