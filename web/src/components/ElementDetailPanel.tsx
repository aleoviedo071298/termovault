import React, { useEffect, useState } from "react";
import { apiDownload } from "../api/client";
import {
  FileText,
  FolderArchive,
  Thermometer,
  Calendar,
  Edit3,
  AlertTriangle,
  Trash2,
  Plus,
  MapPin,
  Flame,
  Download,
} from "lucide-react";
import {
  getElemento,
  deleteElemento,
  type ElementoDetailResponse,
  type InspeccionArchivo,
} from "../api/elementos";

import { Drawer } from "./ui/Drawer";
import { Tabs } from "./ui/Tabs";
import { Badge, badgeToneForEstado } from "./ui/Badge";
import { Button } from "./ui/Button";

interface ElementDetailPanelProps {
  isOpen: boolean;
  onClose: () => void;
  elementId: number | null;
  onEditClick: (id: number) => void;
  userGroups: string[];
  onDeleteSuccess?: () => void;
  onNewInspectionClick?: (id: number) => void;
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

function estadoLabel(estado: string): string {
  if (estado === "enviada") return "Enviada";
  if (estado === "revisada") return "Revisada";
  if (estado === "cerrada") return "Cerrada";
  return estado;
}

function estadoOperativoLabel(estado?: string | null): string {
  if (estado === "mantenimiento") return "En mantenimiento";
  if (estado === "fuera_de_servicio") return "Fuera de servicio";
  if (estado === "operativo") return "Operativo";
  return estado ?? "—";
}

function estadoOperativoTone(estado?: string | null): "success" | "warning" | "danger" | "neutral" {
  if (estado === "operativo") return "success";
  if (estado === "mantenimiento") return "warning";
  if (estado === "fuera_de_servicio") return "danger";
  return "neutral";
}

export const ElementDetailPanel: React.FC<ElementDetailPanelProps> = ({
  isOpen,
  onClose,
  elementId,
  onEditClick,
  userGroups,
  onDeleteSuccess,
  onNewInspectionClick,
}) => {
  const [data, setData] = useState<ElementoDetailResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<"ficha" | "historial">("ficha");
  const [deleting, setDeleting] = useState(false);

  const canEdit = userGroups.includes("admin") || userGroups.includes("supervisor");
  const canInspect =
    userGroups.includes("admin") || userGroups.includes("supervisor") || userGroups.includes("tecnico");

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
        setActiveTab("ficha");
      } catch (err) {
        setError(err instanceof Error ? err.message : "Error al cargar los detalles del elemento");
      } finally {
        setLoading(false);
      }
    }

    void loadDetail();
  }, [isOpen, elementId]);

  async function handleDownload(file: InspeccionArchivo) {
    try {
      setError(null);
      await apiDownload(file.download_url, file.nombre);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al descargar el archivo");
    }
  }

  const subtitle = data?.elemento.codigo
    ? `Código ${data.elemento.codigo}`
    : undefined;

  const tabs = [
    { id: "ficha", label: "Ficha técnica" },
    { id: "historial", label: "Historial", count: data?.inspecciones.length },
  ];

  return (
    <Drawer
      open={isOpen}
      onClose={onClose}
      title={loading ? "Cargando…" : data?.elemento.nombre ?? "—"}
      subtitle={subtitle}
      width={540}
    >
      {loading && (
        <div style={{ padding: 40, textAlign: "center", color: "var(--tv-text-muted)" }}>
          Cargando detalles e historial…
        </div>
      )}

      {error && (
        <div className="tv-notice tv-notice--danger" role="alert" style={{ marginBottom: 14 }}>
          <AlertTriangle size={16} />
          <span>{error}</span>
        </div>
      )}

      {!loading && !error && data && (
        <>
          {/* ─── Action bar ─── */}
          <div className="tv-actionbar">
            {canInspect && onNewInspectionClick && (
              <Button
                variant="primary"
                size="sm"
                leftIcon={<Plus size={14} />}
                onClick={() => onNewInspectionClick(data.elemento.id)}
              >
                Cargar inspección
              </Button>
            )}
            {canEdit && (
              <>
                <Button
                  variant="secondary"
                  size="sm"
                  leftIcon={<Edit3 size={14} />}
                  onClick={() => onEditClick(data.elemento.id)}
                >
                  Editar ficha técnica
                </Button>
                <Button
                  variant="danger"
                  size="sm"
                  leftIcon={<Trash2 size={14} />}
                  onClick={() => handleDelete(data.elemento.id)}
                  disabled={deleting}
                >
                  {deleting ? "Eliminando…" : "Eliminar elemento"}
                </Button>
              </>
            )}
          </div>

          <Tabs tabs={tabs} active={activeTab} onChange={(id) => setActiveTab(id as "ficha" | "historial")} />

          <div style={{ marginTop: 16 }}>
            {activeTab === "ficha" && (
              <>
                {/* Datos principales */}
                <div className="tv-meta-grid">
                  <div className="tv-meta-grid__row">
                    <span className="tv-meta-grid__label">Yacimiento:</span>
                    <span className="tv-meta-grid__value">{data.elemento.yacimiento ?? "—"}</span>
                  </div>
                  <div className="tv-meta-grid__row">
                    <span className="tv-meta-grid__label">Tipo:</span>
                    <span className="tv-meta-grid__value">{data.elemento.tipo ?? "—"}</span>
                  </div>
                  <div className="tv-meta-grid__row">
                    <span className="tv-meta-grid__label">Función:</span>
                    <span className="tv-meta-grid__value">{data.elemento.funcion ?? "—"}</span>
                  </div>
                  <div className="tv-meta-grid__row">
                    <span className="tv-meta-grid__label">Tensión:</span>
                    <span className="tv-meta-grid__value">
                      {data.elemento.tension ?? <span style={{ color: "var(--tv-text-muted)" }}>No requiere</span>}
                    </span>
                  </div>
                  <div className="tv-meta-grid__row">
                    <span className="tv-meta-grid__label">Criticidad:</span>
                    <span className="tv-meta-grid__value">
                      {data.elemento.criticidad ? (
                        <span
                          className="tv-badge tv-badge--solid"
                          style={{
                            background: data.elemento.criticidad_color ?? "#4a7a5e",
                            color: "white",
                          }}
                        >
                          {data.elemento.criticidad}
                        </span>
                      ) : (
                        <span style={{ color: "var(--tv-text-muted)" }}>Sin asignar</span>
                      )}
                    </span>
                  </div>
                  <div className="tv-meta-grid__row">
                    <span className="tv-meta-grid__label">Estado operativo:</span>
                    <span className="tv-meta-grid__value">
                      <Badge tone={estadoOperativoTone(data.elemento.estado_operativo)}>
                        {estadoOperativoLabel(data.elemento.estado_operativo)}
                      </Badge>
                    </span>
                  </div>
                </div>

                {/* Fabricante */}
                <section className="tv-section">
                  <div className="tv-section__title">Detalles de fabricante y ubicación</div>
                  <div className="tv-meta-grid">
                    <div className="tv-meta-grid__row">
                      <span className="tv-meta-grid__label">Marca:</span>
                      <span className="tv-meta-grid__value">{data.elemento.marca ?? "—"}</span>
                    </div>
                    <div className="tv-meta-grid__row">
                      <span className="tv-meta-grid__label">Modelo:</span>
                      <span className="tv-meta-grid__value">{data.elemento.modelo ?? "—"}</span>
                    </div>
                    <div className="tv-meta-grid__row">
                      <span className="tv-meta-grid__label">N° de serie:</span>
                      <span className="tv-meta-grid__value">{data.elemento.n_serie ?? "—"}</span>
                    </div>
                  </div>
                </section>

                {/* Observaciones */}
                <section className="tv-section">
                  <div className="tv-section__title">Observaciones generales</div>
                  <div className="tv-section__body">
                    {data.elemento.observaciones ? (
                      <p className="tv-section__paragraph">{data.elemento.observaciones}</p>
                    ) : (
                      <span className="tv-section__empty">Sin observaciones.</span>
                    )}
                  </div>
                </section>
              </>
            )}

            {activeTab === "historial" && (
              <>
                {data.inspecciones.length === 0 ? (
                  <div className="tv-empty-timeline">
                    <Calendar size={28} strokeWidth={1.5} />
                    <span>No se registran inspecciones previas para este elemento.</span>
                  </div>
                ) : (
                  <div className="tv-timeline">
                    {data.inspecciones.map((inspeccion) => {
                      const informes = inspeccion.archivos.filter((f) => f.tipo.startsWith("informe"));
                      const termografias = inspeccion.archivos.filter((f) => !f.tipo.startsWith("informe"));

                      return (
                        <article key={inspeccion.id} className="tv-timeline__item">
                          <div className="tv-timeline__head">
                            <span className="tv-timeline__date">
                              <Calendar size={13} />
                              {new Date(inspeccion.fecha_inspeccion).toLocaleDateString("es-AR", {
                                day: "2-digit",
                                month: "long",
                                year: "numeric",
                              })}
                            </span>
                            <div className="tv-timeline__head-right">
                              <Badge tone={badgeToneForEstado(inspeccion.estado)} dot>
                                {estadoLabel(inspeccion.estado)}
                              </Badge>
                            </div>
                          </div>

                          <div className="tv-timeline__meta">
                            <span><strong>Técnico:</strong> {inspeccion.tecnico ?? "S/D"}</span>
                            <span><strong>Clima:</strong> {inspeccion.condiciones_clima ?? "—"}</span>
                          </div>

                          {inspeccion.resumen && (
                            <div className="tv-timeline__quote">"{inspeccion.resumen}"</div>
                          )}

                          {/* Archivos */}
                          {(informes.length > 0 || termografias.length > 0) && (
                            <div style={{ marginTop: 12 }}>
                              {informes.length > 0 && (
                                <div style={{ marginBottom: 8 }}>
                                  <div className="tv-section__title" style={{ fontSize: 11.5, marginBottom: 6 }}>
                                    Informe formal
                                  </div>
                                  <div className="tv-file-list" style={{ marginTop: 0 }}>
                                    {informes.map((file) => (
                                      <button
                                        key={file.id}
                                        type="button"
                                        className="tv-file-download"
                                        onClick={() => void handleDownload(file)}
                                      >
                                        <span className="tv-file__icon tv-file__icon--report">
                                          <FileText size={14} />
                                        </span>
                                        <div className="tv-file__text">
                                          <div className="tv-file__name">{file.nombre}</div>
                                          <div className="tv-file__size">{formatBytes(file.tamano)}</div>
                                        </div>
                                        <Download size={14} className="tv-file-download__download" />
                                      </button>
                                    ))}
                                  </div>
                                </div>
                              )}

                              {termografias.length > 0 && (
                                <div>
                                  <div className="tv-section__title" style={{ fontSize: 11.5, marginBottom: 6 }}>
                                    Archivos térmicos
                                  </div>
                                  <div className="tv-file-list" style={{ marginTop: 0 }}>
                                    {termografias.map((file) => (
                                      <button
                                        key={file.id}
                                        type="button"
                                        className="tv-file-download"
                                        onClick={() => void handleDownload(file)}
                                      >
                                        <span className="tv-file__icon tv-file__icon--therm">
                                          {file.tipo.includes("zip") ? (
                                            <FolderArchive size={14} />
                                          ) : (
                                            <Thermometer size={14} />
                                          )}
                                        </span>
                                        <div className="tv-file__text">
                                          <div className="tv-file__name">{file.nombre}</div>
                                          <div className="tv-file__size">{formatBytes(file.tamano)}</div>
                                        </div>
                                        <Download size={14} className="tv-file-download__download" />
                                      </button>
                                    ))}
                                  </div>
                                </div>
                              )}
                            </div>
                          )}

                          {/* Hallazgos */}
                          {inspeccion.novedades.length > 0 && (
                            <div style={{ marginTop: 12 }}>
                              <div className="tv-section__title" style={{ fontSize: 11.5, marginBottom: 6 }}>
                                Hallazgos
                                <span className="tv-section__count">· {inspeccion.novedades.length}</span>
                              </div>
                              <div className="tv-finding-list">
                                {inspeccion.novedades.map((novedad) => (
                                  <div key={novedad.id} className="tv-finding">
                                    <span
                                      className="tv-finding__color"
                                      style={{ background: novedad.criticidad_color ?? "#4a7a5e" }}
                                      aria-hidden="true"
                                    />
                                    <div className="tv-finding__main">
                                      <div className="tv-finding__head">
                                        <span className="tv-finding__title">{novedad.titulo}</span>
                                        <span
                                          className="tv-badge tv-badge--solid"
                                          style={{
                                            background: novedad.criticidad_color ?? "#4a7a5e",
                                            color: "white",
                                          }}
                                        >
                                          {novedad.criticidad}
                                        </span>
                                      </div>
                                      {(novedad.ubicacion || novedad.temperatura !== null) && (
                                        <div className="tv-finding__meta">
                                          {novedad.ubicacion && (
                                            <span><MapPin size={12} /> {novedad.ubicacion}</span>
                                          )}
                                          {novedad.temperatura !== null && novedad.temperatura !== undefined && (
                                            <span><Flame size={12} /> {novedad.temperatura} °C</span>
                                          )}
                                        </div>
                                      )}
                                      {novedad.descripcion && (
                                        <div className="tv-finding__desc">{novedad.descripcion}</div>
                                      )}
                                      {novedad.accion_recomendada && (
                                        <div className="tv-finding__desc">
                                          <strong>Recomendación:</strong> {novedad.accion_recomendada}
                                        </div>
                                      )}
                                    </div>
                                  </div>
                                ))}
                              </div>
                            </div>
                          )}
                        </article>
                      );
                    })}
                  </div>
                )}
              </>
            )}
          </div>
        </>
      )}
    </Drawer>
  );
};
