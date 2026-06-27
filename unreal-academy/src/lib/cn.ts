import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

/** Mergt Tailwind-Klassen konfliktfrei. */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}
