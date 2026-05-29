import React, { useEffect, useState } from "react";
import { X, AlertCircle, Plus, Trash2, Upload, FileCheck, CheckCircle, Thermometer, FileText, FolderArchive } from "lucide-react";
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
  const [termografiaFiles, setTermografiaFiles] = useState<File[]>([]);
  const [dragging, setDragging] = useState(false);
  const [incluirInforme, setIncluirInforme] = useState(false);
  const [reporteFile, setReporteFile] = useState<File | null>(null);
  const [draggingInforme, setDraggingInforme] = useState(false);

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
        setTermografiaFiles([]);
        setDragging(false);
        setIncluirInforme(false);
        setReporteFile(null);
        setDraggingInforme(false);
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

  function formatBytes(bytes: number): string {
    if (!bytes) return "0 B";
    const units = ["B", "KB", "MB", "GB"];
    let value = bytes;
    let idx = 0;
    while (value >= 1024 && idx < units.length - 1) {
      value /= 1024;
      idx += 1;
    }
    return `${value.toFixed(1)} ${units[idx]}`;
  }

  function handleAddTermografias(fileList: FileList | null) {
    if (!fileList || fileList.length === 0) return;
    const incoming = Array.from(fileList);
    const allowed = incoming.filter(f => {
      const ext = f.name.split(".").pop()?.toLowerCase();
      return ext === "is2" || ext === "zip";
    });
    if (allowed.length !== incoming.length) {
      setError("Solo se permiten archivos térmicos .is2 o paquetes .zip.");
    }
    // Evitar duplicados por nombre + tamaño
    setTermografiaFiles(prev => {
      const key = (f: File) => `${f.name}::${f.size}`;
      const seen = new Set(prev.map(key));
      const merged = [...prev];
      for (const f of allowed) {
        if (!seen.has(key(f))) merged.push(f);
      }
      return merged;
    });
  }

  function handleRemoveTermografia(index: number) {
    setTermografiaFiles(prev => prev.filter((_, i) => i !== index));
  }

  function handleSetInforme(fileList: FileList | null) {
    const file = fileList?.[0];
    if (!file) return;
    const ext = file.name.split(".").pop()?.toLowerCase();
    const allowed = ["pdf", "doc", "docx", "xls", "xlsx"];
    if (!ext || !allowed.includes(ext)) {
      setError("El informe formal debe ser .pdf, .docx o .xlsx.");
      return;
    }
    setError(null);
    setReporteFile(file);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!elementoId || !fechaInspeccion) {
      setError("Por favor completa los campos obligatorios (*)");
      return;
    }

    if (termografiaFiles.length === 0) {
      setError("Adjuntá al menos un archivo térmico (.is2 o .zip).");
      return;
    }

    if (incluirInforme && !reporteFile) {
      setError("Activaste 'Incluir informe formal' pero no seleccionaste el archivo del informe.");
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

      termografiaFiles.forEach(file => {
        formData.append("termografias[]", file);
      });

      if (incluirInforme && reporteFile) {
        formData.append("reporte", reporteFile);
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

              {/* Carga de Archivos Térmicos (múltiples) */}
              <div className="form-group col-span-2">
                <label>Archivos Térmicos *</label>
                <div
                  className={`file-upload-box ${termografiaFiles.length > 0 ? "has-file" : ""} ${dragging ? "is-dragging" : ""}`}
                  onDragEnter={(e) => { e.preventDefault(); e.stopPropagation(); setDragging(true); }}
                  onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); setDragging(true); }}
                  onDragLeave={(e) => { e.preventDefault(); e.stopPropagation(); setDragging(false); }}
                  onDrop={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setDragging(false);
                    handleAddTermografias(e.dataTransfer.files);
                  }}
                  style={dragging ? { borderColor: "#1d5c46", background: "#eef5ee" } : undefined}
                >
                  <input
                    type="file"
                    accept=".is2,.zip,application/zip,application/x-zip-compressed,application/octet-stream"
                    multiple
                    onChange={(e) => {
                      handleAddTermografias(e.target.files);
                      e.target.value = ""; // permite re-seleccionar el mismo archivo
                    }}
                    id="file-termografias"
                    className="sr-only"
                  />
                  <label htmlFor="file-termografias" className="file-label-box">
                    <Upload size={24} />
                    <span>
                      {dragging
                        ? "Soltá los archivos acá"
                        : "Arrastrá archivos térmicos Fluke (.is2) o paquetes ZIP, o hacé clic para elegir"}
                    </span>
                  </label>
                </div>

                {termografiaFiles.length > 0 && (
                  <div style={{ display: "flex", flexDirection: "column", gap: "6px", marginTop: "10px" }}>
                    {termografiaFiles.map((file, idx) => {
                      const ext = file.name.split(".").pop()?.toLowerCase();
                      const Icon = ext === "zip" ? FolderArchive : Thermometer;
                      return (
                        <div
                          key={`${file.name}-${file.size}-${idx}`}
                          style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "10px",
                            background: "#f6f8ee",
                            border: "1px solid #cbd5c4",
                            borderRadius: "8px",
                            padding: "8px 12px"
                          }}
                        >
                          <Icon size={18} className="text-sage" />
                          <span style={{ flex: 1, fontSize: "0.82rem", color: "#17201a", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                            {file.name}
                          </span>
                          <span style={{ fontSize: "0.75rem", color: "#59645e" }}>{formatBytes(file.size)}</span>
                          <button
                            type="button"
                            aria-label={`Quitar ${file.name}`}
                            style={{ background: "transparent", border: 0, color: "#e53e3e", cursor: "pointer", padding: "2px", display: "flex" }}
                            onClick={() => handleRemoveTermografia(idx)}
                          >
                            <Trash2 size={15} />
                          </button>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>

              {/* Toggle: informe formal opcional */}
              <div className="form-group col-span-2">
                <label
                  htmlFor="toggle-informe"
                  style={{ display: "flex", alignItems: "center", gap: "10px", cursor: "pointer", userSelect: "none" }}
                >
                  <input
                    id="toggle-informe"
                    type="checkbox"
                    checked={incluirInforme}
                    onChange={(e) => {
                      setIncluirInforme(e.target.checked);
                      if (!e.target.checked) setReporteFile(null);
                    }}
                    style={{ width: "16px", height: "16px", cursor: "pointer" }}
                  />
                  <span style={{ fontWeight: 700, color: "#1d5c46" }}>Incluir informe formal</span>
                </label>

                {incluirInforme && (
                  <div
                    className={`file-upload-box ${reporteFile ? "has-file" : ""} ${draggingInforme ? "is-dragging" : ""}`}
                    style={{ marginTop: "10px", ...(draggingInforme ? { borderColor: "#1d5c46", background: "#eef5ee" } : {}) }}
                    onDragEnter={(e) => { e.preventDefault(); e.stopPropagation(); setDraggingInforme(true); }}
                    onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); setDraggingInforme(true); }}
                    onDragLeave={(e) => { e.preventDefault(); e.stopPropagation(); setDraggingInforme(false); }}
                    onDrop={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      setDraggingInforme(false);
                      handleSetInforme(e.dataTransfer.files);
                    }}
                  >
                    <input
                      type="file"
                      accept=".pdf,.doc,.docx,.xls,.xlsx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                      onChange={(e) => {
                        handleSetInforme(e.target.files);
                        e.target.value = "";
                      }}
                      id="file-report"
                      className="sr-only"
                    />
                    <label htmlFor="file-report" className="file-label-box">
                      {draggingInforme ? (
                        <>
                          <FileText size={24} />
                          <span>Soltá el informe acá</span>
                        </>
                      ) : reporteFile ? (
                        <>
                          <FileCheck size={24} className="text-sage" />
                          <span>{reporteFile.name}</span>
                        </>
                      ) : (
                        <>
                          <FileText size={24} />
                          <span>Arrastrá el informe (.pdf / .docx / .xlsx) o hacé clic para elegir</span>
                        </>
                      )}
                    </label>
                  </div>
                )}
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
