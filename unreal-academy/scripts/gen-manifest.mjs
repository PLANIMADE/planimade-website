// Generiert public/manifest.webmanifest mit korrektem basePath (pro Build).
import { writeFileSync, mkdirSync } from "node:fs";

const base = process.env.NEXT_PUBLIC_BASE_PATH || "";
const manifest = {
  name: "Unreal Academy",
  short_name: "Unreal Academy",
  description: "Duolingo für Unreal Engine – lerne Blueprints, indem du sie baust.",
  start_url: `${base}/`,
  scope: `${base}/`,
  display: "standalone",
  orientation: "portrait",
  background_color: "#080a10",
  theme_color: "#080a10",
  icons: [
    { src: `${base}/icon-192.png`, sizes: "192x192", type: "image/png", purpose: "any" },
    { src: `${base}/icon-512.png`, sizes: "512x512", type: "image/png", purpose: "any" },
    { src: `${base}/icon-512.png`, sizes: "512x512", type: "image/png", purpose: "maskable" },
  ],
};

mkdirSync("public", { recursive: true });
writeFileSync("public/manifest.webmanifest", JSON.stringify(manifest, null, 2) + "\n");
console.log("manifest generated, basePath:", base || "(root)");
