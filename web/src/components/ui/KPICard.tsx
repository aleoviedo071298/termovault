import type { ReactNode } from "react";

type Tone = "neutral" | "success" | "warning" | "info" | "danger";

interface Props {
  label: string;
  value: ReactNode;
  caption?: ReactNode;
  icon?: ReactNode;
  tone?: Tone;
}

/**
 * Tarjeta de KPI sobria (Dashboard / Inventario).
 * label arriba, valor grande, caption opcional debajo, icono a la derecha
 * con tinte de color según el "tono" semántico.
 */
export function KPICard({ label, value, caption, icon, tone = "neutral" }: Props) {
  return (
    <div className={`tv-kpi tv-kpi--${tone}`}>
      <div className="tv-kpi__text">
        <div className="tv-kpi__label">{label}</div>
        <div className="tv-kpi__value">{value}</div>
        {caption && <div className="tv-kpi__caption">{caption}</div>}
      </div>
      {icon && <div className="tv-kpi__icon" aria-hidden="true">{icon}</div>}
    </div>
  );
}
