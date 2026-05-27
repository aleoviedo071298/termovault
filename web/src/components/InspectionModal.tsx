import React, { useEffect, useState } from "react";
import { X, AlertCircle, Plus, Trash2, Upload, FileCheck, CheckCircle } from "lucide-react";
import { getCatalogos, listElementos, type Catalogos } from "../api/elementos";
import type { Elemento } from "../types/elemento";
import { createInspeccion } from "../api/inspecciones";
import { useAuth } from "../auth/useAuth";

interface FindingTemp {
  id: string; // client-side temporary ID
  titulo: string;
  criticidad_id: number;
  criticidad_nombre: string;
  criticidad_color: string;
  ubicacion_dentro_elemento: string;
  temperatura_detectada: string;
  accion_recomendada: string;
  descripcion: string;
}

interface InspectionModalProps {
  isOpen: boolean;
  onClose: () => void;
  preSelectedElementId?: number | null;
  onSuccess: () => void;
}

export const InspectionModal: React.FC<InspectionModalProps> = ({
  isOpen,
  onClose,
  preSelectedElementId,
  onSuccess
}) => {
  const { user } = useAuth();
  const [catalogos, setCatalogos] = useState<Catalogos | null>(null);
  const [elementosList, setElementosList] = useState<Elemento[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form states
  const [elementoId, setElementoId] = useState<number | "">("");
  const [fechaInspeccion, setFechaInspeccion] = useState("");
  const [cuadrilla, setCuadrilla] = useState("");
  const [integrantes, setIntegrantes] = useState("");
  const [empresaContratista, setEmpresaContratista] = useState("PECOM S.A.");
  const [condicionesClima, setCondicionesClima] = useState("Despejado");
  const [resumen, setResumen] = useState("");

  // Files
  const [reporteFile, setReporteFile] = useState<File | null>(null);
  const [imagenesFile, setImagenesFile] = useState<File | null>(null);

  // Findings list
  const [novedades, setNovedades] = useState<FindingTemp[]>([]);
  const [showFindingForm, setShowFindingForm] = useState(false);

  // Finding sub-form states
  const [fTitulo, setFTitulo] = useState("");
  const [fCriticidadId, setFCriticidadId] = useState<number | "">("");
  const [fUbicacion, setFUbicacion] = useState("");
  const [fTemperatura, setFTemperatura] = useState("");
  const [fAccion, setFAccion] = useState("");
  const [fDescripcion, setFDescripcion] = useState("");

  // Load catalogs and elements list
  useEffect(() => {
    if (!isOpen) return;

    // Set today as default date
    const today = new Date().toISOString().split("T")[0];
    setFechaInspeccion(today);

    async function loadData() {
      try {
        setLoading(true);
        setError(null);
        
        const [cats, elems] = await Promise.all([
          getCatalogos(),
          listElementos()
        ]);

        setCatalogos(cats);
        setElementosList(elems);

        if (preSelectedElementId) {
          setElementoId(preSelectedElementId);
        } else {
          setElementoId("");
        }

        // Reset other states
        const isTestUser = user?.email === "marijo006@gmail.com";
        setCuadrilla(isTestUser ? "625" : "625");
        setEmpresaContratista("PECOM");
        setIntegrantes("");
        setCondicionesClima("Despejado");
        setResumen("");
        setReporteFile(null);
        setImagenesFile(null);
        setNovedades([]);
        setShowFindingForm(false);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Error al cargar catálogos");
      } finally {
        setLoading(false);
      }
    }

    void loadData();
  }, [isOpen, preSelectedElementId]);

  if (!isOpen) return null;

  function handleAddFinding() {
    if (!fTitulo.trim() || !fCriticidadId) {
      alert("Por favor ingresa un título y selecciona la criticidad del hallazgo.");
      return;
    }

    const selectedCrit = catalogos?.criticidades.find(c => c.id === Number(fCriticidadId));

    const newFinding: FindingTemp = {
      id: Math.random().toString(36).substr(2, 9),
      titulo: fTitulo.trim(),
      criticidad_id: Number(fCriticidadId),
      criticidad_nombre: selectedCrit?.nombre ?? "Desconocida",
      criticidad_color: selectedCrit?.color ?? "#718096",
      ubicacion_dentro_elemento: fUbicacion.trim(),
      temperatura_detectada: fTemperatura,
      accion_recomendada: fAccion.trim(),
      descripcion: fDescripcion.trim()
    };

    setNovedades([...novedades, newFinding]);

    // Reset sub-form
    setFTitulo("");
    setFCriticidadId("");
    setFUbicacion("");
    setFTemperatura("");
    setFAccion("");
    setFDescripcion("");
    setShowFindingForm(false);
  }

  function handleRemoveFinding(id: string) {
    setNovedades(novedades.filter(n => n.id !== id));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!elementoId || !fechaInspeccion) {
      setError("Por favor completa los campos obligatorios (*)");
      return;
    }

    try {
      setSaving(true);
      setError(null);

      const formData = new FormData();
      formData.append("elemento_id", String(elementoId));
      formData.append("fecha_inspeccion", fechaInspeccion);
      formData.append("cuadrilla", cuadrilla.trim());
      formData.append("integrantes", integrantes.trim());
      formData.append("empresa_contratista", empresaContratista.trim());
      formData.append("condiciones_clima", condicionesClima);
      formData.append("resumen", resumen.trim());

      if (reporteFile) {
        formData.append("reporte", reporteFile);
      }
      if (imagenesFile) {
        formData.append("imagenes", imagenesFile);
      }

      // Map findings to backend structure
      const backendFindings = novedades.map(n => ({
        criticidad_id: n.criticidad_id,
        titulo: n.titulo,
        descripcion: n.descripcion || null,
        ubicacion_dentro_elemento: n.ubicacion_dentro_elemento || null,
        temperatura_detectada: n.temperatura_detectada !== "" ? Number(n.temperatura_detectada) : null,
        accion_recomendada: n.accion_recomendada || null
      }));

      formData.append("novedades", JSON.stringify(backendFindings));

      await createInspeccion(formData);

      onSuccess();
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al registrar la inspección");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="modal-backdrop">
      <div className="modal-content" style={{ width: "min(840px, 100%)" }}>
        <header className="modal-header">
          <h2>Registrar Inspección Termográfica</h2>
          <button className="close-btn" onClick={onClose} type="button" aria-label="Cerrar modal">
            <X size={20} />
          </button>
        </header>

        {loading ? (
          <div className="modal-loading">
            <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-sage border-r-2" />
            <p>Cargando información técnica...</p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="modal-form">
            {error && (
              <div className="form-error" role="alert">
                <AlertCircle size={18} />
                <span>{error}</span>
              </div>
            )}

            <div className="form-grid">
              {/* Elemento & Fecha */}
              <div className="form-group">
                <label htmlFor="form-ins-elemento">Elemento *</label>
                <select
                  id="form-ins-elemento"
                  value={elementoId}
                  onChange={(e) => setElementoId(e.target.value ? Number(e.target.value) : "")}
                  disabled={!!preSelectedElementId}
                  required
                >
                  <option value="">Seleccione el equipo</option>
                  {elementosList.map(e => (
                    <option key={e.id} value={e.id}>
                      {e.nombre} ({e.codigo})
                    </option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="form-ins-fecha">Fecha de Inspección *</label>
                <input
                  id="form-ins-fecha"
                  type="date"
                  value={fechaInspeccion}
                  onChange={(e) => setFechaInspeccion(e.target.value)}
                  required
                />
              </div>

              {/* Integrantes & Clima */}
              <div className="form-group">
                <label htmlFor="form-ins-integrantes">Integrantes de Cuadrilla</label>
                <input
                  id="form-ins-integrantes"
                  type="text"
                  value={integrantes}
                  onChange={(e) => setIntegrantes(e.target.value)}
                  placeholder="Ej: A. Oviedo, J. Perez, M. Lopez"
                />
              </div>

              <div className="form-group">
                <label htmlFor="form-ins-clima">Condiciones del Clima</label>
                <select
                  id="form-ins-clima"
                  value={condicionesClima}
                  onChange={(e) => setCondicionesClima(e.target.value)}
                >
                  <option value="Despejado">Despejado</option>
                  <option value="Parcialmente Nublado">Parcialmente Nublado</option>
                  <option value="Nublado">Nublado</option>
                  <option value="Llovizna">Llovizna</option>
                  <option value="Viento Fuerte">Viento Fuerte</option>
                </select>
              </div>

              {/* Carga de Archivos */}
              <div className="form-group">
                <label>Informe Técnico (Word o Excel) *</label>
                <div className={`file-upload-box ${reporteFile ? "has-file" : ""}`}>
                  <input
                    type="file"
                    accept=".doc,.docx,.xls,.xlsx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    onChange={(e) => setReporteFile(e.target.files?.[0] ?? null)}
                    id="file-report"
                    className="sr-only"
                    required
                  />
                  <label htmlFor="file-report" className="file-label-box">
                    {reporteFile ? (
                      <>
                        <FileCheck size={24} className="text-sage" />
                        <span>{reporteFile.name}</span>
                      </>
                    ) : (
                      <>
                        <Upload size={24} />
                        <span>Seleccionar .docx / .xlsx</span>
                      </>
                    )}
                  </label>
                </div>
              </div>

              <div className="form-group">
                <label>Fotos Termográficas (ZIP) *</label>
                <div className={`file-upload-box ${imagenesFile ? "has-file" : ""}`}>
                  <input
                    type="file"
                    accept=".zip,application/zip,application/x-zip-compressed"
                    onChange={(e) => setImagenesFile(e.target.files?.[0] ?? null)}
                    id="file-zip"
                    className="sr-only"
                    required
                  />
                  <label htmlFor="file-zip" className="file-label-box">
                    {imagenesFile ? (
                      <>
                        <FileCheck size={24} className="text-sage" />
                        <span>{imagenesFile.name}</span>
                      </>
                    ) : (
                      <>
                        <Upload size={24} />
                        <span>Seleccionar archivo .zip</span>
                      </>
                    )}
                  </label>
                </div>
              </div>

              <div className="form-group col-span-2">
                <label htmlFor="form-ins-resumen">Resumen Diagnóstico / Diagnóstico General</label>
                <textarea
                  id="form-ins-resumen"
                  rows={2}
                  value={resumen}
                  onChange={(e) => setResumen(e.target.value)}
                  placeholder="Detalles sobre el estado termográfico observado, puntos calientes detectados, etc..."
                />
              </div>
            </div>

            {/* Gestor de Novedades */}
            <hr className="divider" style={{ margin: "20px 0" }} />
            <div className="novedades-manager">
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
                <h3 style={{ margin: 0, fontSize: "1rem", color: "#1d5c46", fontWeight: 800 }}>
                  Novedades / Hallazgos Termográficos ({novedades.length})
                </h3>
                {!showFindingForm && (
                  <button
                    type="button"
                    className="edit-button-quick"
                    style={{ fontSize: "0.8rem", padding: "6px 12px" }}
                    onClick={() => setShowFindingForm(true)}
                  >
                    <Plus size={14} />
                    Agregar Hallazgo
                  </button>
                )}
              </div>

              {showFindingForm && (
                <div className="finding-subform" style={{ background: "#f6f8ee", border: "1px solid #cbd5c4", padding: "16px", borderRadius: "8px", marginBottom: "16px" }}>
                  <h4 style={{ margin: "0 0 12px 0", fontSize: "0.9rem", color: "#17201a", fontWeight: 700 }}>
                    Nuevo Hallazgo
                  </h4>
                  <div className="form-grid" style={{ gap: "12px" }}>
                    <div className="form-group col-span-2">
                      <label>Título del Hallazgo *</label>
                      <input
                        type="text"
                        value={fTitulo}
                        onChange={(e) => setFTitulo(e.target.value)}
                        placeholder="Ej: Calentamiento en borne superior"
                      />
                    </div>
                    <div className="form-group">
                      <label>Criticidad *</label>
                      <select
                        value={fCriticidadId}
                        onChange={(e) => setFCriticidadId(e.target.value ? Number(e.target.value) : "")}
                      >
                        <option value="">Seleccione</option>
                        {catalogos?.criticidades.map(c => (
                          <option key={c.id} value={c.id}>{c.nombre}</option>
                        ))}
                      </select>
                    </div>
                    <div className="form-group">
                      <label>Ubicación en el Equipo</label>
                      <input
                        type="text"
                        value={fUbicacion}
                        onChange={(e) => setFUbicacion(e.target.value)}
                        placeholder="Ej: Fase R - Borna superior"
                      />
                    </div>
                    <div className="form-group">
                      <label>Temperatura Detectada (°C)</label>
                      <input
                        type="number"
                        step="0.1"
                        value={fTemperatura}
                        onChange={(e) => setFTemperatura(e.target.value)}
                        placeholder="Ej: 58.4"
                      />
                    </div>
                    <div className="form-group col-span-2">
                      <label>Descripción / Observación</label>
                      <textarea
                        rows={2}
                        value={fDescripcion}
                        onChange={(e) => setFDescripcion(e.target.value)}
                        placeholder="Detalles térmicos observados..."
                      />
                    </div>
                    <div className="form-group col-span-2">
                      <label>Acción Recomendada</label>
                      <textarea
                        rows={2}
                        value={fAccion}
                        onChange={(e) => setFAccion(e.target.value)}
                        placeholder="Ej: Reapriete de Bornas y limpieza en próxima parada..."
                      />
                    </div>
                  </div>
                  <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px", marginTop: "12px" }}>
                    <button
                      type="button"
                      className="cancel-btn"
                      style={{ fontSize: "0.8rem", padding: "6px 12px" }}
                      onClick={() => setShowFindingForm(false)}
                    >
                      Cancelar
                    </button>
                    <button
                      type="button"
                      className="submit-btn"
                      style={{ fontSize: "0.8rem", padding: "6px 12px" }}
                      onClick={handleAddFinding}
                    >
                      Guardar Hallazgo
                    </button>
                  </div>
                </div>
              )}

              {novedades.length > 0 ? (
                <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                  {novedades.map((n) => (
                    <div
                      key={n.id}
                      style={{
                        background: "#fff",
                        border: `1px solid ${n.criticidad_color}`,
                        borderRadius: "8px",
                        padding: "12px",
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "flex-start"
                      }}
                    >
                      <div>
                        <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "4px" }}>
                          <h4 style={{ margin: 0, fontSize: "0.88rem", fontWeight: 700, color: "#17201a" }}>{n.titulo}</h4>
                          <span
                            className="badge"
                            style={{
                              "--badge-color": n.criticidad_color,
                              transform: "scale(0.8)",
                              transformOrigin: "left center"
                            } as React.CSSProperties}
                          >
                            {n.criticidad_nombre}
                          </span>
                        </div>
                        {n.ubicacion_dentro_elemento && (
                          <p style={{ margin: "2px 0", fontSize: "0.78rem", color: "#59645e" }}>
                            <strong>Ubicación:</strong> {n.ubicacion_dentro_elemento}
                          </p>
                        )}
                        {n.temperatura_detectada && (
                          <p style={{ margin: "2px 0", fontSize: "0.78rem", color: "#59645e" }}>
                            <strong>Temperatura:</strong> {n.temperatura_detectada}°C
                          </p>
                        )}
                      </div>
                      <button
                        type="button"
                        style={{ background: "transparent", border: 0, color: "#e53e3e", cursor: "pointer", padding: "4px" }}
                        onClick={() => handleRemoveFinding(n.id)}
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  ))}
                </div>
              ) : (
                <p style={{ margin: 0, fontSize: "0.85rem", color: "#59645e", fontStyle: "italic" }}>
                  No se han registrado novedades térmicas en esta inspección.
                </p>
              )}
            </div>

            <footer className="modal-actions">
              <button className="cancel-btn" onClick={onClose} type="button" disabled={saving}>
                Cancelar
              </button>
              <button className="submit-btn" type="submit" disabled={saving}>
                {saving ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-white" />
                    Registrando...
                  </>
                ) : (
                  <>
                    <CheckCircle size={18} />
                    Registrar Inspección
                  </>
                )}
              </button>
            </footer>
          </form>
        )}
      </div>
    </div>
  );
};
