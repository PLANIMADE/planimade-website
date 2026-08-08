/**
 * WebGL-Hintergrund für den Hero-Bereich.
 *
 * Ein einzelnes Vollbild-Dreieck mit Fragment-Shader: strömendes Rauschen,
 * das auf Mausposition und Scroll reagiert. Bewusst ohne Bibliothek – das
 * sind ~4 KB statt ~150 KB three.js für einen Hintergrund.
 *
 * Fällt lautlos auf den CSS-Verlauf zurück, wenn kein WebGL da ist,
 * der Nutzer weniger Bewegung möchte oder das Gerät schwach ist.
 */

const VERTEX_SHADER = `#version 300 es
in vec2 position;
void main() {
  gl_Position = vec4(position, 0.0, 1.0);
}`;

const FRAGMENT_SHADER = `#version 300 es
precision highp float;

uniform vec2  uResolution;
uniform vec2  uMouse;      // 0..1, gefedert
uniform float uTime;
uniform float uScroll;     // 0..1
uniform vec3  uAccent;
uniform vec3  uSignal;
uniform float uLight;      // 1.0 = helles Theme

out vec4 fragColor;

// Klassisches Value-Noise + fbm – günstig und für Nebelstrukturen ausreichend.
float hash(vec2 p) {
  return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453123);
}

float noise(vec2 p) {
  vec2 i = floor(p);
  vec2 f = fract(p);
  vec2 u = f * f * (3.0 - 2.0 * f);
  return mix(
    mix(hash(i + vec2(0.0, 0.0)), hash(i + vec2(1.0, 0.0)), u.x),
    mix(hash(i + vec2(0.0, 1.0)), hash(i + vec2(1.0, 1.0)), u.x),
    u.y
  );
}

float fbm(vec2 p) {
  float value = 0.0;
  float amplitude = 0.5;
  for (int i = 0; i < 5; i++) {
    value += amplitude * noise(p);
    p *= 2.02;
    amplitude *= 0.5;
  }
  return value;
}

void main() {
  vec2 uv = gl_FragCoord.xy / uResolution.xy;
  vec2 p = (gl_FragCoord.xy - 0.5 * uResolution.xy) / min(uResolution.x, uResolution.y);

  float t = uTime * 0.045;
  vec2 mouse = (uMouse - 0.5) * 1.4;

  // Zwei versetzte Rauschebenen ergeben eine Strömung statt eines Flimmerns.
  vec2 q = vec2(fbm(p * 1.6 + vec2(t, -t * 0.7)), fbm(p * 1.6 + vec2(4.2, 1.3) - t * 0.5));
  vec2 r = vec2(
    fbm(p * 2.1 + 3.0 * q + vec2(1.7, 9.2) + t * 0.9 + mouse),
    fbm(p * 2.1 + 3.0 * q + vec2(8.3, 2.8) - t * 0.6 + mouse)
  );
  float f = fbm(p * 1.4 + 2.4 * r);

  // Distanz zur Maus erzeugt einen weichen Lichtkegel.
  float glow = 1.0 - smoothstep(0.0, 0.85, length(p - mouse * 0.9));

  vec3 deep = mix(vec3(0.02, 0.02, 0.04), vec3(0.94, 0.94, 0.92), uLight);
  vec3 color = deep;
  color = mix(color, uAccent, clamp(f * f * 1.5, 0.0, 1.0) * 0.55);
  color = mix(color, uSignal, clamp(r.x * 0.9, 0.0, 1.0) * 0.28);
  color += uAccent * glow * 0.22;

  // Nach unten hin auslaufen lassen, damit der Übergang zur Seite weich ist.
  float fade = smoothstep(1.05, 0.15, uv.y + uScroll * 0.35);
  color = mix(deep, color, fade);

  // Feines Korn gegen Farbbanding in den Verläufen.
  color += (hash(gl_FragCoord.xy + uTime) - 0.5) * 0.015;

  fragColor = vec4(color, 1.0);
}`;

function compile(gl: WebGL2RenderingContext, type: number, source: string): WebGLShader | null {
  const shader = gl.createShader(type);
  if (!shader) return null;

  gl.shaderSource(shader, source);
  gl.compileShader(shader);

  if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
    console.warn('[hero] Shader-Fehler:', gl.getShaderInfoLog(shader));
    gl.deleteShader(shader);

    return null;
  }

  return shader;
}

function hexToRgb(hex: string): [number, number, number] {
  const clean = hex.replace('#', '');
  const full = clean.length === 3 ? clean.replace(/(.)/g, '$1$1') : clean;
  const value = Number.parseInt(full, 16);

  return [((value >> 16) & 255) / 255, ((value >> 8) & 255) / 255, (value & 255) / 255];
}

