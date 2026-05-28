import React from "react";
import { ArrowUpRight, FileText, Flame, MapPin, UserRound } from "lucide-react";
import type { DashboardReportRow } from "../../api/dashboard";
import type { ReportTableProps } from "./types";

const CRITICIDAD_COLOR: Record<string, string> = {
  Normal: "#4a7a5e",
  Baja: "#22c55e",
  Media: "#d8a316",
  Alta: "#f97316",
  Critica: "#ef4444",
  "Crítica": "#ef4444",
  "CrÃ­tica": "#ef4444",
};

function estadoLabel(estado: string): string {
  if (estado === "enviada") return "Enviada";
  if (estado === "revisada") return "Revisada";
  if (estado === "cerrada") return "Cerrada";
  return estado;
}

function criticidadLabel(criticidad: DashboardReportRow["criticidad"]): string {
  return String(criticidad).includes("tica") ? "Critica" : criticidad;
}

export function ReportTable({ loading, reports, onOpenDetail }: ReportTableProps) {
  return (
    <section className="dashboard-section report-panel">
      <div className="section-heading report-heading">
        <div>
          <span>Informes termograficos</span>
          <small>Seguimiento operativo por estado, criticidad y alcance</small>
        </div>
        <strong>{reports.length}</strong>
      </div>

      <div className="report-table-wrap">
        <table className="report-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Informe</th>
              <th>Operacion</th>
              <th>Estado</th>
              <th>Criticidad</th>
              <th>Hallazgos</th>
              <th aria-label="Accion" />
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={7} className="empty-cell">Cargando informes...</td></tr>
            ) : reports.length === 0 ? (
              <tr><td colSpan={7} className="empty-cell">No hay resultados para los filtros aplicados.</td></tr>
            ) : reports.map((r) => {
              const criticidad = criticidadLabel(r.criticidad);

              return (
                <tr key={r.id}>
                  <td className="date-cell">
                    <strong>{new Date(r.fecha_inspeccion).toLocaleDateString("es-AR", { day: "2-digit", month: "2-digit" })}</strong>
                    <span>{new Date(r.fecha_inspeccion).getFullYear()}</span>
                  </td>
                  <td className="main-report-cell">
                    <div className="report-title-line">
                      <FileText size={16} />
                      <strong>{r.elemento}</strong>
                    </div>
                    <span>Informe #{r.id}</span>
                  </td>
                  <td>
                    <div className="stacked-cell">
                      <span><UserRound size={13} /> {r.tecnico}</span>
                      <span><MapPin size={13} /> {r.yacimiento} / {r.empresa}</span>
                    </div>
                  </td>
                  <td>
                    <span className={`status-chip status-${r.estado}`}>{estadoLabel(r.estado)}</span>
                  </td>
                  <td>
                    <span className="badge badge-criticidad" style={{ "--badge-color": CRITICIDAD_COLOR[criticidad] ?? "#4a7a5e" } as React.CSSProperties}>
                      <Flame size={13} />
                      {criticidad}
                    </span>
                  </td>
                  <td>
                    <span className="finding-count">{r.hallazgos}</span>
                  </td>
                  <td className="action-cell">
                    <button className="table-action" type="button" onClick={() => onOpenDetail(r.id)}>
                      Ver
                      <ArrowUpRight size={14} />
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </section>
  );
}
