import type { ReactNode } from "react";

type Tone = "neutral" | "info" | "success" | "warning" | "danger" | "primary";
type Variant = "soft" | "solid" | "outline";

interface Props {
  children: ReactNode;
  tone?: Tone;
  variant?: Variant;
  dot?: boolean;
}

/**
 * Badge para estados de informe (enviada/revisada/cerrada),
 * criticidad de hallazgos, roles, etc.
 */
export function Badge({ children, tone = "neutral", variant = "soft", dot }: Props) {
  return (
    <span className={`tv-badge tv-badge--${variant} tv-badge--${tone}`}>
      {dot && <span className="tv-badge__dot" aria-hidden="true" />}
      {children}
    </span>
  );
}

/**
 * Helper para mapear el estado del informe a un tono consistente.
 * Mantiene los nombres de estado tal cual los usa el backend.
 */
export function badgeToneForEstado(estado: string): Tone {
  switch (estado) {
    case "enviada":
      return "info";
    case "revisada":
      return "warning";
    case "cerrada":
      return "success";
    case "borrador":
      return "neutral";
    default:
      return "neutral";
  }
}
