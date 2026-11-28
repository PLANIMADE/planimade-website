/* =============================================================
   PLANIGAMES — app.js
   Ein gemeinsames Skript für alle Seiten. Es kümmert sich um:
   - die "award-winning" Shell (Preloader, Cursor, Reveals, Header …)
   - das Laden der CMS-Daten aus /data/*.json
   - das Rendern von Studio-Home, Games-Liste, Game-Detail (Block-
     Baukasten) und Devlog/Patch Notes.
   Welcher Renderer läuft, entscheidet  <body data-page="…">.
   ============================================================= */

(() => {
    "use strict";

    const VERSION = "55";   // muss zur ?v=… in den HTML-Dateien passen
    try { console.log("%cPLANIGAMES app.js v" + VERSION + " geladen", "color:#ff7d1a;font-weight:700"); } catch (e) {}

    // Aufräumen: der frühere Frontend-Service-Worker (Homescreen-Installation) ist entfernt –
    // ggf. noch registrierte Root-SW abmelden (der Admin-SW unter /admin/ bleibt unangetastet).
    try {
        if ("serviceWorker" in navigator && navigator.serviceWorker.getRegistrations) {
            navigator.serviceWorker.getRegistrations().then((regs) => {
                regs.forEach((r) => { if (r.scope && !/\/admin\//.test(r.scope)) r.unregister(); });
            }).catch(() => {});
        }
    } catch (e) {}

    /* ---------- kleine Helfer ---------- */
    const $  = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const esc = (s = "") => String(s).replace(/[&<>"']/g, c => (
        { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]
    ));
    const slugify = (s = "") => String(s).toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
    // Fokuspunkt aus dem Pfad-Fragment (#focus=x,y) → object-position-Wert
    const focusPos = (path) => {
        const m = String(path || "").match(/#focus=(\d+),(\d+)/);
        return m ? `${m[1]}% ${m[2]}%` : "center";
    };

    async function fetchJSON(path) {
        try {
            const r = await fetch(path, { cache: "no-store" });
            if (!r.ok) throw new Error(r.status);
            return await r.json();
        } catch (e) {
            console.warn("PLANIGAMES: konnte", path, "nicht laden –", e.message);
            return null;
        }
    }

    // Markdown → HTML (nutzt marked, falls eingebunden; sonst Mini-Fallback)
    function md(src = "") {
        if (window.marked) return window.marked.parse(src);
        return src.split(/\n{2,}/).map(p => `<p>${esc(p).replace(/\n/g, "<br>")}</p>`).join("");
    }

    function fmtDate(iso) {
        if (!iso) return "";
        try {
            return new Date(iso).toLocaleDateString(LOCALE(),
                { day: "2-digit", month: "long", year: "numeric" });
        } catch { return iso; }
    }

    // Emoji-Reaktionen (flat-file, ohne Login)
    const REACT_EMOJI = ["👍", "🔥", "💜", "😂", "🎉"];
    function reactionsBar() {
        return `<div class="react-bar" data-react>
            <span class="react-label">${LANG === "en" ? "Liked it?" : "Gefällt dir?"}</span>
            ${REACT_EMOJI.map(em => `<button type="button" class="react-btn" data-emoji="${em}"><span class="re-emo">${em}</span><span class="re-count" data-count-for="${em}">0</span></button>`).join("")}
        </div>`;
    }
    async function initReactions(slug) {
        const bar = $("[data-react]");
        if (!bar || !slug) return;
        const key = "pg_react_" + slug;
        let mine = null; try { mine = localStorage.getItem(key) || null; } catch {}
        const setCounts = (c) => REACT_EMOJI.forEach(em => { const el = bar.querySelector(`[data-count-for="${em}"]`); if (el) el.textContent = (c && c[em]) || 0; });
        const markMine = () => bar.querySelectorAll(".react-btn").forEach(b => b.classList.toggle("mine", b.dataset.emoji === mine));
        try { const r = await fetch("reactions.php?slug=" + encodeURIComponent(slug)); setCounts(await r.json()); } catch {}
        markMine();
        bar.addEventListener("click", async (e) => {
            const btn = e.target.closest(".react-btn"); if (!btn) return;
            const em = btn.dataset.emoji; let add = "", remove = "";
            if (mine === em) { remove = em; mine = null; }
            else { add = em; if (mine) remove = mine; mine = em; }
            try { localStorage.setItem(key, mine || ""); } catch {}
            markMine();
            if (mine) pgBadge("reactor");
            // optimistisches Feedback
            btn.classList.add("pop"); setTimeout(() => btn.classList.remove("pop"), 300);
            const fd = new FormData(); fd.append("slug", slug); fd.append("add", add); fd.append("remove", remove);
            try { const r = await fetch("reactions.php", { method: "POST", body: fd }); setCounts(await r.json()); } catch {}
        });
    }

    // Lesezeit aus dem Text schätzen (~200 Wörter/Min)
    function readingTime(text) {
        const words = String(text || "").trim().split(/\s+/).filter(Boolean).length;
        return Math.max(1, Math.round(words / 200));
    }
    // Teilen-Leiste (X, Bluesky, Reddit, Link kopieren)
    function shareBar(title) {
        const url = location.href;
        const u = encodeURIComponent(url), tt = encodeURIComponent(title);
        const ico = (label, href, svg) => `<a href="${href}" target="_blank" rel="noopener" aria-label="${label}" title="${label}" class="share-btn">${svg}</a>`;
        return `<div class="flex items-center gap-2 flex-wrap">
            <span class="font-mono text-[10px] uppercase tracking-widest text-zinc-500 mr-0.5">${t("share")}</span>
            ${ico("X / Twitter", `https://twitter.com/intent/tweet?text=${tt}&url=${u}`, "𝕏")}
            ${ico("Bluesky", `https://bsky.app/intent/compose?text=${tt}%20${u}`, "🦋")}
            ${ico("Reddit", `https://www.reddit.com/submit?url=${u}&title=${tt}`, "👽")}
            <button type="button" class="share-btn" data-share-copy aria-label="${t("copy_link")}" title="${t("copy_link")}">🔗</button>
        </div>`;
    }

    // Ist ein Devlog-Beitrag öffentlich sichtbar? (kein Entwurf, geplanter Termin erreicht)
    function isPublished(p) {
        if (!p) return false;
        if (p.draft === true || p.draft === "true" || p.draft === 1 || p.draft === "1") return false;
        if (p.publishAt) { const t = Date.parse(p.publishAt); if (!isNaN(t) && t > Date.now()) return false; }
        return true;
    }

    const qs = new URLSearchParams(location.search);
    // In einem Rahmen (Admin-Vorschau / Logo-Platzierung) zählen Aufrufe nicht in die Statistik.
    const IN_FRAME = (() => { try { return window.self !== window.top; } catch (e) { return true; } })();
    const PLACER = IN_FRAME && qs.has("_placer");   // Logo-Platzierung im Admin: nur Kopfbereich, ohne Extras

    /* =========================================================
       0) SPRACHE (i18n) — DE / EN
       ========================================================= */
    const I18N = {
        de: {
            nav_studio: "Studio", nav_games: "Spiele", nav_devlog: "Devlog", nav_contact: "Kontakt",
            cta_discover: "Spiele entdecken", cta_explore: "Spiele entdecken →", cta_devlog: "Devlog lesen",
            foot_discover: "Entdecken", foot_allgames: "Alle Spiele", foot_devlog: "Devlog & Patch Notes",
            foot_about: "Über das Studio", foot_contact: "Kontakt & Presse", foot_newsletter_btn: "Abonnieren",
            foot_newsletter_note: "Kein Spam. Nur News zu neuen Spielen & großen Patches.",
            foot_rights: "Alle Rechte vorbehalten.", foot_made: "Made with 🧡 in Germany",
            foot_impressum: "Impressum", foot_datenschutz: "Datenschutz",
            studio_since: "Indie Game Studio · seit",
            st_released: "Veröffentlicht", st_early: "Early Access", st_demo: "Demo verfügbar",
            st_development: "In Entwicklung", st_announced: "Angekündigt",
            current_project: "Aktuelles Projekt", more_info: "Mehr erfahren", to_game: "Zum Spiel →",
            wishlist: "Auf Steam wishlisten", read_more: "Weiterlesen →", all: "Alle",
            all_posts: "Alle Beiträge", all_games_arrow: "Alle →", back_all: "← Alle Beiträge",
            patchnotes: "Patch Notes", ourgames: "Unsere Spiele", team_heading: "Die Köpfe dahinter",
            devlog_fresh: "Frisch aus dem Devlog", about_kicker: "Über das Studio",
            games_title: "Unsere Spiele", games_sub: "Jede Welt mit eigenem Charakter, eigener Farbe, eigener Magie. Klick dich rein.",
            games_kicker: "Unsere Titel",
            devlog_title: "Devlog & Patch Notes", devlog_kicker: "Entwicklungstagebuch",
            devlog_sub: "Updates, Patchnotes und Blicke hinter die Kulissen. Frisch aus der Werkstatt.",
            nf_text: "Das gibt es hier leider nicht.", nf_home: "Zur Startseite",
             msg_no_games: "Noch keine Spiele angelegt.", msg_no_posts: "Noch keine Einträge.",
            nl_loading: "Lädt…", current_project_tag: "Aktuelles Projekt",
            cookie_settings: "Cookie-Einstellungen", yt_consent: "Zum Abspielen lädt YouTube externe Inhalte von Google.",
            yt_load: "Trailer laden", yt_load_always: "Immer laden",
            presskit: "Press Kit", press_contact: "Presse-Kontakt", press_facts: "Fact Sheet",
            press_downloads: "Downloads", press_games: "Unsere Spiele", download: "Herunterladen",
            c_name: "Name", c_email: "E-Mail", c_subject: "Betreff", c_message: "Deine Nachricht",
            c_name_ph: "Wie heißt du?", c_email_ph: "deine@mail.de", c_subject_ph: "Worum geht's?",
            c_message_ph: "Schreib uns …", c_send: "Nachricht senden", c_or_mail: "Oder schreib direkt an",
            read_time: "Min Lesezeit", share: "Teilen", link_copied: "Link kopiert ✓", copy_link: "Link kopieren",
            cta_playtest: "Zum Playtest", foot_games: "Spiele", foot_studio: "Studio", foot_contact_h: "Kontakt",
            foot_form: "Kontaktformular", foot_about_short: "Über uns", support: "Unterstützen",
            nl_kicker: "Newsletter", nl_email_label: "E-Mail-Adresse", follow_h: "Folge uns",
            follow_sub: "Clips, Devlogs und Abstimmungen – direkt aus der Werkstatt.",
            soc_youtube: "Trailer, Gameplay & Devlogs", soc_tiktok: "Kurze Clips aus der Werkstatt",
            soc_discord: "Community, Playtests & Abstimmungen", soc_instagram: "Bilder & Einblicke",
            soc_x: "News & Updates", soc_steam: "Wunschliste & News", soc_twitch: "Live aus der Entwicklung",
            soc_bsky: "News & Updates", menu_open: "Menü öffnen", menu_close: "Menü schließen",
            home_label: "Startseite", lang_label: "Sprache",
            st_paused: "Pausiert", hs_label: "Unsere Spiele", hs_pause: "Durchlauf anhalten", hs_play: "Durchlauf fortsetzen",
            hs_show: "zeigen", hs_of: "von", to_game2: "Zum Spiel", trailer_watch: "Trailer ansehen", cta_beta: "Zur Beta",
            worlds_h: ["Eine Welt. Eine Handschrift.", "Zwei Welten. Eine Handschrift.", "Drei Welten. Eine Handschrift.", "Vier Welten. Eine Handschrift.", "Fünf Welten. Eine Handschrift.", "Sechs Welten. Eine Handschrift."],
            worlds_n: "Welten. Eine Handschrift.", stat_founded: "gegründet", stat_team: "Köpfe im Team", stat_games: "eigene Spiele",
            team_label: "Das Team", devlog_fresh2: "Frisch aus der Werkstatt.", read_more2: "Weiterlesen", profile_view: "Profil ansehen",
            sec_about: "Über das Spiel", sn_about: "Über das Spiel", h_about_q: "Was ist {title}?", sec_play: "Selbst spielen", sn_play: "Spielen",
            h_play: "Jetzt spielen", sec_tech: "Technik", sec_media: "Einblicke", sn_media: "Einblicke", h_media_multi: "Trailer & Gameplay",
            h_media_one: "So sieht es aus", sec_features: "Features", sn_features: "Features", h_features: "Was dich erwartet",
            sec_quotes: "Stimmen", h_quotes: "Was andere sagen", sec_countdown: "Countdown", sec_roadmap: "Roadmap", sn_roadmap: "Roadmap",
            h_roadmap: "Stand der Entwicklung", lead_roadmap: "Was schon steht, woran wir gerade arbeiten und was als Nächstes kommt.",
            lead_roadmap_paused: "Was bisher geschafft ist – und was nach der Pause kommt.",
            paused_default: "{title} ist gerade pausiert – die nächsten Schritte bekommen wieder Termine, sobald es weitergeht.",
            to_post: "Zum Devlog-Beitrag", sec_join: "Mitmachen", sn_join: "Mitmachen", h_join: "Werde Teil der Entwicklung",
            lead_join: "Trag dich ein oder bring deine Ideen ein – wir lesen alles.", sn_faq: "FAQ", h_faq: "Häufige Fragen", sn_devlog: "Devlog",
            h_devlog_game: "Neues zu {title}", allposts_h: "Alle Beiträge im Devlog", allposts_p: "Updates, Patch Notes und Blicke hinter die Kulissen – zu allen unseren Spielen.",
            to_devlog: "Zum Devlog", sec_more: "Weitere Spiele", h_more: "Entdecke auch", facts_label: "Steckbrief", to_top: "nach oben",
            cta_notify: "Benachrichtigt werden", cta_wl_beta: "Zur Beta anmelden", cta_wl_playtest: "Für den Playtest eintragen", cta_wl: "Auf die Warteliste",
            cta_demo: "Demo spielen", wl_intro: "Trag dich ein – wir sagen dir Bescheid, sobald du spielen kannst.",
            wl_tick1: "Deinen Platz in der Warteliste siehst du sofort", wl_tick2: "Mit deinem Link Freunde einladen und aufsteigen",
        },
        en: {
            nav_studio: "Studio", nav_games: "Games", nav_devlog: "Devlog", nav_contact: "Contact",
            cta_discover: "Explore games", cta_explore: "Explore games →", cta_devlog: "Read the devlog",
            foot_discover: "Explore", foot_allgames: "All games", foot_devlog: "Devlog & patch notes",
            foot_about: "About the studio", foot_contact: "Contact & press", foot_newsletter_btn: "Subscribe",
            foot_newsletter_note: "No spam. Just news on new games & major patches.",
            foot_rights: "All rights reserved.", foot_made: "Made with 🧡 in Germany",
            foot_impressum: "Imprint", foot_datenschutz: "Privacy",
            studio_since: "Indie game studio · since",
            st_released: "Released", st_early: "Early Access", st_demo: "Demo available",
            st_development: "In development", st_announced: "Announced",
            current_project: "Current project", more_info: "Learn more", to_game: "View game →",
            wishlist: "Wishlist on Steam", read_more: "Read more →", all: "All",
            all_posts: "All posts", all_games_arrow: "All →", back_all: "← All posts",
            patchnotes: "Patch notes", ourgames: "Our games", team_heading: "The people behind it",
            devlog_fresh: "Fresh from the devlog", about_kicker: "About the studio",
            games_title: "Our games", games_sub: "Each world with its own character, colour and magic. Dive in.",
            games_kicker: "Our titles",
            devlog_title: "Devlog & patch notes", devlog_kicker: "Development diary",
            devlog_sub: "Updates, patch notes and behind-the-scenes looks. Fresh from the workshop.",
            nf_text: "Sorry, this doesn't exist.", nf_home: "Back to home",
            msg_no_games: "No games yet.", msg_no_posts: "No entries yet.",
            nl_loading: "Loading…", current_project_tag: "Current project",
            cookie_settings: "Cookie settings", yt_consent: "Playing this loads external content from YouTube/Google.",
            yt_load: "Load trailer", yt_load_always: "Always load",
            presskit: "Press Kit", press_contact: "Press contact", press_facts: "Fact sheet",
            press_downloads: "Downloads", press_games: "Our games", download: "Download",
            c_name: "Name", c_email: "Email", c_subject: "Subject", c_message: "Your message",
            c_name_ph: "What's your name?", c_email_ph: "your@mail.com", c_subject_ph: "What's it about?",
            c_message_ph: "Write to us …", c_send: "Send message", c_or_mail: "Or email us directly at",
            read_time: "min read", share: "Share", link_copied: "Link copied ✓", copy_link: "Copy link",
            cta_playtest: "Join the playtest", foot_games: "Games", foot_studio: "Studio", foot_contact_h: "Contact",
            foot_form: "Contact form", foot_about_short: "About us", support: "Support us",
            nl_kicker: "Newsletter", nl_email_label: "Email address", follow_h: "Follow us",
            follow_sub: "Clips, devlogs and polls – straight from the workshop.",
            soc_youtube: "Trailers, gameplay & devlogs", soc_tiktok: "Short clips from the workshop",
            soc_discord: "Community, playtests & polls", soc_instagram: "Pictures & insights",
            soc_x: "News & updates", soc_steam: "Wishlist & news", soc_twitch: "Live from development",
            soc_bsky: "News & updates", menu_open: "Open menu", menu_close: "Close menu",
            home_label: "Home", lang_label: "Language",
            st_paused: "Paused", hs_label: "Our games", hs_pause: "Pause slideshow", hs_play: "Resume slideshow",
            hs_show: "show", hs_of: "of", to_game2: "View game", trailer_watch: "Watch trailer", cta_beta: "Join the beta",
            worlds_h: ["One world. One signature.", "Two worlds. One signature.", "Three worlds. One signature.", "Four worlds. One signature.", "Five worlds. One signature.", "Six worlds. One signature."],
            worlds_n: "worlds. One signature.", stat_founded: "founded", stat_team: "people on the team", stat_games: "original games",
            team_label: "The team", devlog_fresh2: "Fresh from the workshop.", read_more2: "Read more", profile_view: "View profile",
            sec_about: "About the game", sn_about: "About", h_about_q: "What is {title}?", sec_play: "Play it yourself", sn_play: "Play",
            h_play: "Play now", sec_tech: "Tech", sec_media: "Insights", sn_media: "Media", h_media_multi: "Trailer & gameplay",
            h_media_one: "A first look", sec_features: "Features", sn_features: "Features", h_features: "What to expect",
            sec_quotes: "Voices", h_quotes: "What others say", sec_countdown: "Countdown", sec_roadmap: "Roadmap", sn_roadmap: "Roadmap",
            h_roadmap: "Development status", lead_roadmap: "What's done, what we're working on and what comes next.",
            lead_roadmap_paused: "What's done so far – and what comes after the break.",
            paused_default: "{title} is currently paused – the next steps will get dates again once development resumes.",
            to_post: "Read the devlog post", sec_join: "Get involved", sn_join: "Get involved", h_join: "Be part of the development",
            lead_join: "Sign up or share your ideas – we read everything.", sn_faq: "FAQ", h_faq: "Frequently asked questions", sn_devlog: "Devlog",
            h_devlog_game: "News about {title}", allposts_h: "All devlog posts", allposts_p: "Updates, patch notes and behind-the-scenes looks – for all our games.",
            to_devlog: "Go to the devlog", sec_more: "More games", h_more: "Discover more", facts_label: "Fact sheet", to_top: "back to top",
            cta_notify: "Get notified", cta_wl_beta: "Join the beta", cta_wl_playtest: "Sign up for the playtest", cta_wl: "Join the waitlist",
            cta_demo: "Play the demo", wl_intro: "Sign up – we'll let you know as soon as you can play.",
            wl_tick1: "See your spot on the waitlist right away", wl_tick2: "Invite friends with your link and move up",
        },
    };
    function detectLang() {
        const q = qs.get("lang");
        if (q === "en" || q === "de") { try { localStorage.setItem("pg_lang", q); } catch {} return q; }
        try { const s = localStorage.getItem("pg_lang"); if (s === "en" || s === "de") return s; } catch {}
        return "de";
    }
    let LANG = detectLang();
    const t = (k) => (I18N[LANG] && I18N[LANG][k]) || I18N.de[k] || k;
    const LOCALE = () => (LANG === "en" ? "en-GB" : "de-DE");
    function setLang(l) {
        if (l !== "de" && l !== "en") return;
        try { localStorage.setItem("pg_lang", l); } catch {}
        const u = new URL(location.href); u.searchParams.delete("lang"); history.replaceState(null, "", u);
        LANG = l; location.reload();
    }
    function applyI18n(root = document) {
        $$("[data-i18n]", root).forEach(el => { el.textContent = t(el.dataset.i18n); });
        $$("[data-i18n-ph]", root).forEach(el => { el.placeholder = t(el.dataset.i18nPh); });
    }
    // Tiefen-Merge: englische TEXTE über die deutsche Basis legen.
    // - Deutsch ist die Vorlage: Aufbau (Reihenfolge, Blöcke, Ein/Aus, Bilder, Farben, Links, Zahlen) kommt IMMER aus Deutsch.
    // - Einträge mit Adresse (Spiele, Beiträge) werden über den Slug zugeordnet, Blöcke über Typ +
    //   Vorkommen – so passt EN auch dann noch, wenn auf Deutsch umsortiert oder ergänzt wurde.
    // - Jeder englische Text merkt sich in „_src“ einen Fingerabdruck seines deutschen Originals.
    //   Ist das Deutsche inzwischen anders (veraltet), wird der aktuelle deutsche Text gezeigt.
    // - Leeres Englisch (oder leeres Deutsch) → Deutsch.
    const SHARED_KEYS = new Set(["type", "slug", "hidden", "status", "align", "platform", "effect", "density", "game",
        "steamAppId", "itchEmbed", "youtube", "version", "publishAt", "draft", "notify", "featured", "enabled",
        "icon", "emoji", "email", "accent", "accent2", "particleColor", "logoScale", "logoLayout", "logoPos", "logoGap", "logoBox"]);
    const looksShared = (v) => /^(\/|https?:\/\/|www\.|#|data:|mailto:|tel:)/i.test(v)
        || /\.(png|jpe?g|webp|gif|svg|avif|mp4|webm|mov|zip|pdf)(\?[^#]*)?(#.*)?$/i.test(v)
        || /^\d{4}-\d{2}-\d{2}/.test(v);
    // Fingerabdruck (FNV-1a, 32 Bit) – identisch zu admin/lib.php pg_tr_hash()
    const trHash = (s) => {
        const b = new TextEncoder().encode(String(s).replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, ""));
        let h = 0x811c9dc5;
        for (const x of b) { h ^= x; h = Math.imul(h, 0x01000193) >>> 0; }
        return h.toString(16).padStart(8, "0");
    };
    function mergeLang(de, en, key, path = "", src = null) {
        const sub = (k) => (path === "" ? String(k) : path + "." + k);
        if (en === undefined || en === null || en === "") return de;
        if (Array.isArray(de)) {
            if (!Array.isArray(en)) return de;
            const isObj = (x) => x && typeof x === "object" && !Array.isArray(x);
            if (de.some(d => isObj(d) && d.slug)) {                       // Spiele, Beiträge …
                const deSlugs = new Set(de.filter(isObj).map(d => String(d.slug || "").toLowerCase()));
                return de.map((d, i) => {
                    if (!isObj(d) || !d.slug) return mergeLang(d, en[i], key, sub(i), src);
                    const s = String(d.slug).toLowerCase();
                    let e = en.find(x => isObj(x) && String(x.slug || "").toLowerCase() === s);
                    // Altbestand: gleiche Position, wenn der EN-Eintrag zu keinem deutschen gehört (z. B. alte Adresse)
                    if (!e && isObj(en[i]) && (!en[i].slug || !deSlugs.has(String(en[i].slug).toLowerCase()))) e = en[i];
                    return mergeLang(d, e, key, sub("@" + d.slug), src);
                });
            }
            if (de.some(d => isObj(d) && d.type)) {                       // Seiten-Blöcke
                const byType = {}, seen = {};
                en.forEach(x => { if (isObj(x) && x.type) (byType[x.type] = byType[x.type] || []).push(x); });
                return de.map((d, i) => {
                    if (!isObj(d) || !d.type) return d;
                    const k = seen[d.type] = (seen[d.type] || 0) + 1;
                    return mergeLang(d, (byType[d.type] || [])[k - 1], key, sub(d.type + "#" + k), src);
                });
            }
            return de.map((d, i) => mergeLang(d, en[i], key, sub(i), src));
        }
        if (de && typeof de === "object") {
            if (typeof en !== "object" || Array.isArray(en)) return de;
            const out = { ...de };
            for (const k of Object.keys(de)) out[k] = SHARED_KEYS.has(k) ? de[k] : mergeLang(de[k], en[k], k, sub(k), src);
            return out;
        }
        if (typeof de === "number" || typeof de === "boolean") return de;   // Zahlen & Schalter: immer DE
        if (typeof en !== "string") return de;
        if (typeof de === "string" && !/\p{L}/u.test(de)) return de;      // leer oder ohne Buchstaben (z. B. „2025“) → wie Deutsch
        if (looksShared(en) || (typeof de === "string" && looksShared(de))) return de;   // Bilder, Dateien, Links, Farben, Datum
        if (src && typeof src[path] === "string" && src[path] !== trHash(de)) return de;   // Übersetzung veraltet → aktuelles Deutsch
        return en;
    }

    /* =========================================================
       1) SHELL — globale Effekte
       ========================================================= */
    function initShell() {
        initCursor();
        initScrollProgress();
        initHeader();
        initMobileMenu();
        initReveal();
        initMagnetic();
        initTilt();
        initYear();
        initLangSwitch();
        initBackgroundFX();
    }

    /* Schwebende Magie-/Glut-Partikel im Hintergrund.
       Reine CSS-Animation: jeder Partikel ist ein <span>, das per @keyframes
       aufsteigt. Das läuft zuverlässig in jedem Browser und kann – anders als
       die alte Canvas-Lösung – nicht durch "Bewegung reduzieren" einfrieren.
       Dichte / Tempo / Farbe bleiben im Admin konfigurierbar. */
    function initBackgroundFX() {
        const layer = $("#fx-layer");
        if (!layer) return;
        if (PLACER) { layer.innerHTML = ""; return; }
        const cfg = (DATA.studio && DATA.studio.background) || {};
        const eff = cfg.effect || "particles";
        if (eff !== "particles" && eff !== "particles_only") { layer.innerHTML = ""; return; }  // nur Glow / aus -> keine Partikel

        const color = (cfg.color || "#ff7d1a");
        // Anzahl je nach Dichte – an die Fensterbreite gekoppelt (Handy = weniger)
        const base = { low: 16, med: 30, high: 50 }[cfg.density] || 30;
        const count = Math.max(10, Math.round(base * Math.min(1.4, Math.max(0.5, window.innerWidth / 1280))));
        // Tempo: Dauer einer Aufstiegsrunde in Sekunden (calm = langsam)
        const span = { calm: [22, 38], normal: [14, 26], lively: [8, 16] }[cfg.speed] || [14, 26];

        const rnd = (a, b) => a + Math.random() * (b - a);
        let html = "";
        for (let i = 0; i < count; i++) {
            const size = rnd(3, 9);                     // px
            const left = rnd(0, 100);                   // %
            const dur = rnd(span[0], span[1]);          // s
            const delay = -rnd(0, dur);                 // sofort über den Schirm verteilt
            const drift = rnd(-60, 60);                 // px seitliche Drift
            const op = rnd(0.25, 0.7).toFixed(2);
            html += `<span class="fx-p" style="left:${left.toFixed(2)}%;width:${size.toFixed(1)}px;height:${size.toFixed(1)}px;`
                  + `--dur:${dur.toFixed(1)}s;--delay:${delay.toFixed(1)}s;--dx:${drift.toFixed(0)}px;--o:${op}"></span>`;
        }
        // Farbe zentral am Layer setzen, damit Game-Seiten sie per --fx-c überschreiben können
        layer.style.setProperty("--fx-c", color);
        layer.innerHTML = html;
    }
    /* Partikelfarbe live umfärben (Game-/Lore-Seiten). Leerer Wert = Studio-Standard behalten. */
    function setParticleColor(color) {
        const layer = $("#fx-layer");
        if (layer && color) layer.style.setProperty("--fx-c", color);
    }

    /* ---------- Cookie-/Consent-Banner ---------- */
    // Consent V2: kategorienbasiert. Speicherung als JSON-Objekt
    // (abwärtskompatibel zu den alten Werten "all"/"essential").
    function pgConsentGet() {
        let raw = null; try { raw = localStorage.getItem("pg_consent"); } catch {}
        if (!raw) return null;                         // noch nicht entschieden
        if (raw === "all") return { externalMedia: true };
        if (raw === "essential") return { externalMedia: false };
        try { return JSON.parse(raw); } catch { return { externalMedia: false }; }
    }
    function pgConsentExternal() { const c = pgConsentGet(); return !!(c && c.externalMedia); }
    function saveConsent(consent) {
        try { localStorage.setItem("pg_consent", JSON.stringify(consent)); } catch {}
        const banner = $("#cookie-banner");
        if (banner) banner.remove();
        if (consent.externalMedia) $$("[data-yt-embed]").forEach(loadYouTube);
    }
    function loadYouTube(holder) {
        const id = holder.getAttribute("data-yt-embed");
        if (!id) return;
        holder.innerHTML = `<iframe class="absolute inset-0 w-full h-full" src="https://www.youtube-nocookie.com/embed/${esc(id)}?autoplay=1" title="Trailer" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
    }
    function initCookieBanner(openSettings) {
        const cfg = (DATA.studio && DATA.studio.cookie) || {};
        if (cfg.enabled === false) return;
        if (!openSettings && pgConsentGet()) return;   // bereits entschieden
        const cur = pgConsentGet() || {};
        const msg = cfg.message || "Wir nutzen nur, was die Seite zum Laufen braucht. Für eingebettete YouTube-Trailer brauchen wir deine Zustimmung.";
        const acc = cfg.acceptLabel || "Alle akzeptieren";
        const dec = cfg.declineLabel || "Nur Notwendige";
        $("#cookie-banner")?.remove();
        const el = document.createElement("div");
        el.id = "cookie-banner";
        el.setAttribute("role", "dialog");
        el.setAttribute("aria-label", "Cookie-Hinweis");
        el.innerHTML = `
            <p class="cb-text">${esc(msg)} <a href="rechtliches.html?doc=datenschutz">${esc(t("foot_datenschutz"))}</a></p>
            <div class="cb-settings"${openSettings ? "" : " hidden"}>
                <label class="cb-cat"><span><b>${LANG === "en" ? "Necessary" : "Notwendig"}</b><span class="cb-sub">${LANG === "en" ? "Always on – required for the site to work." : "Immer aktiv – für den Betrieb der Seite."}</span></span><input type="checkbox" checked disabled></label>
                <label class="cb-cat"><span><b>${LANG === "en" ? "External media" : "Externe Medien"}</b><span class="cb-sub">${LANG === "en" ? "YouTube trailers (loads content from Google)." : "YouTube-Trailer (lädt Inhalte von Google)."}</span></span><input type="checkbox" data-cat="externalMedia"${cur.externalMedia ? " checked" : ""}></label>
                <button class="cb-accept cb-save" data-consent="save">${LANG === "en" ? "Save selection" : "Auswahl speichern"}</button>
            </div>
            <div class="cb-actions">
                <button class="cb-settings-toggle" data-cb-settings>${esc(t("cookie_settings"))}</button>
                <button class="cb-decline" data-consent="essential">${esc(dec)}</button>
                <button class="cb-accept" data-consent="all">${esc(acc)}</button>
            </div>`;
        document.body.appendChild(el);
        el.addEventListener("click", (e) => {
            if (e.target.closest("[data-cb-settings]")) { e.preventDefault(); el.querySelector(".cb-settings").hidden = !el.querySelector(".cb-settings").hidden; return; }
            const b = e.target.closest("[data-consent]");
            if (!b) return;
            if (b.dataset.consent === "save") saveConsent({ externalMedia: !!el.querySelector('[data-cat="externalMedia"]')?.checked });
            else saveConsent({ externalMedia: b.dataset.consent === "all" });
        });
        requestAnimationFrame(() => el.classList.add("in"));
    }
    // Footer-Link „Cookie-Einstellungen" öffnet das Banner mit offenen Einstellungen
    function reopenCookieBanner() { initCookieBanner(true); }

    function initLangSwitch() {
        document.addEventListener("click", (e) => {
            const b = e.target.closest("[data-setlang]");
            if (b) { e.preventDefault(); setLang(b.dataset.setlang); return; }
            const link = e.target.closest("[data-cookie-open]");
            if (link) { e.preventDefault(); reopenCookieBanner(); return; }
            // Trailer-Platzhalter: nur diesen einen laden
            const one = e.target.closest("[data-yt-load]");
            if (one) {
                e.preventDefault();
                const holder = one.closest("[data-yt-embed]");
                if (holder) loadYouTube(holder);
                return;
            }
            // „Immer laden" innerhalb eines Trailers = Zustimmung für alle
            const all = e.target.closest(".yt-consent [data-consent='all']");
            if (all) { e.preventDefault(); saveConsent({ externalMedia: true }); }
            // Spielbare Web-Demo erst nach Klick laden (DSGVO)
            const dl = e.target.closest("[data-embed-load]");
            if (dl) {
                e.preventDefault();
                const holder = dl.closest("[data-embed-holder]");
                if (holder) loadEmbed(holder);
            }
        });
    }

    function loadEmbed(holder) {
        const src = holder.getAttribute("data-embed-src");
        if (!src) return;
        holder.innerHTML = `<iframe class="absolute inset-0 w-full h-full" src="${esc(src)}" title="Web-Demo" allow="autoplay; fullscreen; gamepad; clipboard-write" allowfullscreen></iframe>`;
    }

    function initCursor() {
        if (!matchMedia("(pointer: fine)").matches) return;
        const dot = $("#cursor-dot"), ring = $("#cursor-ring");
        if (!dot || !ring) return;
        let mx = innerWidth / 2, my = innerHeight / 2, rx = mx, ry = my;
        addEventListener("mousemove", e => {
            mx = e.clientX; my = e.clientY;
            dot.style.transform = `translate3d(${mx}px,${my}px,0) translate(-50%,-50%)`;
            document.documentElement.style.setProperty("--x", mx + "px");
            document.documentElement.style.setProperty("--y", my + "px");
        });
        (function loop() {
            rx += (mx - rx) * 0.18; ry += (my - ry) * 0.18;
            ring.style.transform = `translate3d(${rx}px,${ry}px,0) translate(-50%,-50%)`;
            requestAnimationFrame(loop);
        })();
        const hot = "a, button, .cursor-hot, input, textarea, select, .tilt-card";
        document.addEventListener("mouseover", e => {
            if (e.target.closest(hot)) document.body.classList.add("cursor-active");
        });
        document.addEventListener("mouseout", e => {
            if (e.target.closest(hot)) document.body.classList.remove("cursor-active");
        });
    }

    // Magie-Funken am Mauszeiger (im Admin unter „Hintergrund & Atmosphäre" aktivierbar)
    function initCursorSparkles() {
        const bg = (DATA.studio && DATA.studio.background) || {};
        if (!bg.cursorSparkles) return;
        if (!matchMedia("(pointer: fine)").matches) return;   // nur echte Maus, kein Touch
        const layer = document.createElement("div");
        layer.className = "pg-spark-layer";
        document.body.appendChild(layer);
        let last = 0;
        addEventListener("mousemove", (e) => {
            const now = performance.now();
            if (now - last < 36) return;                       // drosseln
            last = now;
            spark(e.clientX, e.clientY);
        }, { passive: true });
        function spark(x, y) {
            const s = document.createElement("span");
            s.className = "pg-spark";
            const dx = (Math.random() - 0.5) * 30;
            const dy = 8 + Math.random() * 30;                 // fällt leicht wie Funken
            const isStar = Math.random() < 0.16;
            if (isStar) {
                s.classList.add("star"); s.textContent = "✨";
                s.style.cssText = `left:${x}px;top:${y}px;font-size:${9 + Math.random() * 9}px`;
            } else {
                const size = 4 + Math.random() * 7;
                s.style.cssText = `left:${x}px;top:${y}px;width:${size}px;height:${size}px`;
            }
            layer.appendChild(s);
            s.animate([
                { transform: "translate(-50%,-50%) scale(1) rotate(0deg)", opacity: .95 },
                { transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(.2) rotate(${(Math.random() - 0.5) * 140}deg)`, opacity: 0 }
            ], { duration: 620 + Math.random() * 320, easing: "cubic-bezier(.2,.6,.3,1)" }).onfinish = () => s.remove();
        }
    }

    function initScrollProgress() {
        const bar = $("#scroll-progress");
        if (!bar) return;
        const upd = () => {
            const h = document.documentElement;
            const max = h.scrollHeight - h.clientHeight;
            bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + "%";
        };
        addEventListener("scroll", upd, { passive: true }); upd();
    }

    function initHeader() {
        const h = $("#site-header");
        if (!h) return;
        const body = document.body;
        let lastY = scrollY;
        const kbFocus = () => { try { return h.contains(document.activeElement) && document.activeElement.matches(":focus-visible"); } catch { return false; } };
        const upd = () => {
            const y = scrollY;
            h.classList.toggle("scrolled", y > 20);
            if (PLACER || body.classList.contains("menu-open") || kbFocus() || y < 160) body.classList.remove("pg-hd-up");
            else if (y > lastY + 6) body.classList.add("pg-hd-up");
            else if (y < lastY - 6) body.classList.remove("pg-hd-up");
            lastY = y;
        };
        addEventListener("scroll", upd, { passive: true }); upd();
        h.addEventListener("focusin", () => body.classList.remove("pg-hd-up"));
    }

    function initMobileMenu() {
        const btn = $("#menu-toggle"), menu = $("#mobile-menu");
        if (!btn || !menu) return;
        const setOpen = (open) => {
            menu.classList.toggle("open", open);
            btn.classList.toggle("open", open);
            btn.setAttribute("aria-expanded", String(open));
            btn.setAttribute("aria-label", open ? t("menu_close") : t("menu_open"));
            menu.setAttribute("aria-hidden", String(!open));
            document.body.classList.toggle("menu-open", open);
        };
        btn.addEventListener("click", () => setOpen(!menu.classList.contains("open")));
        $$("a", menu).forEach(a => a.addEventListener("click", () => setOpen(false)));
        addEventListener("keydown", (e) => { if (e.key === "Escape") setOpen(false); });
    }

    function initReveal() {
        const els = $$(".reveal, .reveal-on, .pg-rv");
        if (!("IntersectionObserver" in window) || !els.length) {
            els.forEach(el => el.classList.add("in", "reveal-on")); return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (en.isIntersecting) {
                    en.target.classList.add("in", "reveal-on");
                    io.unobserve(en.target);
                }
            });
        }, { threshold: 0.12 });
        els.forEach(el => io.observe(el));
    }

    // Beobachtet später hinzugefügte (dynamisch gerenderte) Reveal-Elemente
    function observeReveals(root) {
        // WICHTIG: querySelectorAll findet nur Nachfahren – das root-Element selbst
        // (z. B. der Countdown-Mount mit class="reveal") muss separat berücksichtigt werden,
        // sonst bekommt es nie ".in" und bleibt unsichtbar (opacity:0).
        const els = $$(".reveal, .reveal-on, .pg-rv", root);
        if (root && root.classList && (root.classList.contains("reveal") || root.classList.contains("reveal-on") || root.classList.contains("pg-rv"))) {
            els.unshift(root);
        }
        if (!("IntersectionObserver" in window)) { els.forEach(el => el.classList.add("in", "reveal-on")); return; }
        els.forEach(el => {
            const io = new IntersectionObserver((entries, obs) => {
                entries.forEach(en => {
                    if (en.isIntersecting) { en.target.classList.add("in", "reveal-on"); obs.disconnect(); }
                });
            }, { threshold: 0.12 });
            io.observe(el);
        });
    }

    function initMagnetic() {
        if (!matchMedia("(pointer: fine)").matches) return;
        $$(".magnetic").forEach(el => {
            el.addEventListener("mousemove", e => {
                const r = el.getBoundingClientRect();
                el.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * 0.25}px, ${(e.clientY - r.top - r.height / 2) * 0.35}px)`;
            });
            el.addEventListener("mouseleave", () => el.style.transform = "translate(0,0)");
        });
    }

    function initTilt(root = document) {
        if (!matchMedia("(pointer: fine)").matches) return;
        $$(".tilt-card", root).forEach(card => {
            card.addEventListener("mousemove", e => {
                const r = card.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
                card.classList.add("tilting");
                card.style.transform = `perspective(1000px) rotateY(${(px - 0.5) * 7}deg) rotateX(${(0.5 - py) * 7}deg) translateZ(0)`;
                card.style.setProperty("--x", px * 100 + "%");
                card.style.setProperty("--y", py * 100 + "%");
            });
            card.addEventListener("mouseleave", () => {
                card.classList.remove("tilting");
                card.style.transform = "perspective(1000px) rotateY(0) rotateX(0)";
            });
        });
    }

    function initYear() { $$("[data-year]").forEach(el => el.textContent = new Date().getFullYear()); }

    /* Akzentfarbe live setzen (Game-Welten) */
    // Hex aufhellen (Anteil 0..1 Richtung Weiß) – für die abgeleitete helle Akzentfarbe
    function lightenHex(hex, amt) {
        const m = String(hex).trim().replace("#", "").match(/^([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (!m) return hex;
        let h = m[1]; if (h.length === 3) h = h.split("").map(c => c + c).join("");
        const ch = (i) => parseInt(h.substr(i, 2), 16);
        const up = (c) => Math.round(c + (255 - c) * amt).toString(16).padStart(2, "0");
        return "#" + up(ch(0)) + up(ch(2)) + up(ch(4));
    }
    // Wahrgenommene Helligkeit (YIQ, 0..255) – für adaptive Schriftfarbe
    function hexBrightness(hex) {
        const m = String(hex).trim().replace("#", "").match(/^([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (!m) return 0;
        let h = m[1]; if (h.length === 3) h = h.split("").map(c => c + c).join("");
        const r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
        return (r * 299 + g * 587 + b * 114) / 1000;
    }
    // Akzent-Variablen auf ein beliebiges Style-Ziel schreiben (Doc-Root ODER eine einzelne Card)
    function setAccentVars(st, hex, hex2) {
        const c1 = hex || "#ff7d1a";
        const c2 = hex2 || c1;
        if (hex) {
            st.setProperty("--accent", hex);
            // --accent-3 (helleres Geschwister) MUSS mitziehen, sonst bleibt hier Orange stehen
            st.setProperty("--accent-3", lightenHex(hex, 0.28));
        }
        if (hex2) st.setProperty("--accent-2", hex2);
        // Adaptive Schriftfarbe: heller Verlauf (z. B. weiße Buttons) -> dunkle Schrift
        const bright = (hexBrightness(c1) + hexBrightness(c2)) / 2;
        st.setProperty("--accent-ink", bright > 176 ? "#1a0d00" : "#ffffff");
    }
    function applyAccent(hex, hex2) {
        setAccentVars(document.documentElement.style, hex, hex2);
    }

    const STATUS = {
        released:   { key: "st_released",    cls: "text-emerald-100 border-emerald-400/60 bg-emerald-400/20" },
        early:      { key: "st_early",       cls: "text-orange-100 border-orange-400/60 bg-orange-400/20" },
        demo:       { key: "st_demo",        cls: "text-amber-100 border-amber-300/60 bg-amber-300/20" },
        development:{ key: "st_development", cls: "text-orange-100 border-orange-400/60 bg-orange-400/20" },
        announced:  { key: "st_announced",   cls: "text-zinc-100 border-white/40 bg-white/15" },
        paused:     { key: "st_paused",      cls: "text-zinc-300 border-white/25 bg-white/5" },
    };
    function statusBadge(s) {
        const m = STATUS[s] || STATUS.development;
        return `<span class="badge inline-flex items-center gap-2 px-3 py-1 rounded-full border font-semibold ${m.cls}">
            <span class="w-1.5 h-1.5 rounded-full bg-current shadow-[0_0_8px_currentColor]"></span>${t(m.key)}</span>`;
    }

    /* =========================================================
       2) DATEN
       ========================================================= */
    const DATA = {};
    async function loadData(...names) {
        const map = { studio: "data/studio.json", team: "data/team.json", games: "data/games.json", patchnotes: "data/patchnotes.json", legal: "data/legal.json" };
        await Promise.all(names.map(async n => {
            if (DATA[n] !== undefined) return;   // schon geladen
            const de = await fetchJSON(map[n]);
            if (LANG === "en" && de) {
                const en = await fetchJSON(map[n].replace(/\.json$/, ".en.json"));
                const src = en && en._src && typeof en._src === "object" && !Array.isArray(en._src) ? en._src : null;
                DATA[n] = en ? mergeLang(de, en, undefined, "", src) : de;
                applyShared(n, DATA[n], de);     // Nicht-Text-Einstellungen immer aus DE
            } else {
                DATA[n] = de;
            }
        }));
        return DATA;
    }

    // Sprach-unabhängige Einstellungen (Farben, Partikel, Logos, Bilder, Links)
    // immer aus der deutschen Basis übernehmen – sie gelten für beide Sprachen.
    function applyShared(name, merged, de) {
        if (!merged || !de) return;
        if (name === "studio") {
            ["name", "background", "logo", "favicon", "heroBackground", "heroVideo", "aboutImages"].forEach(k => {
                if (de[k] !== undefined) merged[k] = de[k];
            });
        } else if (name === "games" && Array.isArray(merged.games) && Array.isArray(de.games)) {
            const SHARED_GAME = ["title", "accent", "accent2", "particleColor", "cover", "homeImage", "logo", "logoScale", "logoPos", "logoLayout", "logoGap", "logoBox", "wishlistUrl", "status", "featured", "slug"];
            merged.games.forEach((g, i) => {
                const d = de.games[i]; if (!d) return;
                SHARED_GAME.forEach(k => { if (d[k] !== undefined) g[k] = d[k]; });
            });
        }
    }

    /* =========================================================
       3) STUDIO-HOME
       ========================================================= */
    /* =========================================================
       3) STUDIO-HOME — Design v7 „Kino trifft Ordnung"
       Oben ein Durchlauf durch die Spiele: Bild, Logo, Status, Satz, Beschreibung und Buttons
       wechseln gemeinsam. Darunter Spiele, Über uns, Team und Devlog – alles aus den CMS-Daten.
       ========================================================= */
    const RM = (() => { try { return matchMedia("(prefers-reduced-motion: reduce)").matches; } catch (e) { return false; } })();
    const pad2 = (n) => String(n).padStart(2, "0");
    const heroBlockOf = (g) => ((g && g.blocks) || []).find(b => b && b.type === "hero" && !b.hidden) || {};
    // Markdown/Zeilenumbrüche → schlichter Text (für kurze Zeilen auf Karten)
    const plainText = (v) => String(v || "").replace(/\r/g, "")
        .replace(/!\[[^\]]*\]\([^)]*\)/g, "").replace(/\[([^\]]+)\]\([^)]*\)/g, "$1")
        .replace(/[*_`>#]+/g, "").replace(/\s+/g, " ").trim();
    function firstSentence(v) {
        const s = plainText(v);
        const m = s.match(/^(.{12,}?[.!?…])(?=\s|$)/);
        return m ? m[1] : s;
    }
    // Großer Satz im Durchlauf: Tagline aus dem Kopfbereich der Spielseite, sonst die Tagline des Spiels (lang → erster Satz)
    function slideTagline(g) {
        const s = plainText(heroBlockOf(g).tagline || g.tagline || "");
        return s.length > 90 ? firstSentence(s) : s;
    }
    // Beschreibung: eigenes Feld → erster Satz aus „Über das Spiel" (Textabschnitt) → Rest der Spiel-Tagline
    function gameSummary(g, tag = "") {
        const own = plainText(g.summary);
        if (own) return own;
        const rt = (g.blocks || []).find(b => b && b.type === "richtext" && !b.hidden && String(b.body || "").trim());
        if (rt) { const fs = firstSentence(rt.body); if (fs && fs.length <= 230) return fs; }
        const gt = plainText(g.tagline);
        if (!gt || gt === tag) return "";
        const head = tag.replace(/[.!?…]+$/, "");
        if (head && gt.startsWith(head)) return gt.slice(head.length).replace(/^[.!?…\s]+/, "").trim();
        return gt;
    }
    const ST_KEY = { released: "st_released", early: "st_early", demo: "st_demo", development: "st_development", announced: "st_announced", paused: "st_paused" };
    const pgStatus = (st) => ST_KEY[st] ? st : "development";
    const pgChip = (st) => `<span class="pg-chip pg-st-${pgStatus(st)}"><i></i>${t(ST_KEY[pgStatus(st)])}</span>`;
    // Farben eines Spiels für einen einzelnen Bereich (Karte, Folie …) – wie setAccentVars, aber als Style-Text
    function accentStyle(g) {
        const c1 = (g && g.accent) || "#ff7d1a", c2 = (g && g.accent2) || c1;
        const ink = (hexBrightness(c1) + hexBrightness(c2)) / 2 > 176 ? "#1a0d00" : "#ffffff";
        return `--accent:${esc(c1)};--accent-2:${esc(c2)};--accent-3:${esc(lightenHex(c1, 0.28))};--accent-ink:${ink}`;
    }
    // Logo mit gleicher sichtbarer Fläche (breite und kompakte Logos wirken gleich groß), ohne durchsichtigen Rand
    function logoFit(g, area, maxH, cls = "", named = true) {
        const src = logoPath(g);
        if (!src) return "";
        const aria = named ? ` role="img" aria-label="${esc(g.title || "")}"` : ' aria-hidden="true"';
        const box = logoBoxOf(g);
        if (!box) {
            const h = Math.round(Math.min(Math.sqrt(area / 2.6), maxH));
            return `<span class="pg-lg pg-lg-plain ${cls}"${aria} style="--h:${h}px"><img src="${esc(src)}" alt=""></span>`;
        }
        const fw = box.x1 - box.x0, fh = box.y1 - box.y0, va = box.ar * fw / fh;
        const h = Math.min(Math.sqrt(area / va), maxH), w = h * va;
        return `<span class="pg-lg ${cls}"${aria} style="--w:${w.toFixed(1)}px;aspect-ratio:${va.toFixed(4)}">`
            + `<img src="${esc(src)}" alt="" style="width:${(100 / fw).toFixed(3)}%;left:${(-100 * box.x0 / fw).toFixed(3)}%;top:${(-100 * box.y0 / fh).toFixed(3)}%"></span>`;
    }
    const pgKick = (num, label) => `<span class="pg-kicker">${num ? `<b>${pad2(num)}</b>` : ""}${label}</span>`;
    const pgSh = (num, label, h, lead = "", right = "") =>
        `<div class="pg-sh pg-rv"><div>${pgKick(num, label)}<h2 class="pg-h2">${h}</h2>${lead ? `<p class="pg-lead">${lead}</p>` : ""}</div>${right}</div>`;
    const gameHref = (g) => `game.php?slug=${encodeURIComponent((g && g.slug) || "")}`;

    // ---------- Spiele-Durchlauf ----------
    function homeVisual(g, s, isCur) {
        if (g.homeImage) return { img: g.homeImage, video: "" };
        // aktuelles Projekt: Hintergrund aus „Studio & Startseite" (falls gesetzt)
        if (isCur && (s.heroBackground || s.heroVideo)) return { img: s.heroBackground || g.cover || "", video: s.heroVideo || "" };
        return { img: g.cover || "", video: "" };
    }
    function slideSecondary(g) {
        const hb = heroBlockOf(g);
        const wish = String(g.wishlistUrl || "").trim();
        if (/^https?:\/\//i.test(wish)) return `<a class="pg-btn pg-btn-s" href="${esc(wish)}" target="_blank" rel="noopener">${esc(hb.ctaLabel || t("wishlist"))}</a>`;
        const tr = (g.blocks || []).find(b => b && b.type === "trailer" && !b.hidden && /^https?:\/\//i.test(String(b.youtube || "").trim()));
        const trUrl = String(hb.trailerUrl || (tr && tr.youtube) || "").trim();
        if (/^https?:\/\//i.test(trUrl)) return `<a class="pg-btn pg-btn-s" href="${esc(trUrl)}" target="_blank" rel="noopener">${pgIc("play")} ${t("trailer_watch")}</a>`;
        const wl = (g.blocks || []).find(b => b && b.type === "waitlist" && !b.hidden);
        if (wl) return `<a class="pg-btn pg-btn-s" href="${gameHref(g)}#warteliste">${t(/beta/i.test(String(wl.heading || "")) ? "cta_beta" : "cta_playtest")}</a>`;
        return "";
    }
    function homeFigure(g, i, s, isCur) {
        const v = homeVisual(g, s, isCur);
        const img = String(v.img || "");
        const pos = focusPos(img);
        const style = `--fp:${pos};--a:${esc((g && g.accent) || "#ff7d1a")}`;
        if (v.video) {
            return `<figure class="pg-hs-img${i ? "" : " on"}" style="${style}"><video muted loop playsinline preload="${i ? "none" : "auto"}"${img ? ` poster="${esc(img)}"` : ""} data-hs-video><source src="${esc(v.video)}"></video></figure>`;
        }
        if (!img) return `<figure class="pg-hs-img pg-hs-noimg${i ? "" : " on"}" style="${style}"></figure>`;
        return `<figure class="pg-hs-img${i ? "" : " on"}" style="${style}"><img ${i ? "data-src" : 'fetchpriority="high" src'}="${esc(img)}" alt=""></figure>`;
    }
    function homeSlide(g, i, n) {
        const tag = slideTagline(g), sum = gameSummary(g, tag), genre = plainText(g.genre);
        const logo = logoPath(g) ? `<h2 class="pg-hs-logo">${logoFit(g, 30000, 120)}</h2>` : `<h2 class="pg-hs-title">${esc(g.title || "")}</h2>`;
        return `<div class="pg-hs-slide${i ? "" : " on"}" id="pg-hs-${i}" role="group" aria-roledescription="Folie" aria-label="${i + 1} ${t("hs_of")} ${n}: ${esc(g.title || "")}" style="${accentStyle(g)}">
                ${logo}
                <div class="pg-meta">${pgChip(g.status)}${genre ? `<span class="pg-genre">${esc(genre)}</span>` : ""}</div>
                ${tag ? `<p class="pg-hs-tag">${esc(tag)}</p>` : ""}
                ${sum ? `<p class="pg-hs-txt">${esc(sum)}</p>` : ""}
                <div class="pg-row"><a class="pg-btn pg-btn-p" href="${gameHref(g)}">${t("to_game2")} ${pgIc("arrow")}</a>${slideSecondary(g)}</div>
            </div>`;
    }
    function homeTab(g, i) {
        return `<button type="button" class="pg-hs-tab${i ? "" : " on"}" data-hs-go="${i}" aria-controls="pg-hs-${i}" aria-label="${esc(g.title || "")} ${t("hs_show")}"${i ? "" : ' aria-current="true"'} style="${accentStyle(g)}">
                <span class="pg-hs-thumb">${g.cover ? `<img src="${esc(g.cover)}" alt="" loading="lazy" style="object-position:${focusPos(g.cover)}">` : ""}${logoFit(g, 2400, 30, "", false)}</span>
                <span class="pg-hs-tt"><b>${esc(g.title || "")}</b><small class="pg-st-${pgStatus(g.status)}">${t(ST_KEY[pgStatus(g.status)])}</small></span><i class="pg-hs-bar"></i></button>`;
    }
    function homeHero(order, s, cur) {
        const n = order.length;
        const claim = `${esc(s.heroLine1 || "")} ${esc(s.heroLine2 || "")}`.trim();
        const h1 = `<h1 class="pg-sr">${esc(s.name || "PLANIGAMES")}${claim ? " – " + claim : ""}</h1>`;
        if (!n) {
            // ohne Spiele mit Titelbild: ruhiger Studio-Kopfbereich mit dem Claim
            const bg = s.heroBackground ? `<div class="pg-hs-media" aria-hidden="true"><figure class="pg-hs-img on" style="--fp:${focusPos(s.heroBackground)}"><img src="${esc(s.heroBackground)}" alt=""></figure></div>` : "";
            return `<section class="pg-hs pg-hs-claim">${bg}<div class="pg-wrap pg-hs-body"><div class="pg-hs-slide on">
                <h1 class="pg-h1">${esc(s.heroLine1 || s.name || "PLANIGAMES")}${s.heroLine2 ? `<br><span class="pg-grad">${esc(s.heroLine2)}</span>` : ""}</h1>
                ${s.intro ? `<p class="pg-hs-txt">${esc(s.intro)}</p>` : ""}
                <div class="pg-row"><a class="pg-btn pg-btn-p" href="games.html">${t("cta_discover")} ${pgIc("arrow")}</a><a class="pg-btn pg-btn-s" href="devlog.php">${t("cta_devlog")}</a></div>
                <div data-hero-countdown></div></div></div></section>`;
        }
        return `<section class="pg-hs" data-hs aria-roledescription="Karussell" aria-label="${t("hs_label")}">
            ${h1}
            <div class="pg-hs-media" aria-hidden="true">${order.map((g, i) => homeFigure(g, i, s, g === cur)).join("")}</div>
            <div class="pg-wrap pg-hs-body">
                <div class="pg-hs-slides">${order.map((g, i) => homeSlide(g, i, n)).join("")}</div>
                <div data-hero-countdown></div>
                ${n > 1 ? `<div class="pg-hs-nav">
                    <div class="pg-hs-navtop"><span>${t("hs_label")}</span><i class="pg-line"></i><span class="pg-hs-count"><b>01</b> / ${pad2(n)}</span><button type="button" class="pg-hs-pause" aria-pressed="false" aria-label="${t("hs_pause")}">${pgIc("pause")}</button></div>
                    <div class="pg-hs-tabs" style="--n:${Math.min(n, 4)}">${order.map(homeTab).join("")}</div>
                </div>` : ""}
            </div>
        </section>`;
    }
    function homeTicker(s) {
        let ph = String(s.marquee || "").split(/\r?\n/).map(x => x.trim()).filter(Boolean);
        if (!ph.length) ph = ["Original Worlds", "Handcrafted", "Indie & Proud"];
        const est = ph.reduce((a, x) => a + x.length * 13 + 80, 0);
        const reps = Math.max(1, Math.ceil(2800 / Math.max(300, est)));
        const half = Array.from({ length: reps }, () => ph.map(x => `<span>${esc(x)}</span>`).join("")).join("");
        return `<div class="pg-tick" aria-hidden="true"><div class="pg-tick-in">${half}${half}</div></div>`;
    }

    // ---------- Spiele-Karten ----------
    function homeCard(g, isCur) {
        const tag = slideTagline(g), sum = gameSummary(g, tag), genre = plainText(g.genre);
        return `<a class="pg-gcard pg-rv" href="${gameHref(g)}" style="${accentStyle(g)}">
            <div class="pg-gimg">${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.coverAlt || g.title || "")}" loading="lazy" style="object-position:${focusPos(g.cover)}">` : ""}
                <div class="pg-chips">${pgChip(g.status)}${isCur ? `<span class="pg-chip pg-chip-hl">${t("current_project_tag")}</span>` : ""}</div>
                ${logoPath(g) ? logoFit(g, 13000, 92, "pg-gimg-logo") : `<span class="pg-gimg-title">${esc(g.title || "")}</span>`}</div>
            <div class="pg-gbody">${genre ? `<span class="pg-gmeta">${esc(genre)}</span>` : ""}${tag ? `<b class="pg-gtag">${esc(tag)}</b>` : ""}${sum ? `<p>${esc(sum)}</p>` : ""}<span class="pg-link">${t("to_game2")} ${pgIc("arrow")}</span></div>
        </a>`;
    }
    function homeExtraRow(g) {
        const txt = plainText(g.summary) || plainText(heroBlockOf(g).tagline) || plainText(g.tagline);
        return `<a class="pg-demos pg-rv" href="${gameHref(g)}" style="${accentStyle(g)}"><span class="pg-icn">${pgIc("pad")}</span>`
            + `<span class="pg-demos-tx"><b>${esc(g.title || "")}</b>${txt ? `<small>${esc(txt)}</small>` : ""}</span><span class="pg-link">${t("more_info")} ${pgIc("arrow")}</span></a>`;
    }
    function homeGames(list, extra, s, cur, num) {
        if (!list.length && !extra.length) return "";
        const n = list.length, hs = t("worlds_h");
        const h = n >= 1 && n <= 6 && Array.isArray(hs) ? hs[n - 1] : `${n} ${t("worlds_n")}`;
        return `<section class="pg-sec" id="unsere-spiele"><div class="pg-wrap">
            ${pgSh(num, t("ourgames"), esc(h), s.intro ? esc(s.intro) : "", `<a class="pg-btn pg-btn-s pg-btn-sm" href="games.html">${t("foot_allgames")} ${pgIc("arrow")}</a>`)}
            ${n ? `<div class="pg-games"${n % 2 ? " data-odd" : ""}>${list.map(g => homeCard(g, g === cur)).join("")}</div>` : ""}
            ${extra.map(homeExtraRow).join("")}
        </div></section>`;
    }

    // ---------- Über uns ----------
    function aboutImages(s, games, posts) {
        const own = (Array.isArray(s.aboutImages) ? s.aboutImages : [])
            .map(x => (x && typeof x === "object") ? (x.image || x.src || "") : x).filter(x => typeof x === "string" && x.trim());
        if (own.length) return own.slice(0, 3);
        const covers = games.filter(g => g && g.cover), cur = covers.find(g => g.featured);
        const gallery = [];
        games.forEach(g => (g.blocks || []).filter(b => b && b.type === "gallery" && !b.hidden).forEach(b =>
            (b.images || b.items || []).forEach(im => { const src = typeof im === "string" ? im : (im && (im.src || im.image)); if (src) gallery.push(src); })));
        const postImgs = posts.map(p => p.cover).filter(Boolean);
        const out = [];
        const add = (src) => { const k = String(src || "").split("#")[0]; if (k && out.length < 3 && !out.some(o => o.split("#")[0] === k)) out.push(src); };
        add((covers.find(g => g !== cur) || {}).cover);   // großes Bild: ein Spiel neben dem aktuellen Projekt
        add(gallery[0]); add(postImgs[0]);
        gallery.slice(1).forEach(add); postImgs.slice(1).forEach(add); covers.forEach(g => add(g.cover));
        return out;
    }
    function homeAbout(s, games, posts, members, num) {
        const imgs = aboutImages(s, games, posts);
        const stats = [];
        if (String(s.founded || "").trim()) stats.push([s.founded, t("stat_founded")]);
        if (members.length) stats.push([members.length, t("stat_team")]);
        const nGames = games.filter(g => g && g.cover).length;
        if (nGames) stats.push([nGames, t("stat_games")]);
        const pillars = (Array.isArray(s.pillars) ? s.pillars : []).filter(p => p && (p.title || p.text));
        const head = s.heroLine1 || s.heroLine2
            ? `<h2 class="pg-h2">${esc(s.heroLine1 || "")}${s.heroLine2 ? `${s.heroLine1 ? "<br>" : ""}<span class="pg-grad">${esc(s.heroLine2)}</span>` : ""}</h2>${s.aboutTitle ? `<p class="pg-stmt">${esc(s.aboutTitle)}</p>` : ""}`
            : `<h2 class="pg-h2">${esc(s.aboutTitle || "")}</h2>`;
        return `<section class="pg-sec pg-band" id="studio"><div class="pg-wrap">
            <div class="pg-about${imgs.length ? "" : " solo"}">
                <div class="pg-rv">${pgKick(num, t("foot_about_short"))}${head}
                    ${s.aboutBody ? `<div class="pg-txt">${md(s.aboutBody)}</div>` : ""}
                    ${stats.length ? `<div class="pg-stats" style="--n:${stats.length}">${stats.map(([v, l]) => `<div><b>${esc(String(v))}</b><span>${esc(l)}</span></div>`).join("")}</div>` : ""}
                </div>
                ${imgs.length ? `<div class="pg-collage pg-rv" data-n="${imgs.length}">${imgs.map(src => `<figure><img src="${esc(src)}" alt="" loading="lazy" style="object-position:${focusPos(src)}"></figure>`).join("")}</div>` : ""}
            </div>
            ${pillars.length ? `<div class="pg-vals">${pillars.map(p => `<div class="pg-val pg-rv"><span class="pg-icn pg-emo">${esc(p.icon || "✦")}</span><h3>${esc(p.title || "")}</h3><p>${esc(p.text || "")}</p></div>`).join("")}</div>` : ""}
        </div></section>`;
    }

    // ---------- Team ----------
    function homeTeam(members, num) {
        const hasProfile = (m) => (m.bio && String(m.bio).trim()) || m.funFact || m.location || (m.socials || []).some(x => x && x.url && x.url !== "#");
        return `<section class="pg-sec" id="team-section"><div class="pg-wrap">
            ${pgSh(num, t("team_label"), esc(t("team_heading")))}
            <div class="pg-team">${members.map((m, i) => {
                const more = hasProfile(m), quote = plainText(m.tagline || m.funFact || "");
                return `<figure class="pg-tm pg-rv"${more ? ` data-member="${i}" role="button" tabindex="0" aria-label="${esc(m.name || "")} – ${t("profile_view")}"` : ""}>
                    <div class="pg-tm-ph">${m.photo ? `<img src="${esc(m.photo)}" alt="${esc(m.photoAlt || m.name || "")}" loading="lazy" style="object-position:${focusPos(m.photo)}">` : `<span class="pg-tm-emo">${esc(m.emoji || "🧙")}</span>`}
                        <div class="pg-nm"><b>${esc(m.name || "")}</b>${m.role ? `<span>${esc(m.role)}</span>` : ""}</div>${more ? `<span class="pg-tm-more">${t("profile_view")}</span>` : ""}</div>
                    <figcaption>${quote ? `<p class="pg-q">${esc(quote)}</p>` : ""}${m.location ? `<p class="pg-loc">📍 ${esc(m.location)}</p>` : ""}</figcaption>
                </figure>`;
            }).join("")}</div>
        </div></section>`;
    }

    // ---------- Devlog ----------
    function homePost(p, g, cls) {
        const mini = cls === "mini";
        const fig = p.cover ? `<img src="${esc(p.cover)}" alt="${esc(p.coverAlt || "")}" loading="lazy" style="object-position:${focusPos(p.cover)}">`
            : (g && g.cover ? `<img class="pg-ph-img" src="${esc(g.cover)}" alt="" loading="lazy" style="object-position:${focusPos(g.cover)}">${logoFit(g, mini ? 2600 : 9000, mini ? 40 : 70, "pg-card-logo", false)}`
                            : `<span class="pg-ph-blank"></span>`);
        return `<a class="pg-post pg-rv ${cls}" href="devlog.php?slug=${encodeURIComponent(p.slug || "")}" style="${accentStyle(g || {})}">
            <figure>${fig}</figure>
            <div class="pg-pbody"><div class="pg-pmeta">${g ? `<span class="pg-chip pg-chip-game"><i></i>${esc(g.title || "")}</span>` : ""}${p.version ? `<span class="pg-ver">v${esc(p.version)}</span>` : ""}<time datetime="${esc(p.date || "")}">${esc(fmtDate(p.date))}</time></div>
                <h3>${esc(p.title || "")}</h3>${!mini && p.excerpt ? `<p>${esc(plainText(p.excerpt))}</p>` : ""}<span class="pg-link">${t("read_more2")} ${pgIc("arrow")}</span></div>
        </a>`;
    }
    function homeDevlog(posts, games, num) {
        const gOf = (p) => {
            const v = String(p.game || "").trim().toLowerCase();
            return games.find(x => x && v && (String(x.slug || "").toLowerCase() === v || String(x.title || "").toLowerCase() === v)) || null;
        };
        const [first, ...rest] = posts.slice(0, 4);
        return `<section class="pg-sec pg-band" id="devlog-section"><div class="pg-wrap">
            ${pgSh(num, "Devlog", esc(t("devlog_fresh2")), "", `<a class="pg-btn pg-btn-s pg-btn-sm" href="devlog.php">${t("all_posts")} ${pgIc("arrow")}</a>`)}
            <div class="pg-dl${rest.length ? "" : " solo"}">${homePost(first, gOf(first), "big")}${rest.length ? `<div class="pg-dl-side">${rest.map(p => homePost(p, gOf(p), "mini")).join("")}</div>` : ""}</div>
        </div></section>`;
    }

    let stopHomeHero = null;
    async function renderHome() {
        await loadData("studio", "team", "games", "patchnotes");
        const root = $("[data-home]");
        if (!root) return;
        const s = DATA.studio || {};
        const games = ((DATA.games && DATA.games.games) || []).filter(g => g && typeof g === "object");
        const members = ((DATA.team && DATA.team.members) || []).filter(m => m && (m.name || m.photo));
        const posts = ((DATA.patchnotes && DATA.patchnotes.posts) || [])
            .filter(isPublished).slice().sort((a, b) => (b.date || "").localeCompare(a.date || ""));
        const main = games.filter(g => g.cover);          // Spiele mit Titelbild: Durchlauf + Karten
        const extra = games.filter(g => !g.cover);        // z. B. „Browser-Demos": schmale Zeile unter den Karten
        const cur = main.find(g => g.featured) || null;   // aktuelles Projekt startet den Durchlauf
        const order = cur ? [cur, ...main.filter(g => g !== cur)] : main;
        await Promise.all(games.filter(g => logoPath(g)).map(g => ensureLogoBox(g, 1200)));

        let num = 0;
        root.innerHTML = homeHero(order, s, cur) + homeTicker(s)
            + homeGames(main, extra, s, cur, ++num)
            + homeAbout(s, games, posts, members, ++num)
            + (members.length ? homeTeam(members, ++num) : "")
            + (posts.length ? homeDevlog(posts, games, ++num) : "");

        renderHeroCountdown(s);
        const team = $(".pg-team", root);
        if (team) {
            const open = (el) => { const m = members[+el.dataset.member]; if (m) openMemberModal(m); };
            team.addEventListener("click", (e) => { const c = e.target.closest("[data-member]"); if (c) open(c); });
            team.addEventListener("keydown", (e) => { const c = e.target.closest("[data-member]"); if (c && (e.key === "Enter" || e.key === " ")) { e.preventDefault(); open(c); } });
        }
        observeReveals(root);
        if (stopHomeHero) stopHomeHero();
        stopHomeHero = initHomeSlider(root);
        scrollToHash();
    }

    // Durchlauf: alle 7 s das nächste Spiel. Hält an bei Pause-Knopf, Maus über den Spiel-Kacheln,
    // Tastatur-Fokus, verdecktem Tab oder wenn der Kopfbereich aus dem Bild gescrollt ist.
    function initHomeSlider(root) {
        const hs = $("[data-hs]", root);
        if (!hs) return null;
        const figs = $$(".pg-hs-img", hs), slides = $$(".pg-hs-slide", hs), tabs = $$(".pg-hs-tab", hs);
        const n = slides.length;
        const vids = figs.map(f => f.querySelector("video"));
        const playVid = (k) => vids.forEach((v, j) => { if (!v) return; if (j === k && !RM) { const p = v.play(); if (p && p.catch) p.catch(() => {}); } else v.pause(); });
        // Bilder der weiteren Spiele nachladen, sobald das erste Bild da ist
        const loadRest = () => figs.forEach(f => { const im = f.querySelector("img[data-src]"); if (im) { im.src = im.dataset.src; im.removeAttribute("data-src"); } });
        const firstImg = figs[0] && figs[0].querySelector("img");
        if (firstImg && !firstImg.complete) {
            firstImg.addEventListener("load", () => setTimeout(loadRest, 150), { once: true });
            firstImg.addEventListener("error", loadRest, { once: true });
            setTimeout(loadRest, 2500);
        } else setTimeout(loadRest, 150);
        playVid(0);
        if (n < 2) return null;

        const count = $(".pg-hs-count b", hs), pauseBtn = $(".pg-hs-pause", hs), nav = $(".pg-hs-nav", hs), DUR = 7000;
        let cur = 0, timer = 0, t0 = 0, left = DUR;
        const holds = new Set();
        hs.style.setProperty("--dur", DUR + "ms");
        const arm = () => { clearTimeout(timer); timer = 0; if (holds.size) return; t0 = performance.now(); timer = setTimeout(() => go(cur + 1), left); };
        const hold = (r) => { if (!holds.size && timer) { left = Math.max(0, left - (performance.now() - t0)); clearTimeout(timer); timer = 0; } holds.add(r); hs.classList.add("paused"); };
        const release = (r) => { if (!holds.delete(r)) return; if (!holds.size) { hs.classList.remove("paused"); arm(); } };
        function go(i) {
            cur = (i + n) % n;
            figs.forEach((x, k) => x.classList.toggle("on", k === cur));
            slides.forEach((x, k) => x.classList.toggle("on", k === cur));
            tabs.forEach((tb, k) => { tb.classList.remove("on"); tb.removeAttribute("aria-current"); if (k === cur) { void tb.offsetWidth; tb.classList.add("on"); tb.setAttribute("aria-current", "true"); } });
            if (count) count.textContent = pad2(cur + 1);
            if (figs[cur]) { const im = figs[cur].querySelector("img[data-src]"); if (im) loadRest(); }
            playVid(cur);
            left = DUR; arm();
        }
        tabs.forEach((tb, k) => {
            tb.addEventListener("click", () => go(k));
            tb.addEventListener("keydown", (e) => {
                if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
                e.preventDefault();
                const j = (k + (e.key === "ArrowRight" ? 1 : -1) + n) % n;
                go(j); tabs[j].focus();
            });
        });
        const setPause = (on) => {
            if (!pauseBtn) return;
            pauseBtn.setAttribute("aria-pressed", String(on));
            pauseBtn.setAttribute("aria-label", t(on ? "hs_play" : "hs_pause"));
            pauseBtn.innerHTML = pgIc(on ? "play" : "pause");
            if (on) hold("user"); else release("user");
        };
        if (pauseBtn) pauseBtn.addEventListener("click", () => setPause(!holds.has("user")));
        if (nav) {
            nav.addEventListener("pointerenter", (e) => { if (e.pointerType === "mouse") hold("hover"); });
            nav.addEventListener("pointerleave", (e) => { if (e.pointerType === "mouse") release("hover"); });
        }
        hs.addEventListener("focusin", (e) => { try { if (e.target.matches(":focus-visible")) hold("focus"); } catch (er) {} });
        hs.addEventListener("focusout", (e) => { if (!hs.contains(e.relatedTarget)) release("focus"); });
        let sx = 0, sy = 0;
        hs.addEventListener("touchstart", (e) => { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
        hs.addEventListener("touchend", (e) => {
            const dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
            if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.4) go(cur + (dx < 0 ? 1 : -1));
        }, { passive: true });
        const vis = () => (document.hidden ? hold("hidden") : release("hidden"));
        document.addEventListener("visibilitychange", vis);
        const ob = "IntersectionObserver" in window ? new IntersectionObserver(([en]) => (en.isIntersecting ? release("off") : hold("off")), { threshold: 0.15 }) : null;
        if (ob) ob.observe(hs);
        if (RM) { hs.classList.add("pg-is-static"); setPause(true); }
        go(0);
        return () => { clearTimeout(timer); document.removeEventListener("visibilitychange", vis); if (ob) ob.disconnect(); };
    }

    // ---------- Mitmachen: Vorschlagsbox & Warteliste (Spiel-Blöcke, eigene Liste je Spiel) ----------
    // Logik wie bisher (Abstimmen, Freigabe, Platz in der Warteliste, Empfehlungslink) – nur im Design v7.
    const pgMsg = (el, kind, text) => { if (!el) return; el.className = "pg-msg is-" + kind; el.hidden = false; el.textContent = text; };
    function suggCard(b, g, i = 0) {
        const en = LANG === "en", id = `pg-idea-${slugify(g.slug || "x")}-${i}`;
        return `<div class="pg-card pg-idea pg-rv" data-sugg-box>
            <h3>${esc(b.heading || (en ? "What should we build next?" : "Was sollen wir als Nächstes bauen?"))}</h3>
            ${b.intro ? `<p>${esc(b.intro)}</p>` : ""}
            <form data-sugg-form data-scope="${esc(g.slug || "")}" class="pg-form">
                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="pg-hp">
                <label class="pg-sr" for="${id}">${en ? "Your idea" : "Deine Idee"}</label>
                <input id="${id}" name="text" required maxlength="200" autocomplete="off" placeholder="${en ? "Your idea…" : "Deine Idee…"}">
                <button class="pg-btn pg-btn-s">${en ? "Submit" : "Einreichen"}</button>
            </form>
            <p data-sugg-msg class="pg-msg" hidden></p>
            <div data-sugg-list class="pg-votes" aria-live="polite"></div>
        </div>`;
    }

    async function initSuggestions(box) {
        const en = LANG === "en";
        const list = box.querySelector("[data-sugg-list]");
        const form = box.querySelector("[data-sugg-form]");
        const msg = box.querySelector("[data-sugg-msg]");
        const scope = form.dataset.scope || "";
        const q = scope ? "?scope=" + encodeURIComponent(scope) : "";
        let voted = {};
        try { voted = JSON.parse(localStorage.getItem("pg_sugg_voted") || "{}"); } catch {}
        const stLabel = { planned: en ? "Planned" : "Geplant", done: en ? "Done" : "Umgesetzt" };
        // Lange Listen: erst 7 Vorschläge, dann per „Mehr anzeigen" jeweils 7 weitere
        const STEP = 7;
        let all = [], shown = STEP;
        const render = (items) => {
            all = items;
            const vis = items.slice(0, shown), rest = items.length - vis.length;
            list.innerHTML = (items.length ? vis.map(s => `
                <div class="pg-vote-row">
                    <button type="button" class="pg-vote${voted[s.id] ? " voted" : ""}" data-vote="${esc(String(s.id))}" ${voted[s.id] ? "disabled" : ""} aria-label="${en ? "Upvote" : "Stimme geben"}">
                        ${pgIc("up")}<span class="sv-count">${+s.votes || 0}</span></button>
                    <span class="pg-vote-tx">${esc(s.text)}</span>
                    ${s.status && s.status !== "open" ? `<span class="pg-tag${s.status === "done" ? " is-done" : ""}">${esc(stLabel[s.status] || s.status)}</span>` : ""}
                </div>`).join("") : `<p class="pg-empty-s">${en ? "No suggestions yet — add the first!" : "Noch keine Vorschläge — mach den Anfang!"}</p>`)
                + (rest > 0
                    ? `<button type="button" data-sugg-more class="pg-more-btn">${en ? "Show more" : "Mehr anzeigen"} <span>(${rest} ${en ? "more" : "weitere"})</span></button>`
                    : (items.length > STEP ? `<button type="button" data-sugg-less class="pg-more-btn">${en ? "Show less" : "Weniger anzeigen"}</button>` : ""));
        };
        async function load() { try { const r = await fetch("suggestions.php" + q); render((await r.json()).suggestions || []); } catch {} }
        await load();
        list.addEventListener("click", async (e) => {
            if (e.target.closest("[data-sugg-more]")) { shown += STEP; render(all); return; }
            if (e.target.closest("[data-sugg-less]")) { shown = STEP; render(all); box.scrollIntoView({ behavior: "smooth", block: "start" }); return; }
            const btn = e.target.closest("[data-vote]"); if (!btn) return;
            const id = btn.dataset.vote; if (voted[id]) return;
            voted[id] = 1; try { localStorage.setItem("pg_sugg_voted", JSON.stringify(voted)); } catch {}
            const cnt = btn.querySelector(".sv-count"); cnt.textContent = (+cnt.textContent || 0) + 1;
            const it = all.find(x => String(x.id) === String(id)); if (it) it.votes = (+it.votes || 0) + 1;   // bleibt beim Auf-/Zuklappen erhalten
            btn.classList.add("voted"); btn.disabled = true;
            pgBadge("voter");
            const fd = new FormData(); fd.append("upvote", id);
            try { await fetch("suggestions.php", { method: "POST", body: fd }); } catch {}
        });
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            if (scope) fd.append("scope", scope);
            pgMsg(msg, "info", en ? "Sending…" : "Wird gesendet…");
            try {
                const r = await fetch("suggestions.php", { method: "POST", body: fd });
                const j = await r.json();
                if (j.error) { pgMsg(msg, "err", j.error); return; }
                form.reset();
                pgMsg(msg, "ok", en ? "Thanks! Your idea will appear after review." : "Danke! Deine Idee erscheint nach der Prüfung.");
                confetti({ count: 60, y: innerHeight * 0.6 });
                pgBadge("ideator");
            } catch { pgMsg(msg, "err", en ? "Something went wrong." : "Etwas ist schiefgelaufen."); }
        });
    }

    // Warteliste – eigene Liste & eigenes Empfehlungs-Ranking je Spiel (scope = Spiel-Slug)
    function wlCard(b, g, i = 0) {
        const en = LANG === "en", id = `pg-wl-${slugify(g.slug || "x")}-${i}`;
        return `<div class="pg-card pg-hot pg-wl pg-rv"${i ? "" : ' id="warteliste"'}>
            <h3>${esc(b.heading || (en ? "Join the beta" : "Sei bei der Beta dabei"))}</h3>
            <p>${esc(b.intro || t("wl_intro"))}</p>
            <form data-wl-form data-scope="${esc(g.slug || "")}" data-success="${esc(b.successText || "")}" class="pg-form">
                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="pg-hp">
                <label class="pg-sr" for="${id}">${t("nl_email_label")}</label>
                <input id="${id}" name="email" type="email" required autocomplete="email" placeholder="${en ? "your@email.com" : "deine@email.de"}">
                <button class="pg-btn pg-btn-p">${en ? "Join waitlist" : "Eintragen"}</button>
            </form>
            <ul class="pg-ticks" data-wl-ticks><li>${pgIc("check")}${t("wl_tick1")}</li><li>${pgIc("check")}${t("wl_tick2")}</li></ul>
            <p data-wl-msg class="pg-msg" hidden></p>
            <div data-wl-status class="pg-wl-status" hidden></div>
        </div>`;
    }

    // „Welt erkunden"-Teaser: verlinkt auf die spielspezifische Lore-Seite (lore.html?slug=…)
    function loreTeaser(b, g) {
        const lore = g.lore || {};
        if (!lore.enabled) return "";                       // ohne aktive Lore nichts anzeigen
        const en = LANG === "en";
        const heading = b.heading || lore.title || (en ? "Discover the world" : "Entdecke die Welt");
        const text = b.text || lore.intro || "";
        const label = b.label || (en ? "Explore the world" : "Welt erkunden");
        const bg = b.image || lore.hero || g.cover || "";
        return `<a class="pg-lore pg-rv" href="lore.html?slug=${encodeURIComponent(g.slug || "")}">
            ${bg ? `<img src="${esc(bg)}" alt="" loading="lazy" style="object-position:${focusPos(bg)}">` : ""}
            <span class="pg-lore-tx"><small>${en ? "World & Story" : "Welt & Story"}</small><b>${esc(heading)}</b>${text ? `<span>${esc(text)}</span>` : ""}</span>
            <span class="pg-btn pg-btn-s pg-btn-sm">${esc(label)} ${pgIc("arrow")}</span></a>`;
    }

    function initWaitlist(box) {
        const en = LANG === "en";
        const form = box.querySelector("[data-wl-form]");
        const msg = box.querySelector("[data-wl-msg]");
        const statusBox = box.querySelector("[data-wl-status]");
        const ticks = box.querySelector("[data-wl-ticks]");
        const scope = form.dataset.scope || "";
        const successText = form.dataset.success || "";
        const sfx = scope ? "_" + scope : "";                       // eigene Speicher-Keys je Bereich
        const scopeQ = scope ? "&scope=" + encodeURIComponent(scope) : "";

        // Empfehlungs-Code aus ?ref= merken (nur für diesen Bereich)
        try {
            const r = qs.get("ref");
            if (r) localStorage.setItem("pg_wl_ref" + sfx, r.replace(/[^a-z0-9]/gi, "").toLowerCase());
        } catch {}

        // Empfehlungslink = aktuelle Seite (inkl. Spiel-Slug) + ?ref=CODE
        const refLink = (code) => { try { const u = new URL(location.href); u.searchParams.set("ref", code); u.hash = ""; return u.toString(); } catch { return location.href; } };
        function showStatus(d) {
            form.hidden = true; if (ticks) ticks.hidden = true;
            statusBox.hidden = false;
            const link = refLink(d.code);
            statusBox.innerHTML = `
                <p class="pg-wl-ok">${esc(successText || (en ? "You're in! 🎉" : "Du bist dabei! 🎉"))}</p>
                <p class="pg-wl-pos">${en ? "Your spot:" : "Dein Platz:"} <b>#${esc(String(d.position))}</b>${d.referrals ? ` · ${esc(String(d.referrals))} ${en ? "referred" : "geworben"}` : ""}</p>
                <p class="pg-wl-hint">${en ? "Move up by inviting friends with your link:" : "Steig auf, indem du Freunde mit deinem Link einlädst:"}</p>
                <div class="pg-form">
                    <input readonly value="${esc(link)}" data-wl-link aria-label="${en ? "Your invite link" : "Dein Einladungslink"}">
                    <button type="button" class="pg-btn pg-btn-p" data-wl-copy>${en ? "Copy" : "Kopieren"}</button>
                </div>`;
            statusBox.querySelector("[data-wl-copy]").addEventListener("click", () => {
                const inp = statusBox.querySelector("[data-wl-link]");
                const done = () => { const b = statusBox.querySelector("[data-wl-copy]"); b.textContent = en ? "Copied!" : "Kopiert!"; setTimeout(() => b.textContent = en ? "Copy" : "Kopieren", 1400); };
                if (navigator.clipboard) navigator.clipboard.writeText(link).then(done, () => {}); else { inp.select(); try { document.execCommand("copy"); done(); } catch {} }
            });
        }

        // Schon eingetragen? Stand laden
        let token = "";
        try { token = localStorage.getItem("pg_wl_token" + sfx) || ""; } catch {}
        if (token) {
            fetch("waitlist.php?status=" + encodeURIComponent(token) + scopeQ).then(r => r.json()).then(d => { if (d && d.ok) showStatus(d); }).catch(() => {});
        }

        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            // Empfehlungscode: bevorzugt direkt aus der aktuellen URL (?ref=…), sonst aus dem Speicher.
            let ref = "";
            const urlRef = (qs.get("ref") || "").replace(/[^a-z0-9]/gi, "").toLowerCase();
            if (urlRef) ref = urlRef;
            else { try { ref = localStorage.getItem("pg_wl_ref" + sfx) || ""; } catch {} }
            if (ref) fd.append("ref", ref);
            if (scope) fd.append("scope", scope);
            pgMsg(msg, "info", en ? "Joining…" : "Wird eingetragen…");
            try {
                const r = await fetch("waitlist.php", { method: "POST", body: fd });
                const j = await r.json();
                if (j.error) { pgMsg(msg, "err", j.error); return; }
                msg.hidden = true;
                if (j.token) { try { localStorage.setItem("pg_wl_token" + sfx, j.token); } catch {} }
                showStatus(j);
                if (!j.already) { confetti({ count: 80, y: innerHeight * 0.55 }); pgBadge("subscriber"); }
            } catch { pgMsg(msg, "err", en ? "Something went wrong." : "Etwas ist schiefgelaufen."); }
        });
    }

    // Release-Countdown im Hero (im Admin konfigurierbar)
    function renderHeroCountdown(s) {
        const mount = $("[data-hero-countdown]");
        if (!mount) return;
        const c = s.heroCountdown || {};
        if (c.enabled === false || c.enabled === "0" || c.enabled === "false") { mount.innerHTML = ""; return; }
        // Datum tolerant parsen: "2026-12-31T18:00" oder "2026-12-31 18:00" oder nur Datum
        let raw = (c.date || "").trim();
        if (raw && !raw.includes("T")) raw = raw.replace(" ", "T");
        const ts = raw ? Date.parse(raw) : NaN;
        if (isNaN(ts)) { mount.innerHTML = ""; return; }
        // Bereits abgelaufen → „fertig"-Text zeigen (statt nichts), falls vorhanden
        if (ts <= Date.now()) {
            mount.className = "reveal mt-12";
            mount.innerHTML = c.doneText
                ? `<div class="text-2xl md:text-3xl font-bold text-[color:var(--accent)]">${esc(c.doneText)}</div>` : "";
            observeReveals(mount);
            return;
        }
        const units = [["d", "Tage", "Days"], ["h", "Std", "Hrs"], ["m", "Min", "Min"], ["s", "Sek", "Sec"]];
        const cells = units.map(([k, de, en]) => `
            <div class="text-center">
                <div class="cd-num font-display text-3xl md:text-5xl font-extrabold text-gradient tabular-nums" data-cd="${k}">--</div>
                <div class="font-mono text-[9px] uppercase tracking-widest text-zinc-500 mt-1">${LANG === "en" ? en : de}</div>
            </div>`).join("");
        mount.className = "reveal mt-12 inline-flex flex-col gap-3";
        mount.innerHTML = `
            ${c.label ? `<span class="font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)]">${esc(c.label)}</span>` : ""}
            <div class="cd-grid grid grid-cols-4 gap-4 md:gap-6" data-countdown="${ts}">${cells}</div>
            <div class="cd-done hidden text-2xl font-bold text-[color:var(--accent)]" data-done="${esc(c.doneText || "")}"></div>`;
        initCountdowns(mount);
        observeReveals(mount);
    }

    function gameCard(g) {
        return `
        <a href="game.php?slug=${esc(g.slug)}" class="reveal tilt-card group relative block overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]" style="--accent:${esc(g.accent || "#8b5cf6")}">
            <div class="card-glow"></div><div class="card-shine"></div>
            <div class="relative aspect-[16/10] overflow-hidden">
                ${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.coverAlt || g.title)}" style="object-position:${focusPos(g.cover)}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
                          : `<div class="w-full h-full grid-lines bg-white/[0.03]"></div>`}
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>
                <div class="absolute top-3 left-3">${statusBadge(g.status)}</div>
            </div>
            <div class="relative p-6">
                <h3 class="font-display text-2xl font-extrabold text-white group-hover:text-gradient">${esc(g.title)}</h3>
                <p class="text-sm text-zinc-400 mt-2 line-clamp-2">${esc(g.tagline || "")}</p>
                <div class="mt-4 font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] flex items-center gap-2">
                    ${t("more_info")} <span class="transition-transform group-hover:translate-x-1">→</span></div>
            </div>
        </a>`;
    }

    /* =========================================================
       4) GAMES-ÜBERSICHT
       ========================================================= */
    async function renderGamesList() {
        await loadData("games");
        const games = (DATA.games && DATA.games.games) || [];
        const grid = $("[data-games='all']");
        if (!grid) return;
        if (!games.length) { grid.innerHTML = `<p class="text-zinc-500">${t("msg_no_games")}</p>`; return; }
        grid.innerHTML = games.map(gameCard).join("");
        initTilt(grid); observeReveals(grid);
        setText("[data-games='count']", games.length + " " + (LANG === "en" ? "titles" : "Titel"));
    }

    /* =========================================================
       5) GAME-DETAIL — Block-Baukasten ("Website-Builder")
       ========================================================= */
    async function renderGame() {
        await loadData("games", "patchnotes");
        const games = ((DATA.games && DATA.games.games) || []).filter(x => x && typeof x === "object");
        const slug = String(qs.get("slug") || "").toLowerCase();
        const g = games.find(x => String(x.slug || "").toLowerCase() === slug);
        const root = $("[data-game-root]");
        if (!root) return;
        if (!g) {
            if (slug && await followRedirect("games", slug)) return;   // umbenannt → neue Adresse
            root.innerHTML = notFound(t("nf_text"));
            return;
        }

        applyAccent(g.accent || "#ff7d1a", g.accent2 || g.accent || "#e6a015");
        setParticleColor(g.particleColor || g.accent);   // Partikel an die Spielwelt anpassen
        document.title = `${g.title} — PLANIGAMES`;

        // Logos ohne durchsichtigen Rand (dieses Spiel + „Weitere Spiele")
        await Promise.all(games.filter(x => logoPath(x)).map(x => ensureLogoBox(x, x === g ? 1500 : 800)));
        // ausgeblendete Blöcke bleiben gespeichert, werden aber nicht gezeigt
        const blocks = (Array.isArray(g.blocks) ? g.blocks : []).filter(b => b && !b.hidden);
        const of = (type) => blocks.filter(b => (b.type || b._type) === type);
        const hb = of("hero")[0] || {};
        const secs = gameSections(g, of, gamePosts(g), games.filter(x => x !== g));
        root.innerHTML = blockHero(hb, g) + gameSubnav(g, secs, heroCtas(g, hb, of))
            + secs.map((s, i) => `<section class="pg-sec${i % 2 ? " pg-band" : ""}" id="${s.id}">${s.html(i + 1)}</section>`).join("");

        initMagnetic(); observeReveals(root);
        initLightbox(); initCountdowns(root);
        // Interaktive Community-Blöcke dieses Spiels starten (Vorschläge / Warteliste)
        $$("[data-sugg-form]", root).forEach(f => initSuggestions(f.closest("[data-sugg-box]") || f.parentElement));
        $$("[data-wl-form]", root).forEach(f => initWaitlist(f.closest(".pg-wl") || f.parentElement));
        initGameSubnav(root);
        scrollToHash();
    }

    // Beiträge dieses Spiels (Zuordnung tolerant: Slug ODER Titel, auch über Tags)
    function gamePosts(g) {
        const gslug = String(g.slug || "").toLowerCase(), gtitle = String(g.title || "").toLowerCase();
        const belongs = (p) => {
            const v = String(p.game || "").trim().toLowerCase();
            if (v && (v === gslug || v === gtitle)) return true;
            return (p.tags || []).some(tg => { const x = String(tg).toLowerCase(); return x === gslug || x === gtitle; });
        };
        return ((DATA.patchnotes && DATA.patchnotes.posts) || [])
            .filter(p => isPublished(p) && belongs(p))
            .sort((a, b) => (b.date || "").localeCompare(a.date || ""));
    }

    const fmtT = (key, title) => t(key).replace("{title}", title || "");
    const isRealLink = (u) => { u = String(u || "").trim(); return !!u && u !== "#" && !/^javascript:/i.test(u); };
    function logoScale(g) { return Math.min(3, Math.max(0.3, (+g.logoScale || 100) / 100)); }
    // Abstand zwischen Status-Zeile, Logo und Text (px, einstellbar unter „Kopfbereich gestalten“)
    function logoGap(g) { const n = Number(g.logoGap); return Number.isFinite(n) && String(g.logoGap ?? "").trim() !== "" ? Math.min(160, Math.max(0, Math.round(n))) : 20; }

    // Nächster Schritt im Kopfbereich: Wishlist → Warteliste (Playtest/Beta/Benachrichtigen) → Demo; dazu Trailer
    function heroCtas(g, hb, of) {
        const out = [];
        const wish = String(g.wishlistUrl || "").trim();
        if (/^https?:\/\//i.test(wish)) out.push({ href: wish, ext: true, label: hb.ctaLabel || t("wishlist") });
        const wl = of("waitlist")[0];
        if (wl) {
            const h = String(wl.heading || "");
            out.push({ href: "#warteliste", label: g.status === "paused" ? t("cta_notify") : /beta/i.test(h) ? t("cta_wl_beta") : /playtest/i.test(h) ? t("cta_wl_playtest") : t("cta_wl") });
        } else if (of("download").some(d => d.embedUrl || (d.builds || []).some(x => x && (x.url || x.file)))) {
            out.push({ href: "#spielen", label: t("cta_demo") });
        }
        const trUrl = String(hb.trailerUrl || "").trim();
        if (/^https?:\/\//i.test(trUrl)) out.push({ href: trUrl, ext: true, play: true, label: t("trailer_watch") });
        else if (of("trailer").some(tb => tb.youtube || tb.url || tb.file)) out.push({ href: "#einblicke", play: true, label: t("trailer_watch") });
        return out.slice(0, 2);
    }
    const ctaLink = (c, cls) => `<a class="pg-btn ${cls}" href="${esc(c.href)}"${c.ext ? ' target="_blank" rel="noopener"' : ""}>${c.play ? pgIc("play") + " " : ""}${esc(c.label)}${c.play ? "" : " " + pgIc("arrow")}</a>`;

    // Steckbrief-Leiste: eigene Angaben (Admin) – sonst die Kennzahlen des Spiels
    function factIcon(label) {
        const l = String(label || "").toLowerCase();
        if (/genre|art|typ/.test(l)) return "tag";
        if (/spieler|player|koop|co-?op|team/.test(l)) return "users";
        if (/plattform|platform|pc|konsole|console|steam/.test(l)) return "screen";
        if (/release|erschein|datum|date|jahr|year|start/.test(l)) return "cal";
        if (/status|phase/.test(l)) return "flag";
        return "spark";
    }
    function gameFacts(g, of) {
        const clean = (x, k1, k2) => x && String(x[k1] || "").trim() && String(x[k2] || "").trim();
        const own = (Array.isArray(g.facts) ? g.facts : []).filter(f => clean(f, "label", "value"));
        if (own.length) return { list: own.slice(0, 4).map(f => [f.label, f.value]), fromStats: false };
        const st = of("stats").flatMap(b => b.items || []).filter(x => clean(x, "label", "value"));
        if (st.length >= 2) return { list: st.slice(0, 4).map(x => [x.label, x.value]), fromStats: true };
        return { list: [], fromStats: false };
    }

    // Kopfbereich der Spielseite. Das Logo steht IMMER im Textblock (links · mittig · rechts neben dem Text),
    // damit es auf jedem Bildschirm gleich zum Text steht. Wird auch von der Logo-Platzierung im Admin genutzt.
    function heroLayout(g) { return ["left", "center", "split"].includes(g.logoLayout) ? g.logoLayout : "left"; }
    function blockHero(b, g) {
        const blocks = (Array.isArray(g.blocks) ? g.blocks : []).filter(x => x && !x.hidden);
        const of = (type) => blocks.filter(x => (x.type || x._type) === type);
        const bg = b.background || g.cover || "";
        const bgMobile = b.backgroundMobile || "";
        let media = "";
        if (b.video) media = `<video autoplay muted loop playsinline ${b.poster ? `poster="${esc(b.poster)}"` : ""}><source src="${esc(b.video)}"></video>`;
        else if (bg) media = bgMobile
            ? `<picture><source media="(max-width: 900px)" srcset="${esc(bgMobile)}"><img src="${esc(bg)}" alt="" style="object-position:${focusPos(bg)}"></picture>`
            : `<img src="${esc(bg)}" alt="" style="object-position:${focusPos(bg)}">`;
        const ls = logoScale(g);
        const layout = heroLayout(g) === "split" && !logoPath(g) ? "left" : heroLayout(g);
        // Logo-Höhe wächst mit der Bildschirmbreite – in festen Grenzen (gleiche Wirkung auf Laptop, Full HD, 4K)
        const h = layout === "split"
            ? `clamp(${Math.round(72 * ls)}px, ${(11 * ls).toFixed(2)}vw, ${Math.round(220 * ls)}px)`
            : `clamp(${Math.round(56 * ls)}px, ${(8.5 * ls).toFixed(2)}vw, ${Math.round(150 * ls)}px)`;
        const logo = logoPath(g)
            ? `<h1 class="pg-gh-logo">${logoMarkup(g, { h, cls: "pg-gh-logo-el", attrs: 'data-hero-logo="inline"' })}</h1>`
            : `<h1 data-hero-logo="title" class="pg-gh-title">${esc(g.title || "")}</h1>`;
        const genre = plainText(g.genre);
        const crumb = `<nav class="pg-crumb" aria-label="Breadcrumb"><a href="games.html">${t("nav_games")}</a><span aria-hidden="true">/</span><span>${esc(g.title || "")}</span></nav>`;
        const badge = `<div data-hero-badge class="pg-meta">${pgChip(g.status)}${genre ? `<span class="pg-genre">${esc(genre)}</span>` : ""}</div>`;
        const tagline = plainText(b.tagline || g.tagline || "");
        const tag = tagline ? `<p data-hero-tagline class="pg-gh-tag">${esc(tagline)}</p>` : `<p data-hero-tagline class="pg-gh-tag pg-gh-tag-empty"></p>`;
        const ctas = heroCtas(g, b, of);
        const btns = `<div data-hero-btns class="pg-row">${ctas.map((c, i) => ctaLink(c, i ? "pg-btn-s" : "pg-btn-p")).join("")}</div>`;
        const inner = layout === "split"
            ? `<div class="pg-gh-text">${crumb}${badge}${tag}${btns}</div><div class="pg-gh-side">${logo}</div>`
            : `${crumb}${badge}${logo}${tag}${btns}`;
        const facts = gameFacts(g, of).list;
        const bar = facts.length >= 2 ? `<ul class="pg-factbar" style="--n:${facts.length}" aria-label="${t("facts_label")}">${facts.map(([k, v]) =>
            `<li><span class="pg-fi">${pgIc(factIcon(k))}</span><span><small>${esc(k)}</small> <b>${esc(v)}</b></span></li>`).join("")}</ul>` : "";
        return `<section data-hero class="pg-gh${media ? "" : " is-plain"}${bgMobile ? " has-mobile-img" : ""}" style="--a:${esc(g.accent || "#ff7d1a")}">
            ${media ? `<div class="pg-gh-media" aria-hidden="true">${media}</div>` : ""}
            <div class="pg-wrap pg-gh-in">
                <div data-hero-inner class="pg-gh-copy pg-gh--${layout}" style="--logo-gap:${logoGap(g)}px">${inner}</div>
                ${bar}
            </div>
        </section>`;
    }

    // Abschnitts-Menü (bleibt beim Scrollen oben stehen und zeigt, wo man gerade ist)
    function gameSubnav(g, secs, ctas) {
        const links = secs.filter(s => s.nav);
        if (links.length < 2) return "";
        return `<nav class="pg-subnav" aria-label="${esc(g.title || "")}"><div class="pg-wrap">
            ${logoPath(g) ? `<a class="pg-sn-logo" href="#top" data-sn-top aria-label="${esc(g.title || "")} – ${t("to_top")}">${logoFit(g, 1500, 28, "", false)}</a>` : ""}
            <div class="pg-sn-links">${links.map(s => `<a href="#${s.id}" data-spy="${s.id}">${s.nav}</a>`).join("")}</div>
            ${ctas[0] ? ctaLink(ctas[0], "pg-btn-p pg-btn-sm pg-sn-cta") : ""}
        </div></nav>`;
    }
    function initGameSubnav(root) {
        const nav = $(".pg-subnav", root);
        if (!nav) return;
        const links = $$("[data-spy]", nav), secs = links.map(a => document.getElementById(a.dataset.spy));
        let raf = 0;
        const upd = () => {
            raf = 0;
            const y = nav.getBoundingClientRect().bottom + 24;
            let k = -1; secs.forEach((s, i) => { if (s && s.getBoundingClientRect().top <= y) k = i; });
            links.forEach((a, i) => { a.classList.toggle("on", i === k); if (i === k) a.setAttribute("aria-current", "true"); else a.removeAttribute("aria-current"); });
            const on = links[k];
            if (on) { const box = on.parentElement; if (on.offsetLeft < box.scrollLeft || on.offsetLeft + on.offsetWidth > box.scrollLeft + box.clientWidth) box.scrollTo({ left: on.offsetLeft - 16, behavior: "smooth" }); }
            nav.classList.toggle("stuck", nav.getBoundingClientRect().top <= (document.body.classList.contains("pg-hd-up") ? 1 : 80));
        };
        addEventListener("scroll", () => { if (!raf) raf = requestAnimationFrame(upd); }, { passive: true });
        upd();
        $("[data-sn-top]", nav)?.addEventListener("click", (e) => { e.preventDefault(); scrollTo({ top: 0, behavior: "smooth" }); });
    }

    // ---------- Abschnitte der Spielseite (feste Reihenfolge, fehlende Blöcke entfallen) ----------
    function gameSections(g, of, posts, others) {
        const en = LANG === "en";
        const secs = [];
        const tagline = slideTagline(g);
        const facts = gameFacts(g, of);

        // 1) Über das Spiel – Textabschnitte (+ Kennzahlen, falls nicht schon im Steckbrief)
        const rts = of("richtext").filter(b => String(b.body || "").trim() || b.heading);
        const stats = facts.fromStats ? [] : of("stats").flatMap(b => b.items || []).filter(x => x && String(x.value || "").trim());
        if (rts.length || stats.length) secs.push({ id: "ueber", nav: t("sn_about"), html: (n) => {
            const [first, ...more] = rts;
            const kick = first ? plainText(first.kicker) : "";
            const kickAsLabel = kick && kick.length <= 26 && kick !== tagline;
            const stmt = kick && !kickAsLabel && kick !== tagline ? kick : "";
            return `${first ? `<div class="pg-wrap pg-ov">
                    <div class="pg-rv">${pgKick(n, kickAsLabel ? esc(kick) : t("sec_about"))}<h2 class="pg-h2">${esc(first.heading || fmtT("h_about_q", g.title))}</h2>${stmt ? `<p class="pg-stmt">${esc(stmt)}</p>` : ""}</div>
                    <div class="pg-prose pg-rv">${md(first.body || "")}</div>
                </div>` : `<div class="pg-wrap">${pgSh(n, t("sec_about"), esc(fmtT("h_about_q", g.title)))}</div>`}
                ${more.map(rt => `<div class="pg-wrap pg-ov pg-ov-more">
                    <div class="pg-rv">${rt.kicker ? `<span class="pg-kicker">${esc(plainText(rt.kicker))}</span>` : ""}${rt.heading ? `<h3 class="pg-h3">${esc(rt.heading)}</h3>` : ""}</div>
                    <div class="pg-prose pg-rv">${md(rt.body || "")}</div></div>`).join("")}
                ${stats.length ? `<div class="pg-wrap"><div class="pg-gstats pg-rv" style="--n:${Math.min(4, stats.length)}">${stats.map(x => `<div><b>${esc(x.value)}</b><span>${esc(x.label || "")}</span></div>`).join("")}</div></div>` : ""}`;
        } });

        // 2) Selbst spielen – Demo & Downloads (+ spielbare Web-Demo nach Zustimmung)
        const dls = of("download").filter(d => d.embedUrl || (d.builds || []).some(x => x && (x.url || x.file)));
        if (dls.length) secs.push({ id: "spielen", nav: t("sn_play"), html: (n) => `<div class="pg-wrap">
                ${pgSh(n, t("sec_play"), esc(dls[0].heading || t("h_play")), dls[0].text ? esc(dls[0].text) : "")}
                ${dls.map((d, i) => `${i && d.heading ? `<h3 class="pg-h3 pg-sub">${esc(d.heading)}</h3>` : ""}${i && d.text ? `<p class="pg-lead pg-sub-lead">${esc(d.text)}</p>` : ""}${pgEmbed(d)}${pgBuilds(d)}`).join("")}
            </div>` });

        // 3) Systemanforderungen
        const reqs = of("requirements").filter(r => ["min", "rec"].some(k => r[k] && ["os", "cpu", "ram", "gpu", "storage"].some(f => String(r[k][f] || "").trim())));
        if (reqs.length) secs.push({ id: "technik", nav: "", html: (n) => `<div class="pg-wrap">
                ${pgSh(n, t("sec_tech"), esc(reqs[0].heading || (en ? "System requirements" : "Systemanforderungen")), reqs[0].note ? esc(reqs[0].note) : "")}
                ${reqs.map(r => `<div class="pg-reqs">${pgReqCol(r.min, en ? "Minimum" : "Minimal")}${pgReqCol(r.rec, en ? "Recommended" : "Empfohlen")}</div>`).join("")}
            </div>` });

        // 4) Einblicke – Trailer, Videos & Bilder
        const vids = of("trailer").filter(tb => tb.youtube || tb.url || tb.file);
        const imgs = of("gallery").flatMap(b => (b.images || []).map(im => (typeof im === "string" ? { src: im, alt: "" } : { src: im && im.image, alt: (im && im.alt) || "" }))).filter(x => x.src);
        if (vids.length || imgs.length) secs.push({ id: "einblicke", nav: t("sn_media"), html: (n) => {
            const gh = of("gallery").find(b => b.heading);
            const h = vids.length > 1 ? t("h_media_multi") : ((vids[0] && vids[0].heading) || (gh && gh.heading) || t("h_media_one"));
            const shot = (im, i) => `<button type="button" class="pg-shot pg-rv" data-lb data-lb-group="g-media" data-lb-index="${i}" aria-label="${esc(im.alt || (en ? "Enlarge image" : "Bild vergrößern"))}"><img src="${esc(im.src)}" alt="${esc(im.alt)}" loading="lazy" style="object-position:${focusPos(im.src)}"></button>`;
            let body;
            if (vids.length === 1 && imgs.length >= 3) {
                body = `<div class="pg-bento">${pgVideo(vids[0], false)}${imgs.slice(0, 3).map(shot).join("")}</div>`
                    + (imgs.length > 3 ? `<div class="pg-gal">${imgs.slice(3).map((im, i) => shot(im, i + 3)).join("")}</div>` : "");
            } else {
                body = (vids.length ? `<div class="pg-vids${vids.length > 1 ? " two" : ""}">${vids.map(v => pgVideo(v, vids.length > 1)).join("")}</div>` : "")
                    + (imgs.length ? `<div class="pg-gal${vids.length ? "" : " solo"}">${imgs.map(shot).join("")}</div>` : "");
            }
            return `<div class="pg-wrap">${pgSh(n, t("sec_media"), esc(h))}${body}</div>`;
        } });

        // 5) Features – Kacheln (+ Welt-Teaser)
        const fb = of("features").filter(b => (b.items || []).some(x => x && (x.title || x.text)));
        const lore = of("lore").map(b => loreTeaser(b, g)).filter(Boolean);
        if (fb.length || lore.length) secs.push({ id: "features", nav: t("sn_features"), html: (n) => `<div class="pg-wrap">
                ${pgSh(n, t("sec_features"), esc((fb[0] && fb[0].heading) || t("h_features")))}
                ${fb.map((b, bi) => {
                    const items = (b.items || []).filter(x => x && (x.title || x.text));
                    return `${bi && b.heading ? `<h3 class="pg-h3 pg-sub">${esc(b.heading)}</h3>` : ""}<div class="pg-feats" data-n="${items.length >= 4 && items.length % 3 !== 0 ? 4 : Math.min(3, items.length)}">${items.map(f =>
                        `<div class="pg-feat pg-rv"><span class="pg-icn pg-emo">${esc(f.icon || "✦")}</span><h3>${esc(f.title || "")}</h3>${f.text ? `<p>${esc(f.text)}</p>` : ""}</div>`).join("")}</div>`;
                }).join("")}
                ${lore.join("")}
            </div>` });

        // 6) Stimmen – Zitate & Reviews
        const qs2 = of("quotes").filter(b => (b.items || []).some(x => x && x.text));
        if (qs2.length) secs.push({ id: "stimmen", nav: "", html: (n) => `<div class="pg-wrap">
                ${pgSh(n, t("sec_quotes"), esc(qs2[0].heading || t("h_quotes")))}
                <div class="pg-quotes">${qs2.flatMap(b => b.items || []).filter(x => x && x.text).map(q => `<figure class="pg-quote pg-rv"><blockquote>${esc(q.text)}</blockquote>${q.author || q.source ? `<figcaption>${esc(q.author || "")}${q.source ? `<span>${esc(q.source)}</span>` : ""}</figcaption>` : ""}</figure>`).join("")}</div>
            </div>` });

        // 7) Countdown
        const cds = of("countdown").filter(c => c.date && !isNaN(Date.parse(String(c.date).includes("T") ? c.date : String(c.date).replace(" ", "T"))));
        if (cds.length) secs.push({ id: "countdown", nav: "", html: (n) => `<div class="pg-wrap">${cds.map(c => {
                const raw = String(c.date).includes("T") ? c.date : String(c.date).replace(" ", "T");
                const units = [["d", en ? "days" : "Tage"], ["h", en ? "hrs" : "Std"], ["m", "Min"], ["s", en ? "sec" : "Sek"]];
                return `<div class="pg-cd pg-rv">${pgKick(n, t("sec_countdown"))}<h2 class="pg-h2">${esc(c.heading || (en ? "Release in" : "Release in"))}</h2>${c.label ? `<p class="pg-lead">${esc(c.label)}</p>` : ""}
                    <div class="pg-cd-grid" data-countdown="${Date.parse(raw)}">${units.map(([k, l]) => `<div><b data-cd="${k}">--</b><span>${l}</span></div>`).join("")}</div>
                    <div class="cd-done pg-cd-done hidden"></div></div>`;
            }).join("")}</div>` });

        // 8) Roadmap – Zeitleiste (ohne Prozent); bei „Pausiert" Hinweis statt Termine
        const rms = of("roadmap").filter(b => (b.items || []).some(x => x && x.title));
        if (rms.length) secs.push({ id: "roadmap", nav: t("sn_roadmap"), html: (n) => {
            const paused = g.status === "paused";
            const rh = String(rms[0].heading || "").trim();
            const lab = { done: en ? "Done" : "Erledigt", active: en ? "In progress" : "In Arbeit", planned: en ? "Planned" : "Geplant" };
            const ic = { done: "check", active: "bolt", planned: "flag" };
            const note = paused ? `<div class="pg-note"><span aria-hidden="true">⏸️</span><span>${esc(plainText(g.pauseNote) || fmtT("paused_default", g.title))}${posts[0] ? `<br><a class="pg-link" href="devlog.php?slug=${encodeURIComponent(posts[0].slug || "")}">${t("to_post")} ${pgIc("arrow")}</a>` : ""}</span></div>` : "";
            return `<div class="pg-wrap pg-roadmap">
                <div class="pg-rv">${pgKick(n, t("sec_roadmap"))}<h2 class="pg-h2">${esc(rh && !/^roadmap$/i.test(rh) ? rh : t("h_roadmap"))}</h2>
                    <p class="pg-lead">${t(paused ? "lead_roadmap_paused" : "lead_roadmap")}</p>${note}</div>
                <div>${rms.map((b, bi) => `${bi && b.heading ? `<h3 class="pg-h3 pg-sub">${esc(b.heading)}</h3>` : ""}<ol class="pg-tl pg-rv${paused ? " muted" : ""}">${(b.items || []).filter(x => x && x.title).map(m => {
                    const st = ["done", "active", "planned"].includes(m.status) ? m.status : "planned";
                    const date = paused && st === "planned" ? "" : plainText(m.date);
                    return `<li class="${st}"><span class="pg-dot">${pgIc(ic[st])}</span><span><small>${date ? esc(date) + " · " : ""}${lab[st]}</small><b>${esc(m.title)}</b>${m.text ? `<p>${esc(m.text)}</p>` : ""}</span></li>`;
                }).join("")}</ol>`).join("")}</div>
            </div>`;
        } });

        // 9) Mitmachen – Warteliste, Vorschläge, Store-Widget, Aufruf
        const wls = of("waitlist"), sgs = of("suggestions");
        const sws = of("storewidget").map(pgStoreWidget).filter(Boolean);
        const ctas = of("cta").filter(c => isRealLink(c.url) || /^https?:\/\//i.test(String(g.wishlistUrl || "")));
        if (wls.length || sgs.length || sws.length || ctas.length) secs.push({ id: "mitmachen", nav: t("sn_join"), html: (n) => {
            const cards = wls.map((b, i) => wlCard(b, g, i)).concat(sgs.map((b, i) => suggCard(b, g, i)));
            return `<div class="pg-wrap">
                ${pgSh(n, t("sec_join"), esc(t("h_join")), esc(t("lead_join")))}
                ${cards.length ? `<div class="pg-mit${cards.length === 1 ? " solo" : ""}">${cards.join("")}</div>` : ""}
                ${sws.join("")}
                ${ctas.map(c => { const href = isRealLink(c.url) ? c.url : g.wishlistUrl; return `<div class="pg-ctaband pg-rv"><div><h3>${esc(c.heading || (en ? "Ready for the adventure?" : "Bereit für das Abenteuer?"))}</h3>${c.text ? `<p>${esc(c.text)}</p>` : ""}</div>
                    <a class="pg-btn pg-btn-p" href="${esc(href)}"${/^https?:/i.test(href) ? ' target="_blank" rel="noopener"' : ""}>${esc(c.label || t("wishlist"))} ${pgIc("arrow")}</a></div>`; }).join("")}
            </div>`;
        } });

        // 10) FAQ
        const fq = of("faq").flatMap(b => (b.items || []).filter(x => x && x.question));
        if (fq.length) secs.push({ id: "faq", nav: t("sn_faq"), html: (n) => {
            const fh = String((of("faq")[0] || {}).heading || "").trim();
            return `<div class="pg-wrap">${pgSh(n, "FAQ", esc(fh && !/^faq$/i.test(fh) ? fh : t("h_faq")))}
                <div class="pg-faq">${fq.map(q => `<details class="pg-rv"><summary>${esc(q.question)}</summary><div class="pg-prose">${md(q.answer || "")}</div></details>`).join("")}</div></div>`;
        } });

        // 11) Devlog zu diesem Spiel
        if (posts.length) secs.push({ id: "devlog", nav: t("sn_devlog"), html: (n) => `<div class="pg-wrap">
                ${pgSh(n, "Devlog", esc(fmtT("h_devlog_game", g.title)), "", posts.length > 3 ? `<a class="pg-btn pg-btn-s pg-btn-sm" href="devlog.php?game=${encodeURIComponent(g.slug || "")}">${t("all_posts")} ${pgIc("arrow")}</a>` : "")}
                <div class="pg-posts">${posts.slice(0, 3).map(p => homePost(p, g, "")).join("")}${posts.length < 3 ? `<a class="pg-morecard pg-rv" href="devlog.php"><span class="pg-icn">${pgIc("news")}</span><h3>${t("allposts_h")}</h3><p>${t("allposts_p")}</p><span class="pg-link">${t("to_devlog")} ${pgIc("arrow")}</span></a>` : ""}</div>
            </div>` });

        // 12) Weitere Spiele
        if (others.length) secs.push({ id: "mehr", nav: "", html: (n) => `<div class="pg-wrap">
                ${pgSh(n, t("sec_more"), esc(t("h_more")))}
                <div class="pg-mgs" data-n="${Math.min(3, others.length)}">${others.map(o => `<a class="pg-mg pg-rv" href="${gameHref(o)}" style="${accentStyle(o)}">
                    ${o.cover ? `<img src="${esc(o.cover)}" alt="" loading="lazy" style="object-position:${focusPos(o.cover)}">` : ""}${pgChip(o.status)}
                    ${logoPath(o) ? logoFit(o, 16000, 96, "", true) : `<b class="pg-mg-title">${esc(o.title || "")}</b>`}
                    <span class="pg-link">${t("to_game2")} ${pgIc("arrow")}</span></a>`).join("")}</div>
            </div>` });
        return secs;
    }

    // Video: YouTube erst nach Zustimmung (Klick-Platzhalter), sonst eigene Datei
    function pgVideo(b, caption) {
        let embed = "";
        const url = String(b.youtube || b.url || "");
        const yt = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{11})/);
        if (yt) {
            const needConsent = (DATA.studio && DATA.studio.cookie && DATA.studio.cookie.enabled !== false) && !pgConsentExternal();
            if (needConsent) {
                const poster = b.poster ? esc(b.poster) : `https://i.ytimg.com/vi/${yt[1]}/hqdefault.jpg`;
                embed = `<div class="yt-consent absolute inset-0" data-yt-embed="${yt[1]}" style="background-image:url('${poster}')">
                    <div class="yt-consent-inner">
                        <span class="pg-play" aria-hidden="true">${pgIc("play")}</span>
                        <p>${esc(t("yt_consent"))}</p>
                        <div class="yt-consent-btns">
                            <button class="pg-btn pg-btn-p pg-btn-sm" data-yt-load>${esc(t("yt_load"))}</button>
                            <button class="pg-btn pg-btn-s pg-btn-sm" data-consent="all">${esc(t("yt_load_always"))}</button>
                        </div>
                    </div></div>`;
            } else {
                embed = `<iframe class="absolute inset-0 w-full h-full" src="https://www.youtube-nocookie.com/embed/${yt[1]}" title="${esc(b.heading || "Trailer")}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            }
        } else if (b.file) {
            embed = `<video class="absolute inset-0 w-full h-full object-cover" controls preload="metadata" ${b.poster ? `poster="${esc(b.poster)}"` : ""}><source src="${esc(b.file)}"></video>`;
        }
        if (!embed) return "";
        return `<figure class="pg-vidbox pg-rv"><div class="pg-vid">${embed}</div>${caption && b.heading ? `<figcaption>${esc(b.heading)}</figcaption>` : ""}</figure>`;
    }

    // Download-Pakete & spielbare Web-Demo (erst nach Zustimmung)
    function pgBuilds(b) {
        const PLAT = { windows: ["🪟", "Windows"], mac: ["🍎", "macOS"], linux: ["🐧", "Linux"], android: ["🤖", "Android"], web: ["🌐", "Web"], other: ["📦", "Download"] };
        const list = (b.builds || []).filter(x => x && (x.url || x.file));
        if (!list.length) return "";
        return `<div class="pg-builds">${list.map(d => {
            const p = PLAT[d.platform] || PLAT.other, ext = !!d.url;
            const meta = [d.version, d.size, p[1]].filter(Boolean).map(plainText).join(" · ");
            return `<a class="pg-build pg-rv" href="${esc(d.url || d.file)}"${ext ? ' target="_blank" rel="noopener"' : " download"}>
                <span class="pg-icn pg-emo">${p[0]}</span><span class="pg-build-tx"><b>${esc(d.label || p[1])}</b>${meta ? `<small>${esc(meta)}</small>` : ""}</span>
                <span class="pg-build-dl" aria-hidden="true">${pgIc(ext ? "ext" : "dl")}</span><span class="pg-sr">${ext ? "" : t("download")}</span></a>`;
        }).join("")}</div>`;
    }
    function pgEmbed(b) {
        if (!b.embedUrl) return "";
        const h = Math.min(900, Math.max(200, +b.embedHeight || 540));
        const needConsent = (DATA.studio && DATA.studio.cookie && DATA.studio.cookie.enabled !== false) && !pgConsentExternal();
        const inner = !needConsent
            ? `<iframe class="absolute inset-0 w-full h-full" src="${esc(b.embedUrl)}" title="Web-Demo" allow="autoplay; fullscreen; gamepad; clipboard-write" allowfullscreen></iframe>`
            : `<div class="yt-consent absolute inset-0" data-embed-holder data-embed-src="${esc(b.embedUrl)}">
                   <div class="yt-consent-inner"><p>${esc(t("yt_consent"))}</p>
                       <div class="yt-consent-btns">
                           <button class="pg-btn pg-btn-p pg-btn-sm" data-embed-load>${pgIc("play")} ${LANG === "en" ? "Play demo" : "Demo starten"}</button>
                           <button class="pg-btn pg-btn-s pg-btn-sm" data-consent="all">${esc(t("yt_load_always"))}</button>
                       </div></div></div>`;
        return `<div class="pg-embed pg-rv" style="height:${h}px">${inner}</div>`;
    }
    function pgStoreWidget(b) {
        let src = "", h = 190, maxW = 646;
        if (b.platform === "itch" && b.itchEmbed) { src = b.itchEmbed; h = Math.min(900, Math.max(150, +b.height || 167)); maxW = 560; }
        else if (b.steamAppId) { const id = String(b.steamAppId).replace(/\D/g, ""); if (id) src = `https://store.steampowered.com/widget/${id}/`; }
        if (!src) return "";
        const needConsent = (DATA.studio && DATA.studio.cookie && DATA.studio.cookie.enabled !== false) && !pgConsentExternal();
        const inner = !needConsent
            ? `<iframe class="absolute inset-0 w-full h-full" src="${esc(src)}" frameborder="0" title="Store-Widget"></iframe>`
            : `<div class="yt-consent absolute inset-0" data-embed-holder data-embed-src="${esc(src)}">
                   <div class="yt-consent-inner"><p>${esc(t("yt_consent"))}</p>
                       <div class="yt-consent-btns">
                           <button class="pg-btn pg-btn-p pg-btn-sm" data-embed-load>${LANG === "en" ? "Load widget" : "Widget laden"}</button>
                           <button class="pg-btn pg-btn-s pg-btn-sm" data-consent="all">${esc(t("yt_load_always"))}</button>
                       </div></div></div>`;
        return `<div class="pg-widget pg-rv">${b.heading ? `<h3 class="pg-h3">${esc(b.heading)}</h3>` : ""}<div class="pg-embed" style="max-width:${maxW}px;height:${h}px">${inner}</div></div>`;
    }
    function pgReqCol(spec, title) {
        const en = LANG === "en";
        const ROWS = [["os", en ? "OS" : "Betriebssystem"], ["cpu", en ? "Processor" : "Prozessor"], ["ram", en ? "Memory" : "Arbeitsspeicher"], ["gpu", en ? "Graphics" : "Grafikkarte"], ["storage", en ? "Storage" : "Speicherplatz"]];
        if (!spec || !ROWS.some(([k]) => String(spec[k] || "").trim())) return "";
        return `<div class="pg-card pg-req pg-rv"><h3>${esc(title)}</h3><dl>${ROWS.filter(([k]) => String(spec[k] || "").trim()).map(([k, l]) => `<div><dt>${l}</dt><dd>${esc(spec[k])}</dd></div>`).join("")}</dl></div>`;
    }

    /* ---------- Logo ohne durchsichtigen Rand ----------
       Viele Logo-Dateien haben rundum durchsichtigen Rand. Gezeigt wird nur der sichtbare Teil (Ausschnitt,
       die Datei bleibt unverändert) – so steht das Logo exakt bündig am Text und es entstehen keine Lücken.
       Den sichtbaren Bereich misst das Admin einmal und speichert ihn als „logoBox“
       (x0,y0,x1,y1 als Anteile, Seitenverhältnis, Prüfsumme der Datei-Adresse). Fehlt er, misst die
       Website selbst (einmal je Browser, gemerkt im localStorage). */
    const LOGO_BOX_LS = "pg_logobox_v1";
    const logoBoxMem = Object.create(null), logoBoxJobs = Object.create(null);
    const logoPath = (g) => String((g && g.logo) || "").split("#")[0].trim();
    function parseLogoBox(raw, src) {
        const v = String(raw || "").split(",");
        if (v.length !== 6 || !src || v[5] !== trHash(src)) return null;
        const [x0, y0, x1, y1, ar] = v.slice(0, 5).map(Number);
        if (![x0, y0, x1, y1, ar].every(Number.isFinite) || x0 < 0 || y0 < 0 || x1 > 1.0001 || y1 > 1.0001
            || x1 - x0 < 0.01 || y1 - y0 < 0.01 || ar <= 0 || ar > 100) return null;
        return { x0, y0, x1: Math.min(1, x1), y1: Math.min(1, y1), ar };
    }
    // Ergebnis: Ausschnitt {x0,y0,x1,y1,ar} · null = kein Rand / nicht messbar · undefined = noch unbekannt
    function logoBoxOf(g) {
        const src = logoPath(g); if (!src) return null;
        const own = parseLogoBox(g.logoBox, src); if (own) return own;
        if (src in logoBoxMem) return logoBoxMem[src];
        try {
            const c = JSON.parse(localStorage.getItem(LOGO_BOX_LS) || "{}")[src];
            if (c && Date.now() - (c.t || 0) < 14 * 864e5) return (logoBoxMem[src] = c.b === "-" ? null : parseLogoBox(c.b, src));
        } catch (e) {}
        return undefined;
    }
    function measureLogoBox(src) {
        if (logoBoxJobs[src]) return logoBoxJobs[src];
        return (logoBoxJobs[src] = (async () => {
            let box = null, blobUrl = null;
            try {
                let url = src, ar = 0;
                if (/\.svg(\?|$)/i.test(src)) {
                    // SVG: Seitenverhältnis aus der Datei, feste Pixelgröße setzen (sonst zeichnen manche Browser nichts)
                    const doc = new DOMParser().parseFromString(await (await fetch(src, { credentials: "same-origin" })).text(), "image/svg+xml");
                    const svg = doc.documentElement;
                    if (svg && svg.nodeName.toLowerCase() === "svg") {
                        const num = (v) => { const m = String(v || "").trim().match(/^([\d.]+)(px)?$/i); const n = m ? parseFloat(m[1]) : 0; return n > 0 ? n : 0; };
                        const vb = (svg.getAttribute("viewBox") || "").trim().split(/[\s,]+/).map(Number);
                        const w = num(svg.getAttribute("width")), h = num(svg.getAttribute("height"));
                        ar = w && h ? w / h : (vb.length === 4 && vb[2] > 0 && vb[3] > 0 ? vb[2] / vb[3] : 0);
                        if (ar) {
                            svg.setAttribute("width", ar >= 1 ? 2000 : Math.round(2000 * ar));
                            svg.setAttribute("height", ar >= 1 ? Math.round(2000 / ar) : 2000);
                            url = blobUrl = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(doc)], { type: "image/svg+xml" }));
                        }
                    }
                }
                const im = await new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = url; });
                if (!ar && im.naturalWidth && im.naturalHeight) ar = im.naturalWidth / im.naturalHeight;
                if (ar) {
                    let W = ar >= 1 ? 1600 : Math.round(1600 * ar), H = ar >= 1 ? Math.round(1600 / ar) : 1600;
                    if (!blobUrl && im.naturalWidth && im.naturalWidth < W) { W = im.naturalWidth; H = im.naturalHeight; }   // Pixelbild: nie hochrechnen
                    W = Math.max(1, W); H = Math.max(1, H);
                    const c = document.createElement("canvas"); c.width = W; c.height = H;
                    const cx = c.getContext("2d", { willReadFrequently: true });
                    cx.drawImage(im, 0, 0, W, H);
                    const d = cx.getImageData(0, 0, W, H).data;
                    let x0 = W, y0 = H, x1 = -1, y1 = -1;
                    for (let y = 0; y < H; y++) { const row = y * W * 4; for (let x = 0; x < W; x++) if (d[row + x * 4 + 3] > 10) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; } }
                    if (x1 >= 0 && (x0 > 0 || y0 > 0 || x1 < W - 1 || y1 < H - 1)) box = { x0: x0 / W, y0: y0 / H, x1: (x1 + 1) / W, y1: (y1 + 1) / H, ar };
                }
            } catch (e) { box = null; }
            if (blobUrl) URL.revokeObjectURL(blobUrl);
            logoBoxMem[src] = box;
            try {
                const all = JSON.parse(localStorage.getItem(LOGO_BOX_LS) || "{}");
                all[src] = { b: box ? fmtLogoBox(box, src) : "-", t: Date.now() };
                const keys = Object.keys(all); if (keys.length > 40) keys.slice(0, keys.length - 40).forEach(k => delete all[k]);
                localStorage.setItem(LOGO_BOX_LS, JSON.stringify(all));
            } catch (e) {}
            return box;
        })());
    }
    const fmtLogoBox = (b, src) => [b.x0, b.y0, b.x1, b.y1].map(n => n.toFixed(4)).concat(b.ar.toFixed(4), trHash(src)).join(",");
    // vor dem Anzeigen messen (nur falls nötig; höchstens ms Millisekunden warten)
    async function ensureLogoBox(g, ms = 1500) {
        const src = logoPath(g);
        if (!src || logoBoxOf(g) !== undefined) return;
        await Promise.race([measureLogoBox(src), new Promise(r => setTimeout(r, ms))]);
    }
    // Logo-HTML: h = CSS-Höhe des ganzen Bildes (wie bisher). Mit Ausschnitt bleibt das sichtbare Logo
    // genauso groß – nur der durchsichtige Rand fällt weg.
    function logoMarkup(g, { h, cls = "", attrs = "", style = "" }) {
        const src = logoPath(g), box = logoBoxOf(g);
        if (!box) return `<img ${attrs} src="${esc(src)}" alt="${esc(g.title || "")}" class="${cls}" style="height:${h};${style}">`;
        const fw = box.x1 - box.x0, fh = box.y1 - box.y0, va = box.ar * fw / fh;
        const vh = `calc(${h} * ${fh.toFixed(4)})`;
        return `<span ${attrs} data-logo-crop class="pg-logo-crop ${cls}" role="img" aria-label="${esc(g.title || "")}" style="width:min(100%, calc(${vh} * ${va.toFixed(4)}));aspect-ratio:${va.toFixed(4)};${style}">`
            + `<img src="${esc(src)}" alt="" style="width:${(100 / fw).toFixed(4)}%;left:${(-100 * box.x0 / fw).toFixed(4)}%;top:${(-100 * box.y0 / fh).toFixed(4)}%"></span>`;
    }

    /* ---------- Interaktive Block-Helfer ---------- */
    let _lbBound = false, _lbList = [], _lbIdx = 0;
    function initLightbox() {
        if (!$("#lightbox")) {
            document.body.insertAdjacentHTML("beforeend", `
                <div id="lightbox" class="hidden" aria-hidden="true">
                    <button class="lb-close" data-lb-close aria-label="Schließen">✕</button>
                    <button class="lb-nav lb-prev" data-lb-prev aria-label="Zurück">‹</button>
                    <img class="lb-img" alt="">
                    <button class="lb-nav lb-next" data-lb-next aria-label="Weiter">›</button>
                    <div class="lb-count"></div>
                </div>`);
        }
        if (_lbBound) return; _lbBound = true;
        const lb = $("#lightbox"), img = $(".lb-img", lb), counter = $(".lb-count", lb);
        const show = () => { img.src = _lbList[_lbIdx] || ""; counter.textContent = `${_lbIdx + 1} / ${_lbList.length}`; };
        const open = (group, idx) => {
            _lbList = $$(`[data-lb][data-lb-group="${group}"]`).sort((a, b) => a.dataset.lbIndex - b.dataset.lbIndex)
                .map(el => el.querySelector("img")?.src).filter(Boolean);
            _lbIdx = idx; show();
            lb.classList.remove("hidden"); document.body.classList.add("menu-open");
        };
        const close = () => { lb.classList.add("hidden"); document.body.classList.remove("menu-open"); };
        const step = (d) => { _lbIdx = (_lbIdx + d + _lbList.length) % _lbList.length; show(); };
        document.addEventListener("click", (e) => {
            const trig = e.target.closest("[data-lb]");
            if (trig) { e.preventDefault(); open(trig.dataset.lbGroup, +trig.dataset.lbIndex); return; }
            if (e.target.closest("[data-lb-close]") || e.target.id === "lightbox") return close();
            if (e.target.closest("[data-lb-next]")) return step(1);
            if (e.target.closest("[data-lb-prev]")) return step(-1);
        });
        addEventListener("keydown", (e) => {
            if (lb.classList.contains("hidden")) return;
            if (e.key === "Escape") close(); else if (e.key === "ArrowRight") step(1); else if (e.key === "ArrowLeft") step(-1);
        });
    }

    function initCountdowns(root = document) {
        const els = $$("[data-countdown]", root);
        els.forEach(grid => {
            const ts = parseInt(grid.dataset.countdown, 10);
            const done = grid.parentElement.querySelector(".cd-done");
            const tick = () => {
                if (!ts) return;
                let diff = Math.floor((ts - Date.now()) / 1000);
                if (diff <= 0) {
                    grid.style.display = "none";
                    if (done) { done.classList.remove("hidden"); done.textContent = done.dataset.done || (LANG === "en" ? "Out now! 🎉" : "Jetzt verfügbar! 🎉"); }
                    return false;
                }
                const d = Math.floor(diff / 86400); diff -= d * 86400;
                const h = Math.floor(diff / 3600); diff -= h * 3600;
                const m = Math.floor(diff / 60); const s = diff - m * 60;
                const set = (k, v) => { const el = grid.querySelector(`[data-cd="${k}"]`); if (el) el.textContent = String(v).padStart(2, "0"); };
                set("d", d); set("h", h); set("m", m); set("s", s);
                return true;
            };
            if (tick() !== false) { const iv = setInterval(() => { if (tick() === false) clearInterval(iv); }, 1000); }
        });
    }

    function initCounters(root = document) {
        if (!("IntersectionObserver" in window)) return;
        const io = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (!en.isIntersecting) return;
                io.unobserve(en.target);
                const el = en.target, raw = el.textContent.trim();
                const m = raw.match(/^(\D*)(\d[\d,]*)(\D*)$/);
                if (!m) return;
                const target = parseFloat(m[2].replace(/,/g, "")), pre = m[1], suf = m[3];
                const t0 = performance.now(), dur = 1200;
                const step = (t) => {
                    const p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
                    el.textContent = pre + Math.round(target * e) + suf;
                    if (p < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });
        }, { threshold: 0.4 });
        $$("[data-count]", root).forEach(el => io.observe(el));
    }

    /* =========================================================
       6) DEVLOG / PATCH NOTES
       ========================================================= */
    async function renderDevlog() {
        await loadData("games", "patchnotes");
        const games = (DATA.games && DATA.games.games) || [];
        const posts = ((DATA.patchnotes && DATA.patchnotes.posts) || [])
            .filter(isPublished).slice().sort((a, b) => (b.date || "").localeCompare(a.date || ""));
        const slug = qs.get("slug");

        if (slug) return renderDevlogSingle(posts.find(p => p.slug === slug), games);

        const list = $("[data-devlog='list']");
        if (!list) return;
        if (!posts.length) { list.innerHTML = `<p class="text-zinc-500">${t("msg_no_posts")}</p>`; return; }

        // Filter-Chips pro Spiel
        const filterWrap = $("[data-devlog='filters']");
        const activeGame = qs.get("game");
        if (filterWrap) {
            filterWrap.innerHTML = [`<a href="devlog.php" class="badge px-4 py-1.5 rounded-full border ${!activeGame ? "bg-white text-black border-white" : "border-white/15 text-zinc-300 hover:border-white/40"}">${t("all")}</a>`]
                .concat(games.map(g => `<a href="devlog.php?game=${esc(g.slug)}" class="badge px-4 py-1.5 rounded-full border ${activeGame === g.slug ? "bg-white text-black border-white" : "border-white/15 text-zinc-300 hover:border-white/40"}">${esc(g.title)}</a>`)).join("");
        }

        const shown = activeGame ? posts.filter(p => p.game === activeGame) : posts;
        // Kommentar-Anzahlen je Beitrag (für 💬-Badge)
        let cmtCounts = {};
        try { const rc = await fetch("comments.php?counts=1"); cmtCounts = ((await rc.json()) || {}).counts || {}; } catch {}
        list.innerHTML = shown.map(p => {
            const g = games.find(x => x.slug === p.game);
            const cmtN = cmtCounts[p.slug] || 0;
            return `
            <a href="devlog.php?slug=${esc(p.slug)}" class="reveal tilt-card group relative block overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]" style="--accent:${esc(g?.accent || "#8b5cf6")}">
                <div class="card-glow"></div>
                <div class="grid md:grid-cols-[260px_1fr]">
                    <div class="relative aspect-video md:aspect-auto overflow-hidden">
                        ${p.cover ? `<img src="${esc(p.cover)}" alt="${esc(p.coverAlt || p.title || "")}" style="object-position:${focusPos(p.cover)}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
                                  : `<div class="w-full h-full grid-lines bg-white/[0.03] min-h-[160px]"></div>`}
                    </div>
                    <div class="p-7">
                        <div class="flex items-center gap-2 flex-wrap mb-3">
                            <span class="font-mono text-xs text-zinc-500">${esc(fmtDate(p.date))}</span>
                            ${g ? `<span class="badge px-2 py-0.5 rounded border border-white/10 text-zinc-400">${esc(g.title)}</span>` : ""}
                            ${p.version ? `<span class="badge text-[color:var(--accent)]">v${esc(p.version)}</span>` : ""}
                            ${cmtN ? `<span class="badge px-2 py-0.5 rounded border border-white/10 text-zinc-400">💬 ${cmtN}</span>` : ""}
                        </div>
                        <h2 class="font-display text-2xl font-extrabold text-white group-hover:text-gradient">${esc(p.title)}</h2>
                        ${p.excerpt ? `<p class="text-zinc-400 mt-2 line-clamp-2">${esc(p.excerpt)}</p>` : ""}
                        <div class="mt-4 font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)]">${t("read_more")}</div>
                    </div>
                </div>
            </a>`;
        }).join("");
        initTilt(list); observeReveals(list);
    }

    function renderDevlogSingle(p, games) {
        const root = $("[data-devlog='single']");
        const list = $("[data-devlog='list-wrap']");
        if (list) list.classList.add("hidden");
        if (!root) return;
        root.classList.remove("hidden");
        if (!p) { root.innerHTML = notFound(t("nf_text")); return; }
        const g = games.find(x => x.slug === p.game);
        if (g) applyAccent(g.accent, g.accent2);
        document.title = `${p.title} — PLANIGAMES Devlog`;
        root.innerHTML = `
            <div class="max-w-3xl mx-auto px-6 pt-36 pb-24">
                <a href="devlog.php" class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 hover:text-white">${t("back_all")}</a>
                <div class="flex items-center gap-2 flex-wrap mt-8 mb-5">
                    <span class="font-mono text-xs text-zinc-500">${esc(fmtDate(p.date))}</span>
                    <span class="font-mono text-xs text-zinc-600">· ${readingTime(p.body)} ${t("read_time")}</span>
                    ${g ? `<a href="game.php?slug=${esc(g.slug)}" class="badge px-2 py-0.5 rounded border border-white/10 text-zinc-300 hover:border-white/40">${esc(g.title)}</a>` : ""}
                    ${p.version ? `<span class="badge text-[color:var(--accent)]">v${esc(p.version)}</span>` : ""}
                    ${(p.tags || []).map(t => `<span class="badge text-zinc-600">#${esc(t)}</span>`).join("")}
                </div>
                <h1 class="font-display text-4xl md:text-6xl font-extrabold text-white leading-[1.05] mb-8">${esc(p.title)}</h1>
                ${p.cover ? `<img src="${esc(p.cover)}" alt="${esc(p.coverAlt || p.title || "")}" class="w-full rounded-2xl border border-white/10 mb-10">` : ""}
                <div class="prose-pg">${md(p.body || "")}</div>
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-4 mt-14 pt-8 border-t border-white/10">
                    ${reactionsBar()}
                    ${shareBar(p.title)}
                </div>
                ${commentsSection()}
                ${g && g.wishlistUrl ? `<div class="mt-16 pt-10 border-t border-white/10 text-center">
                    <a href="${esc(g.wishlistUrl)}" target="_blank" rel="noopener" class="btn-accent magnetic inline-block px-9 py-4 rounded-full font-semibold">${esc(g.title)} auf Steam wishlisten</a></div>` : ""}
            </div>`;
        initMagnetic();
        initShareCopy();
        initReactions(p.slug);
        initComments(p.slug);
    }

    function commentsSection() {
        const en = LANG === "en";
        return `
        <section class="mt-16 pt-10 border-t border-white/10" data-comments>
            <h2 class="font-display text-2xl md:text-3xl font-extrabold text-white mb-6">${en ? "Comments" : "Kommentare"}<span data-comments-count class="ml-3 align-middle text-base font-mono font-medium text-zinc-500"></span></h2>
            <div data-comments-list class="flex flex-col gap-4 mb-8"></div>
            <form data-comments-form class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 md:p-6">
                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden">
                <div class="flex flex-col sm:flex-row gap-3 mb-3">
                    <input name="name" required maxlength="60" placeholder="${en ? "Your name" : "Dein Name"}" class="flex-1 bg-black/30 border border-white/15 rounded-xl px-4 py-3 text-white placeholder:text-zinc-500 focus:border-[color:var(--accent)] outline-none">
                </div>
                <textarea name="body" required maxlength="3000" rows="4" placeholder="${en ? "Write a comment…" : "Schreib einen Kommentar…"}" class="w-full bg-black/30 border border-white/15 rounded-xl px-4 py-3 text-white placeholder:text-zinc-500 focus:border-[color:var(--accent)] outline-none"></textarea>
                <div class="flex items-center justify-between gap-3 mt-3 flex-wrap">
                    <span class="text-xs text-zinc-500">${en ? "Comments are reviewed before they appear." : "Kommentare werden vor der Veröffentlichung geprüft."}</span>
                    <button class="btn-accent magnetic px-7 py-3 rounded-full font-semibold">${en ? "Post comment" : "Kommentar senden"}</button>
                </div>
                <p data-comments-msg class="text-sm mt-3 hidden"></p>
            </form>
        </section>`;
    }

    async function initComments(slug) {
        const wrap = $("[data-comments]");
        if (!wrap) return;
        const en = LANG === "en";
        const list = wrap.querySelector("[data-comments-list]");
        const form = wrap.querySelector("[data-comments-form]");
        const msg = wrap.querySelector("[data-comments-msg]");
        const countEl = wrap.querySelector("[data-comments-count]");
        const renderC = (c, pending) => `<div class="cmt-item${pending ? " cmt-new" : ""} rounded-2xl border ${pending ? "border-[color:var(--accent)]/50" : "border-white/8"} bg-white/[0.02] p-5">
            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span class="font-semibold text-white">${esc(c.name)}</span>
                <span class="font-mono text-xs text-zinc-600">${pending ? (en ? "just now" : "gerade eben") : esc(fmtDate(c.date))}</span>
                ${pending ? `<span class="font-mono text-[10px] uppercase tracking-widest text-[color:var(--accent)]">⏳ ${en ? "under review" : "wird geprüft"}</span>` : ""}
            </div>
            <p class="text-zinc-300 whitespace-pre-wrap leading-relaxed">${esc(c.body)}</p>
        </div>`;
        // bereits freigegebene Kommentare laden
        try {
            const r = await fetch(`comments.php?slug=${encodeURIComponent(slug)}`);
            const j = await r.json();
            const cs = (j && j.comments) || [];
            if (countEl) countEl.textContent = cs.length ? `(${cs.length})` : "";
            if (!cs.length) {
                list.innerHTML = `<p class="text-zinc-500 text-sm">${en ? "No comments yet — be the first!" : "Noch keine Kommentare — sei die*der Erste!"}</p>`;
            } else {
                const INITIAL = 5;
                const older = cs.slice(0, Math.max(0, cs.length - INITIAL));
                const recent = cs.slice(Math.max(0, cs.length - INITIAL));
                list.innerHTML = (older.length
                    ? `<button type="button" class="cmt-more" data-cmt-more>${en ? `Load ${older.length} earlier comments` : `${older.length} frühere Kommentare laden`}</button><div class="cmt-older" hidden>${older.map(c => renderC(c, false)).join("")}</div>`
                    : "") + recent.map(c => renderC(c, false)).join("");
                const moreBtn = list.querySelector("[data-cmt-more]");
                if (moreBtn) moreBtn.addEventListener("click", () => { const o = list.querySelector(".cmt-older"); if (o) { o.hidden = false; o.querySelectorAll(".cmt-item").forEach(el => el.classList.add("cmt-new")); } moreBtn.remove(); });
            }
        } catch { list.innerHTML = ""; }
        // Absenden
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const name = (form.querySelector("[name=name]") || {}).value || "";
            const body = (form.querySelector("[name=body]") || {}).value || "";
            const fd = new FormData(form);
            fd.append("slug", slug);
            const btn = form.querySelector("button");
            msg.className = "text-sm mt-3 text-zinc-400"; msg.textContent = en ? "Sending…" : "Wird gesendet…";
            if (btn) btn.disabled = true;
            try {
                const r = await fetch("comments.php", { method: "POST", body: fd });
                const j = await r.json();
                if (j.error) { msg.className = "text-sm mt-3 text-red-400"; msg.textContent = j.error; return; }
                form.reset();
                msg.className = "cmt-success text-sm mt-3";
                msg.innerHTML = `<span class="cmt-check">✓</span> ${en ? "Thanks! Your comment will appear after review." : "Danke! Dein Kommentar erscheint nach der Prüfung."}`;
                pgBadge("commenter");
                // eigene Vorschau (ausstehend) oben einfügen
                const empty = list.querySelector(":scope > p");
                if (empty) empty.remove();
                list.insertAdjacentHTML("afterbegin", renderC({ name, body, date: new Date().toISOString() }, true));
            } catch { msg.className = "text-sm mt-3 text-red-400"; msg.textContent = en ? "Something went wrong." : "Etwas ist schiefgelaufen."; }
            finally { if (btn) btn.disabled = false; }
        });
    }

    /* =========================================================
       7) RECHTLICHES (Impressum / Datenschutz)
       ========================================================= */
    async function renderLegal() {
        const data = await fetchJSON("data/legal.json") || {};
        const root = $("[data-legal]");
        if (!root) return;
        const doc = qs.get("doc");
        const section = (body) => `<div class="prose-pg">${md(body || "_Inhalt folgt._")}</div>`;
        let inner, title;
        if (doc === "datenschutz") { inner = section(data.datenschutz); title = "Datenschutz"; }
        else if (doc === "impressum") { inner = section(data.impressum); title = "Impressum"; }
        else {
            inner = section(data.impressum) + `<hr class="my-16 border-white/10">` + section(data.datenschutz);
            title = "Rechtliches";
        }
        document.title = `${title} — PLANIGAMES`;
        root.innerHTML = `<section class="px-6 pt-36 pb-24"><div class="max-w-3xl mx-auto reveal">
            <div class="flex gap-2 mb-10">
                <a href="rechtliches.html?doc=impressum" class="badge px-4 py-1.5 rounded-full border ${doc === "impressum" ? "bg-white text-black border-white" : "border-white/15 text-zinc-300 hover:border-white/40"}">Impressum</a>
                <a href="rechtliches.html?doc=datenschutz" class="badge px-4 py-1.5 rounded-full border ${doc === "datenschutz" ? "bg-white text-black border-white" : "border-white/15 text-zinc-300 hover:border-white/40"}">Datenschutz</a>
            </div>${inner}</div></section>`;
        observeReveals(root);
    }

    // ---------- Lore / Story-Seite (pro Spiel, Scrollytelling) – lore.html?slug=<spiel> ----------
    async function renderLore() {
        await loadData("games");
        const root = $("[data-lore]");
        if (!root) return;
        const en = LANG === "en";
        const games = (DATA.games && DATA.games.games) || [];
        const slug = String(qs.get("slug") || "").toLowerCase();
        const g = games.find(x => String(x.slug || "").toLowerCase() === slug) || null;
        if (!g && slug && await followRedirect("games", slug)) return;
        const d = (g && g.lore) || {};
        if (!g || !d.enabled) {
            document.title = (en ? "World" : "Welt") + " — PLANIGAMES";
            root.innerHTML = notFound(en ? "This world isn't available." : "Diese Welt ist nicht verfügbar.");
            return;
        }
        applyAccent(g.accent || "#8b5cf6", g.accent2 || "#22d3ee");   // an das Spiel anpassen
        setParticleColor(g.particleColor || g.accent);
        document.title = (d.title || g.title) + " — PLANIGAMES";
        const scenes = (Array.isArray(d.scenes) ? d.scenes : []).filter(s => s && (s.heading || s.text || s.image));
        const back = `<a href="game.php?slug=${esc(g.slug)}" class="lore-back">← ${esc(g.title)}</a>`;

        const hero = `<header class="lore-hero">
            ${d.hero ? `<div class="lore-hero-bg"><img src="${esc(d.hero)}" alt="" data-lore-px></div>` : ""}
            <div class="lore-hero-inner reveal">
                ${back}
                <h1 class="font-display text-4xl md:text-6xl font-extrabold text-white leading-[1.05] mt-4">${esc(d.title || (en ? "The world of " : "Die Welt von ") + g.title)}</h1>
                ${d.intro ? `<p class="mt-5 text-lg md:text-xl text-zinc-300 max-w-2xl mx-auto">${esc(d.intro)}</p>` : ""}
                ${scenes.length ? `<div class="lore-scroll-hint mt-10" aria-hidden="true">↓</div>` : ""}
            </div></header>`;

        const body = scenes.length
            ? scenes.map((s, i) => loreScene(s, i)).join("")
            : `<section class="px-6 py-24 text-center text-zinc-500">${en ? "The story will be told soon…" : "Die Geschichte wird bald erzählt …"}</section>`;

        const foot = `<div class="px-6 pb-24 text-center"><a href="game.php?slug=${esc(g.slug)}" class="btn-accent magnetic px-8 py-4 rounded-full font-semibold inline-block">← ${en ? "Back to " : "Zurück zu "}${esc(g.title)}</a></div>`;

        root.innerHTML = hero + `<div class="lore-scenes">${body}</div>` + foot;
        observeReveals(root); initMagnetic();
        initLoreParallax(root);
    }
    function loreScene(s, i) {
        let align = s.align || "auto";
        if (align === "auto") align = i % 2 === 0 ? "left" : "right";
        if (align === "full" && s.image) {
            return `<section class="lore-scene lore-full">
                <div class="lore-full-bg"><img src="${esc(s.image)}" alt="" loading="lazy" data-lore-px></div>
                <div class="lore-full-inner reveal">
                    ${s.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-4">${esc(s.heading)}</h2>` : ""}
                    <div class="prose-pg text-zinc-200">${md(s.text || "")}</div>
                </div></section>`;
        }
        const textCol = `<div class="lore-text reveal">
            ${s.heading ? `<h2 class="font-display text-3xl md:text-4xl font-extrabold text-white mb-4">${esc(s.heading)}</h2>` : ""}
            <div class="prose-pg text-zinc-300">${md(s.text || "")}</div></div>`;
        const imgCol = s.image ? `<div class="lore-img reveal"><img src="${esc(s.image)}" alt="${esc(s.heading || "")}" loading="lazy" data-lore-px></div>` : "";
        const right = align === "right";
        return `<section class="lore-scene lore-split ${right ? "img-right" : "img-left"}">${right ? textCol + imgCol : imgCol + textCol}</section>`;
    }
    function initLoreParallax(root) {
        const imgs = $$("[data-lore-px]", root);
        if (!imgs.length) return;
        let ticking = false;
        const upd = () => {
            const vh = innerHeight;
            imgs.forEach(img => {
                const r = img.getBoundingClientRect();
                if (r.bottom < -240 || r.top > vh + 240) return;
                const off = ((r.top + r.height / 2) - vh / 2) / vh;   // -0.5 … 0.5
                img.style.transform = `translate3d(0, ${(-off * 26).toFixed(1)}px, 0) scale(1.08)`;
            });
            ticking = false;
        };
        addEventListener("scroll", () => { if (!ticking) { ticking = true; requestAnimationFrame(upd); } }, { passive: true });
        addEventListener("resize", upd, { passive: true });
        upd();
    }

    async function renderPressKit() {
        await loadData("studio", "games");
        const s = DATA.studio || {};
        const p = s.presskit || {};
        const games = (DATA.games && DATA.games.games) || [];
        const root = $("[data-presskit]");
        if (!root) return;
        const contact = p.contactEmail || s.email || "";
        document.title = `Press Kit — ${s.name || "PLANIGAMES"}`;

        const facts = (p.facts || []).filter(f => f && (f.label || f.value)).map(f => `
            <div class="flex justify-between gap-4 py-3 border-b border-white/8">
                <span class="text-zinc-400">${esc(f.label || "")}</span>
                <span class="text-white font-medium text-right">${esc(f.value || "")}</span>
            </div>`).join("");

        const downloads = (p.downloads || []).filter(d => d && d.file).map(d => `
            <a href="${esc(d.file)}" download class="tilt-card group flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.02] p-5 hover:border-[color:var(--accent)] transition-colors">
                <div class="card-glow"></div>
                <span class="text-3xl shrink-0">📦</span>
                <span class="flex-1 min-w-0">
                    <span class="block font-semibold text-white">${esc(d.label || t("download"))}</span>
                    ${d.note ? `<span class="block text-sm text-zinc-400">${esc(d.note)}</span>` : ""}
                </span>
                <span class="badge text-[color:var(--accent)] whitespace-nowrap">${t("download")} ↓</span>
            </a>`).join("");

        const gameCards = games.map(g => `
            <div class="rounded-2xl border border-white/10 overflow-hidden bg-white/[0.02]">
                <div class="aspect-[16/9] overflow-hidden">${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.coverAlt || g.title)}" style="object-position:${focusPos(g.cover)}" class="w-full h-full object-cover">` : `<div class="w-full h-full grid-lines"></div>`}</div>
                <div class="p-5">
                    <div class="flex items-center gap-2 mb-2">${statusBadge(g.status)}</div>
                    <h3 class="font-display text-xl font-bold text-white">${esc(g.title)}</h3>
                    <p class="text-sm text-zinc-400 mt-1 line-clamp-3">${esc(g.tagline || "")}</p>
                    <a href="game.php?slug=${esc(g.slug)}" class="inline-block mt-4 badge text-[color:var(--accent)]">${t("to_game")}</a>
                </div>
            </div>`).join("");

        root.innerHTML = `
            <section class="px-6 pt-36 pb-16">
                <div class="max-w-5xl mx-auto">
                    <div class="font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] mb-4 reveal">${t("presskit")}</div>
                    <h1 class="font-display text-5xl md:text-7xl font-extrabold text-white mb-8 reveal">${esc(s.name || "PLANIGAMES")}</h1>
                    <div class="prose-pg max-w-2xl reveal">${md(p.intro || "")}</div>
                </div>
            </section>
            <section class="px-6 pb-20">
                <div class="max-w-5xl mx-auto grid lg:grid-cols-2 gap-12">
                    <div class="reveal">
                        <h2 class="font-display text-2xl font-bold text-white mb-5">${t("press_facts")}</h2>
                        <div>${facts || `<p class="text-zinc-500">—</p>`}</div>
                        ${contact ? `<h2 class="font-display text-2xl font-bold text-white mt-10 mb-3">${t("press_contact")}</h2>
                            <a href="mailto:${esc(contact)}" class="btn-accent magnetic inline-block px-7 py-3.5 rounded-full font-semibold text-sm">${esc(contact)}</a>` : ""}
                    </div>
                    <div class="reveal">
                        <h2 class="font-display text-2xl font-bold text-white mb-5">${t("press_downloads")}</h2>
                        <div class="flex flex-col gap-3">${downloads || `<p class="text-zinc-500">${LANG === "en" ? "No downloads yet." : "Noch keine Downloads hinterlegt."}</p>`}</div>
                    </div>
                </div>
            </section>
            ${games.length ? `<section class="px-6 pb-28"><div class="max-w-5xl mx-auto">
                <h2 class="font-display text-3xl md:text-4xl font-extrabold text-white mb-8 reveal">${t("press_games")}</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">${gameCards}</div></div></section>` : ""}`;
        initTilt(root); observeReveals(root);
    }

    async function renderContact() {
        await loadData("studio");
        const s = DATA.studio || {};
        const c = s.contact || {};
        const root = $("[data-contact]");
        if (!root) return;
        document.title = `${LANG === "en" ? "Contact" : "Kontakt"} — ${s.name || "PLANIGAMES"}`;
        const email = s.email || "";
        root.innerHTML = `
            <section class="px-6 pt-36 pb-24">
                <div class="max-w-2xl mx-auto">
                    <div class="font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] mb-4 reveal">${t("nav_contact")}</div>
                    <h1 class="font-display text-5xl md:text-7xl font-extrabold text-white mb-6 reveal">${esc(c.heading || "Sag Hallo 👋")}</h1>
                    <p class="text-zinc-300 text-lg leading-relaxed mb-10 max-w-xl reveal">${esc(c.intro || "")}</p>
                    <form action="contact.php" method="POST" data-contact-form class="reveal grid gap-4 relative overflow-hidden rounded-3xl border border-white/10 p-6 md:p-8"
                          style="background:radial-gradient(120% 130% at 50% 0%, color-mix(in srgb,var(--accent) 12%, transparent), transparent 60%)">
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="block text-sm text-zinc-400 mb-1.5">${t("c_name")}</span>
                                <input type="text" name="name" required placeholder="${t("c_name_ph")}" class="w-full bg-black/30 border border-white/15 rounded-2xl px-5 py-3.5 text-white placeholder:text-zinc-600 focus:border-[color:var(--accent)] outline-none">
                            </label>
                            <label class="block">
                                <span class="block text-sm text-zinc-400 mb-1.5">${t("c_email")}</span>
                                <input type="email" name="email" required placeholder="${t("c_email_ph")}" class="w-full bg-black/30 border border-white/15 rounded-2xl px-5 py-3.5 text-white placeholder:text-zinc-600 focus:border-[color:var(--accent)] outline-none">
                            </label>
                        </div>
                        <label class="block">
                            <span class="block text-sm text-zinc-400 mb-1.5">${t("c_subject")}</span>
                            <input type="text" name="subject" placeholder="${t("c_subject_ph")}" class="w-full bg-black/30 border border-white/15 rounded-2xl px-5 py-3.5 text-white placeholder:text-zinc-600 focus:border-[color:var(--accent)] outline-none">
                        </label>
                        <label class="block">
                            <span class="block text-sm text-zinc-400 mb-1.5">${t("c_message")}</span>
                            <textarea name="message" required rows="6" placeholder="${t("c_message_ph")}" class="w-full bg-black/30 border border-white/15 rounded-2xl px-5 py-3.5 text-white placeholder:text-zinc-600 focus:border-[color:var(--accent)] outline-none resize-y"></textarea>
                        </label>
                        <div class="flex items-center justify-between gap-4 flex-wrap mt-1">
                            <button class="btn-accent magnetic px-8 py-4 rounded-full font-semibold">${esc(c.buttonLabel || t("c_send"))}</button>
                            ${email ? `<span class="text-sm text-zinc-500">${t("c_or_mail")} <a href="mailto:${esc(email)}" class="text-[color:var(--accent)] hover:underline">${esc(email)}</a></span>` : ""}
                        </div>
                    </form>
                </div>
            </section>`;
        observeReveals(root);
    }

    // Umbenannte Spiele: alte Adresse → neue (data/redirects.json wird nur bei Bedarf geladen)
    async function followRedirect(type, slug) {
        try {
            const map = await fetchJSON("data/redirects.json");
            const to = map && map[type] && map[type][slug];
            if (!to || typeof to !== "string" || to === slug) return false;
            const u = new URL(location.href);
            u.searchParams.set("slug", to);
            location.replace(u.toString());
            return true;
        } catch (e) { return false; }
    }

    function notFound(msg) {
        return `<div class="min-h-[60vh] grid place-items-center text-center px-6">
            <div><div class="text-6xl mb-6">🪄</div>
            <p class="text-zinc-400 mb-6">${esc(msg)}</p>
            <a href="index.html" class="btn-ghost magnetic inline-block px-7 py-3 rounded-full">${t("nf_home")}</a></div></div>`;
    }

    // Nach dem dynamischen Rendern die Sprungmarke aus der Adresse (z. B. #warteliste) anfahren
    function scrollToHash() {
        let id = ""; try { id = decodeURIComponent((location.hash || "").slice(1)); } catch {}
        if (!id || PLACER) return;
        const el = document.getElementById(id);
        if (!el) return;
        let moved = false;
        const onUser = () => { moved = true; };
        addEventListener("wheel", onUser, { once: true, passive: true });
        addEventListener("touchstart", onUser, { once: true, passive: true });
        const go = () => { if (!moved) el.scrollIntoView({ block: "start" }); };
        requestAnimationFrame(go);
        if (document.readyState !== "complete") addEventListener("load", () => setTimeout(go, 30), { once: true });
    }

    /* ---------- mini DOM-Helfer für statische Slots ---------- */
    function setText(sel, val) { $$(sel).forEach(el => { if (val != null) el.textContent = val; }); }
    function setHTML(sel, val) { $$(sel).forEach(el => { if (val != null) el.innerHTML = val; }); }

    /* =========================================================
       CHROME — gemeinsame Hülle (Header, Footer, Cursor …)
       wird in jede Seite injiziert, damit die HTML-Dateien
       schlank bleiben und Navigation überall identisch ist.
       ========================================================= */
    // Navigation – global (v7): Spiele · Studio · Devlog · Kontakt. Markiert wird der Bereich der aktuellen Seite.
    function getNav() {
        return [
            ["games.html", "nav_games", ["games", "game"]],
            ["index.html#studio", "nav_studio", []],
            ["devlog.php", "nav_devlog", ["devlog"]],
            ["kontakt.html", "nav_contact", ["contact"]],
        ];
    }
    const navActive = (pages) => pages.includes(document.body.dataset.page || "");
    function navLinks() {
        return getNav().map(([href, key, pages]) =>
            `<a href="${href}"${navActive(pages) ? ' class="on" aria-current="page"' : ""}>${t(key)}</a>`).join("");
    }
    function mobileNavLinks() { return navLinks(); }
    function langSwitch() {
        const b = (l) => LANG === l
            ? `<span class="on" aria-current="true">${l.toUpperCase()}</span>`
            : `<button type="button" data-setlang="${l}" aria-label="${l === "en" ? "English" : "Deutsch"}">${l.toUpperCase()}</button>`;
        return `<span class="pg-lang" role="group" aria-label="${t("lang_label")}">${b("de")}${b("en")}</span>`;
    }

    // Marke: Zeichen (eigenes Logo-Bild aus dem CMS, sonst Raute) + Schriftzug PLANI|GAMES.
    // Ist das hinterlegte Logo ein breiter Schriftzug, entfällt der Text automatisch (siehe fitBrand).
    function brandHTML(size) {
        const s = DATA.studio || {};
        const name = String(s.name || "PLANIGAMES");
        const m = name.match(/^(.+?)(games)$/i);
        const wm = m ? `${esc(m[1])}<span>${esc(m[2])}</span>` : esc(name);
        const mark = s.logo ? `<img src="${esc(s.logo)}" alt="" class="pg-mark">` : `<span class="pg-diamond" aria-hidden="true"></span>`;
        return `${mark}<span class="pg-wm">${wm}</span>`;
    }
    function fitBrand() {
        $$(".pg-brand .pg-mark").forEach(img => {
            const f = () => { if (img.naturalWidth > img.naturalHeight * 2.2) img.closest(".pg-brand")?.classList.add("pg-logo-wide"); };
            if (img.complete) f(); else img.addEventListener("load", f, { once: true });
        });
    }

    // Kleine Linien-Icons für Kopfzeile, Footer und Kanäle
    const PG_IC = {
        arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>', ext: '<path d="M7 17 17 7M8 7h9v9"/>',
        yt: '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3z"/>',
        tiktok: '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.5 2.6 2.4 4.5 5 4.8"/>',
        discord: '<path d="M8.6 7.3c2.3-.7 4.5-.7 6.8 0M8.2 16.6c2.5.9 5.1.9 7.6 0"/><path d="M7.4 6.4 5 7.8c-1.4 2.7-1.9 5.6-1.6 8.6 1.5 1.2 3.1 2 4.7 2.4l1-1.9M16.6 6.4 19 7.8c1.4 2.7 1.9 5.6 1.6 8.6-1.5 1.2-3.1 2-4.7 2.4l-1-1.9"/><circle cx="9.4" cy="12.6" r="1.2"/><circle cx="14.6" cy="12.6" r="1.2"/>',
        insta: '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        x: '<path d="M5 4l14 16M19 4 5 20"/>',
        steam: '<circle cx="12" cy="12" r="9"/><circle cx="15" cy="9.5" r="2.4"/><path d="m3.5 13.5 6.3 2.6"/><circle cx="10.4" cy="16.3" r="1.8"/>',
        twitch: '<path d="M4 3h16v11l-5 5h-4l-3 3v-3H4z"/><path d="M11 8v4M15 8v4"/>',
        bsky: '<path d="M12 10.5C10.2 7 7 4.6 5.2 5.4 3.5 6.2 5 12 8.4 12.4 5.6 13.3 5 16 7.2 16.8c2.4.8 3.9-2.5 4.8-5.6.9 3.1 2.4 6.4 4.8 5.6 2.2-.8 1.6-3.5-1.2-4.4 3.4-.4 4.9-6.2 3.2-7-1.8-.8-5 1.6-6.8 5.1z"/>',
        link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        play: '<path class="pg-fill" d="M8 5.5v13l11-6.5z"/>', pause: '<path d="M9 5v14M15 5v14"/>',
        pad: '<rect x="2.5" y="7" width="19" height="11" rx="5.5"/><path d="M7 10.5v4M5 12.5h4M15.5 11.5h.01M18 13.5h.01"/>',
        up: '<path d="M6 15l6-6 6 6"/>', check: '<path d="M5 12.5 10 17l9-10"/>', bolt: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        flag: '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>', dl: '<path d="M12 4v11M7 10.5l5 5 5-5M5 20h14"/>',
        news: '<path d="M4 5h12v14H6a2 2 0 0 1-2-2zM16 9h4v8a2 2 0 0 1-2 2h-2M7.5 9h5M7.5 12.5h5M7.5 16h3"/>',
        tag: '<path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-9 9z"/><circle cx="8" cy="8" r="1.4"/>',
        users: '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.8-3.2 3-5 5.5-5s4.7 1.8 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M15.6 14.2c2.2.3 3.8 1.8 4.6 4.8"/>',
        screen: '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20"/>',
        cal: '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        spark: '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
    };
    const pgIc = (n) => `<svg class="pg-ic" viewBox="0 0 24 24" aria-hidden="true">${PG_IC[n] || PG_IC.link}</svg>`;

    // Social-Kanal erkennen (Icon, Farbe, Kurzbeschreibung) – anhand Adresse oder Beschriftung
    function channelOf(label, url) {
        let host = ""; try { host = new URL(url, location.href).hostname.replace(/^www\./, "").toLowerCase(); } catch {}
        const L = String(label || "").toLowerCase();
        const is = (re) => re.test(host) || re.test(L);
        const mk = (ic, x, name, key) => ({ ic, x, name: label || name, desc: key ? t(key) : "" });
        if (is(/youtube|youtu\.be/)) return mk("yt", "#ff0033", "YouTube", "soc_youtube");
        if (is(/tiktok/)) return mk("tiktok", "#25232f", "TikTok", "soc_tiktok");
        if (is(/discord/)) return mk("discord", "#5865f2", "Discord", "soc_discord");
        if (is(/instagram/)) return mk("insta", "#c13584", "Instagram", "soc_instagram");
        if (/(^|\.)(x|twitter)\.com$/.test(host) || /^(x|twitter)$/.test(L)) return mk("x", "#25232f", "X", "soc_x");
        if (is(/steam/)) return mk("steam", "#1b2838", "Steam", "soc_steam");
        if (is(/twitch/)) return mk("twitch", "#9146ff", "Twitch", "soc_twitch");
        if (is(/bsky|bluesky/)) return mk("bsky", "#1185fe", "Bluesky", "soc_bsky");
        return { ic: "link", x: "#2a2a34", name: label || host, desc: label ? host : "" };
    }
    // Community-/Discord-Link aus dem CMS – nur, wenn wirklich ein externer Link eingetragen ist
    // (ein Platzhalter auf die eigene Website wird ignoriert).
    function communityLink(s) {
        const c = (s && s.community) || {};
        if (c.enabled === false) return null;
        const url = String(c.url || "").trim();
        if (!/^https?:\/\//i.test(url)) return null;
        let host = "";
        try { host = new URL(url).hostname.replace(/^www\./, "").toLowerCase(); } catch { return null; }
        const own = location.hostname.replace(/^www\./, "").toLowerCase();
        if (!host || host === own || /(^|\.)planigames\.(com|de)$/.test(host)) return null;
        return { url, label: c.buttonLabel || "Discord", host };
    }
    // Alle Kanäle für „Folge uns" und Footer: Social Links + Discord (ohne Dubletten)
    function channels(s) {
        const out = [], seen = new Set();
        const add = (label, url) => {
            url = String(url || "").trim();
            if (!url || url === "#" || !/^(https?:)?\/\//i.test(url)) return;
            const key = url.replace(/^https?:\/\/(www\.)?/i, "").replace(/\/+$/, "").toLowerCase();
            if (seen.has(key)) return; seen.add(key);
            out.push({ url, ...channelOf(label, url) });
        };
        ((s && s.socials) || []).forEach(x => x && add(x.label, x.url));
        const c = communityLink(s);
        if (c && !out.some(o => o.ic === "discord" && /discord/.test(c.host))) add(/discord/.test(c.host) ? "Discord" : c.label, c.url);
        return out;
    }
    // Hauptbutton der Kopfzeile: Playtest des aktuellen Projekts (falls es eine Warteliste hat), sonst Spiele
    function headerCta() {
        const games = (DATA.games && DATA.games.games) || [];
        const f = games.find(g => g && g.featured);
        const wl = f && (f.blocks || []).some(b => b && b.type === "waitlist" && !b.hidden);
        return wl ? { href: `game.php?slug=${encodeURIComponent(f.slug)}#warteliste`, label: t("cta_playtest") }
                  : { href: "games.html", label: t("cta_discover") };
    }
    // Seiten mit eigenem „Bleib dran"-Block (Newsletter + Kanäle) über dem Footer
    const STAY_PAGES = ["home", "games", "devlog", "contact", "presskit"];

    function injectChrome() {
        // Hintergrund-Effekte (im Admin unter „Hintergrund & Atmosphäre" steuerbar) einmalig einsetzen
        if (!$("#fx-layer")) {
            document.body.insertAdjacentHTML("afterbegin", `
                <div class="glow-blob glow-1" style="top:-12%;left:-12%;width:55vw;height:55vw;background:color-mix(in srgb,var(--accent) 13%,transparent)"></div>
                <div class="glow-blob glow-2" style="bottom:-12%;right:-12%;width:50vw;height:50vw;background:color-mix(in srgb,var(--accent-2) 11%,transparent)"></div>
                <div id="fx-layer"></div>`);
        }
        const cta = headerCta();
        const ctaHTML = (cls) => `<a href="${esc(cta.href)}" class="pg-btn pg-btn-p ${cls}" data-hd-cta>${esc(cta.label)}</a>`;
        const tipHTML = (cls) => `<a href="#" class="pg-btn pg-btn-s ${cls}" data-tipjar-slot target="_blank" rel="noopener" hidden>${t("support")}</a>`;

        const headerMount = $("[data-shell='header']");
        if (headerMount) headerMount.outerHTML = `
            <header id="site-header" class="pg-hd">
                <div class="pg-wrap pg-hd-in">
                    <a href="index.html" class="pg-brand" data-brand="sm" aria-label="${esc((DATA.studio && DATA.studio.name) || "PLANIGAMES")} – ${t("home_label")}">${brandHTML("sm")}</a>
                    <nav class="pg-nav" aria-label="Hauptmenü">${navLinks()}</nav>
                    <div class="pg-hd-r">
                        ${langSwitch()}
                        ${tipHTML("pg-btn-sm")}
                        ${ctaHTML("pg-btn-sm")}
                        <button id="menu-toggle" class="pg-burger" type="button" aria-label="${t("menu_open")}" aria-expanded="false" aria-controls="mobile-menu"><i></i><i></i><i></i></button>
                    </div>
                </div>
            </header>
            <div id="mobile-menu" class="pg-mn" aria-hidden="true">
                <nav class="pg-mn-nav" aria-label="Menü">${mobileNavLinks()}</nav>
                <div class="pg-mn-foot">
                    <div class="pg-mn-row">${ctaHTML("")}${tipHTML("")}</div>
                    ${langSwitch()}
                </div>
            </div>`;

        const page = document.body.dataset.page || "";
        const nlSrc = page || "footer";
        const stayHTML = STAY_PAGES.includes(page) ? `
            <section class="pg-stay-sec" data-newsletter-section>
                <div class="pg-wrap"><div class="pg-stay">
                    <div class="pg-stay-main">
                        <span class="pg-kicker">${t("nl_kicker")}</span>
                        <h2 class="pg-h2" data-nl="heading">Sei dabei, bevor die Magie startet.</h2>
                        <p class="pg-lead" data-nl="text">Trag dich ein und erfahre als Erste*r von Release-Terminen, Demos und großen Updates.</p>
                        <form action="subscribe.php" method="POST" data-newsletter data-source="${esc(nlSrc)}" class="pg-nl">
                            <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="pg-hp">
                            <input type="hidden" name="source" value="${esc(nlSrc)}">
                            <label class="pg-sr" for="pg-nl-mail">${t("nl_email_label")}</label>
                            <input id="pg-nl-mail" type="email" name="email" required autocomplete="email" placeholder="deine@mail.de" data-nl-input>
                            <button class="pg-btn pg-btn-p" data-nl="button">${t("foot_newsletter_btn")}</button>
                        </form>
                        <p class="pg-fine">${t("foot_newsletter_note")}</p>
                    </div>
                    <div class="pg-stay-side">
                        <h3 data-follow-h>${t("follow_h")}</h3>
                        <p data-follow-p>${t("follow_sub")}</p>
                        <div class="pg-soc" data-foot-channels></div>
                    </div>
                </div></div>
            </section>` : "";

        const footerMount = $("[data-shell='footer']");
        if (footerMount) footerMount.outerHTML = `${stayHTML}
            <footer id="kontakt" class="pg-ft">
                <div class="pg-wrap">
                    <div class="pg-ft-top">
                        <div class="pg-ft-brand">
                            <a href="index.html" class="pg-brand pg-brand-lg" data-brand="lg" aria-label="${t("home_label")}">${brandHTML("lg")}</a>
                            <p data-studio="footerNote">Ein unabhängiges Indie-Spielestudio. Wir bauen Welten, die wackeln, zaubern und im Kopf bleiben.</p>
                        </div>
                        <div class="pg-ft-col"><b>${t("foot_games")}</b>
                            <ul data-foot-games><li><a href="games.html">${t("foot_allgames")}</a></li></ul></div>
                        <div class="pg-ft-col"><b>${t("foot_studio")}</b>
                            <ul>
                                <li><a href="index.html#studio">${t("foot_about_short")}</a></li>
                                <li><a href="devlog.php">${t("foot_devlog")}</a></li>
                                <li data-foot-press hidden><a href="presse.html">${t("presskit")}</a></li>
                                <li data-tipjar-li hidden><a href="#" data-tipjar-slot target="_blank" rel="noopener">${t("support")}</a></li>
                            </ul></div>
                        <div class="pg-ft-col"><b>${t("foot_contact_h")}</b>
                            <ul data-foot-contact><li><a href="kontakt.html">${t("foot_form")}</a></li></ul></div>
                    </div>
                    <div class="pg-ft-bot">
                        <span>© <span data-year></span> PLANIGAMES · ${t("foot_made")}</span>
                        <span class="pg-ft-links">
                            <a href="rechtliches.html?doc=impressum">${t("foot_impressum")}</a>
                            <a href="rechtliches.html?doc=datenschutz">${t("foot_datenschutz")}</a>
                            <a href="#" data-cookie-open>${t("cookie_settings")}</a>
                            <a href="admin/" title="Zum Dashboard">🔒 Admin</a>
                        </span>
                    </div>
                </div>
            </footer>`;
        fitBrand();
    }

    function fillStudioFooter() {
        const s = DATA.studio;
        if (!s) return;
        // Marke (Logo-Zeichen + Schriftzug) mit den geladenen Studio-Daten neu setzen
        $$("[data-brand]").forEach(el => { el.innerHTML = brandHTML(el.dataset.brand); });
        fitBrand();
        if (s.footerNote) setText("[data-studio='footerNote']", s.footerNote);
        // Spiele-Spalte
        const games = (DATA.games && DATA.games.games) || [];
        const gl = $("[data-foot-games]");
        if (gl && games.length) gl.innerHTML = games.map(g => `<li><a href="game.php?slug=${encodeURIComponent(g.slug || "")}">${esc(g.title || g.slug || "")}</a></li>`).join("");
        // Kontakt-Spalte + „Folge uns"-Kanäle (Social Links + Discord)
        const ch = channels(s);
        const cl = $("[data-foot-contact]");
        if (cl) cl.innerHTML = (s.email ? `<li><a href="mailto:${esc(s.email)}" data-studio="email">${esc(s.email)}</a></li>` : "")
            + `<li><a href="kontakt.html">${t("foot_form")}</a></li>`
            + ch.map(c => `<li><a href="${esc(c.url)}" target="_blank" rel="noopener">${esc(c.name)}</a></li>`).join("");
        const fl = $("[data-foot-channels]");
        // Mit Discord-Link: Überschrift & Text aus dem Community-Banner (Admin) im „Folge uns"-Bereich
        const cm = communityLink(s), cc = s.community || {};
        if (cm && cc.heading) setText("[data-follow-h]", cc.heading);
        if (cm && cc.text) setText("[data-follow-p]", cc.text);
        if (fl) {
            if (ch.length) {
                // Discord-Eintrag trägt den Button-Text aus dem Community-Banner (z. B. „Discord beitreten")
                const nameOf = (c) => (cm && c.url === cm.url && String(cc.buttonLabel || "").trim()) ? String(cc.buttonLabel).trim() : c.name;
                fl.innerHTML = ch.map(c => `<a href="${esc(c.url)}" target="_blank" rel="noopener"><span class="pg-si" style="--x:${c.x}">${pgIc(c.ic)}</span>`
                    + `<span><b>${esc(nameOf(c))}</b>${c.desc ? `<small>${esc(c.desc)}</small>` : ""}</span>${pgIc("ext")}</a>`).join("");
            } else {
                fl.closest(".pg-stay-side")?.remove();
                $(".pg-stay")?.classList.add("solo");
            }
        }
        // Press-Kit-Link im Footer nur zeigen, wenn aktiviert
        if (!s.presskit || s.presskit.enabled !== false) $$("[data-foot-press]").forEach(el => el.hidden = false);
        // Favicon (eigenes Feld, unabhängig vom Logo) – altes Icon ersetzen
        if (s.favicon) {
            $$("link[rel~='icon'], link[rel='apple-touch-icon']").forEach(l => l.remove());
            const fav = document.createElement("link"); fav.rel = "icon"; fav.href = s.favicon;
            document.head.appendChild(fav);
            const at = document.createElement("link"); at.rel = "apple-touch-icon"; at.href = s.favicon;
            document.head.appendChild(at);
        }
        fillNewsletter(s.newsletter || {});
    }

    function fillNewsletter(nl) {
        // Newsletter komplett aus, falls deaktiviert
        if (nl.enabled === false) {
            $$("[data-newsletter-section]").forEach(el => el.remove());
            $$("form[data-newsletter]").forEach(f => f.closest("div")?.remove() || f.remove());
            return;
        }
        if (nl.heading) setText("[data-nl='heading']", nl.heading);
        if (nl.text) setText("[data-nl='text']", nl.text);
        if (nl.buttonLabel) setText("[data-nl='button']", nl.buttonLabel);
        if (nl.placeholder) $$("[data-nl-input]").forEach(i => i.placeholder = nl.placeholder);
    }

    // Anmeldeformulare ohne Seitenneuladen abschicken
    function initNewsletter() {
        document.addEventListener("submit", async (e) => {
            const form = e.target.closest("form[data-newsletter]");
            if (!form) return;
            e.preventDefault();
            if (form.dataset.busy) return;
            form.dataset.busy = "1";
            const nl = (DATA.studio && DATA.studio.newsletter) || {};
            try {
                const r = await fetch(form.getAttribute("action") || "subscribe.php", { method: "POST", body: new FormData(form) });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.ok) {
                    const msg = j.message || nl.successMessage || "Danke! Du bist dabei. 🧡";
                    form.innerHTML = `<div class="w-full text-center text-[color:var(--accent)] font-semibold py-2">${esc(msg)}</div>`;
                    const rc = form.getBoundingClientRect();
                    confetti({ count: 110, x: rc.left + rc.width / 2, y: rc.top + rc.height / 2 });
                    pgBadge("subscriber");
                } else {
                    flashFormError(form, j.error || "Hat nicht geklappt. Bitte später erneut.");
                }
            } catch {
                flashFormError(form, "Verbindung fehlgeschlagen. Bitte später erneut.");
            }
            delete form.dataset.busy;
        });
    }

    function initContact() {
        document.addEventListener("submit", async (e) => {
            const form = e.target.closest("form[data-contact-form]");
            if (!form) return;
            e.preventDefault();
            if (form.dataset.busy) return;
            form.dataset.busy = "1";
            const c = (DATA.studio && DATA.studio.contact) || {};
            const btn = form.querySelector("button");
            if (btn) btn.disabled = true;
            try {
                const r = await fetch(form.getAttribute("action") || "contact.php", { method: "POST", body: new FormData(form) });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.ok) {
                    const msg = j.message || c.successMessage || "Danke für deine Nachricht! 🧡";
                    form.innerHTML = `<div class="text-center py-8"><div class="text-5xl mb-4">🧡</div>
                        <p class="text-[color:var(--accent)] font-semibold text-lg">${esc(msg)}</p></div>`;
                } else {
                    flashFormError(form, j.error || "Hat nicht geklappt. Bitte später erneut.");
                    if (btn) btn.disabled = false;
                }
            } catch {
                flashFormError(form, "Verbindung fehlgeschlagen. Bitte später erneut.");
                if (btn) btn.disabled = false;
            }
            delete form.dataset.busy;
        });
    }

    let _shareBound = false;
    function initShareCopy() {
        if (_shareBound) return; _shareBound = true;
        document.addEventListener("click", (e) => {
            const btn = e.target.closest("[data-share-copy]");
            if (!btn) return;
            const done = () => {
                const old = btn.textContent;
                btn.textContent = "✓";
                btn.classList.add("copied");
                setTimeout(() => { btn.textContent = old; btn.classList.remove("copied"); }, 1400);
            };
            if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done).catch(done);
            else done();
        });
    }

    function flashFormError(form, msg) {
        let n = form.querySelector("[data-nl-err]");
        if (!n) { n = document.createElement("div"); n.dataset.nlErr = "1"; n.className = "w-full text-center text-red-400 text-sm mt-2 basis-full"; form.appendChild(n); }
        n.textContent = msg;
    }

    /* =========================================================
       BOOT
       ========================================================= */
    // ---------- Team-Profil-Modal ----------
    function openMemberModal(m) {
        if (!m) return;
        document.getElementById("pg-member-modal")?.remove();
        const socials = (m.socials || []).filter(s => s.url && s.url !== "#");
        const ov = document.createElement("div");
        ov.id = "pg-member-modal";
        ov.className = "pg-modal";
        ov.innerHTML = `<div class="pg-modal-box">
            <button class="pg-modal-x" data-mm-close aria-label="Schließen">✕</button>
            <div class="pg-member-head">
                ${m.photo ? `<img src="${esc(m.photo)}" alt="${esc(m.photoAlt || m.name)}" style="object-position:${focusPos(m.photo)}">` : `<div class="pg-member-emoji">${esc(m.emoji || "🧙")}</div>`}
                <div>
                    <h3>${esc(m.name || "")}</h3>
                    <div class="pg-member-role">${esc(m.role || "")}</div>
                    ${m.tagline ? `<div class="pg-member-tag">${esc(m.tagline)}</div>` : ""}
                </div>
            </div>
            ${m.bio ? `<div class="prose-pg pg-member-bio">${md(m.bio)}</div>` : ""}
            ${(m.location || m.funFact) ? `<div class="pg-member-meta">${m.location ? `<span>📍 ${esc(m.location)}</span>` : ""}${m.funFact ? `<span>✨ ${esc(m.funFact)}</span>` : ""}</div>` : ""}
            ${socials.length ? `<div class="pg-member-socials">${socials.map(s => `<a href="${esc(s.url)}" target="_blank" rel="noopener" class="btn-ghost px-4 py-2 rounded-full text-sm font-semibold">${esc(s.label || "Link")} ↗</a>`).join("")}</div>` : ""}
        </div>`;
        document.body.appendChild(ov);
        requestAnimationFrame(() => ov.classList.add("show"));
        const close = () => { ov.classList.remove("show"); setTimeout(() => ov.remove(), 250); };
        ov.addEventListener("click", (e) => { if (e.target === ov || e.target.closest("[data-mm-close]")) close(); });
        document.addEventListener("keydown", function onEsc(e) { if (e.key === "Escape") { close(); document.removeEventListener("keydown", onEsc); } });
    }

    // ---------- Konfetti (canvas, ohne Library) ----------
    function confetti(opts = {}) {
        // „reduce motion" respektieren – außer wenn explizit als direkte Nutzer-Reaktion erzwungen (opts.force)
        if (!opts.force && matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        const n = opts.count || 90;
        const colors = ["#ff7d1a", "#e6a015", "#8b5cf6", "#22d3ee", "#5fd07f", "#ff5a8a", "#ffffff"];
        const cv = document.createElement("canvas");
        cv.style.cssText = "position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:9999";
        cv.width = innerWidth; cv.height = innerHeight;
        document.body.appendChild(cv);
        const ctx = cv.getContext("2d");
        const ox = opts.x ?? innerWidth / 2, oy = opts.y ?? innerHeight * 0.35;
        const parts = Array.from({ length: n }, () => ({
            x: ox, y: oy, vx: (Math.random() - 0.5) * 14, vy: Math.random() * -12 - 4,
            g: 0.3 + Math.random() * 0.15, w: 6 + Math.random() * 6, h: 8 + Math.random() * 8,
            rot: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.3, color: colors[(Math.random() * colors.length) | 0],
        }));
        const t0 = performance.now();
        (function frame(t) {
            ctx.clearRect(0, 0, cv.width, cv.height);
            let alive = false;
            for (const p of parts) {
                p.vy += p.g; p.x += p.vx; p.y += p.vy; p.rot += p.vr; p.vx *= 0.99;
                if (p.y < cv.height + 20) alive = true;
                ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
                ctx.globalAlpha = Math.max(0, 1 - (t - t0) / 2600); ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore();
            }
            if (alive && t - t0 < 2800) requestAnimationFrame(frame); else cv.remove();
        })(t0);
    }

    // ---------- Konami-Code → Zauberer-Modus ----------
    function initKonami() {
        const seq = ["arrowup", "arrowup", "arrowdown", "arrowdown", "arrowleft", "arrowright", "arrowleft", "arrowright", "b", "a"];
        let i = 0;
        addEventListener("keydown", (e) => {
            const k = (e.key || "").toLowerCase();
            if (k === seq[i]) { if (++i === seq.length) { i = 0; wizardMode(); } }
            else { i = (k === seq[0]) ? 1 : 0; }
        });
    }
    function wizardMode() {
        confetti({ count: 170 });
        pgBadge("wizard");
        document.body.classList.toggle("wizard-mode");
        const on = document.body.classList.contains("wizard-mode");
        const toast = document.createElement("div");
        toast.className = "wizard-toast";
        toast.textContent = on ? (LANG === "en" ? "🧙 Wizard mode unlocked!" : "🧙 Zauberer-Modus aktiviert!") : (LANG === "en" ? "Wizard mode off" : "Zauberer-Modus aus");
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add("show"));
        setTimeout(() => { toast.classList.remove("show"); setTimeout(() => toast.remove(), 400); }, 3000);
    }

    // ---------- Maskottchen (im Admin ein-/ausschaltbar) ----------
    function initMascot() {
        const m = (DATA.studio && DATA.studio.mascot) || {};
        if (!m.enabled) return;
        // Vom Besucher geschlossen? Nur für die AKTUELLE Sitzung merken (sessionStorage):
        // ein versehentlicher ✕-Klick soll das Maskottchen nicht dauerhaft (pro Gerät) verschwinden lassen.
        // Alte dauerhafte Markierung (localStorage) wird dabei entfernt – so taucht es nach Fixes wieder auf.
        if (m.closable !== false) { try { localStorage.removeItem("pg_mascot_closed"); if (sessionStorage.getItem("pg_mascot_closed") === "1") return; } catch {} }
        // Hinweis: Das Maskottchen ist ein bewusst aktiviertes Spaß-Element und bleibt
        // auch bei „prefers-reduced-motion" lebendig (Augen, Blick, Idle, Konfetti beim Klick).
        // Die Bewegungen sind klein/lokal bzw. nutzergesteuert.

        const el = document.createElement("div");
        el.className = "pg-mascot";
        const num = (v, d) => { v = +v; return isFinite(v) ? v : d; };
        el.style.setProperty("--ms", Math.max(48, Math.min(220, num(m.size, 96))) + "px");
        const face = m.image ? `<img src="${esc(m.image)}" alt="">` : `<span class="pg-mascot-emoji">${esc(m.emoji || "🧙")}</span>`;
        // Wackelaugen-Overlay (über Bild/Emoji), folgen dem Cursor
        const ey = m.eyes || {};
        const eyesOn = !!ey.enabled;
        const eyesHTML = eyesOn
            ? `<span class="pg-mascot-eyes" aria-hidden="true" style="--eye:${num(ey.size, 22)}">`
              + `<span class="pg-eye" style="left:${num(ey.lx, 35)}%;top:${num(ey.ly, 42)}%"><span class="pg-pupil"></span></span>`
              + `<span class="pg-eye" style="left:${num(ey.rx, 65)}%;top:${num(ey.ry, 42)}%"><span class="pg-pupil"></span></span></span>`
            : "";
        el.innerHTML =
            `<div class="pg-mascot-bubble" aria-hidden="true"></div>` +
            `<button type="button" class="pg-mascot-btn" aria-label="Maskottchen"><span class="pg-mascot-face">${face}${eyesHTML}</span><span class="pg-mascot-zzz" aria-hidden="true">💤</span></button>` +
            (m.closable !== false ? `<button type="button" class="pg-mascot-close" aria-label="${LANG === "en" ? "Hide mascot" : "Maskottchen ausblenden"}">✕</button>` : "");
        document.body.appendChild(el);

        const btn = el.querySelector(".pg-mascot-btn");
        const faceEl = el.querySelector(".pg-mascot-face");
        const bubble = el.querySelector(".pg-mascot-bubble");

        // ---- 1) + 4) Sprüche-Pool (seiten-spezifisch übersteuert wechselnde) ----
        const page = (document.body.dataset.page || "");
        let pool = [];
        const ctx = (m.context || []).filter(c => c && c.page === page && c.text);
        if (ctx.length) pool = ctx.map(c => c.text);
        else if (Array.isArray(m.messages) && m.messages.length) pool = m.messages.map(x => (x && x.text) || "").filter(Boolean);
        else if (m.message) pool = [m.message];
        let pIdx = Math.floor(Math.random() * Math.max(pool.length, 1));
        function showBubble() {
            if (!pool.length) return;
            bubble.textContent = pool[pIdx % pool.length];
            pIdx++;
            el.classList.add("talking");
        }
        function hideBubble() { el.classList.remove("talking"); }
        el.addEventListener("mouseenter", () => { wake(); showBubble(); });
        el.addEventListener("mouseleave", hideBubble);

        // ---- 6) Position wiederherstellen / Drag & Drop ----
        if (m.draggable !== false) {
            try {
                const saved = JSON.parse(localStorage.getItem("pg_mascot_pos") || "null");
                if (saved && typeof saved.x === "number") placeAt(saved.x, saved.y);
            } catch {}
        }
        function placeAt(x, y) {
            const r = el.getBoundingClientRect();
            x = Math.max(4, Math.min(innerWidth - r.width - 4, x));
            y = Math.max(4, Math.min(innerHeight - r.height - 4, y));
            el.style.left = x + "px"; el.style.top = y + "px";
            el.style.right = "auto"; el.style.bottom = "auto";
        }
        let dragging = false, moved = false, sx = 0, sy = 0, ox = 0, oy = 0;
        btn.addEventListener("pointerdown", (e) => {
            if (m.draggable === false) return;
            const r = el.getBoundingClientRect();
            dragging = true; moved = false; sx = e.clientX; sy = e.clientY; ox = r.left; oy = r.top;
            btn.setPointerCapture(e.pointerId); el.classList.add("dragging");
        });
        btn.addEventListener("pointermove", (e) => {
            if (!dragging) return;
            const dx = e.clientX - sx, dy = e.clientY - sy;
            if (Math.abs(dx) > 5 || Math.abs(dy) > 5) moved = true;
            if (moved) placeAt(ox + dx, oy + dy);
        });
        btn.addEventListener("pointerup", (e) => {
            if (!dragging) return;
            dragging = false; el.classList.remove("dragging");
            try { btn.releasePointerCapture(e.pointerId); } catch {}
            if (moved) { const r = el.getBoundingClientRect(); try { localStorage.setItem("pg_mascot_pos", JSON.stringify({ x: r.left, y: r.top })); } catch {} }
        });

        // ---- 5) Klick = winken + Konfetti, mehrfach = Geheim-Animation + Badge ----
        let pets = 0, petTimer = null;
        btn.addEventListener("click", () => {
            if (moved) { moved = false; return; }   // war ein Drag, kein Klick
            wake();
            el.classList.remove("wave"); void el.offsetWidth; el.classList.add("wave");
            confetti({ count: 40, force: true, x: el.getBoundingClientRect().left + 40, y: el.getBoundingClientRect().top + 40 });
            setTimeout(() => el.classList.remove("wave"), 800);
            pets++;
            clearTimeout(petTimer); petTimer = setTimeout(() => { pets = 0; }, 1500);
            if (pets >= 5) {
                pets = 0;
                el.classList.remove("spin"); void el.offsetWidth; el.classList.add("spin");
                confetti({ count: 90, force: true, x: el.getBoundingClientRect().left + 40, y: el.getBoundingClientRect().top + 40 });
                setTimeout(() => el.classList.remove("spin"), 900);
                pgBadge("friend");
            }
        });

        // ---- 2) Blick folgt der Maus (Neigung – funktioniert mit Emoji & PNG) ----
        // KEIN (pointer:fine)- und KEIN reduce-Gate: das blockierte das Tracking auf
        // Touch-fähigen Desktops bzw. bei aktivem „Animationen reduzieren".
        if (m.follow !== false) {
            const onTilt = (e) => {
                if (dragging) return;
                const r = el.getBoundingClientRect();
                const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
                const tilt = Math.max(-14, Math.min(14, (e.clientX - cx) / 22));
                const lift = e.clientY < cy ? Math.max(-4, (e.clientY - cy) / 60) : 0;
                faceEl.style.transform = `rotate(${tilt}deg) translateY(${lift}px)`;
            };
            addEventListener("pointermove", onTilt, { passive: true });
            addEventListener("mousemove", onTilt, { passive: true });
        }

        // ---- Wackelaugen: Pupillen folgen dem Cursor ----
        if (eyesOn) {
            const eyeEls = [...el.querySelectorAll(".pg-eye")];
            const onEyes = (e) => {
                eyeEls.forEach(eye => {
                    const r = eye.getBoundingClientRect();
                    if (!r.width) return;
                    const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
                    const a = Math.atan2(e.clientY - cy, e.clientX - cx);
                    const reach = r.width * 0.30;
                    const pupil = eye.firstElementChild;
                    if (pupil) pupil.style.transform = `translate(${Math.cos(a) * reach}px, ${Math.sin(a) * reach}px)`;
                });
            };
            addEventListener("pointermove", onEyes, { passive: true });
            addEventListener("mousemove", onEyes, { passive: true });
        }

        // ---- 3) Einschlafen bei Inaktivität ----
        let idleTimer = null;
        function wake() {
            el.classList.remove("sleeping");
            if (m.idle === false) return;
            clearTimeout(idleTimer);
            idleTimer = setTimeout(() => el.classList.add("sleeping"), 22000);
        }
        if (m.idle !== false) {
            ["scroll", "pointermove", "keydown", "touchstart"].forEach(ev => addEventListener(ev, wake, { passive: true }));
            wake();
        }

        // ---- 8) Schließen ----
        const closeBtn = el.querySelector(".pg-mascot-close");
        if (closeBtn) closeBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            el.classList.add("leaving");
            try { sessionStorage.setItem("pg_mascot_closed", "1"); } catch {}
            setTimeout(() => el.remove(), 350);
        });
    }

    // gemeinsamer Container unten rechts (Tip-Jar + Abzeichen stapeln)
    function pgCornerRight() {
        let c = document.getElementById("pg-corner-r");
        if (!c) { c = document.createElement("div"); c.id = "pg-corner-r"; document.body.appendChild(c); }
        return c;
    }

    // ---------- Tip-Jar / Ko-fi-Button (im Admin konfigurierbar) ----------
    function initTipJar() {
        const tj = (DATA.studio && DATA.studio.tipjar) || {};
        if (!tj.enabled || !tj.url || tj.url === "#") return;
        // v7: „Unterstützen" sitzt in Kopfzeile, Handy-Menü und Footer – kein schwebender Button mehr
        const slots = $$("[data-tipjar-slot]");
        if (slots.length) {
            const label = tj.label || t("support");
            slots.forEach(el => { el.href = tj.url; el.textContent = label; el.hidden = false; el.closest("[data-tipjar-li]")?.removeAttribute("hidden"); });
            return;
        }
        const a = document.createElement("a");
        a.className = "pg-tipjar";
        a.href = tj.url; a.target = "_blank"; a.rel = "noopener";
        a.innerHTML = `<span class="pg-tip-emo">☕</span><span class="pg-tip-lbl">${esc(tj.label || (LANG === "en" ? "Support us" : "Unterstützen"))}</span>`;
        pgCornerRight().appendChild(a);
    }

    // ---------- Community-Abzeichen (anonym, im Browser gespeichert) ----------
    const BADGES = [
        { id: "reactor", emoji: "❤️", title: { de: "Reaktionsfreudig", en: "Reactive" }, desc: { de: "Auf einen Devlog reagiert", en: "Reacted to a devlog" } },
        { id: "commenter", emoji: "💬", title: { de: "Wortmelder*in", en: "Commenter" }, desc: { de: "Einen Kommentar geschrieben", en: "Posted a comment" } },
        { id: "subscriber", emoji: "📬", title: { de: "Eingeweiht", en: "Subscriber" }, desc: { de: "Newsletter abonniert", en: "Joined the newsletter" } },
        { id: "ideator", emoji: "💡", title: { de: "Ideengeber*in", en: "Ideator" }, desc: { de: "Eine Idee eingereicht", en: "Submitted an idea" } },
        { id: "voter", emoji: "🗳️", title: { de: "Wählerisch", en: "Voter" }, desc: { de: "Für eine Idee abgestimmt", en: "Upvoted an idea" } },
        { id: "explorer", emoji: "🧭", title: { de: "Entdecker*in", en: "Explorer" }, desc: { de: "5 Seiten erkundet", en: "Explored 5 pages" } },
        { id: "wizard", emoji: "🧙", title: { de: "Zauberer*in", en: "Wizard" }, desc: { de: "Den Geheim-Code gefunden", en: "Found the secret code" } },
        { id: "friend", emoji: "🤝", title: { de: "Maskottchen-Freund*in", en: "Mascot friend" }, desc: { de: "Das Maskottchen oft gestreichelt", en: "Petted the mascot a lot" } },
    ];
    const badgeT = (o) => (o && (o[LANG] || o.de)) || "";
    function badgesEarned() { try { return JSON.parse(localStorage.getItem("pg_badges") || "{}"); } catch { return {}; } }
    function pgBadge(id) {
        const earned = badgesEarned();
        if (earned[id] || !BADGES.some(b => b.id === id)) return;
        earned[id] = Date.now();
        try { localStorage.setItem("pg_badges", JSON.stringify(earned)); } catch {}
        ensureBadgeButton();
        updateBadgeButton();
        const def = BADGES.find(b => b.id === id);
        const toast = document.createElement("div");
        toast.className = "badge-toast";
        toast.innerHTML = `<span class="badge-toast-emo">${def.emoji}</span><span><b>${LANG === "en" ? "Badge unlocked!" : "Abzeichen freigeschaltet!"}</b><br>${esc(badgeT(def.title))}</span>`;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add("show"));
        setTimeout(() => { toast.classList.remove("show"); setTimeout(() => toast.remove(), 400); }, 3600);
        confetti({ count: 50, x: innerWidth - 80, y: innerHeight - 120 });
    }
    function ensureBadgeButton() {
        if (document.getElementById("pg-badges-btn")) return;
        const btn = document.createElement("button");
        btn.id = "pg-badges-btn"; btn.type = "button"; btn.className = "pg-badges-btn";
        btn.setAttribute("aria-label", LANG === "en" ? "Your badges" : "Deine Abzeichen");
        btn.addEventListener("click", openBadgePanel);
        pgCornerRight().appendChild(btn);
    }
    function updateBadgeButton() {
        const btn = document.getElementById("pg-badges-btn"); if (!btn) return;
        const n = Object.keys(badgesEarned()).length;
        btn.innerHTML = `🏆 <span class="pgb-count">${n}/${BADGES.length}</span>`;
    }
    function openBadgePanel() {
        const earned = badgesEarned();
        document.getElementById("pg-member-modal")?.remove();
        const ov = document.createElement("div");
        ov.id = "pg-member-modal"; ov.className = "pg-modal";
        ov.innerHTML = `<div class="pg-modal-box"><button class="pg-modal-x" data-mm-close aria-label="Schließen">✕</button>
            <h3 style="font-family:'Montserrat',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;margin-bottom:.3rem">🏆 ${LANG === "en" ? "Your badges" : "Deine Abzeichen"}</h3>
            <p class="text-zinc-400" style="font-size:.88rem;margin-bottom:1.2rem">${Object.keys(earned).length}/${BADGES.length} ${LANG === "en" ? "unlocked — earned by engaging with the site." : "freigeschaltet – durch Mitmachen auf der Seite."}</p>
            <div class="pg-badge-grid">${BADGES.map(b => {
                const got = !!earned[b.id];
                return `<div class="pg-badge ${got ? "got" : "locked"}"><span class="pg-badge-emo">${got ? b.emoji : "🔒"}</span><span class="pg-badge-t">${esc(badgeT(b.title))}</span><span class="pg-badge-d">${esc(badgeT(b.desc))}</span></div>`;
            }).join("")}</div></div>`;
        document.body.appendChild(ov);
        requestAnimationFrame(() => ov.classList.add("show"));
        const close = () => { ov.classList.remove("show"); setTimeout(() => ov.remove(), 250); };
        ov.addEventListener("click", (e) => { if (e.target === ov || e.target.closest("[data-mm-close]")) close(); });
        document.addEventListener("keydown", function onEsc(e) { if (e.key === "Escape") { close(); document.removeEventListener("keydown", onEsc); } });
    }
    function initBadges() {
        if (Object.keys(badgesEarned()).length) { ensureBadgeButton(); updateBadgeButton(); }
        // Entdecker: besuchte Seiten zählen
        try {
            const page = (document.body.dataset.page || "") + (qs.get("slug") ? ":" + qs.get("slug") : "");
            const seen = JSON.parse(localStorage.getItem("pg_seen") || "[]");
            if (page && !seen.includes(page)) { seen.push(page); localStorage.setItem("pg_seen", JSON.stringify(seen.slice(-30))); }
            if (seen.length >= 5) pgBadge("explorer");
        } catch {}
    }

    async function boot() {
        document.documentElement.lang = LANG;
        await loadData("studio", "games");   // früh laden (Hintergrund-Effekt, Kopfzeilen-Button, Footer-Spieleliste)
        const fx = (DATA.studio && DATA.studio.background && DATA.studio.background.effect) || "particles";
        const fxClass = { particles: "fx-particles", particles_only: "fx-particles-only", glow: "fx-glow", off: "fx-off" }[fx] || "fx-particles";
        document.body.classList.add(fxClass);
        injectChrome();
        applyI18n();          // statische [data-i18n]-Texte übersetzen
        initShell();
        const page = document.body.dataset.page;
        // Seiten-Renderer abschirmen: ein Fehler hier darf NICHT verhindern, dass
        // Footer, Cookie-Banner, Maskottchen usw. unten noch initialisiert werden.
        try {
            await ({
                home: renderHome,
                games: renderGamesList,
                game: renderGame,
                devlog: renderDevlog,
                legal: renderLegal,
                lore: renderLore,
                presskit: renderPressKit,
                contact: renderContact,
            }[page] || (() => {}))();
        } catch (e) { try { console.error("PLANIGAMES Seiten-Render-Fehler:", e); } catch {} }
        if (PLACER && page === "game") { initPlacerMode(); return; }
        // Studio-Daten für den Footer sicherstellen (auch ohne Home)
        if (!DATA.studio) await loadData("studio");
        fillStudioFooter();
        initNewsletter();
        initContact();
        injectOrgJsonLd();
        initCookieBanner();
        initKonami();
        initMascot();
        initCursorSparkles();
        initTipJar();
        initBadges();
        trackView();
        if (qs.has("diag")) renderDiag();
    }

    /* ---------- Logo-Platzierung im Admin (?_placer=1, nur im Rahmen) ----------
       Das Admin zeigt die ECHTE Spielseite in Bildschirmgröße und steuert den Kopfbereich
       über window.PLANIGAMES.placer: update() baut den Hero mit geänderten Werten neu,
       move() verschiebt das Logo, measure() liefert die Lage aller Elemente. */
    function initPlacerMode() {
        const st = document.createElement("style");
        st.textContent = ".reveal,.reveal-on,.line-inner,[data-hero] *{opacity:1!important;transform:none;transition:none!important;animation:none!important}"
            + "#cookie-banner,.pg-mascot,#fx-layer,#cursor-ring,#cursor-dot,#scroll-progress{display:none!important}"
            + "html,body{overflow:hidden!important}*{cursor:default!important}";
        document.head.appendChild(st);
        const games = (DATA.games && DATA.games.games) || [];
        const slug = String(qs.get("slug") || "").toLowerCase();
        const base = games.find(x => String(x.slug || "").toLowerCase() === slug);
        if (!base) return;
        let cur = JSON.parse(JSON.stringify(base));
        const heroBlock = () => (cur.blocks || []).find(b => b && b.type === "hero" && !b.hidden) || { type: "hero" };
        const rect = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return r.width || r.height ? { x: r.left, y: r.top, w: r.width, h: r.height } : null; };
        (window.PLANIGAMES = window.PLANIGAMES || {}).placer = {
            async update(patch) {
                const p = patch || {};
                const { hero, ...rest } = p;
                cur = { ...cur, ...rest };
                if (hero) { const hb = heroBlock(); Object.assign(hb, hero); if (!(cur.blocks || []).includes(hb)) cur.blocks = [hb, ...(cur.blocks || [])]; }
                if (logoPath(cur) && logoBoxOf(cur) === undefined) await measureLogoBox(logoPath(cur));   // neues Logo: Rand erst messen
                applyAccent(cur.accent || "#8b5cf6", cur.accent2 || "#22d3ee");
                const old = document.querySelector("[data-hero]");
                const tmp = document.createElement("div");
                tmp.innerHTML = blockHero(heroBlock(), cur).trim();
                const sec = tmp.firstElementChild;
                if (old) old.replaceWith(sec); else document.querySelector("[data-game-root]")?.prepend(sec);
                return this.measure();
            },
            measure() {
                const vis = (el) => el && getComputedStyle(el).display !== "none" ? el : null;
                return {
                    vw: window.innerWidth, vh: window.innerHeight,
                    section: rect(document.querySelector("[data-hero]")),
                    inner: rect(document.querySelector("[data-hero-inner]")),
                    logo: rect(vis(document.querySelector('[data-hero-logo="inline"], [data-hero-logo="title"]'))),
                    // Ausschnitt aktiv: logo ist bereits genau der sichtbare Teil
                    cropped: !!document.querySelector('[data-hero-logo="inline"][data-logo-crop]'),
                    box: (() => { const b = logoBoxOf(cur); return b ? fmtLogoBox(b, logoPath(cur)) : ""; })(),
                    gap: logoGap(cur),
                    nat: (() => { const el = document.querySelector('[data-hero-logo="inline"]'); const im = el && (el.tagName === "IMG" ? el : el.querySelector("img")); return im ? { w: im.naturalWidth, h: im.naturalHeight, done: im.complete } : null; })(),
                    header: rect(document.getElementById("site-header")),
                    tagline: rect(document.querySelector("[data-hero-tagline]")),
                };
            },
            ready: true,
        };
        document.documentElement.classList.add("pg-placer");
    }

    // Selbst-Diagnose: ?diag=1 an die URL hängen → zeigt, was wirklich live ist.
    function renderDiag() {
        const page = document.body.dataset.page || "?";
        const ok = (b) => b ? "✅" : "❌";
        const lines = [];
        lines.push(["app.js Version", "v" + VERSION + "  (HTML erwartet ?v=…)"]);
        lines.push(["Seite (data-page)", page]);

        // Maskottchen-Diagnose: zeigt, was der Browser meldet & ob es rendert
        const rm = matchMedia("(prefers-reduced-motion: reduce)").matches;
        const ms = (DATA.studio && DATA.studio.mascot) || null;
        lines.push(["Browser meldet 'reduce motion'", rm ? "JA ⚠️ (ab v37 egal fürs Maskottchen)" : "nein ✅"]);
        if (ms) {
            lines.push(["Maskottchen · enabled", String(ms.enabled) + " " + ok(!!ms.enabled)]);
            lines.push(["Maskottchen · im DOM gerendert", ok(!!$(".pg-mascot"))]);
            lines.push(["Maskottchen · Augen aktiv", String(!!(ms.eyes && ms.eyes.enabled))]);
        } else {
            lines.push(["Maskottchen in studio.json", "❌ fehlt / nicht geladen"]);
        }

        // Hero-Countdown (nur Home relevant)
        const c = (DATA.studio && DATA.studio.heroCountdown) || null;
        if (page === "home") {
            lines.push(["[data-hero-countdown] im HTML", ok(!!$("[data-hero-countdown]"))]);
            if (!c) lines.push(["heroCountdown in studio.json", "❌ fehlt"]);
            else {
                let raw = (c.date || "").trim(); if (raw && !raw.includes("T")) raw = raw.replace(" ", "T");
                const ts = raw ? Date.parse(raw) : NaN;
                lines.push(["Countdown · enabled", String(c.enabled) + " " + ok(c.enabled !== false && c.enabled !== "false" && c.enabled !== "0")]);
                lines.push(["Countdown · date", JSON.stringify(c.date || "") ]);
                lines.push(["Countdown · date gültig", isNaN(ts) ? "❌ nicht lesbar" : (new Date(ts)).toLocaleString() + (ts > Date.now() ? " ✅ Zukunft" : " ⚠️ Vergangenheit")]);
                lines.push(["Countdown sichtbar gerendert", ok(!!$("[data-countdown]"))]);
            }
        }

        // Patchnotes (nur Game relevant)
        if (page === "game") {
            const g = (DATA.games && DATA.games.games || []).find(x => x.slug === qs.get("slug")) || (DATA.games && DATA.games.games || [])[0];
            const all = (DATA.patchnotes && DATA.patchnotes.posts) || null;
            lines.push(["[data-game-patchnotes] im HTML", ok(!!$("[data-game-patchnotes]"))]);
            lines.push(["patchnotes.json geladen", all ? ("✅ " + all.length + " Beiträge") : "❌ nicht geladen / leer"]);
            if (g && all) {
                const gs = (g.slug || "").toLowerCase(), gt = (g.title || "").toLowerCase();
                const belongs = (p) => { const v = (p.game || "").trim().toLowerCase(); if (v && (v === gs || v === gt)) return true; return (p.tags || []).some(t => { const x = String(t).toLowerCase(); return x === gs || x === gt; }); };
                const mine = all.filter(p => belongs(p));
                const pub = mine.filter(isPublished);
                lines.push(["Spiel (slug)", g.slug || "?"]);
                lines.push(["Beiträge mit game=" + (g.slug || "?"), String(mine.length) + " " + ok(mine.length)]);
                lines.push(["davon veröffentlicht (nicht Entwurf/geplant)", String(pub.length) + " " + ok(pub.length)]);
                if (mine.length) lines.push(["game-Werte der Beiträge", JSON.stringify(all.map(p => p.game || ""))]);
            }
        }

        const box = document.createElement("div");
        box.style.cssText = "position:fixed;inset:auto 12px 12px auto;z-index:99999;max-width:min(92vw,460px);max-height:80vh;overflow:auto;background:#0e0e14;color:#eee;border:1px solid #ff7d1a;border-radius:12px;padding:14px 16px;font:12px/1.5 ui-monospace,Menlo,monospace;box-shadow:0 16px 48px rgba(0,0,0,.6)";
        box.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem"><b style="color:#ff7d1a">PLANIGAMES Diagnose</b><button style="background:#ff7d1a;border:0;border-radius:6px;padding:2px 8px;cursor:pointer;font-weight:700">✕</button></div>'
            + lines.map(([k, v]) => `<div style="display:flex;gap:10px;padding:2px 0;border-bottom:1px solid #222"><span style="color:#9aa;flex:0 0 46%">${esc(k)}</span><span style="flex:1;word-break:break-word">${esc(v)}</span></div>`).join("");
        box.querySelector("button").addEventListener("click", () => box.remove());
        document.body.appendChild(box);
    }

    // Datenschutzfreundliche Statistik: nur aggregierter Seitenaufruf (kein Cookie, keine IP)
    function trackView() {
        if (IN_FRAME) return;   // Admin-Vorschau / Logo-Platzierung nicht mitzählen
        try {
            const page = document.body.dataset.page || "page";
            const slug = qs.get("slug");
            const label = (slug ? page + ":" + slug : page).slice(0, 60);
            const data = new FormData(); data.append("p", label);
            // Verweis-Quelle: nur die Domain (keine vollständige URL), externe ausgenommen
            try {
                if (document.referrer) {
                    const rh = new URL(document.referrer).hostname.replace(/^www\./, "");
                    if (rh && rh !== location.hostname.replace(/^www\./, "")) data.append("r", rh.slice(0, 80));
                }
            } catch (e) {}
            if (navigator.sendBeacon) navigator.sendBeacon("stats.php", data);
            else fetch("stats.php", { method: "POST", body: data, keepalive: true });
        } catch (e) {}
    }

    // Strukturierte Daten (Organization) für Seiten ohne serverseitiges JSON-LD
    function injectOrgJsonLd() {
        if (document.querySelector('script[type="application/ld+json"]')) return;
        const s = DATA.studio; if (!s) return;
        const base = location.origin;
        const sameAs = (s.socials || []).map(x => x.url).filter(u => u && u !== "#");
        const org = { "@context": "https://schema.org", "@type": "Organization", name: s.name || "PLANIGAMES", url: base + "/", logo: base + "/media/og.jpg" };
        if (s.email) org.email = s.email;
        if (sameAs.length) org.sameAs = sameAs;
        const sc = document.createElement("script");
        sc.type = "application/ld+json"; sc.textContent = JSON.stringify(org);
        document.head.appendChild(sc);
    }

    if (document.readyState === "loading") addEventListener("DOMContentLoaded", boot);
    else boot();

    // für Debugging im Browser
    window.PLANIGAMES = { DATA, loadData, VERSION };
})();
