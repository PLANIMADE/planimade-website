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
            return new Date(iso).toLocaleDateString("de-DE",
                { day: "2-digit", month: "long", year: "numeric" });
        } catch { return iso; }
    }

    const qs = new URLSearchParams(location.search);

    /* =========================================================
       1) SHELL — globale Effekte
       ========================================================= */
    function initShell() {
        initPreloader();
        initCursor();
        initScrollProgress();
        initHeader();
        initMobileMenu();
        initReveal();
        initMagnetic();
        initTilt();
        initYear();
    }

    function initPreloader() {
        const pre = $("#preloader");
        if (!pre) return;
        const fill = $("#pre-bar-fill"), count = $("#pre-count");
        let p = 0;
        const tick = setInterval(() => {
            p = Math.min(100, p + Math.random() * 18);
            if (fill) fill.style.width = p + "%";
            if (count) count.textContent = String(Math.floor(p)).padStart(3, "0");
            if (p >= 100) {
                clearInterval(tick);
                setTimeout(() => {
                    pre.classList.add("done");
                    document.body.classList.remove("overflow-hidden");
                }, 350);
            }
        }, 130);
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
        const close = () => { menu.classList.add("hidden"); btn.setAttribute("aria-expanded", "false"); };
        btn.addEventListener("click", () => {
            const open = menu.classList.toggle("hidden");
            btn.setAttribute("aria-expanded", String(!open));
        });
        $$("a", menu).forEach(a => a.addEventListener("click", close));
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

    /* Akzentfarbe live setzen (Game-Welten) */
    function applyAccent(hex, hex2) {
        if (hex)  document.documentElement.style.setProperty("--accent", hex);
        if (hex2) document.documentElement.style.setProperty("--accent-2", hex2);
    }

    const STATUS = {
        released:   { label: "Veröffentlicht", cls: "text-emerald-300 border-emerald-400/30 bg-emerald-400/10" },
        early:      { label: "Early Access",   cls: "text-orange-300 border-orange-400/30 bg-orange-400/10" },
        demo:       { label: "Demo verfügbar", cls: "text-amber-200 border-amber-300/30 bg-amber-300/10" },
        development:{ label: "In Entwicklung",  cls: "text-orange-300 border-orange-400/30 bg-orange-400/10" },
        announced:  { label: "Angekündigt",    cls: "text-zinc-300 border-white/20 bg-white/5" },
    };
    function statusBadge(s) {
        const m = STATUS[s] || STATUS.development;
        return `<span class="badge inline-flex items-center gap-2 px-3 py-1 rounded-full border ${m.cls}">
            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>${m.label}</span>`;
    }

    /* =========================================================
       2) DATEN
       ========================================================= */
    const DATA = {};
    async function loadData(...names) {
        const map = { studio: "data/studio.json", games: "data/games.json", patchnotes: "data/patchnotes.json" };
        await Promise.all(names.map(async n => { DATA[n] = await fetchJSON(map[n]); }));
        return DATA;
    }

    /* =========================================================
       3) STUDIO-HOME
       ========================================================= */
    async function renderHome() {
        await loadData("studio", "games", "patchnotes");
        const s = DATA.studio || {};
        const games = (DATA.games && DATA.games.games) || [];
        const posts = ((DATA.patchnotes && DATA.patchnotes.posts) || [])
            .slice().sort((a, b) => (b.date || "").localeCompare(a.date || ""));

        // Texte aus dem CMS einsetzen
        setText("[data-studio='name']", s.name);
        setText("[data-studio='tagline']", s.tagline);
        setText("[data-studio='founded']", s.founded);
        setHTML("[data-studio='heroLine1']", esc(s.heroLine1 || ""));
        setHTML("[data-studio='heroLine2']", esc(s.heroLine2 || ""));
        setText("[data-studio='intro']", s.intro);
        setText("[data-studio='aboutTitle']", s.aboutTitle);
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

        // Team
        const team = $("[data-studio='team']");
        if (team && Array.isArray(s.team) && s.team.length) {
            team.innerHTML = s.team.map((m, i) => `
                <div class="reveal text-center" style="transition-delay:${i * 70}ms">
                    <div class="relative mx-auto w-28 h-28 rounded-2xl overflow-hidden border border-white/10 bg-white/5">
                        ${m.photo ? `<img src="${esc(m.photo)}" alt="${esc(m.name)}" class="w-full h-full object-cover">`
                                  : `<div class="w-full h-full grid place-items-center text-3xl">${esc(m.emoji || "🧙")}</div>`}
                    </div>
                    <div class="mt-4 font-bold text-white">${esc(m.name || "")}</div>
                    <div class="font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] mt-1">${esc(m.role || "")}</div>
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
    }

    function renderFeatured(g) {
        const wrap = $("[data-featured]");
        if (!wrap) return;
        // Game-Farbe nur innerhalb der Featured-Card – der Studio-Rahmen behält seinen Look.
        wrap.style.setProperty("--accent", g.accent || "#8b5cf6");
        wrap.innerHTML = `
            <div class="relative grid lg:grid-cols-2 gap-10 items-center rounded-3xl border border-white/10 overflow-hidden bg-gradient-to-br from-white/[0.04] to-transparent p-2 md:p-3">
                <a href="game.html?slug=${esc(g.slug)}" class="block relative aspect-[16/10] rounded-2xl overflow-hidden group">
                    ${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.title)}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
                              : `<div class="w-full h-full grid-lines bg-white/[0.03]"></div>`}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent"></div>
                </a>
                <div class="p-5 md:p-8">
                    <div class="flex items-center gap-3 mb-5">${statusBadge(g.status)}
                        <span class="badge text-zinc-500">Aktuelles Projekt</span></div>
                    ${g.logo ? `<img src="${esc(g.logo)}" alt="${esc(g.title)}" class="h-16 md:h-20 mb-4 object-contain">`
                             : `<h2 class="font-display text-4xl md:text-6xl font-extrabold text-white mb-4">${esc(g.title)}</h2>`}
                    <p class="text-zinc-300 text-lg leading-relaxed mb-7 max-w-xl">${esc(g.tagline || "")}</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="game.html?slug=${esc(g.slug)}" class="btn-accent magnetic px-7 py-3.5 rounded-full font-semibold text-sm">Zum Spiel →</a>
                        ${g.wishlistUrl ? `<a href="${esc(g.wishlistUrl)}" target="_blank" rel="noopener" class="btn-ghost magnetic px-7 py-3.5 rounded-full font-semibold text-sm">Auf Steam wishlisten</a>` : ""}
                    </div>
                </div>
            </div>`;
        initMagnetic();
    }

    function gameCard(g) {
        return `
        <a href="game.html?slug=${esc(g.slug)}" class="reveal tilt-card group relative block overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]" style="--accent:${esc(g.accent || "#8b5cf6")}">
            <div class="card-glow"></div><div class="card-shine"></div>
            <div class="relative aspect-[16/10] overflow-hidden">
                ${g.cover ? `<img src="${esc(g.cover)}" alt="${esc(g.title)}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
                          : `<div class="w-full h-full grid-lines bg-white/[0.03]"></div>`}
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>
                <div class="absolute top-3 left-3">${statusBadge(g.status)}</div>
            </div>
            <div class="relative p-6">
                <h3 class="font-display text-2xl font-extrabold text-white group-hover:text-gradient">${esc(g.title)}</h3>
                <p class="text-sm text-zinc-400 mt-2 line-clamp-2">${esc(g.tagline || "")}</p>
                <div class="mt-4 font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] flex items-center gap-2">
                    Mehr erfahren <span class="transition-transform group-hover:translate-x-1">→</span></div>
            </div>
        </a>`;
    }

    function devlogRow(p, games) {
        const g = games.find(x => x.slug === p.game);
        return `
        <a href="devlog.html?slug=${esc(p.slug)}" class="reveal group flex items-center gap-5 py-5 border-b border-white/8 hover:border-white/20 transition-colors">
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
        if (!games.length) { grid.innerHTML = `<p class="text-zinc-500">Noch keine Spiele angelegt.</p>`; return; }
        grid.innerHTML = games.map(gameCard).join("");
        initTilt(grid); observeReveals(grid);
        setText("[data-games='count']", games.length + (games.length === 1 ? " Titel" : " Titel"));
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
        if (!g) { root.innerHTML = notFound("Dieses Spiel gibt es (noch) nicht."); return; }

        applyAccent(g.accent || "#8b5cf6", g.accent2 || "#22d3ee");
        document.title = `${g.title} — PLANIGAMES`;

        const blocks = Array.isArray(g.blocks) ? g.blocks : [];
        root.innerHTML = blocks.map((b, i) => renderBlock(b, g, i)).join("");

        // Patch Notes dieses Spiels anhängen (falls Platzhalter vorhanden)
        const pnMount = $("[data-game-patchnotes]");
        if (pnMount) {
            const posts = ((DATA.patchnotes && DATA.patchnotes.posts) || [])
                .filter(p => p.game === g.slug)
                .sort((a, b) => (b.date || "").localeCompare(a.date || "")).slice(0, 4);
            if (posts.length) {
                pnMount.innerHTML = posts.map(p => `
                    <a href="devlog.html?slug=${esc(p.slug)}" class="reveal group flex items-center gap-4 py-4 border-b border-white/8 hover:border-white/20">
                        <span class="font-mono text-xs text-zinc-500 w-28 shrink-0">${esc(fmtDate(p.date))}</span>
                        ${p.version ? `<span class="badge text-[color:var(--accent)]">v${esc(p.version)}</span>` : ""}
                        <span class="flex-1 text-white font-medium group-hover:text-gradient">${esc(p.title)}</span>
                        <span class="text-zinc-600 group-hover:translate-x-1 transition-transform">→</span>
                    </a>`).join("");
            } else { $("[data-game-patchnotes-section]")?.remove(); }
        }

        initTilt(root); initMagnetic(); observeReveals(root);
    }

    // Mapped jeden Block-Typ auf HTML. Neue Typen hier + in admin/config.yml ergänzen.
    function renderBlock(b, g, i) {
        const type = b.type || b._type;
        switch (type) {
            case "hero": return blockHero(b, g);
            case "richtext": return blockRichtext(b);
            case "features": return blockFeatures(b);
            case "gallery": return blockGallery(b);
            case "trailer": return blockTrailer(b);
            case "quotes": return blockQuotes(b);
            case "stats": return blockStats(b);
            case "roadmap": return blockRoadmap(b);
            case "cta": return blockCTA(b, g);
            case "spacer": return `<div style="height:${Math.max(0, +b.size || 48)}px"></div>`;
            default: return "";
        }
    }

    const sec = (inner, extra = "") =>
        `<section class="relative px-6 ${extra}"><div class="max-w-6xl mx-auto">${inner}</div></section>`;

    function blockHero(b, g) {
        const bg = b.background || g.cover;
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
                ${g.logo ? `<img src="${esc(g.logo)}" alt="${esc(g.title)}" class="h-24 md:h-40 mb-6 object-contain reveal">`
                         : `<h1 class="font-display text-6xl md:text-8xl font-extrabold text-white mb-6 reveal-on"><span class="line-mask"><span class="line-inner">${esc(g.title)}</span></span></h1>`}
                <p class="text-xl md:text-2xl text-zinc-200 max-w-2xl mb-9 reveal">${esc(b.tagline || g.tagline || "")}</p>
                <div class="flex flex-wrap gap-3 reveal">
                    ${g.wishlistUrl ? `<a href="${esc(g.wishlistUrl)}" target="_blank" rel="noopener" class="btn-accent magnetic px-8 py-4 rounded-full font-semibold">${esc(b.ctaLabel || "Auf Steam wishlisten")}</a>` : ""}
                    ${b.trailerUrl ? `<a href="${esc(b.trailerUrl)}" target="_blank" rel="noopener" class="btn-ghost magnetic px-8 py-4 rounded-full font-semibold inline-flex items-center gap-2">▶ Trailer ansehen</a>` : ""}
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

    function blockGallery(b) {
        const imgs = (b.images || []).map((im, i) => {
            const src = typeof im === "string" ? im : im.image;
            return `<a href="${esc(src)}" target="_blank" rel="noopener" class="reveal group relative block overflow-hidden rounded-xl border border-white/10 ${i % 5 === 0 ? "sm:col-span-2 sm:row-span-2" : ""}" style="transition-delay:${i * 50}ms">
                <img src="${esc(src)}" alt="" class="w-full h-full object-cover aspect-video transition-transform duration-700 group-hover:scale-105">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            </a>`;
        }).join("");
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-12 reveal">${esc(b.heading)}</h2>` : ""}
            <div class="grid sm:grid-cols-3 gap-3 auto-rows-[1fr]">${imgs}</div>`, "py-20 md:py-28");
    }

    function blockTrailer(b) {
        let embed = "";
        const url = b.youtube || b.url || "";
        const yt = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([\w-]{11})/);
        if (yt) {
            embed = `<iframe class="absolute inset-0 w-full h-full" src="https://www.youtube-nocookie.com/embed/${yt[1]}" title="Trailer" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
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
                <div class="font-display text-4xl md:text-6xl font-extrabold text-gradient">${esc(st.value || "")}</div>
                <div class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 mt-2">${esc(st.label || "")}</div>
            </div>`).join("");
        return sec(`<div class="grid grid-cols-2 md:grid-cols-4 gap-8 py-12 border-y border-white/10">${items}</div>`, "py-16");
    }

    function blockRoadmap(b) {
        const items = (b.items || []).map((m, i) => {
            const done = m.status === "done", active = m.status === "active";
            return `<div class="reveal relative pl-10 pb-10 last:pb-0" style="transition-delay:${i * 80}ms">
                <div class="absolute left-0 top-1 w-5 h-5 rounded-full border-2 ${done ? "bg-[color:var(--accent)] border-[color:var(--accent)]" : active ? "border-[color:var(--accent)] bg-transparent animate-pulse" : "border-white/25 bg-transparent"}"></div>
                <div class="absolute left-[9px] top-6 bottom-0 w-px bg-white/12"></div>
                <div class="font-mono text-[11px] uppercase tracking-widest ${done ? "text-[color:var(--accent)]" : "text-zinc-500"} mb-1">${esc(m.date || (done ? "Erledigt" : active ? "In Arbeit" : "Geplant"))}</div>
                <h3 class="text-lg font-bold text-white">${esc(m.title || "")}</h3>
                ${m.text ? `<p class="text-sm text-zinc-400 mt-1 max-w-xl">${esc(m.text)}</p>` : ""}
            </div>`;
        }).join("");
        return sec(`
            ${b.heading ? `<h2 class="font-display text-3xl md:text-5xl font-extrabold text-white mb-12 reveal">${esc(b.heading)}</h2>` : ""}
            <div class="max-w-2xl">${items}</div>`, "py-20 md:py-28");
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

    /* =========================================================
       6) DEVLOG / PATCH NOTES
       ========================================================= */
    async function renderDevlog() {
        await loadData("games", "patchnotes");
        const games = (DATA.games && DATA.games.games) || [];
        const posts = ((DATA.patchnotes && DATA.patchnotes.posts) || [])
            .slice().sort((a, b) => (b.date || "").localeCompare(a.date || ""));
        const slug = qs.get("slug");

        if (slug) return renderDevlogSingle(posts.find(p => p.slug === slug), games);

        const list = $("[data-devlog='list']");
        if (!list) return;
        if (!posts.length) { list.innerHTML = `<p class="text-zinc-500">Noch keine Einträge.</p>`; return; }

        // Filter-Chips pro Spiel
        const filterWrap = $("[data-devlog='filters']");
        const activeGame = qs.get("game");
        if (filterWrap) {
            filterWrap.innerHTML = [`<a href="devlog.html" class="badge px-4 py-1.5 rounded-full border ${!activeGame ? "bg-white text-black border-white" : "border-white/15 text-zinc-300 hover:border-white/40"}">Alle</a>`]
                .concat(games.map(g => `<a href="devlog.html?game=${esc(g.slug)}" class="badge px-4 py-1.5 rounded-full border ${activeGame === g.slug ? "bg-white text-black border-white" : "border-white/15 text-zinc-300 hover:border-white/40"}">${esc(g.title)}</a>`)).join("");
        }

        const shown = activeGame ? posts.filter(p => p.game === activeGame) : posts;
        list.innerHTML = shown.map(p => {
            const g = games.find(x => x.slug === p.game);
            return `
            <a href="devlog.html?slug=${esc(p.slug)}" class="reveal tilt-card group relative block overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]" style="--accent:${esc(g?.accent || "#8b5cf6")}">
                <div class="card-glow"></div>
                <div class="grid md:grid-cols-[260px_1fr]">
                    <div class="relative aspect-video md:aspect-auto overflow-hidden">
                        ${p.cover ? `<img src="${esc(p.cover)}" alt="" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">`
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
                        <div class="mt-4 font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)]">Weiterlesen →</div>
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
        if (!p) { root.innerHTML = notFound("Diesen Beitrag gibt es nicht."); return; }
        const g = games.find(x => x.slug === p.game);
        if (g) applyAccent(g.accent, g.accent2);
        document.title = `${p.title} — PLANIGAMES Devlog`;
        root.innerHTML = `
            <div class="max-w-3xl mx-auto px-6 pt-36 pb-24">
                <a href="devlog.html" class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 hover:text-white">← Alle Beiträge</a>
                <div class="flex items-center gap-2 flex-wrap mt-8 mb-5">
                    <span class="font-mono text-xs text-zinc-500">${esc(fmtDate(p.date))}</span>
                    ${g ? `<a href="game.html?slug=${esc(g.slug)}" class="badge px-2 py-0.5 rounded border border-white/10 text-zinc-300 hover:border-white/40">${esc(g.title)}</a>` : ""}
                    ${p.version ? `<span class="badge text-[color:var(--accent)]">v${esc(p.version)}</span>` : ""}
                    ${(p.tags || []).map(t => `<span class="badge text-zinc-600">#${esc(t)}</span>`).join("")}
                </div>
                <h1 class="font-display text-4xl md:text-6xl font-extrabold text-white leading-[1.05] mb-8">${esc(p.title)}</h1>
                ${p.cover ? `<img src="${esc(p.cover)}" alt="" class="w-full rounded-2xl border border-white/10 mb-10">` : ""}
                <div class="prose-pg">${md(p.body || "")}</div>
                ${g && g.wishlistUrl ? `<div class="mt-14 pt-10 border-t border-white/10 text-center">
                    <a href="${esc(g.wishlistUrl)}" target="_blank" rel="noopener" class="btn-accent magnetic inline-block px-9 py-4 rounded-full font-semibold">${esc(g.title)} auf Steam wishlisten</a></div>` : ""}
            </div>`;
        initMagnetic();
    }

    function notFound(msg) {
        return `<div class="min-h-[60vh] grid place-items-center text-center px-6">
            <div><div class="text-6xl mb-6">🪄</div>
            <p class="text-zinc-400 mb-6">${esc(msg)}</p>
            <a href="index.html" class="btn-ghost magnetic inline-block px-7 py-3 rounded-full">Zur Startseite</a></div></div>`;
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
        ["index.html", "Studio"],
        ["games.html", "Games"],
        ["devlog.html", "Devlog"],
        ["index.html#kontakt", "Kontakt"],
    ];
    function navLinks(extra = "") {
        const here = location.pathname.split("/").pop() || "index.html";
        return NAV.map(([href, label]) => {
            const active = href.split("#")[0] === here;
            return `<a href="${href}" class="${extra} ${active ? "text-white" : "text-zinc-400 hover:text-white"} transition-colors">${label}</a>`;
        }).join("");
    }

    function injectChrome() {
        // Pflicht-Effekte oben einsetzen (falls noch nicht vorhanden)
        if (!$("#scroll-progress")) {
            document.body.insertAdjacentHTML("afterbegin", `
                <div id="scroll-progress"></div>
                <div id="cursor-ring"></div><div id="cursor-dot"></div>
                <div id="preloader">
                    <div class="relative h-24 w-24 grid place-items-center">
                        <div class="absolute inset-0 rounded-full border border-white/10 spin-slow"></div>
                        <div class="absolute inset-2 rounded-full border-t border-[color:var(--accent)] spin-rev"></div>
                        <span class="inline-block w-5 h-5 rotate-45 rounded-[3px]" style="background:linear-gradient(135deg,var(--accent-2),var(--accent))"></span>
                    </div>
                    <div class="pre-bar"><span id="pre-bar-fill"></span></div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.4em] text-zinc-500 flex items-center gap-3">
                        <span>Lade Welt</span><span id="pre-count" class="text-white tabular-nums">000</span>
                    </div>
                </div>
                <div class="glow-blob" style="top:-12%;left:-12%;width:60vw;height:60vw;background:color-mix(in srgb,var(--accent) 16%,transparent)"></div>
                <div class="glow-blob" style="bottom:-12%;right:-12%;width:55vw;height:55vw;background:color-mix(in srgb,var(--accent-2) 14%,transparent)"></div>`);
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
                    <a href="games.html" class="hidden md:inline-block btn-accent magnetic px-5 py-2.5 rounded-full text-sm font-semibold">Spiele entdecken</a>
                    <button id="menu-toggle" class="md:hidden text-white p-2" aria-label="Menü" aria-expanded="false">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                    </button>
                </div>
                <div id="mobile-menu" class="hidden md:hidden border-t border-white/10 bg-[#06060a]/95 backdrop-blur-xl">
                    <nav class="flex flex-col px-6 py-5 gap-4 text-lg">${navLinks("py-1")}</nav>
                </div>
            </header>`;

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
                                <button class="btn-accent magnetic px-5 py-3 rounded-full text-sm font-semibold shrink-0">Abonnieren</button>
                            </form>
                            <p class="text-[11px] text-zinc-600 mt-2">Kein Spam. Nur News zu neuen Spielen & großen Patches.</p>
                        </div>
                        <div>
                            <div class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 mb-5">Entdecken</div>
                            <ul class="space-y-3 text-zinc-300">
                                <li><a href="games.html" class="hover:text-white">Alle Spiele</a></li>
                                <li><a href="devlog.html" class="hover:text-white">Devlog & Patch Notes</a></li>
                                <li><a href="index.html#studio" class="hover:text-white">Über das Studio</a></li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 mb-5">Kontakt & Presse</div>
                            <ul class="space-y-3 text-zinc-300">
                                <li><a href="mailto:hello.dominicmajewski@gmail.com" class="hover:text-white" data-studio="email">hello.dominicmajewski@gmail.com</a></li>
                                <li class="flex gap-4 pt-2 text-zinc-400" data-studio="socials"></li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-16 pt-8 border-t border-white/8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-zinc-600">
                        <div>© <span data-year></span> PLANIGAMES — Alle Rechte vorbehalten.</div>
                        <div class="font-mono uppercase tracking-widest">Made with 🧡 in Germany</div>
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
        // Favicon an das Logo angleichen (falls hinterlegt)
        if (s.logo) { const fav = $("link[rel='icon']"); if (fav) fav.href = s.logo; }
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

    function flashFormError(form, msg) {
        let n = form.querySelector("[data-nl-err]");
        if (!n) { n = document.createElement("div"); n.dataset.nlErr = "1"; n.className = "w-full text-center text-red-400 text-sm mt-2 basis-full"; form.appendChild(n); }
        n.textContent = msg;
    }

    /* =========================================================
       BOOT
       ========================================================= */
    async function boot() {
        injectChrome();
        initShell();
        const page = document.body.dataset.page;
        await ({
            home: renderHome,
            games: renderGamesList,
            game: renderGame,
            devlog: renderDevlog,
        }[page] || (() => {}))();
        // Studio-Daten für den Footer sicherstellen (auch ohne Home)
        if (!DATA.studio) await loadData("studio");
        fillStudioFooter();
        initNewsletter();
    }

    if (document.readyState === "loading") addEventListener("DOMContentLoaded", boot);
    else boot();

    // für Debugging im Browser
    window.PLANIGAMES = { DATA, loadData };
})();