function cssColor(name: string, fallback: string): [number, number, number] {
  const raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

  return hexToRgb(raw.startsWith('#') ? raw : fallback);
}

export function initHero(): void {
  const canvas = document.querySelector<HTMLCanvasElement>('[data-hero-canvas]');
  if (!canvas) return;

  const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const weakDevice = navigator.hardwareConcurrency !== undefined && navigator.hardwareConcurrency <= 2;
  const gl = calm || weakDevice ? null : canvas.getContext('webgl2', { antialias: false, alpha: false, powerPreference: 'low-power' });

  if (!gl) {
    canvas.remove();
    document.querySelector<HTMLElement>('[data-hero-fallback]')?.classList.remove('opacity-0');
    return;
  }

  const vertex = compile(gl, gl.VERTEX_SHADER, VERTEX_SHADER);
  const fragment = compile(gl, gl.FRAGMENT_SHADER, FRAGMENT_SHADER);
  const program = gl.createProgram();
  if (!vertex || !fragment || !program) {
    canvas.remove();
    return;
  }

  gl.attachShader(program, vertex);
  gl.attachShader(program, fragment);
  gl.linkProgram(program);
  if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
    console.warn('[hero] Programm-Fehler:', gl.getProgramInfoLog(program));
    canvas.remove();
    return;
  }
  gl.useProgram(program);

  // Ein einzelnes übergroßes Dreieck deckt den Bildschirm ab (billiger als zwei).
  const buffer = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
  const positionLocation = gl.getAttribLocation(program, 'position');
  gl.enableVertexAttribArray(positionLocation);
  gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);

  const uniforms = {
    resolution: gl.getUniformLocation(program, 'uResolution'),
    mouse: gl.getUniformLocation(program, 'uMouse'),
    time: gl.getUniformLocation(program, 'uTime'),
    scroll: gl.getUniformLocation(program, 'uScroll'),
    accent: gl.getUniformLocation(program, 'uAccent'),
    signal: gl.getUniformLocation(program, 'uSignal'),
    light: gl.getUniformLocation(program, 'uLight'),
  };

  let targetX = 0.5;
  let targetY = 0.5;
  let mouseX = 0.5;
  let mouseY = 0.5;
  let running = true;
  let start = performance.now();

  const applyTheme = (): void => {
    const [ar, ag, ab] = cssColor('--accent', '#a855f7');
    const [sr, sg, sb] = cssColor('--signal', '#38bdf8');
    gl.uniform3f(uniforms.accent, ar, ag, ab);
    gl.uniform3f(uniforms.signal, sr, sg, sb);
    gl.uniform1f(uniforms.light, document.documentElement.dataset.theme === 'light' ? 1 : 0);
  };

  const resize = (): void => {
    const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
    const width = Math.floor(canvas.clientWidth * ratio);
    const height = Math.floor(canvas.clientHeight * ratio);
    if (canvas.width === width && canvas.height === height) return;

    canvas.width = width;
    canvas.height = height;
    gl.viewport(0, 0, width, height);
    gl.uniform2f(uniforms.resolution, width, height);
  };

  const render = (now: number): void => {
    if (!running) return;

    // Federung sorgt für das „Nachziehen" des Lichtkegels.
    mouseX += (targetX - mouseX) * 0.045;
    mouseY += (targetY - mouseY) * 0.045;

    resize();
    gl.uniform1f(uniforms.time, (now - start) / 1000);
    gl.uniform2f(uniforms.mouse, mouseX, mouseY);
    gl.uniform1f(
      uniforms.scroll,
      Math.min(1, window.scrollY / Math.max(1, window.innerHeight)),
    );
    gl.drawArrays(gl.TRIANGLES, 0, 3);

    requestAnimationFrame(render);
  };

  window.addEventListener(
    'pointermove',
    (event) => {
      targetX = event.clientX / window.innerWidth;
      targetY = 1 - event.clientY / window.innerHeight;
    },
    { passive: true },
  );

  // Außerhalb des Sichtbereichs oder im Hintergrund-Tab: Renderer anhalten.
  const observer = new IntersectionObserver(
    ([entry]) => {
      running = entry?.isIntersecting ?? false;
      if (running) {
        start = performance.now() - (performance.now() - start);
        requestAnimationFrame(render);
      }
    },
    { threshold: 0 },
  );
  observer.observe(canvas);

  document.addEventListener('visibilitychange', () => {
    running = !document.hidden;
    if (running) requestAnimationFrame(render);
  });

  window.addEventListener('dm:theme', applyTheme);

  applyTheme();
  resize();
  requestAnimationFrame(render);
}
