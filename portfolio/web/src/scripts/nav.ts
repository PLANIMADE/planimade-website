/** Mobiles Vollbild-Menü inklusive Fokus-Handling und Escape-Taste. */

export function initNav(): void {
  const toggle = document.querySelector<HTMLButtonElement>('[data-menu-toggle]');
  const panel = document.querySelector<HTMLElement>('[data-menu-panel]');
  if (!toggle || !panel) return;

  const bars = toggle.querySelectorAll<HTMLElement>('[data-menu-bar]');
  let open = false;

  const setOpen = (next: boolean): void => {
    open = next;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
    panel.classList.toggle('opacity-100', open);
    panel.classList.toggle('opacity-0', !open);
    panel.classList.toggle('pointer-events-auto', open);
    panel.classList.toggle('pointer-events-none', !open);
    document.body.style.overflow = open ? 'hidden' : '';

    // Burger wird zum Kreuz.
    bars.forEach((bar, index) => {
      bar.style.transform = open
        ? `translateY(${index === 0 ? '6px' : '-6px'}) rotate(${index === 0 ? 45 : -45}deg)`
        : '';
    });
  };

  toggle.addEventListener('click', () => setOpen(!open));
  panel.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && open) setOpen(false);
  });
  window.addEventListener('resize', () => {
    if (open && window.innerWidth >= 768) setOpen(false);
  });
}
