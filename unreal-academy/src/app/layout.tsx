import type { Metadata, Viewport } from "next";
import "@/styles/globals.css";
import { AuthSyncProvider } from "@/features/auth/AuthSync";
import { ServiceWorkerRegister } from "@/features/pwa/ServiceWorkerRegister";

// Bei GitHub-Pages-Unterpfad müssen Manifest-/Icon-Links den basePath tragen
// (Next prefixt diese Metadata-URLs im Static Export nicht automatisch).
const base = process.env.NEXT_PUBLIC_BASE_PATH || "";

export const metadata: Metadata = {
  title: "Unreal Academy — Duolingo für Unreal Engine",
  description:
    "Lerne Unreal Engine spielerisch: baue Blueprints, löse Missionen, sammle XP.",
  applicationName: "Unreal Academy",
  manifest: `${base}/manifest.webmanifest`,
  appleWebApp: {
    capable: true,
    statusBarStyle: "black-translucent",
    title: "Unreal Academy",
  },
  icons: {
    icon: `${base}/icon-192.png`,
    apple: `${base}/apple-touch-icon.png`,
  },
};

export const viewport: Viewport = {
  themeColor: "#080a10",
  width: "device-width",
  initialScale: 1,
  maximumScale: 1, // Simulator steuert Zoom selbst -> kein Browser-Pinch-Zoom
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="de">
      <body className="min-h-dvh">
        <ServiceWorkerRegister />
        <AuthSyncProvider>{children}</AuthSyncProvider>
      </body>
    </html>
  );
}
