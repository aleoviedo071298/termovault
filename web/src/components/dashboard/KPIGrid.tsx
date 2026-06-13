import { FileText, AlertTriangle, Paperclip, Calendar } from "lucide-react";
import { KPICard } from "../ui/KPICard";
import type { KpiCardData } from "./types";

interface KPIGridProps {
  cards: KpiCardData[];
}

// Mapeo del "tone" interno del dashboard a tonos del design system.
function mapTone(tone: KpiCardData["tone"]): "neutral" | "success" | "warning" | "danger" | "info" {
  switch (tone) {
    case "operativo": return "success";
    case "critico":   return "danger";
    case "global":    return "info";
    default:          return "neutral";
  }
}

// Icono por defecto según palabra clave del label (sin alterar la data).
function pickIcon(label: string) {
  const l = label.toLowerCase();
  if (l.includes("observ")) return <AlertTriangle size={18} strokeWidth={1.8} />;
  if (l.includes("adjunt") || l.includes("archiv") || l.includes("docu")) return <Paperclip size={18} strokeWidth={1.8} />;
  if (l.includes("mes"))    return <Calendar size={18} strokeWidth={1.8} />;
  return <FileText size={18} strokeWidth={1.8} />;
}

export function KPIGrid({ cards }: KPIGridProps) {
  return (
    <div className="tv-kpi-grid">
      {cards.map((card) => (
        <KPICard
          key={card.label}
          label={card.label}
          value={card.value}
          caption={card.trend}
          icon={pickIcon(card.label)}
          tone={mapTone(card.tone)}
        />
      ))}
    </div>
  );
}
