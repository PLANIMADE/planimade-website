"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/cn";

const items = [
  { href: "/learn", label: "Lernen", icon: "🗺️" },
  { href: "/sandbox", label: "Sandbox", icon: "🧪" },
  { href: "/profile", label: "Profil", icon: "👤" },
];

/** Mobile-first Bottom-Navigation (Duolingo-Stil). */
export function BottomNav() {
  const pathname = usePathname();
  return (
    <nav className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-bg/90 backdrop-blur">
      <div className="mx-auto flex max-w-md items-stretch justify-around">
        {items.map((it) => {
          const active = pathname === it.href;
          return (
            <Link
              key={it.href}
              href={it.href}
              className={cn(
                "flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] transition",
                active ? "text-accent" : "text-muted hover:text-text",
              )}
            >
              <span className="text-lg" aria-hidden>
                {it.icon}
              </span>
              {it.label}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
