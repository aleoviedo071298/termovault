import { Download, FileText, FolderArchive, X } from "lucide-react";
import React, { useEffect, useState } from "react";
import { apiDownload } from "../api/client";
import { getInspeccion, type InspeccionDetalle, updateInspeccionEstado } from "../api/inspecciones";

interface Props {
  inspeccionId: number | null;
  isOpen: boolean;
  onClose: () => void;
  userGroups?: string[];
  canReviewOverride?: boolean;
  onStatusChanged?: () => void;
}

function fmtBytes(bytes?: number | null): string {
  if (!bytes) return "-";
  const units = ["B", "KB", "MB", "GB"];
  let value = bytes;
  let idx = 0;
  while (value >= 1024 && idx < units.length - 1) {
    value /= 1024;
    idx += 1;
  }
  return `${value.toFixed(1)} ${units[idx]}`;
}

export function InspectionDetailModal({ inspeccionId, isOpen, onClose, userGroups = [], canReviewOverride, onStatusChanged }: Props) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<InspeccionDetalle | null>(null);
  const [observaciones, setObservaciones] = useState("");
  const [updating, setUpdating] = useState(false);
  const canReview = typeof canReviewOverride === "boolean"
    ? canReviewOverride
    : (userGroups.includes("admin") || userGroups.includes("supervisor"));

  useEffect(() => {
    if (!isOpen || !inspeccionId) return;
    void (async () => {
      try {
        setLoading(true);
        setError(null);
        setData(await getInspeccion(inspeccionId));
      } catch (err) {
        setError(err instanceof Error ? err.message : "No se pudo cargar el detalle del informe.");
      } finally {
        setLoading(false);
      }
    })();
  }, [inspeccionId, isOpen]);

  if (!isOpen) return null;

  async function markStatus(estado: "revisada" | "cerrada") {
    if (!inspeccionId) return;
    try {
      setUpdating(true);
      await updateInspeccionEstado(inspeccionId, { estado, observaciones_revisor: observaciones || undefined });
      const refreshed = await getInspeccion(inspeccionId);
      setData(refreshed);
      onStatusChanged?.();
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo actualizar el estado.");
    } finally {
      setUpdating(false);
    }
  }

  async function downloadFile(file: InspeccionDetalle["archivos"][number]) {
    try {
      setError(null);
      await apiDownload(file.download_url, file.nombre);
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo descargar el archivo.");
    }
  }

  return (
    <div className="modal-backdrop">
      <div className="modal-content" style={{ width: "min(980px, 100%)" }}>
        <header className="modal-header">
          <h2>Detalle de Informe #{inspeccionId ?? "-"}</h2>
          <button className="close-btn" onClick={onClose} type="button"><X size={18} /></button>
        </header>
        <div className="modal-form">
          {loading ? <p>Cargando detalle...</p> : null}
          {error ? <div className="form-error">{error}</div> : null}
          {!loading && !error && data ? (
            <>
              <div className="form-grid">
                <div><strong>Fecha:</strong> {new Date(data.fecha_inspeccion).toLocaleString("es-AR")}</div>
                <div><strong>Estado:</strong> {data.estado}</div>
                <div><strong>Tecnico:</strong> {data.tecnico?.nombre ?? "-"}</div>
                <div><strong>Empresa:</strong> {data.empresa_contratista ?? "-"}</div>
                <div><strong>Cuadrilla:</strong> {data.cuadrilla ?? "-"}</div>
                <div><strong>Integrantes:</strong> {data.integrantes ?? "-"}</div>
                <div><strong>Yacimiento:</strong> {data.elemento?.yacimiento ?? "-"}</div>
                <div><strong>Subestacion/Elemento:</strong> {data.elemento ? `${data.elemento.nombre} (${data.elemento.codigo})` : "-"}</div>
                <div><strong>Revisada por:</strong> {data.revisada_por?.nombre ?? "-"}</div>
                <div><strong>Cerrada por:</strong> {data.cerrada_por?.nombre ?? "-"}</div>
              </div>

              <div>
                <strong>Descripcion / Resumen</strong>
                <p className="field-paragraph">{data.resumen ?? "Sin descripcion."}</p>
              </div>

              {data.observaciones_revisor ? (
                <div>
                  <strong>Observaciones del supervisor</strong>
                  <p className="field-paragraph">{data.observaciones_revisor}</p>
                </div>
              ) : null}

              {canReview && (data.estado === "enviada" || data.estado === "revisada") ? (
                <div style={{ display: "grid", gap: 10 }}>
                  <strong>Revision</strong>
                  <textarea
                    rows={3}
                    placeholder="Observaciones del revisor (opcional)"
                    value={observaciones}
                    onChange={(e) => setObservaciones(e.target.value)}
                  />
                  <div style={{ display: "flex", gap: 8 }}>
                    {data.estado === "enviada" ? (
                      <button className="submit-btn" type="button" disabled={updating} onClick={() => void markStatus("revisada")}>
                        Marcar revisada
                      </button>
                    ) : null}
                    {data.estado === "revisada" ? (
                      <button className="submit-btn" type="button" disabled={updating} onClick={() => void markStatus("cerrada")}>
                        Cerrar informe
                      </button>
                    ) : null}
                  </div>
                </div>
              ) : null}

              <div className="findings-section">
                <h4>Hallazgos</h4>
                {data.novedades.length === 0 ? (
                  <p>Sin hallazgos cargados.</p>
                ) : (
                  <div className="findings-list">
                    {data.novedades.map((novedad) => (
                      <article key={novedad.id} className={`finding-card ${novedad.criticidad === "normal" ? "normal" : ""}`}>
                        <div className="finding-header">
                          <h5>{novedad.titulo}</h5>
                          <span className="badge" style={{ "--badge-color": novedad.criticidad_color ?? "#4a7a5e" } as React.CSSProperties}>
                            {novedad.criticidad ?? "normal"}
                          </span>
                        </div>
                        {novedad.descripcion ? <p className="finding-desc">{novedad.descripcion}</p> : null}
                        <p><strong>Estado:</strong> {novedad.estado}</p>
                        {novedad.ubicacion ? <p><strong>Ubicacion:</strong> {novedad.ubicacion}</p> : null}
                        {novedad.temperatura !== null ? <p><strong>Temperatura:</strong> {novedad.temperatura}°C</p> : null}
                        {novedad.accion_recomendada ? <p className="recommendation"><strong>Accion recomendada:</strong> {novedad.accion_recomendada}</p> : null}
                      </article>
                    ))}
                  </div>
                )}
              </div>

              <div>
                <strong>Archivos y fotos</strong>
                <div className="files-list" style={{ marginTop: 8 }}>
                  {data.archivos.length === 0 ? (
                    <p>Sin archivos adjuntos.</p>
                  ) : (
                    data.archivos.map((file) => {
                      return (
                        <button className="file-download-btn" key={file.id} type="button" onClick={() => void downloadFile(file)}>
                          {file.tipo.includes("zip") ? <FolderArchive size={18} className="icon-zip" /> : <FileText size={18} className="icon-word" />}
                          <div className="file-info">
                            <span className="file-name">{file.nombre}</span>
                            <span className="file-size">{fmtBytes(file.tamano)}</span>
                          </div>
                          <Download size={16} style={{ marginLeft: "auto" }} />
                        </button>
                      );
                    })
                  )}
                </div>
              </div>
            </>
          ) : null}
        </div>
      </div>
    </div>
  );
}
