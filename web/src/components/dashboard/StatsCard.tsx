import { Activity, AlertTriangle, Gauge, Minus, TrendingUp } from "lucide-react";
import type { KpiCardData } from "./types";

function getIcon(tone: KpiCardData["tone"]) {
  if (tone === "critico") return <AlertTriangle size={16} />;
  if (tone === "global") return <Gauge size={16} />;
  return <Activity size={16} />;
}

export function StatsCard({ label, value, trend, tone }: KpiCardData) {
  return (
    <article className={`kpi-card tone-${tone}`}>
      <header>
        <i>{getIcon(tone)}</i>
        <span>{label}</span>
      </header>
      <div className="kpi-value-row">
        <strong>{value}</strong>
        <Minus size={18} />
      </div>
      <footer>
        <TrendingUp size={14} />
        <small>{trend}</small>
      </footer>
    </article>
  );
}
