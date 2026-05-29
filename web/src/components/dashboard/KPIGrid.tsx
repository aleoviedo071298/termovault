import { StatsCard } from "./StatsCard";
import type { KpiCardData } from "./types";

interface KPIGridProps {
  cards: KpiCardData[];
}

export function KPIGrid({ cards }: KPIGridProps) {
  return (
    <section className="dashboard-section kpi-section">
      <div className="section-heading">
        <span>Indicadores</span>
        <small>Resumen de tu alcance actual</small>
      </div>
      <div className="kpi-grid">
        {cards.map((card) => (
          <StatsCard key={card.label} {...card} />
        ))}
      </div>
    </section>
  );
}
