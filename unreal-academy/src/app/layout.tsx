import type { Metadata, Viewport } from "next";
import "@/styles/globals.css";

export const metadata: Metadata = {
  title: "Unreal Academy — Duolingo für Unreal Engine",
  description:
    "Lerne Unreal Engine spielerisch: baue Blueprints, löse Missionen, sammle XP.",
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
      <body className="min-h-dvh">{children}</body>
    </html>
  );
}
