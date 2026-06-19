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

    /* ---------- kleine Helfer ---------- */
    const $  = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const esc = (s = "") => String(s).replace(/[&<>"']/g, c => (
        { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]
    ));
    const slugify = (s = "") => String(s).toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");

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
        return `<div class="flex items-center gap-3 flex-wrap mt-14 pt-8 border-t border-white/10">
            <span class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 mr-1">${t("share")}</span>
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

    /* =========================================================
       0) SPRACHE (i18n) — DE / EN
       ========================================================= */
    const I18N = {
        de: {
            nav_studio: "Studio", nav_games: "Games", nav_devlog: "Devlog", nav_contact: "Kontakt",
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
    // Tiefen-Merge: englische Werte über deutsche legen, leeres EN fällt auf DE zurück
    function mergeLang(de, en) {
        if (en === undefined || en === null || en === "") return de;
        if (Array.isArray(de)) {
            if (!Array.isArray(en)) return de;
            return de.map((d, i) => mergeLang(d, en[i]));
        }
        if (de && typeof de === "object") {
            const out = Array.isArray(de) ? [] : { ...de };
            for (const k of Object.keys(de)) out[k] = mergeLang(de[k], en ? en[k] : undefined);
            return out;
        }
        return en; // Skalar: EN gewinnt (wenn nicht leer, s. o.)
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
                  + `--fx-c:${color};--dur:${dur.toFixed(1)}s;--delay:${delay.toFixed(1)}s;--dx:${drift.toFixed(0)}px;--o:${op}"></span>`;
        }
        layer.innerHTML = html;
    }

    /* ---------- Cookie-/Consent-Banner ---------- */
    function pgConsent() { try { return localStorage.getItem("pg_consent"); } catch { return null; } }
    function setConsent(v) {
        try { localStorage.setItem("pg_consent", v); } catch {}
        const banner = $("#cookie-banner");
        if (banner) banner.remove();
        if (v === "all") { $$("[data-yt-embed]").forEach(loadYouTube); }   // Trailer nachladen
    }
    function loadYouTube(holder) {
        const id = holder.getAttribute("data-yt-embed");
        if (!id) return;
        holder.innerHTML = `<iframe class="absolute inset-0 w-full h-full" src="https://www.youtube-nocookie.com/embed/${esc(id)}?autoplay=1" title="Trailer" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
    }
    function initCookieBanner() {
        const cfg = (DATA.studio && DATA.studio.cookie) || {};
        if (cfg.enabled === false) return;
        if (pgConsent()) return;                       // bereits entschieden
        const msg = cfg.message || "Wir nutzen nur, was die Seite zum Laufen braucht. Für eingebettete YouTube-Trailer brauchen wir deine Zustimmung.";
        const acc = cfg.acceptLabel || "Alle akzeptieren";
        const dec = cfg.declineLabel || "Nur Notwendige";
        const el = document.createElement("div");
        el.id = "cookie-banner";
        el.setAttribute("role", "dialog");
        el.setAttribute("aria-label", "Cookie-Hinweis");
        el.innerHTML = `
            <p class="cb-text">${esc(msg)} <a href="rechtliches.html#datenschutz">${esc(t("foot_datenschutz"))}</a></p>
            <div class="cb-actions">
                <button class="cb-decline" data-consent="essential">${esc(dec)}</button>
                <button class="cb-accept" data-consent="all">${esc(acc)}</button>
            </div>`;
        document.body.appendChild(el);
        el.addEventListener("click", (e) => {
            const b = e.target.closest("[data-consent]");
            if (b) setConsent(b.dataset.consent);
        });
        requestAnimationFrame(() => el.classList.add("in"));
    }
    // Footer-Link „Cookie-Einstellungen" öffnet das Banner erneut
    function reopenCookieBanner() {
        try { localStorage.removeItem("pg_consent"); } catch {}
        if (!$("#cookie-banner")) initCookieBanner();
    }

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
            if (all) { e.preventDefault(); setConsent("all"); }
        });
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
        const upd = () => h.classList.toggle("scrolled", scrollY > 40);
        addEventListener("scroll", upd, { passive: true }); upd();
    }

    function initMobileMenu() {
        const btn = $("#menu-toggle"), menu = $("#mobile-menu");
        if (!btn || !menu) return;
        const setOpen = (open) => {
            menu.classList.toggle("open", open);
            btn.classList.toggle("open", open);
            btn.setAttribute("aria-expanded", String(open));
            btn.setAttribute("aria-label", open ? "Menü schließen" : "Menü öffnen");
            menu.setAttribute("aria-hidden", String(!open));
            document.body.classList.toggle("menu-open", open);
        };
        btn.addEventListener("click", () => setOpen(!menu.classList.contains("open")));
        $$("a", menu).forEach(a => a.addEventListener("click", () => setOpen(false)));
        addEventListener("keydown", (e) => { if (e.key === "Escape") setOpen(false); });
    }

    function initReveal() {
        const els = $$(".reveal, .reveal-on");
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
        $$(".reveal, .reveal-on", root).forEach(el => {
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

    // Laufband nahtlos füllen: jede Hälfte breiter als der Viewport -> kein „Stopp"
    function initMarquee() {
        const halves = $$(".marquee-half");
        if (!halves.length) return;
        // Im Admin editierbar: studio.marquee (eine Phrase pro Zeile)
        const raw = (DATA.studio && DATA.studio.marquee) || "";
        let phrases = String(raw).split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        if (!phrases.length) phrases = ["Original Worlds", "Handcrafted Magic", "Indie & Proud", "Wobbly Wizards"];
        const group = phrases.map(p => `<span>${esc(p)}</span><span class="text-[color:var(--accent)]">✦</span>`).join("");
        const reps = Math.max(3, Math.ceil((window.innerWidth * 1.3) / 420));
        const html = group.repeat(reps);
        halves.forEach(h => h.innerHTML = html);
    }

    /* Akzentfarbe live setzen (Game-Welten) */
    function applyAccent(hex, hex2) {
        if (hex)  document.documentElement.style.setProperty("--accent", hex);
        if (hex2) document.documentElement.style.setProperty("--accent-2", hex2);
    }

    const STATUS = {
        released:   { key: "st_released",    cls: "text-emerald-300 border-emerald-400/30 bg-emerald-400/10" },
        early:      { key: "st_early",       cls: "text-orange-300 border-orange-400/30 bg-orange-400/10" },
        demo:       { key: "st_demo",        cls: "text-amber-200 border-amber-300/30 bg-amber-300/10" },
        development:{ key: "st_development", cls: "text-orange-300 border-orange-400/30 bg-orange-400/10" },
        announced:  { key: "st_announced",   cls: "text-zinc-300 border-white/20 bg-white/5" },
    };
    function statusBadge(s) {
        const m = STATUS[s] || STATUS.development;
        return `<span class="badge inline-flex items-center gap-2 px-3 py-1 rounded-full border ${m.cls}">
            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>${t(m.key)}</span>`;
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
                DATA[n] = en ? mergeLang(de, en) : de;
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
            ["background", "logo", "favicon", "heroBackground", "heroVideo"].forEach(k => {
                if (de[k] !== undefined) merged[k] = de[k];
            });
        } else if (name === "games" && Array.isArray(merged.games) && Array.isArray(de.games)) {
            const SHARED_GAME = ["accent", "accent2", "cover", "logo", "logoScale", "wishlistUrl", "status", "featured", "slug"];
            merged.games.forEach((g, i) => {
                const d = de.games[i]; if (!d) return;
                SHARED_GAME.forEach(k => { if (d[k] !== undefined) g[k] = d[k]; });
            });
        }
    }

    /* =========================================================
       3) STUDIO-HOME
       ========================================================= */
    async function renderHome() {
        await loadData("studio", "team", "games", "patchnotes");
        const s = DATA.studio || {};
        const games = (DATA.games && DATA.games.games) || [];
        const posts = ((DATA.patchnotes && DATA.patchnotes.posts) || [])
            .filter(isPublished).slice().sort((a, b) => (b.date || "").localeCompare(a.date || ""));

        // Texte aus dem CMS einsetzen
        setText("[data-studio='name']", s.name);
        setText("[data-studio='tagline']", s.tagline);
        setText("[data-studio='founded']", s.founded);
        setHTML("[data-studio='heroLine1']", esc(s.heroLine1 || ""));
        setHTML("[data-studio='heroLine2']", esc(s.heroLine2 || ""));
        setText("[data-studio='intro']", s.intro);
        setText("[data-studio='aboutTitle']", s.aboutTitle);

        initMarquee();
        renderHeroCountdown(s);

        // Hero-Hintergrund (Bild/Video) – optional aus den Studio-Einstellungen
        const heroBg = $("[data-hero-bg]");
        if (heroBg && (s.heroVideo || s.heroBackground)) {
            const media = s.heroVideo
                ? `<video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline ${s.heroBackground ? `poster="${esc(s.heroBackground)}"` : ""}><source src="${esc(s.heroVideo)}"></video>`
                : `<img src="${esc(s.heroBackground)}" alt="" class="absolute inset-0 w-full h-full object-cover">`;
            heroBg.innerHTML = media
                + `<div class="absolute inset-0" style="background:linear-gradient(to top, var(--bg) 6%, color-mix(in srgb,var(--accent) 10%, transparent) 60%, rgba(0,0,0,.45))"></div>`
                + `<div class="absolute inset-0 bg-black/40"></div>`;
        }
        const aboutBody = $("[data-studio='aboutBody']");
        if (aboutBody && s.aboutBody) aboutBody.innerHTML = md(s.aboutBody);

        // Werte / Pillars
        const pillars = $("[data-studio='pillars']");
        if (pillars && Array.isArray(s.pillars)) {
            pillars.innerHTML = s.pillars.map((p, i) => `
                <div class="reveal tilt-card relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02] p-7" style="transition-delay:${i * 80}ms">
                    <div class="card-glow"></div><div class="card-shine"></div>
                    <div class="text-3xl mb-4">${esc(p.icon || "✦")}</div>
                    <h3 class="text-lg font-bold text-white mb-2">${esc(p.title || "")}</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">${esc(p.text || "")}</p>
                </div>`).join("");
            initTilt(pillars); observeReveals(pillars);
        }

        // Team (eigene Datei team.json)
        const team = $("[data-studio='team']");
        const members = (DATA.team && DATA.team.members) || [];
        if (team && members.length) {
            team.innerHTML = members.map((m, i) => `
                <div class="reveal text-center w-48 sm:w-56" style="transition-delay:${i * 70}ms">
                    <div class="relative mx-auto w-40 h-40 sm:w-48 sm:h-48 rounded-3xl overflow-hidden border border-white/10 bg-white/5 transition-transform duration-500 hover:scale-[1.03]">
                        ${m.photo ? `<img src="${esc(m.photo)}" alt="${esc(m.photoAlt || m.name)}" class="w-full h-full object-cover">`
                                  : `<div class="w-full h-full grid place-items-center text-6xl">${esc(m.emoji || "🧙")}</div>`}
                        <div class="absolute inset-0 ring-1 ring-inset ring-white/10 rounded-3xl"></div>
                    </div>
                    <div class="mt-5 text-xl font-bold text-white">${esc(m.name || "")}</div>
                    <div class="font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] mt-1.5">${esc(m.role || "")}</div>
                </div>`).join("");
            observeReveals(team);
        } else { $("#team-section")?.remove(); }

        // Featured Game + Games-Teaser
        const featured = games.find(g => g.featured) || games[0];
        if (featured) renderFeatured(featured);
        const grid = $("[data-games='teaser']");
        if (grid && games.length) {
            grid.innerHTML = games.map(gameCard).join("");
            initTilt(grid); observeReveals(grid);
        }

        // Neueste Devlog-Einträge
        const dl = $("[data-devlog='latest']");
        if (dl) {
            if (posts.length) {
                dl.innerHTML = posts.slice(0, 3).map(p => devlogRow(p, games)).join("");
                observeReveals(dl);
            } else { $("#devlog-section")?.remove(); }
        }

        renderCommunity(s);
    }

    // Release-Countdown im Hero (im Admin konfigurierbar)
    function renderHeroCountdown(s) {
        const mount = $("[data-hero-countdown]");
        if (!mount) return;
        const c = s.heroCountdown || {};
        const ts = c.date ? Date.parse(c.date) : NaN;
        if (c.enabled === false || isNaN(ts) || ts <= Date.now()) { mount.innerHTML = ""; return; }
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

    // Discord-/Community-Banner (vor der Newsletter-Sektion einsetzen)
    function renderCommunity(s) {
        const c = s.community || {};
        if (c.enabled === false || !c.url || c.url === "#") return;
        const nl = $("[data-newsletter-section]");
        const sec = document.createElement("section");
        sec.className = "relative px-6 py-12 md:py-20";
        sec.innerHTML = `
            <div class="max-w-6xl mx-auto reveal relative overflow-hidden rounded-3xl border border-white/10 p-10 md:p-14 flex flex-col md:flex-row items-center gap-8 md:gap-12"
                 style="background:radial-gradient(120% 140% at 0% 0%, color-mix(in srgb,var(--accent) 20%, transparent), transparent 60%)">
                <div class="text-6xl md:text-7xl shrink-0">💬</div>
                <div class="flex-1 text-center md:text-left">
                    <h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-3">${esc(c.heading || "Komm in unsere Community")}</h2>
                    <p class="text-zinc-300 text-lg max-w-2xl">${esc(c.text || "")}</p>
                </div>
                <a href="${esc(c.url)}" target="_blank" rel="noopener" class="btn-accent magnetic px-8 py-4 rounded-full font-semibold whitespace-nowrap shrink-0">${esc(c.buttonLabel || "Discord beitreten")}</a>
            </div>`;
        if (nl && nl.parentNode) nl.parentNode.insertBefore(sec, nl);
        else document.body.appendChild(sec);
        observeReveals(sec);
    }

    function renderFeatured(g) {
        const wrap = $("[data-featured]");
        if (!wrap) return;
        // Game-Farbe nur innerhalb der Featured-Card – der Studio-Rahmen behält seinen Look.
        wrap.style.setProperty("--accent", g.accent || "#8b5cf6");
        const ls = logoScale(g);
        wrap.innerHTML = `
            <div class="relative grid lg:grid-cols-2 gap-10 items-center rounded-3xl border border-white/10 overflow-hidden bg-gradient-to-br from-white/[0.04] to-transparent p-2 md:p-3">
                <a href="game.php?slug=${esc(g.slug)}" class="block relative aspect-[16/10] rounded-2xl overflow-hidden group">
                    ${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.coverAlt || g.title)}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
                              : `<div class="w-full h-full grid-lines bg-white/[0.03]"></div>`}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent"></div>
                </a>
                <div class="p-5 md:p-8">
                    <div class="flex items-center gap-3 mb-5">${statusBadge(g.status)}
                        <span class="badge text-zinc-500">${t("current_project")}</span></div>
                    ${g.logo ? `<img src="${esc(g.logo)}" alt="${esc(g.title)}" class="mb-4 object-contain object-left" style="height:${Math.round(80 * ls)}px;width:auto;max-width:100%">`
                             : `<h2 class="font-display text-4xl md:text-6xl font-extrabold text-white mb-4">${esc(g.title)}</h2>`}
                    <p class="text-zinc-300 text-lg leading-relaxed mb-7 max-w-xl">${esc(g.tagline || "")}</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="game.php?slug=${esc(g.slug)}" class="btn-accent magnetic px-7 py-3.5 rounded-full font-semibold text-sm">${t("to_game")}</a>
                        ${g.wishlistUrl ? `<a href="${esc(g.wishlistUrl)}" target="_blank" rel="noopener" class="btn-ghost magnetic px-7 py-3.5 rounded-full font-semibold text-sm">${t("wishlist")}</a>` : ""}
                    </div>
                </div>
            </div>`;
        initMagnetic();
    }

    function gameCard(g) {
        return `
        <a href="game.php?slug=${esc(g.slug)}" class="reveal tilt-card group relative block overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]" style="--accent:${esc(g.accent || "#8b5cf6")}">
            <div class="card-glow"></div><div class="card-shine"></div>
            <div class="relative aspect-[16/10] overflow-hidden">
                ${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.coverAlt || g.title)}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
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

    function devlogRow(p, games) {
        const g = games.find(x => x.slug === p.game);
        return `
        <a href="devlog.php?slug=${esc(p.slug)}" class="reveal group flex items-center gap-5 py-5 border-b border-white/8 hover:border-white/20 transition-colors">
            <div class="font-mono text-xs text-zinc-500 w-28 shrink-0">${esc(fmtDate(p.date))}</div>
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    ${g ? `<span class="badge px-2 py-0.5 rounded border border-white/10 text-zinc-400" style="--accent:${esc(g.accent || "#8b5cf6")}">${esc(g.title)}</span>` : ""}
                    ${p.version ? `<span class="badge text-[color:var(--accent)]" style="--accent:${esc(g?.accent || "#8b5cf6")}">v${esc(p.version)}</span>` : ""}
                </div>
                <div class="text-white font-semibold mt-1 group-hover:text-gradient">${esc(p.title)}</div>
            </div>
            <div class="text-zinc-600 group-hover:text-white transition-transform group-hover:translate-x-1">→</div>
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
        const games = (DATA.games && DATA.games.games) || [];
        const slug = qs.get("slug");
        const g = games.find(x => x.slug === slug) || games[0];
        const root = $("[data-game-root]");
        if (!root) return;
        if (!g) { root.innerHTML = notFound(t("nf_text")); return; }

        applyAccent(g.accent || "#8b5cf6", g.accent2 || "#22d3ee");
        document.title = `${g.title} — PLANIGAMES`;

        const blocks = Array.isArray(g.blocks) ? g.blocks : [];
        root.innerHTML = blocks.map((b, i) => renderBlock(b, g, i)).join("");

        // Patch Notes dieses Spiels anhängen (falls Platzhalter vorhanden)
        const pnMount = $("[data-game-patchnotes]");
        if (pnMount) {
            // Tolerant zuordnen: Slug ODER Titel (case-insensitive), auch via Tags
            const gslug = (g.slug || "").toLowerCase(), gtitle = (g.title || "").toLowerCase();
            const belongs = (p) => {
                const v = (p.game || "").trim().toLowerCase();
                if (v && (v === gslug || v === gtitle)) return true;
                return (p.tags || []).some(tg => { const x = String(tg).toLowerCase(); return x === gslug || x === gtitle; });
            };
            const posts = ((DATA.patchnotes && DATA.patchnotes.posts) || [])
                .filter(p => isPublished(p) && belongs(p))
                .sort((a, b) => (b.date || "").localeCompare(a.date || "")).slice(0, 3);
            if (posts.length) {
                pnMount.innerHTML = posts.map(p => `
                    <a href="devlog.php?slug=${esc(p.slug)}" class="reveal group flex items-center gap-4 py-4 border-b border-white/8 hover:border-white/20">
                        <span class="font-mono text-xs text-zinc-500 w-28 shrink-0">${esc(fmtDate(p.date))}</span>
                        ${p.version ? `<span class="badge text-[color:var(--accent)]">v${esc(p.version)}</span>` : ""}
                        <span class="flex-1 text-white font-medium group-hover:text-gradient">${esc(p.title)}</span>
                        <span class="text-zinc-600 group-hover:translate-x-1 transition-transform">→</span>
                    </a>`).join("");
            } else { $("[data-game-patchnotes-section]")?.remove(); }
        }

        initTilt(root); initMagnetic(); observeReveals(root);
        initLightbox(); initFaq(root); initCountdowns(root); initCounters(root); initRoadmapBars(root);
    }

    // Roadmap-Fortschrittsbalken auf die Zielbreite animieren, sobald sichtbar
    function initRoadmapBars(root = document) {
        const bars = $$(".roadmap-bar", root);
        if (!bars.length) return;
        if (!("IntersectionObserver" in window)) { bars.forEach(b => b.style.width = b.style.getPropertyValue("--target")); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (!en.isIntersecting) return;
                io.unobserve(en.target);
                requestAnimationFrame(() => en.target.style.width = en.target.style.getPropertyValue("--target") || "0%");
            });
        }, { threshold: 0.4 });
        bars.forEach(b => io.observe(b));
    }

    // Mapped jeden Block-Typ auf HTML. Neue Typen hier + in admin/schema.php ergänzen.
    function renderBlock(b, g, i) {
        const type = b.type || b._type;
        switch (type) {
            case "hero": return blockHero(b, g);
            case "richtext": return blockRichtext(b);
            case "features": return blockFeatures(b);
            case "gallery": return blockGallery(b, i);
            case "trailer": return blockTrailer(b);
            case "quotes": return blockQuotes(b);
            case "stats": return blockStats(b);
            case "roadmap": return blockRoadmap(b);
            case "faq": return blockFaq(b);
            case "countdown": return blockCountdown(b);
            case "cta": return blockCTA(b, g);
            case "spacer": return `<div style="height:${Math.max(0, +b.size || 48)}px"></div>`;
            default: return "";
        }
    }

    const sec = (inner, extra = "") =>
        `<section class="relative px-6 ${extra}"><div class="max-w-6xl mx-auto">${inner}</div></section>`;

    function logoScale(g) { return Math.min(3, Math.max(0.3, (+g.logoScale || 100) / 100)); }

    function blockHero(b, g) {
        const bg = b.background || g.cover;
        const ls = logoScale(g);
        const media = b.video
            ? `<video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline ${b.poster ? `poster="${esc(b.poster)}"` : ""}><source src="${esc(b.video)}"></video>`
            : (bg ? `<img src="${esc(bg)}" alt="" class="absolute inset-0 w-full h-full object-cover">`
                  : `<div class="absolute inset-0 grid-lines"></div>`);
        return `
        <section class="relative min-h-[88vh] flex items-end overflow-hidden">
            ${media}
            <div class="absolute inset-0" style="background:linear-gradient(to top, var(--bg) 4%, color-mix(in srgb, var(--accent) 18%, transparent) 55%, rgba(0,0,0,.35))"></div>
            <div class="absolute inset-0 bg-black/30"></div>
            <div class="relative max-w-6xl mx-auto w-full px-6 pb-16 md:pb-24">
                <div class="mb-6 reveal-on">${statusBadge(g.status)}</div>
                ${g.logo ? `<img src="${esc(g.logo)}" alt="${esc(g.title)}" class="mb-6 object-contain object-left reveal" style="height:${Math.round(150 * ls)}px;width:auto;max-width:92vw">`
                         : `<h1 class="font-display text-6xl md:text-8xl font-extrabold text-white mb-6 reveal-on"><span class="line-mask"><span class="line-inner">${esc(g.title)}</span></span></h1>`}
                <p class="text-xl md:text-2xl text-zinc-200 max-w-2xl mb-9 reveal">${esc(b.tagline || g.tagline || "")}</p>
                <div class="flex flex-wrap gap-3 reveal">
                    ${g.wishlistUrl ? `<a href="${esc(g.wishlistUrl)}" target="_blank" rel="noopener" class="btn-accent magnetic px-8 py-4 rounded-full font-semibold">${esc(b.ctaLabel || t("wishlist"))}</a>` : ""}
                    ${b.trailerUrl ? `<a href="${esc(b.trailerUrl)}" target="_blank" rel="noopener" class="btn-ghost magnetic px-8 py-4 rounded-full font-semibold inline-flex items-center gap-2">▶ ${LANG === "en" ? "Watch trailer" : "Trailer ansehen"}</a>` : ""}
                </div>
            </div>
        </section>`;
    }

    function blockRichtext(b) {
        return sec(`
            <div class="grid md:grid-cols-12 gap-8 items-start">
                ${b.heading ? `<div class="md:col-span-4"><h2 class="font-display text-3xl md:text-4xl font-extrabold text-white reveal">${esc(b.heading)}</h2>
                    ${b.kicker ? `<div class="font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] mt-3 reveal">${esc(b.kicker)}</div>` : ""}</div>` : ""}
                <div class="${b.heading ? "md:col-span-8" : "md:col-span-12 max-w-3xl"} prose-pg reveal">${md(b.body || "")}</div>
            </div>`, "py-20 md:py-28");
    }

    function blockFeatures(b) {
        const items = (b.items || []).map((f, i) => `
            <div class="reveal tilt-card relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02] p-7" style="transition-delay:${i * 70}ms">
                <div class="card-glow"></div>
                <div class="text-4xl mb-4">${esc(f.icon || "✦")}</div>
                <h3 class="text-lg font-bold text-white mb-2">${esc(f.title || "")}</h3>
                <p class="text-sm text-zinc-400 leading-relaxed">${esc(f.text || "")}</p>
            </div>`).join("");
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white text-center mb-14 reveal">${esc(b.heading)}</h2>` : ""}
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">${items}</div>`, "py-20 md:py-28");
    }

    function blockGallery(b, gi = 0) {
        const list = (b.images || []).map(im => (typeof im === "string" ? { src: im, alt: "" } : { src: im.image, alt: im.alt || "" })).filter(x => x.src);
        const grp = "g" + gi;
        const imgs = list.map((im, i) => `
            <button type="button" data-lb data-lb-group="${grp}" data-lb-index="${i}" class="reveal group relative block overflow-hidden rounded-xl border border-white/10 ${i % 5 === 0 ? "sm:col-span-2 sm:row-span-2" : ""}" style="transition-delay:${i * 50}ms">
                <img src="${esc(im.src)}" alt="${esc(im.alt)}" loading="lazy" class="w-full h-full object-cover aspect-video transition-transform duration-700 group-hover:scale-105">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="absolute bottom-2 right-2 text-white/0 group-hover:text-white/90 text-lg transition-colors">⤢</div>
            </button>`).join("");
        if (!list.length) return "";
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-12 reveal">${esc(b.heading)}</h2>` : ""}
            <div class="grid sm:grid-cols-3 gap-3 auto-rows-[1fr]">${imgs}</div>`, "py-20 md:py-28");
    }

    function blockTrailer(b) {
        let embed = "";
        const url = b.youtube || b.url || "";
        const yt = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([\w-]{11})/);
        if (yt) {
            const cookieOff = (DATA.studio && DATA.studio.cookie && DATA.studio.cookie.enabled !== false);
            if (cookieOff && pgConsent() !== "all") {
                // DSGVO: YouTube erst nach Zustimmung laden – solange ein Klick-Platzhalter
                const poster = b.poster ? esc(b.poster) : `https://i.ytimg.com/vi/${yt[1]}/hqdefault.jpg`;
                embed = `<div class="yt-consent absolute inset-0" data-yt-embed="${yt[1]}" style="background-image:url('${poster}')">
                    <div class="yt-consent-inner">
                        <p>${esc(t("yt_consent"))}</p>
                        <div class="yt-consent-btns">
                            <button class="btn-accent px-6 py-3 rounded-full font-semibold text-sm" data-yt-load>▶ ${esc(t("yt_load"))}</button>
                            <button class="btn-ghost px-6 py-3 rounded-full font-semibold text-sm" data-consent="all">${esc(t("yt_load_always"))}</button>
                        </div>
                    </div></div>`;
            } else {
                embed = `<iframe class="absolute inset-0 w-full h-full" src="https://www.youtube-nocookie.com/embed/${yt[1]}" title="Trailer" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            }
        } else if (b.file) {
            embed = `<video class="absolute inset-0 w-full h-full object-cover" controls ${b.poster ? `poster="${esc(b.poster)}"` : ""}><source src="${esc(b.file)}"></video>`;
        }
        if (!embed) return "";
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white text-center mb-12 reveal">${esc(b.heading)}</h2>` : ""}
            <div class="reveal relative aspect-video rounded-2xl overflow-hidden border border-white/10 shadow-2xl" style="box-shadow:0 30px 90px -30px color-mix(in srgb,var(--accent) 60%,transparent)">${embed}</div>`,
            "py-20 md:py-28");
    }

    function blockQuotes(b) {
        const items = (b.items || []).map((q, i) => `
            <figure class="reveal rounded-2xl border border-white/10 bg-white/[0.02] p-8" style="transition-delay:${i * 80}ms">
                <div class="text-[color:var(--accent)] text-2xl mb-3">★★★★★</div>
                <blockquote class="text-lg md:text-xl text-zinc-100 font-medium leading-relaxed">„${esc(q.text || "")}"</blockquote>
                <figcaption class="mt-5 font-mono text-xs uppercase tracking-widest text-zinc-500">${esc(q.author || "")}${q.source ? ` · <span class="text-zinc-400">${esc(q.source)}</span>` : ""}</figcaption>
            </figure>`).join("");
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white text-center mb-12 reveal">${esc(b.heading)}</h2>` : ""}
            <div class="grid md:grid-cols-2 gap-5">${items}</div>`, "py-20 md:py-28");
    }

    function blockStats(b) {
        const items = (b.items || []).map((st, i) => `
            <div class="reveal text-center" style="transition-delay:${i * 70}ms">
                <div class="font-display text-4xl md:text-6xl font-extrabold text-gradient" data-count>${esc(st.value || "")}</div>
                <div class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 mt-2">${esc(st.label || "")}</div>
            </div>`).join("");
        return sec(`<div class="grid grid-cols-2 md:grid-cols-4 gap-8 py-12 border-y border-white/10">${items}</div>`, "py-16");
    }

    function blockRoadmap(b) {
        const all = b.items || [];
        const items = all.map((m, i) => {
            const done = m.status === "done", active = m.status === "active";
            return `<div class="reveal relative pl-10 pb-10 last:pb-0" style="transition-delay:${i * 80}ms">
                <div class="absolute left-0 top-1 w-5 h-5 rounded-full border-2 ${done ? "bg-[color:var(--accent)] border-[color:var(--accent)]" : active ? "border-[color:var(--accent)] bg-transparent animate-pulse" : "border-white/25 bg-transparent"}"></div>
                <div class="absolute left-[9px] top-6 bottom-0 w-px bg-white/12"></div>
                <div class="font-mono text-[11px] uppercase tracking-widest ${done ? "text-[color:var(--accent)]" : "text-zinc-500"} mb-1">${esc(m.date || (done ? "Erledigt" : active ? "In Arbeit" : "Geplant"))}</div>
                <h3 class="text-lg font-bold text-white">${esc(m.title || "")}</h3>
                ${m.text ? `<p class="text-sm text-zinc-400 mt-1 max-w-xl">${esc(m.text)}</p>` : ""}
            </div>`;
        }).join("");
        // Live-Fortschritt: erledigte zählen voll, "in Arbeit" zur Hälfte
        let progress = 0;
        if (all.length) {
            const score = all.reduce((s, m) => s + (m.status === "done" ? 1 : m.status === "active" ? 0.5 : 0), 0);
            progress = Math.round((score / all.length) * 100);
        }
        const bar = all.length ? `
            <div class="reveal max-w-2xl mb-10">
                <div class="flex items-end justify-between mb-2">
                    <span class="font-mono text-[11px] uppercase tracking-widest text-zinc-500">${LANG === "en" ? "Development progress" : "Entwicklungs-Fortschritt"}</span>
                    <span class="font-display text-2xl font-extrabold text-gradient tabular-nums" data-count>${progress}%</span>
                </div>
                <div class="h-2.5 rounded-full bg-white/8 overflow-hidden">
                    <div class="roadmap-bar h-full rounded-full" style="width:0%;--target:${progress}%;background:linear-gradient(90deg,var(--accent-2),var(--accent))"></div>
                </div>
            </div>` : "";
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-8 reveal">${esc(b.heading)}</h2>` : ""}
            ${bar}
            <div class="max-w-2xl">${items}</div>`, "py-20 md:py-28");
    }

    function blockFaq(b) {
        const items = (b.items || []).map((q, i) => `
            <div class="reveal faq-item border-b border-white/10" style="transition-delay:${i * 50}ms">
                <button type="button" class="faq-q w-full flex items-center justify-between gap-4 text-left py-5 group" aria-expanded="false">
                    <span class="text-lg md:text-xl font-semibold text-white group-hover:text-gradient">${esc(q.question || "")}</span>
                    <span class="faq-ico shrink-0 w-8 h-8 rounded-full border border-white/15 grid place-items-center text-[color:var(--accent)] text-lg transition-transform">+</span>
                </button>
                <div class="faq-a overflow-hidden" style="max-height:0;transition:max-height .4s cubic-bezier(0.16,1,0.3,1)">
                    <div class="prose-pg pb-6 pr-12">${md(q.answer || "")}</div>
                </div>
            </div>`).join("");
        if (!items) return "";
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-10 reveal">${esc(b.heading)}</h2>` : ""}
            <div class="max-w-3xl">${items}</div>`, "py-20 md:py-28");
    }

    function blockCountdown(b) {
        const ts = b.date ? Date.parse(b.date) : NaN;
        const valid = !isNaN(ts);
        const units = [["d", "Tage", "days"], ["h", "Std", "hrs"], ["m", "Min", "min"], ["s", "Sek", "sec"]];
        const cells = units.map(([k, de, en]) => `
            <div class="text-center">
                <div class="cd-num font-display text-4xl md:text-6xl font-extrabold text-gradient tabular-nums" data-cd="${k}">--</div>
                <div class="font-mono text-[10px] uppercase tracking-widest text-zinc-500 mt-1">${LANG === "en" ? en : de}</div>
            </div>`).join("");
        return sec(`
            <div class="reveal relative overflow-hidden rounded-3xl border border-white/10 p-10 md:p-14 text-center" style="background:radial-gradient(120% 120% at 50% 0%, color-mix(in srgb,var(--accent) 22%, transparent), transparent 60%)">
                ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-3">${esc(b.heading)}</h2>` : ""}
                ${b.label ? `<p class="text-zinc-300 mb-8">${esc(b.label)}</p>` : ""}
                <div class="cd-grid grid grid-cols-4 gap-4 md:gap-8 max-w-xl mx-auto" data-countdown="${valid ? ts : ""}">${cells}</div>
                <div class="cd-done hidden mt-2 text-2xl font-bold text-[color:var(--accent)]"></div>
            </div>`, "py-20 md:py-28");
    }

    function blockCTA(b, g) {
        const href = b.url || g.wishlistUrl || "#";
        return sec(`
            <div class="reveal relative overflow-hidden rounded-3xl border border-white/10 p-10 md:p-16 text-center" style="background:radial-gradient(120% 120% at 50% 0%, color-mix(in srgb,var(--accent) 28%, transparent), transparent 60%)">
                <h2 class="font-display text-3xl md:text-6xl font-extrabold text-white mb-5">${esc(b.heading || "Bereit für das Abenteuer?")}</h2>
                ${b.text ? `<p class="text-zinc-300 text-lg max-w-2xl mx-auto mb-9">${esc(b.text)}</p>` : ""}
                <a href="${esc(href)}" target="_blank" rel="noopener" class="btn-accent magnetic inline-block px-10 py-4 rounded-full font-semibold text-lg">${esc(b.label || "Jetzt wishlisten")}</a>
            </div>`, "py-20 md:py-28");
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

    function initFaq(root = document) {
        $$(".faq-q", root).forEach(btn => btn.addEventListener("click", () => {
            const item = btn.closest(".faq-item"), ans = $(".faq-a", item), ico = $(".faq-ico", btn);
            const open = item.classList.toggle("open");
            ans.style.maxHeight = open ? ans.scrollHeight + "px" : "0";
            btn.setAttribute("aria-expanded", String(open));
            if (ico) ico.textContent = open ? "–" : "+";
        }));
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
        list.innerHTML = shown.map(p => {
            const g = games.find(x => x.slug === p.game);
            return `
            <a href="devlog.php?slug=${esc(p.slug)}" class="reveal tilt-card group relative block overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]" style="--accent:${esc(g?.accent || "#8b5cf6")}">
                <div class="card-glow"></div>
                <div class="grid md:grid-cols-[260px_1fr]">
                    <div class="relative aspect-video md:aspect-auto overflow-hidden">
                        ${p.cover ? `<img src="${esc(p.cover)}" alt="${esc(p.coverAlt || p.title || "")}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
                                  : `<div class="w-full h-full grid-lines bg-white/[0.03] min-h-[160px]"></div>`}
                    </div>
                    <div class="p-7">
                        <div class="flex items-center gap-2 flex-wrap mb-3">
                            <span class="font-mono text-xs text-zinc-500">${esc(fmtDate(p.date))}</span>
                            ${g ? `<span class="badge px-2 py-0.5 rounded border border-white/10 text-zinc-400">${esc(g.title)}</span>` : ""}
                            ${p.version ? `<span class="badge text-[color:var(--accent)]">v${esc(p.version)}</span>` : ""}
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
                ${shareBar(p.title)}
                ${g && g.wishlistUrl ? `<div class="mt-12 pt-8 border-t border-white/10 text-center">
                    <a href="${esc(g.wishlistUrl)}" target="_blank" rel="noopener" class="btn-accent magnetic inline-block px-9 py-4 rounded-full font-semibold">${esc(g.title)} auf Steam wishlisten</a></div>` : ""}
            </div>`;
        initMagnetic();
        initShareCopy();
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
                <div class="aspect-[16/9] overflow-hidden">${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.coverAlt || g.title)}" class="w-full h-full object-cover">` : `<div class="w-full h-full grid-lines"></div>`}</div>
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

    function notFound(msg) {
        return `<div class="min-h-[60vh] grid place-items-center text-center px-6">
            <div><div class="text-6xl mb-6">🪄</div>
            <p class="text-zinc-400 mb-6">${esc(msg)}</p>
            <a href="index.html" class="btn-ghost magnetic inline-block px-7 py-3 rounded-full">${t("nf_home")}</a></div></div>`;
    }

    /* ---------- mini DOM-Helfer für statische Slots ---------- */
    function setText(sel, val) { $$(sel).forEach(el => { if (val != null) el.textContent = val; }); }
    function setHTML(sel, val) { $$(sel).forEach(el => { if (val != null) el.innerHTML = val; }); }

    /* =========================================================
       CHROME — gemeinsame Hülle (Header, Footer, Cursor …)
       wird in jede Seite injiziert, damit die HTML-Dateien
       schlank bleiben und Navigation überall identisch ist.
       ========================================================= */
    const NAV = [
        ["index.html", "nav_studio"],
        ["games.html", "nav_games"],
        ["devlog.php", "nav_devlog"],
        ["kontakt.html", "nav_contact"],
    ];
    function navLinks(extra = "") {
        const here = location.pathname.split("/").pop() || "index.html";
        return NAV.map(([href, key]) => {
            const active = !href.includes("#") && href === here;
            return `<a href="${href}" class="${extra} ${active ? "text-white" : "text-zinc-400 hover:text-white"} transition-colors">${t(key)}</a>`;
        }).join("");
    }
    function mobileNavLinks() {
        const here = location.pathname.split("/").pop() || "index.html";
        return NAV.map(([href, key], i) => {
            const active = !href.includes("#") && href === here;
            return `<a href="${href}" class="mm-link${active ? " is-active" : ""}" style="--i:${i}">${t(key)}</a>`;
        }).join("");
    }
    function langSwitch() {
        const o = LANG === "en" ? "de" : "en";
        return `<button type="button" data-setlang="${o}" class="font-mono text-[11px] uppercase tracking-widest border border-white/15 rounded-full px-3 py-1.5 text-zinc-300 hover:text-white hover:border-white/40 transition-colors" aria-label="Sprache wechseln">
            <span class="${LANG === "de" ? "text-white" : "text-zinc-500"}">DE</span><span class="text-zinc-600 mx-1">/</span><span class="${LANG === "en" ? "text-white" : "text-zinc-500"}">EN</span></button>`;
    }

    function injectChrome() {
        // Pflicht-Effekte oben einsetzen (falls noch nicht vorhanden)
        if (!$("#scroll-progress")) {
            document.body.insertAdjacentHTML("afterbegin", `
                <div id="scroll-progress"></div>
                <div id="cursor-ring"></div><div id="cursor-dot"></div>
                <div class="glow-blob glow-1" style="top:-12%;left:-12%;width:55vw;height:55vw;background:color-mix(in srgb,var(--accent) 13%,transparent)"></div>
                <div class="glow-blob glow-2" style="bottom:-12%;right:-12%;width:50vw;height:50vw;background:color-mix(in srgb,var(--accent-2) 11%,transparent)"></div>
                <div id="fx-layer"></div>`);
        }

        const headerMount = $("[data-shell='header']");
        if (headerMount) headerMount.outerHTML = `
            <header id="site-header" class="fixed top-0 left-0 w-full z-50">
                <div class="max-w-[95%] mx-auto h-20 flex items-center justify-between">
                    <a href="index.html" class="group" aria-label="PLANIGAMES Start">
                        <span data-brand="sm" class="flex items-center gap-3">
                            <span class="inline-block w-3 h-3 rotate-45 rounded-[2px] transition-transform duration-500 group-hover:rotate-[225deg]" style="background:linear-gradient(135deg,var(--accent-2),var(--accent))"></span>
                            <span class="font-display text-lg font-extrabold uppercase tracking-[0.18em] text-white">PLANI<span class="text-gradient">GAMES</span></span>
                        </span>
                    </a>
                    <nav class="hidden md:flex items-center gap-9 font-medium text-sm">${navLinks()}</nav>
                    <div class="hidden md:flex items-center gap-3">
                        ${langSwitch()}
                        <a href="games.html" class="btn-accent magnetic px-5 py-2.5 rounded-full text-sm font-semibold">${t("cta_discover")}</a>
                    </div>
                    <button id="menu-toggle" class="burger md:hidden" aria-label="Menü öffnen" aria-expanded="false">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </header>
            <div id="mobile-menu" class="mobile-menu md:hidden" aria-hidden="true">
                <nav class="mm-nav">
                    ${mobileNavLinks()}
                    <a href="rechtliches.html?doc=impressum" class="mm-sub" style="--i:5">${t("foot_impressum")}</a>
                    <div class="mm-actions" style="--i:6">
                        <a href="games.html" class="btn-accent magnetic px-7 py-3.5 rounded-full text-base font-semibold">${t("cta_discover")}</a>
                        ${langSwitch()}
                    </div>
                </nav>
            </div>`;

        const footerMount = $("[data-shell='footer']");
        if (footerMount) footerMount.outerHTML = `
            <footer id="kontakt" class="relative border-t border-white/10 mt-10">
                <div class="max-w-6xl mx-auto px-6 py-20">
                    <div class="grid md:grid-cols-[1.5fr_1fr_1fr] gap-12">
                        <div>
                            <a href="index.html" data-brand="lg" class="flex items-center gap-3 mb-5">
                                <span class="inline-block w-4 h-4 rotate-45 rounded-[3px]" style="background:linear-gradient(135deg,var(--accent-2),var(--accent))"></span>
                                <span class="font-display text-2xl font-extrabold uppercase tracking-[0.15em] text-white">PLANI<span class="text-gradient">GAMES</span></span>
                            </a>
                            <p class="text-zinc-400 max-w-sm leading-relaxed" data-studio="footerNote">Ein unabhängiges Indie-Spielestudio. Wir bauen Welten, die wackeln, zaubern und im Kopf bleiben.</p>
                            <form action="subscribe.php" method="POST" data-newsletter data-source="footer" class="mt-7 flex max-w-sm gap-2">
                                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden">
                                <input type="hidden" name="source" value="footer">
                                <input type="email" name="email" required placeholder="deine@mail.de" data-nl-input class="flex-1 bg-white/[0.04] border border-white/12 rounded-full px-5 py-3 text-sm text-white placeholder:text-zinc-600 focus:border-[color:var(--accent)] outline-none">
                                <button class="btn-accent magnetic px-5 py-3 rounded-full text-sm font-semibold shrink-0">${t("foot_newsletter_btn")}</button>
                            </form>
                            <p class="text-[11px] text-zinc-600 mt-2">${t("foot_newsletter_note")}</p>
                        </div>
                        <div>
                            <div class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 mb-5">${t("foot_discover")}</div>
                            <ul class="space-y-3 text-zinc-300">
                                <li><a href="games.html" class="hover:text-white">${t("foot_allgames")}</a></li>
                                <li><a href="devlog.php" class="hover:text-white">${t("foot_devlog")}</a></li>
                                <li><a href="index.html#studio" class="hover:text-white">${t("foot_about")}</a></li>
                                <li data-foot-press hidden><a href="presse.html" class="hover:text-white">${t("presskit")}</a></li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 mb-5">${t("foot_contact")}</div>
                            <ul class="space-y-3 text-zinc-300">
                                <li><a href="mailto:hello.dominicmajewski@gmail.com" class="hover:text-white" data-studio="email">hello.dominicmajewski@gmail.com</a></li>
                                <li class="flex gap-4 pt-2 text-zinc-400" data-studio="socials"></li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-16 pt-8 border-t border-white/8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-zinc-600">
                        <div>© <span data-year></span> PLANIGAMES — ${t("foot_rights")}</div>
                        <div class="flex items-center gap-5">
                            <a href="rechtliches.html?doc=impressum" class="hover:text-white">${t("foot_impressum")}</a>
                            <a href="rechtliches.html?doc=datenschutz" class="hover:text-white">${t("foot_datenschutz")}</a>
                            <a href="#" data-cookie-open class="hover:text-white">${t("cookie_settings")}</a>
                            <a href="admin/" class="hover:text-white inline-flex items-center gap-1" title="Zum Dashboard">🔒 Admin</a>
                        </div>
                        <div class="font-mono uppercase tracking-widest">${t("foot_made")}</div>
                    </div>
                </div>
            </footer>`;
    }

    function fillStudioFooter() {
        const s = DATA.studio;
        if (!s) return;
        // Eigenes Logo-Bild (falls im CMS hinterlegt) ersetzt die Wortmarke überall
        if (s.logo) {
            $$("[data-brand]").forEach(el => {
                const lg = el.dataset.brand === "lg";
                el.innerHTML = `<img src="${esc(s.logo)}" alt="${esc(s.name || "PLANIGAMES")}" class="${lg ? "h-9 md:h-10" : "h-7 md:h-8"} w-auto object-contain">`;
            });
        }
        if (s.footerNote) setText("[data-studio='footerNote']", s.footerNote);
        if (s.email) {
            setText("[data-studio='email']", s.email);
            $$("[data-studio='email']").forEach(el => el.tagName === "A" && (el.href = "mailto:" + s.email));
        }
        const soc = $("[data-studio='socials']");
        if (soc && Array.isArray(s.socials)) {
            soc.innerHTML = s.socials.map(x => `<a href="${esc(x.url)}" target="_blank" rel="noopener" class="hover:text-white">${esc(x.label)}</a>`).join("");
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
    async function boot() {
        document.documentElement.lang = LANG;
        await loadData("studio");   // früh laden (Hintergrund-Effekt braucht die Config)
        const fx = (DATA.studio && DATA.studio.background && DATA.studio.background.effect) || "particles";
        const fxClass = { particles: "fx-particles", particles_only: "fx-particles-only", glow: "fx-glow", off: "fx-off" }[fx] || "fx-particles";
        document.body.classList.add(fxClass);
        injectChrome();
        applyI18n();          // statische [data-i18n]-Texte übersetzen
        initShell();
        const page = document.body.dataset.page;
        await ({
            home: renderHome,
            games: renderGamesList,
            game: renderGame,
            devlog: renderDevlog,
            legal: renderLegal,
            presskit: renderPressKit,
            contact: renderContact,
        }[page] || (() => {}))();
        // Studio-Daten für den Footer sicherstellen (auch ohne Home)
        if (!DATA.studio) await loadData("studio");
        fillStudioFooter();
        initNewsletter();
        initContact();
        injectOrgJsonLd();
        initCookieBanner();
        trackView();
    }

    // Datenschutzfreundliche Statistik: nur aggregierter Seitenaufruf (kein Cookie, keine IP)
    function trackView() {
        try {
            const page = document.body.dataset.page || "page";
            const slug = qs.get("slug");
            const label = (slug ? page + ":" + slug : page).slice(0, 60);
            const data = new FormData(); data.append("p", label);
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
    window.PLANIGAMES = { DATA, loadData };
})();
