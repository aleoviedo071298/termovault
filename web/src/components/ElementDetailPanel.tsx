import React, { useEffect, useState } from "react";
import { X, FileText, FolderArchive, Calendar, ShieldAlert, Edit3, Settings, MapPin, Loader2, AlertTriangle } from "lucide-react";
import { getElemento, deleteElemento, type ElementoDetailResponse } from "../api/elementos";

interface ElementDetailPanelProps {
  isOpen: boolean;
  onClose: () => void;
  elementId: number | null;
  onEditClick: (id: number) => void;
  userGroups: string[];
  onDeleteSuccess?: () => void;
  onNewInspectionClick?: (id: number) => void;
}

export const ElementDetailPanel: React.FC<ElementDetailPanelProps> = ({
  isOpen,
  onClose,
  elementId,
  onEditClick,
  userGroups,
  onDeleteSuccess,
  onNewInspectionClick
}) => {
  const [data, setData] = useState<ElementoDetailResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<"ficha" | "historial">("ficha");
  const [deleting, setDeleting] = useState(false);

  const canEdit = userGroups.includes("admin") || userGroups.includes("supervisor");

  async function handleDelete(id: number) {
    const confirmed = window.confirm(
      "¿Estás seguro de que deseas eliminar este elemento? Esta acción eliminará también todas sus inspecciones y archivos asociados, y no se puede deshacer."
    );
    if (!confirmed) return;

    try {
      setDeleting(true);
      setError(null);
      await deleteElemento(id);
      onClose();
      if (onDeleteSuccess) {
        onDeleteSuccess();
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al eliminar el elemento");
    } finally {
      setDeleting(false);
    }
  }

  useEffect(() => {
    if (!isOpen || !elementId) return;

    async function loadDetail() {
      try {
        setLoading(true);
        setError(null);
        const res = await getElemento(elementId as number);
        setData(res);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Error al cargar los detalles del elemento");
      } finally {
        setLoading(false);
      }
    }

    void loadDetail();
  }, [isOpen, elementId]);

  if (!isOpen) return null;

  const canInspect = userGroups.includes("admin") || userGroups.includes("supervisor") || userGroups.includes("tecnico");

  // Helper to trigger a browser download (real download from backend or simulated mock blob)
  function handleDownload(file: any) {
    if (file.bucket === "local") {
      const backendUrl = (import.meta.env.VITE_API_URL ?? "http://localhost:8000/api").replace("/api", "");
      window.open(`${backendUrl}/storage/${file.key}`, "_blank");
    } else {
      const fileContent = `=== TermoVault - Descarga Simulada ===\nArchivo: ${file.nombre}\nFecha de descarga: ${new Date().toLocaleString()}\nContenido del reporte termografico simulado.`;
      const mimeType = file.tipo === "informe_word"
        ? "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        : file.tipo === "informe_excel"
        ? "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        : "application/zip";
      const blob = new Blob([fileContent], { type: mimeType });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = file.nombre;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }
  }

  // Format bytes into human readable format
  function formatBytes(bytes: number): string {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  return (
    <aside className="detail-panel-wrapper" aria-label="Detalle del elemento">
      <div className="detail-panel-backdrop" onClick={onClose} />
      <div className="detail-panel">
        <header className="detail-panel-header">
          <div className="title-area">
            <h2>{loading ? "Cargando..." : data?.elemento.nombre}</h2>
            <small>{data?.elemento.codigo}</small>
          </div>
          <button className="close-btn" onClick={onClose} aria-label="Cerrar panel">
            <X size={20} />
          </button>
        </header>

        {loading ? (
          <div className="detail-panel-loading">
            <Loader2 className="animate-spin" size={32} />
            <p>Cargando detalles e historial...</p>
          </div>
        ) : error ? (
          <div className="detail-panel-error">
            <AlertTriangle size={24} />
            <p>{error}</p>
          </div>
        ) : data ? (
          <div className="detail-panel-body">
            <div className="action-bar" style={{ display: "flex", flexWrap: "wrap", gap: "8px" }}>
              {canInspect && onNewInspectionClick && (
                <button
                  className="edit-button-quick"
                  onClick={() => onNewInspectionClick(data.elemento.id)}
                  type="button"
                  style={{
                    background: "#1d5c46",
                    color: "#f4f0df",
                    borderColor: "#1d5c46"
                  }}
                >
                  <FileText size={16} />
                  Cargar inspección
                </button>
              )}
              {canEdit && (
                <>
                  <button
                    className="edit-button-quick"
                    onClick={() => onEditClick(data.elemento.id)}
                    type="button"
                  >
                    <Edit3 size={16} />
                    Editar ficha técnica
                  </button>
                  <button
                    className="delete-button-quick"
                    onClick={() => handleDelete(data.elemento.id)}
                    disabled={deleting}
                    type="button"
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                      padding: "8px 14px",
                      background: "#fff5f5",
                      border: "1px solid #feb2b2",
                      borderRadius: "8px",
                      color: "#c53030",
                      fontSize: "0.85rem",
                      fontWeight: 700,
                      cursor: "pointer",
                      transition: "all 0.2s"
                    }}
                  >
                    <X size={16} />
                    {deleting ? "Eliminando..." : "Eliminar elemento"}
                  </button>
                </>
              )}
            </div>

            <div className="panel-tabs">
              <button
                className={`panel-tab ${activeTab === "ficha" ? "active" : ""}`}
                onClick={() => setActiveTab("ficha")}
                type="button"
              >
                Ficha Técnica
              </button>
              <button
                className={`panel-tab ${activeTab === "historial" ? "active" : ""}`}
                onClick={() => setActiveTab("historial")}
                type="button"
              >
                Historial ({data.inspecciones.length})
              </button>
            </div>

            {activeTab === "ficha" ? (
              <section className="tech-sheet">
                <div className="grid-2-col">
                  <div className="field-group">
                    <span className="field-label">Yacimiento</span>
                    <span className="field-val">{data.elemento.yacimiento ?? "-"}</span>
                  </div>
                  <div className="field-group">
                    <span className="field-label">Tipo de Elemento</span>
                    <span className="field-val">{data.elemento.tipo ?? "-"}</span>
                  </div>
                  <div className="field-group">
                    <span className="field-label">Función</span>
                    <span className="field-val">{data.elemento.funcion ?? "-"}</span>
                  </div>
                  <div className="field-group">
                    <span className="field-label">Nivel Tensión</span>
                    <span className="field-val">{data.elemento.tension ?? "No requiere"}</span>
                  </div>
                  <div className="field-group">
                    <span className="field-label">Criticidad</span>
                    <span
                      className="badge"
                      style={{
                        "--badge-color": data.elemento.criticidad_color ?? "#718096"
                      } as React.CSSProperties}
                    >
                      {data.elemento.criticidad ?? "Sin asignar"}
                    </span>
                  </div>
                  <div className="field-group">
                    <span className="field-label">Estado Operativo</span>
                    <span className={`status-pill ${data.elemento.estado_operativo}`}>
                      {data.elemento.estado_operativo === "operativo"
                        ? "Operativo"
                        : data.elemento.estado_operativo === "mantenimiento"
                        ? "En Mantenimiento"
                        : "Fuera de Servicio"}
                    </span>
                  </div>
                </div>

                <hr className="divider" />

                <h3>Detalles de Fabricante y Ubicación</h3>
                <div className="grid-2-col">
                  <div className="field-group">
                    <span className="field-label">Marca</span>
                    <span className="field-val">{data.elemento.marca ?? "-"}</span>
                  </div>
                  <div className="field-group">
                    <span className="field-label">Modelo</span>
                    <span className="field-val">{data.elemento.modelo ?? "-"}</span>
                  </div>
                  <div className="field-group">
                    <span className="field-label">N° de Serie</span>
                    <span className="field-val">{data.elemento.n_serie ?? "-"}</span>
                  </div>
                </div>

                <div className="field-group" style={{ marginTop: "12px" }}>
                  <span className="field-label">Observaciones Generales</span>
                  <p className="field-paragraph">{data.elemento.observaciones ?? "Sin observaciones"}</p>
                </div>
              </section>
            ) : (
              <section className="inspection-history">
                {data.inspecciones.length === 0 ? (
                  <div className="empty-history">
                    <Calendar size={36} />
                    <p>No se registran inspecciones previas para este elemento.</p>
                  </div>
                ) : (
                  <div className="timeline">
                    {data.inspecciones.map((inspeccion) => (
                      <article key={inspeccion.id} className="timeline-item">
                        <header className="timeline-item-header">
                          <span className="inspeccion-date">
                            {new Date(inspeccion.fecha_inspeccion).toLocaleDateString()}
                          </span>
                          <span className={`badge-state ${inspeccion.estado}`}>
                            {inspeccion.estado.toUpperCase()}
                          </span>
                        </header>

                        <div className="inspeccion-details">
                          <p><strong>Técnico:</strong> {inspeccion.tecnico ?? "S/D"}</p>
                          <p><strong>Cuadrilla:</strong> {inspeccion.cuadrilla ?? "S/D"}</p>
                          <p><strong>Clima:</strong> {inspeccion.condiciones_clima ?? "-"}, {inspeccion.temperatura_ambiente}°C</p>
                          <p><strong>Carga %:</strong> {inspeccion.carga_pct ? `${inspeccion.carga_pct}%` : "-"}</p>
                          
                          {inspeccion.resumen && (
                            <p className="resumen-text">
                              <em>"{inspeccion.resumen}"</em>
                            </p>
                          )}
                        </div>

                        {/* Archivos asociados */}
                        <div className="files-section">
                          <h4>Documentación y Archivos</h4>
                          <div className="files-list">
                            {inspeccion.archivos.map((file) => {
                              const isWord = file.tipo === "informe_word";
                              return (
                                <button
                                  key={file.id}
                                  className="file-download-btn"
                                  onClick={() => handleDownload(file)}
                                  type="button"
                                >
                                  {isWord ? (
                                    <FileText size={18} className="icon-word" />
                                  ) : (
                                    <FolderArchive size={18} className="icon-zip" />
                                  )}
                                  <div className="file-info">
                                    <span className="file-name">{file.nombre}</span>
                                    <span className="file-size">{formatBytes(file.tamano)}</span>
                                  </div>
                                </button>
                              );
                            })}
                          </div>
                        </div>

                        {/* Novedades / Hallazgos */}
                        {inspeccion.novedades.length > 0 && (
                          <div className="findings-section">
                            <h4>Hallazgos Detectados ({inspeccion.novedades.length})</h4>
                            <div className="findings-list">
                              {inspeccion.novedades.map((novedad) => (
                                <div key={novedad.id} className="finding-card">
                                  <header className="finding-header">
                                    <h5>{novedad.titulo}</h5>
                                    <span
                                      className="badge font-semibold"
                                      style={{
                                        "--badge-color": novedad.criticidad_color ?? "#e53e3e",
                                        transform: "scale(0.85)",
                                        transformOrigin: "right center"
                                      } as React.CSSProperties}
                                    >
                                      {novedad.criticidad}
                                    </span>
                                  </header>
                                  <p><strong>Ubicación:</strong> {novedad.ubicacion ?? "No especificado"}</p>
                                  {novedad.temperatura !== null && (
                                    <p><strong>Temperatura:</strong> {novedad.temperatura}°C</p>
                                  )}
                                  <p className="finding-desc">{novedad.descripcion}</p>
                                  {novedad.accion_recomendada && (
                                    <p className="recommendation">
                                      <strong>Recomendación:</strong> {novedad.accion_recomendada}
                                    </p>
                                  )}
                                </div>
                              ))}
                            </div>
                          </div>
                        )}
                      </article>
                    ))}
                  </div>
                )}
              </section>
            )}
          </div>
        ) : null}
      </div>
    </aside>
  );
};
