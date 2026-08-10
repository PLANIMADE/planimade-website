/**
 * Videos auf der Website.
 *
 * Zwei Dinge, die vorher fehlten:
 *
 * 1. Ein Klick spielt ab. Vorher sprang stattdessen die Großansicht auf –
 *    wer auf ein Video klickt, erwartet aber, dass es losgeht.
 * 2. Das Standbild kommt aus dem ersten Drittel statt vom allerersten Bild.
 *    Renderings beginnen fast immer mit Schwarz; als Vorschau ist das eine
 *    leere Fläche. Ein eigenes Vorschaubild bräuchte einen Videoschnitt auf
 *    dem Server – den gibt es auf einem geteilten Webspace nicht. Also wird
 *    das Video selbst an die Stelle gespult und zeigt dort sein Bild. Beim
 *    Abspielen fängt es trotzdem von vorn an.
 */

/** Anteil der Laufzeit, an dem das Standbild genommen wird. */
const VORSCHAU_STELLE = 0.3;

function playIcon(): string {
  return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="h-7 w-7 translate-x-0.5"><path d="M8 5.5v13l11-6.5z"/></svg>';
}

/** Rahmen um ein Video – Markup für die Stellen, die Videos ausgeben. */
export function videoMarkup(url: string, mime: string, extraClass = ''): string {
  return `
    <div class="video-frame ${extraClass}" data-video>
      <video
        class="h-full w-full object-cover"
        playsinline
        preload="metadata"
        data-video-el
      ><source src="${url}" type="${mime}"></video>
      <button type="button" class="video-play" data-video-play data-cursor="hover" aria-label="Video abspielen">
        ${playIcon()}
      </button>
    </div>`;
}

function wire(frame: HTMLElement): void {
  const video = frame.querySelector<HTMLVideoElement>('[data-video-el]');
  const button = frame.querySelector<HTMLButtonElement>('[data-video-play]');
  if (!video || !button) return;

  let gestartet = false;
  let vorschauGesetzt = false;

  /** Das Video an die Stelle spulen, die als Standbild taugt. */
  const zeigeVorschau = (): void => {
    if (vorschauGesetzt || !Number.isFinite(video.duration) || video.duration <= 0) return;
    vorschauGesetzt = true;
    video.currentTime = Math.min(video.duration * VORSCHAU_STELLE, Math.max(0, video.duration - 0.1));
  };

  // Drei Anläufe, weil nicht jede Datei ihre Laufzeit sofort verrät: Ein
  // aufgezeichnetes WebM meldet bei `loadedmetadata` noch „unendlich" und
  // erst später die wahre Länge. Ohne `durationchange` bliebe es dann beim
  // schwarzen ersten Bild.
  video.addEventListener('loadedmetadata', zeigeVorschau);
  video.addEventListener('durationchange', zeigeVorschau);
  video.addEventListener('canplay', zeigeVorschau);
  if (video.readyState >= 1) zeigeVorschau();

  const abspielen = (): void => {
    // Beim ersten Mal zurück an den Anfang – gespult wurde ja nur für das
    // Standbild.
    if (!gestartet) {
      gestartet = true;
      video.currentTime = 0;
      // Erst jetzt die eigene Bedienleiste des Browsers: Vorher überdeckte
      // sie das Standbild mit einem grauen Balken. Und erst jetzt weg mit
      // der Abspieltaste – sie liegt über der ganzen Fläche und würde sonst
      // beim Pausieren die Bedienleiste verdecken.
      video.controls = true;
      frame.setAttribute('data-playing', '');
    }

    void video.play().catch(() => undefined);
  };

  button.addEventListener('click', (event) => {
    event.stopPropagation();
    abspielen();
  });

  // Nur der erste Klick gehört uns. Danach hat das Video seine eigene
  // Bedienleiste, und dort schaltet ein Klick ins Bild ohnehin zwischen
  // Abspielen und Pause um. Griffen beide zu, hoben sie sich gegenseitig
  // auf – das Video lief einfach weiter.
  frame.addEventListener('click', () => {
    if (!gestartet) abspielen();
  });

  video.addEventListener('ended', () => {
    // Zurück auf Anfang: wieder Standbild und Abspieltaste, damit der
    // Ausgangszustand derselbe ist wie beim Laden der Seite.
    frame.removeAttribute('data-playing');
    video.controls = false;
    gestartet = false;
    vorschauGesetzt = false;
    zeigeVorschau();
  });
}

export function initVideos(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>('[data-video]').forEach((frame) => {
    if (frame.dataset.videoReady === '') return;
    frame.dataset.videoReady = '';
    wire(frame);
  });
}
