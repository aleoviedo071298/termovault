import { ArrowUpRight, FileText, MapPin, UserRound } from "lucide-react";
import type { DashboardReportRow } from "../../api/dashboard";
import type { ReportTableProps } from "./types";
import { Badge, badgeToneForEstado } from "../ui/Badge";

function estadoLabel(estado: string): string {
  if (estado === "enviada") return "Enviada";
  if (estado === "revisada") return "Revisada";
  if (estado === "cerrada") return "Cerrada";
  return estado;
}

function criticidadLabel(criticidad: DashboardReportRow["criticidad"]): string {
  return String(criticidad)
    .normalize("NFD")
    .replace(/\p{Diacritic}/gu, "")
    .includes("tica")
    ? "Crítica"
    : criticidad;
}

function criticidadTone(c: string): "neutral" | "info" | "success" | "warning" | "danger" {
  const norm = c.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase();
  if (norm === "critica") return "danger";
  if (norm === "alta")    return "warning";
  if (norm === "media")   return "warning";
  if (norm === "baja")    return "success";
  return "neutral";
}

export function ReportTable({ loading, reports, onOpenDetail }: ReportTableProps) {
  return (
    <div className="tv-table-wrap">
      <div className="tv-table-head">
        <div>
          <div className="tv-table-head__title">Informes termográficos</div>
          <div className="tv-table-head__sub">
            Seguimiento por estado, criticidad y alcance
          </div>
        </div>
        <span className="tv-table-head__count">{reports.length}</span>
      </div>

      <div className="tv-table-scroll">
        <table className="tv-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Informe</th>
              <th>Operación</th>
              <th>Estado</th>
              <th>Criticidad</th>
              <th>Hallazgos</th>
              <th aria-label="Acción" />
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan={7} className="tv-table__empty">Cargando informes…</td>
              </tr>
            ) : reports.length === 0 ? (
              <tr>
                <td colSpan={7} className="tv-table__empty">
                  No hay resultados para los filtros aplicados.
                </td>
              </tr>
            ) : (
              reports.map((r) => {
                const fecha = new Date(r.fecha_inspeccion);
                const day = fecha.toLocaleDateString("es-AR", { day: "2-digit", month: "2-digit" });
                const year = fecha.getFullYear();
                const crit = criticidadLabel(r.criticidad);

                return (
                  <tr key={r.id} onClick={() => onOpenDetail(r.id)} role="button" tabIndex={0}>
                    <td data-label="Fecha">
                      <div className="tv-table__date">
                        <span className="tv-table__date-day">{day}</span>
                        <span className="tv-table__date-year">{year}</span>
                      </div>
                    </td>
                    <td className="tv-table__main">
                      <div className="tv-table__main-title">
                        <FileText size={15} strokeWidth={1.8} />
                        <span>{r.elemento}</span>
                      </div>
                      <div className="tv-table__main-sub">Informe #{r.id}</div>
                    </td>
                    <td data-label="Operación">
                      <div className="tv-table__stack">
                        <span><UserRound size={13} strokeWidth={1.8} /> {r.tecnico}</span>
                        <span><MapPin size={13} strokeWidth={1.8} /> {r.yacimiento} · {r.empresa}</span>
                      </div>
                    </td>
                    <td data-label="Estado">
                      <Badge tone={badgeToneForEstado(r.estado)} dot>
                        {estadoLabel(r.estado)}
                      </Badge>
                    </td>
                    <td data-label="Criticidad">
                      <Badge tone={criticidadTone(crit)}>{crit}</Badge>
                    </td>
                    <td data-label="Hallazgos">
                      <span className="tv-table__count">{r.hallazgos}</span>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="tv-table__action"
                        onClick={(e) => { e.stopPropagation(); onOpenDetail(r.id); }}
                      >
                        Ver
                        <ArrowUpRight size={14} />
                      </button>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
