/** @type {import('next').NextConfig} */

// Statischer Export (für GitHub Pages / beliebiges Static-Hosting) wird über
// EXPORT=1 aktiviert; der Unterpfad (z.B. /planimade-website bei Project Pages)
// kommt aus NEXT_PUBLIC_BASE_PATH. Ohne diese Variablen: normales Next-Verhalten
// (dev / Vercel-Root-Deploy) – nichts ändert sich.
const basePath = process.env.NEXT_PUBLIC_BASE_PATH || "";
const isExport = process.env.EXPORT === "1";

const nextConfig = {
  reactStrictMode: true,
  ...(isExport ? { output: "export", trailingSlash: true } : {}),
  ...(basePath ? { basePath } : {}),
  images: { unoptimized: true },
};

export default nextConfig;
