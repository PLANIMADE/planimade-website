/**
 * 3D-Viewer für GLB/glTF-Dateien – ideal, um ein Blender-Modell direkt
 * drehbar in die Case-Study zu legen.
 *
 * three.js wird dynamisch importiert und landet deshalb in einem eigenen
 * Bundle: Seiten ohne Modell laden davon kein einziges Byte.
 */

export async function initModelViewer(): Promise<void> {
  const containers = document.querySelectorAll<HTMLElement>('[data-model-viewer]');
  if (containers.length === 0) return;

  const THREE = await import('three');
  const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
  const { GLTFLoader } = await import('three/examples/jsm/loaders/GLTFLoader.js');
  const { RoomEnvironment } = await import('three/examples/jsm/environments/RoomEnvironment.js');

  containers.forEach((container) => {
    const source = container.dataset.src;
    if (!source) return;

    const loading = document.createElement('div');
    loading.className = 'absolute inset-0 grid place-items-center font-mono text-xs tracking-[0.2em] text-faint';
    loading.textContent = 'MODELL WIRD GELADEN …';
    container.append(loading);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    container.append(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
    camera.position.set(0, 0.6, 4);

    // Studio-Umgebung als Reflexionsquelle – ohne HDRI-Download.
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

    const key = new THREE.DirectionalLight(0xffffff, 2.2);
    key.position.set(4, 6, 4);
    scene.add(key, new THREE.AmbientLight(0xffffff, 0.35));

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.06;
    controls.enablePan = false;
    controls.autoRotate = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    controls.autoRotateSpeed = 1.1;

    new GLTFLoader().load(
      source,
      (gltf) => {
        loading.remove();

        // Modell zentrieren und auf eine einheitliche Größe normalisieren.
        const box = new THREE.Box3().setFromObject(gltf.scene);
        const size = box.getSize(new THREE.Vector3()).length();
        const center = box.getCenter(new THREE.Vector3());

        gltf.scene.position.sub(center);
        const scale = 2.6 / (size || 1);
        gltf.scene.scale.setScalar(scale);

        scene.add(gltf.scene);
        controls.update();
      },
      undefined,
      () => {
        loading.textContent = 'MODELL KONNTE NICHT GELADEN WERDEN';
      },
    );

    let running = true;
    const animate = (): void => {
      if (!running) return;
      controls.update();
      renderer.render(scene, camera);
      requestAnimationFrame(animate);
    };

    const observer = new IntersectionObserver(([entry]) => {
      running = entry?.isIntersecting ?? false;
      if (running) requestAnimationFrame(animate);
    });
    observer.observe(container);

    const resize = (): void => {
      camera.aspect = container.clientWidth / container.clientHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(container.clientWidth, container.clientHeight);
    };
    window.addEventListener('resize', resize);

    requestAnimationFrame(animate);
  });
}
