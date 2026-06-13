import {
  Calendar,
  Building2,
  Users,
  MapPin,
  Hash,
  UserCheck,
  CheckCircle2,
  Activity,
  Thermometer,
  FolderArchive,
  FileText,
  Download,
  Flame,
} from "lucide-react";
import React, { useEffect, useState } from "react";
import { apiDownload } from "../api/client";
import { getInspeccion, type InspeccionDetalle, updateInspeccionEstado } from "../api/inspecciones";

import { Modal } from "./ui/Modal";
import { Button } from "./ui/Button";
import { Badge, badgeToneForEstado } from "./ui/Badge";
import { Textarea } from "./ui/Field";

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

function estadoLabel(estado: string): string {
  if (estado === "enviada") return "Enviada";
  if (estado === "revisada") return "Revisada";
  if (estado === "cerrada") return "Cerrada";
  return estado;
}

export function InspectionDetailModal({
  inspeccionId,
  isOpen,
  onClose,
  userGroups = [],
  canReviewOverride,
  onStatusChanged,
}: Props) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<InspeccionDetalle | null>(null);
  const [observaciones, setObservaciones] = useState("");
  const [updating, setUpdating] = useState(false);
  const canReview =
    typeof canReviewOverride === "boolean"
      ? canReviewOverride
      : userGroups.includes("admin") || userGroups.includes("supervisor");

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

  async function markStatus(estado: "revisada" | "cerrada") {
    if (!inspeccionId) return;
    try {
      setUpdating(true);
      await updateInspeccionEstado(inspeccionId, {
        estado,
        observaciones_revisor: observaciones || undefined,
      });
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

  const informes = data?.archivos.filter((f) => f.tipo.startsWith("informe")) ?? [];
  const termografias = data?.archivos.filter((f) => !f.tipo.startsWith("informe")) ?? [];

  const subtitle = data
    ? `${new Date(data.fecha_inspeccion).toLocaleDateString("es-AR", { day: "2-digit", month: "long", year: "numeric" })}`
    : undefined;

  const titleWithBadge = (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 10 }}>
      <span>Detalle de informe #{inspeccionId ?? "—"}</span>
      {data && (
        <Badge tone={badgeToneForEstado(data.estado)} dot>
          {estadoLabel(data.estado)}
        </Badge>
      )}
    </span>
  );

  return (
    <Modal open={isOpen} onClose={onClose} title={titleWithBadge} subtitle={subtitle} size="xl">
      {loading && (
        <div style={{ padding: 40, textAlign: "center", color: "var(--tv-text-muted)" }}>
          Cargando detalle…
        </div>
      )}

      {error && (
        <div className="tv-notice tv-notice--danger" role="alert" style={{ marginBottom: 14 }}>
          <span>{error}</span>
        </div>
      )}

      {!loading && !error && data && (
        <>
          {/* ─── Metadata grid ─── */}
          <div className="tv-meta-grid">
            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><Calendar /></span>
              <span className="tv-meta-grid__label">Fecha:</span>
              <span className="tv-meta-grid__value">
                {new Date(data.fecha_inspeccion).toLocaleDateString("es-AR", {
                  day: "2-digit", month: "2-digit", year: "numeric",
                })}
              </span>
            </div>

            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><Activity /></span>
              <span className="tv-meta-grid__label">Estado:</span>
              <span className="tv-meta-grid__value" style={{ textTransform: "capitalize" }}>
                {estadoLabel(data.estado)}
              </span>
            </div>

            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><UserCheck /></span>
              <span className="tv-meta-grid__label">Técnico:</span>
              <span className="tv-meta-grid__value">{data.tecnico?.nombre ?? "—"}</span>
            </div>

            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><Building2 /></span>
              <span className="tv-meta-grid__label">Empresa:</span>
              <span className="tv-meta-grid__value">{data.empresa_contratista ?? "—"}</span>
            </div>

            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><Users /></span>
              <span className="tv-meta-grid__label">Integrantes:</span>
              <span className="tv-meta-grid__value">{data.integrantes ?? "—"}</span>
            </div>

            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><MapPin /></span>
              <span className="tv-meta-grid__label">Yacimiento:</span>
              <span className="tv-meta-grid__value">{data.elemento?.yacimiento ?? "—"}</span>
            </div>

            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><Hash /></span>
              <span className="tv-meta-grid__label">Subestación / elemento:</span>
              <span className="tv-meta-grid__value">
                {data.elemento ? `${data.elemento.nombre} (${data.elemento.codigo})` : "—"}
              </span>
            </div>

            <div className="tv-meta-grid__row">
              <span className="tv-meta-grid__icon"><UserCheck /></span>
              <span className="tv-meta-grid__label">Revisada por:</span>
              <span className="tv-meta-grid__value">{data.revisada_por?.nombre ?? "—"}</span>
            </div>

            {data.cerrada_por && (
              <div className="tv-meta-grid__row">
                <span className="tv-meta-grid__icon"><CheckCircle2 /></span>
                <span className="tv-meta-grid__label">Cerrada por:</span>
                <span className="tv-meta-grid__value">{data.cerrada_por.nombre}</span>
              </div>
            )}
          </div>

          {/* ─── Descripción / Resumen ─── */}
          <section className="tv-section">
            <div className="tv-section__title">Descripción / resumen</div>
            <div className="tv-section__body">
              {data.resumen ? (
                <p className="tv-section__paragraph">{data.resumen}</p>
              ) : (
                <span className="tv-section__empty">Sin descripción.</span>
              )}
            </div>
          </section>

          {/* ─── Observaciones del supervisor (si existen) ─── */}
          {data.observaciones_revisor && (
            <section className="tv-section">
              <div className="tv-section__title">Observaciones del supervisor</div>
              <div className="tv-section__body">
                <p className="tv-section__paragraph">{data.observaciones_revisor}</p>
              </div>
            </section>
          )}

          {/* ─── Hallazgos ─── */}
          <section className="tv-section">
            <div className="tv-section__title">
              Hallazgos
              <span className="tv-section__count">· {data.novedades.length}</span>
            </div>
            {data.novedades.length === 0 ? (
              <span className="tv-section__empty">Sin hallazgos cargados.</span>
            ) : (
              <div className="tv-finding-list">
                {data.novedades.map((novedad) => (
                  <article key={novedad.id} className="tv-finding">
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
                          {novedad.criticidad ?? "normal"}
                        </span>
                        <span style={{ marginLeft: "auto" }}>
                          <Badge tone={novedad.estado === "abierta" ? "warning" : "success"} variant="soft">
                            {novedad.estado}
                          </Badge>
                        </span>
                      </div>

                      <div className="tv-finding__meta">
                        {novedad.ubicacion && (
                          <span><MapPin size={12} /> {novedad.ubicacion}</span>
                        )}
                        {novedad.temperatura !== null && novedad.temperatura !== undefined && (
                          <span><Flame size={12} /> {novedad.temperatura} °C</span>
                        )}
                      </div>

                      {novedad.descripcion && (
                        <div className="tv-finding__desc">{novedad.descripcion}</div>
                      )}
                      {novedad.accion_recomendada && (
                        <div className="tv-finding__desc">
                          <strong>Acción recomendada: </strong>
                          {novedad.accion_recomendada}
                        </div>
                      )}
                    </div>
                  </article>
                ))}
              </div>
            )}
          </section>

          {/* ─── Informe formal ─── */}
          <section className="tv-section">
            <div className="tv-section__title">Informe formal</div>
            {informes.length === 0 ? (
              <span className="tv-section__empty">Sin informe formal.</span>
            ) : (
              <div className="tv-file-list" style={{ marginTop: 0 }}>
                {informes.map((file) => (
                  <button
                    key={file.id}
                    type="button"
                    className="tv-file-download"
                    onClick={() => void downloadFile(file)}
                  >
                    <span className="tv-file__icon tv-file__icon--report">
                      <FileText size={15} />
                    </span>
                    <div className="tv-file__text">
                      <div className="tv-file__name">{file.nombre}</div>
                      <div className="tv-file__size">{fmtBytes(file.tamano)}</div>
                    </div>
                    <Download size={15} className="tv-file-download__download" />
                  </button>
                ))}
              </div>
            )}
          </section>

          {/* ─── Archivos térmicos ─── */}
          <section className="tv-section">
            <div className="tv-section__title">
              Archivos térmicos
              <span className="tv-section__count">· {termografias.length}</span>
            </div>
            {termografias.length === 0 ? (
              <span className="tv-section__empty">Sin archivos térmicos.</span>
            ) : (
              <div className="tv-file-list" style={{ marginTop: 0 }}>
                {termografias.map((file) => (
                  <button
                    key={file.id}
                    type="button"
                    className="tv-file-download"
                    onClick={() => void downloadFile(file)}
                  >
                    <span className="tv-file__icon tv-file__icon--therm">
                      {file.tipo.includes("zip") ? (
                        <FolderArchive size={15} />
                      ) : (
                        <Thermometer size={15} />
                      )}
                    </span>
                    <div className="tv-file__text">
                      <div className="tv-file__name">{file.nombre}</div>
                      <div className="tv-file__size">{fmtBytes(file.tamano)}</div>
                    </div>
                    <Download size={15} className="tv-file-download__download" />
                  </button>
                ))}
              </div>
            )}
          </section>

          {/* ─── Bloque de revisión (sólo si canReview y estado lo permite) ─── */}
          {canReview && (data.estado === "enviada" || data.estado === "revisada") && (
            <section className="tv-review-block" style={{ marginTop: 18 }}>
              <div className="tv-review-block__title">
                {data.estado === "enviada" ? "Revisión de informe" : "Cierre de informe"}
              </div>
              <Textarea
                rows={3}
                placeholder="Observaciones del revisor (opcional)"
                value={observaciones}
                onChange={(e) => setObservaciones(e.target.value)}
              />
              <div className="tv-review-block__actions">
                {data.estado === "enviada" && (
                  <Button
                    variant="primary"
                    onClick={() => void markStatus("revisada")}
                    disabled={updating}
                    leftIcon={<CheckCircle2 size={15} />}
                  >
                    {updating ? "Procesando…" : "Marcar revisada"}
                  </Button>
                )}
                {data.estado === "revisada" && (
                  <Button
                    variant="primary"
                    onClick={() => void markStatus("cerrada")}
                    disabled={updating}
                    leftIcon={<CheckCircle2 size={15} />}
                  >
                    {updating ? "Procesando…" : "Cerrar informe"}
                  </Button>
                )}
              </div>
            </section>
          )}
        </>
      )}
    </Modal>
  );
}
