// PLANIGAMES – feste Tailwind-Datei statt Tailwind-CDN (Website v7, Phase 5)
// Erzeugt planigames/assets/tailwind.css. Nur neu bauen, wenn im Code neue Tailwind-Klassen dazukommen:
//   npx tailwindcss@3.4.17 -c design/website-redesign/tailwind/tailwind.config.js \
//       -i design/website-redesign/tailwind/input.css -o planigames/assets/tailwind.css --minify
// (aus dem Repo-Hauptordner ausführen). Gleiche Einstellungen wie früher im <script>tailwind.config …</script> der Seiten.
const path = require('path');
const site = path.join(__dirname, '../../../planigames');
module.exports = {
  content: [
    path.join(site, '*.html'),
    path.join(site, '*.php'),
    path.join(site, 'assets/*.js'),
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Montserrat', 'system-ui', 'sans-serif'],
        mono: ['Montserrat', 'system-ui', 'sans-serif'],
        display: ['Montserrat', 'system-ui', 'sans-serif'],
      },
      // Design v7: ruhige Überschriften statt Riesenschrift
      fontSize: {
        '3xl': ['1.75rem', { lineHeight: '2.15rem' }],
        '4xl': ['2rem', { lineHeight: '2.4rem' }],
        '5xl': ['2.375rem', { lineHeight: '1.15' }],
        '6xl': ['2.75rem', { lineHeight: '1.1' }],
        '7xl': ['3rem', { lineHeight: '1.08' }],
        '8xl': ['3.5rem', { lineHeight: '1.05' }],
        '9xl': ['3.75rem', { lineHeight: '1.05' }],
      },
    },
  },
};
