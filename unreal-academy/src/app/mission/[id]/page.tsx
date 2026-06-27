import { notFound } from "next/navigation";
import { allMissions, getMission } from "@/data/missions";
import { MissionPlayer } from "@/features/missions/MissionPlayer";

/** Alle Missionen vorab als statische Seiten erzeugen. */
export function generateStaticParams() {
  return allMissions.map((m) => ({ id: m.id }));
}

export default function MissionPage({ params }: { params: { id: string } }) {
  const mission = getMission(params.id);
  if (!mission) notFound();
  return <MissionPlayer mission={mission} />;
}
