import React, { useEffect, useState } from "react";
import {
  AlertCircle,
  Plus,
  Trash2,
  Upload,
  Thermometer,
  FileText,
  FolderArchive,
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Flame,
  MapPin,
  Pencil,
  ChevronRight,
} from "lucide-react";
import { getCatalogos, listElementos, type Catalogos } from "../api/elementos";
import type { Elemento } from "../types/elemento";
import { createInspeccion } from "../api/inspecciones";

// Design system
import { Modal } from "./ui/Modal";
import { Button } from "./ui/Button";
import { Field, Input, Select, Textarea } from "./ui/Field";
import { SearchableSelect } from "./ui/SearchableSelect";
import { Stepper, type Step } from "./ui/Stepper";

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

const STEPS: Step[] = [
  { id: "data",     caption: "Paso 1",  label: "Datos de inspección" },
  { id: "files",    caption: "Paso 2",  label: "Archivos" },
  { id: "findings", caption: "Paso 3",  label: "Hallazgos" },
  { id: "review",   caption: "Paso 4",  label: "Revisar y enviar" },
];

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

export const InspectionModal: React.FC<InspectionModalProps> = ({
  isOpen,
  onClose,
  preSelectedElementId,
  onSuccess,
}) => {
  const [catalogos, setCatalogos] = useState<Catalogos | null>(null);
  const [elementosList, setElementosList] = useState<Elemento[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form states (idénticos a la versión anterior)
  const [elementoId, setElementoId] = useState<number | "">("");
  const [fechaInspeccion, setFechaInspeccion] = useState("");
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

  // ─── Wizard navigation (UI only) ───
  const [currentStep, setCurrentStep] = useState(0);

  // Load catalogs and elements list
  useEffect(() => {
    if (!isOpen) return;

    // Set today as default date
    const today = new Date().toISOString().split("T")[0];
    setFechaInspeccion(today);
    setCurrentStep(0);

    async function loadData() {
      try {
        setLoading(true);
        setError(null);

        const [cats, elems] = await Promise.all([getCatalogos(), listElementos()]);

        setCatalogos(cats);
        setElementosList(elems);

        if (preSelectedElementId) {
          setElementoId(preSelectedElementId);
        } else {
          setElementoId("");
        }

        // Reset other states
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

  // ─── Handlers (idénticos a la versión anterior) ───

  function handleAddFinding() {
    if (!fTitulo.trim() || !fCriticidadId) {
      alert("Por favor ingresa un título y selecciona la criticidad del hallazgo.");
      return;
    }

    const selectedCrit = catalogos?.criticidades.find((c) => c.id === Number(fCriticidadId));

    const newFinding: FindingTemp = {
      id: Math.random().toString(36).substr(2, 9),
      titulo: fTitulo.trim(),
      criticidad_id: Number(fCriticidadId),
      criticidad_nombre: selectedCrit?.nombre ?? "Desconocida",
      criticidad_color: selectedCrit?.color ?? "#718096",
      ubicacion_dentro_elemento: fUbicacion.trim(),
      temperatura_detectada: fTemperatura,
      accion_recomendada: fAccion.trim(),
      descripcion: fDescripcion.trim(),
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
    setNovedades(novedades.filter((n) => n.id !== id));
  }

  function handleAddTermografias(fileList: FileList | null) {
    if (!fileList || fileList.length === 0) return;
    const incoming = Array.from(fileList);
    const allowed = incoming.filter((f) => {
      const ext = f.name.split(".").pop()?.toLowerCase();
      return ext === "is2" || ext === "zip";
    });
    if (allowed.length !== incoming.length) {
      setError("Solo se permiten archivos térmicos .is2 o paquetes .zip.");
    }
    // Evitar duplicados por nombre + tamaño
    setTermografiaFiles((prev) => {
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
    setTermografiaFiles((prev) => prev.filter((_, i) => i !== index));
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

  async function handleSubmit() {
    if (!elementoId || !fechaInspeccion) {
      setError("Por favor completa los campos obligatorios (*)");
      setCurrentStep(0);
      return;
    }

    if (termografiaFiles.length === 0) {
      setError("Adjuntá al menos un archivo térmico (.is2 o .zip).");
      setCurrentStep(1);
      return;
    }

    if (incluirInforme && !reporteFile) {
      setError("Activaste 'Incluir informe formal' pero no seleccionaste el archivo del informe.");
      setCurrentStep(1);
      return;
    }

    try {
      setSaving(true);
      setError(null);

      const formData = new FormData();
      formData.append("elemento_id", String(elementoId));
      formData.append("fecha_inspeccion", fechaInspeccion);
      formData.append("integrantes", integrantes.trim());
      formData.append("empresa_contratista", empresaContratista.trim());
      formData.append("condiciones_clima", condicionesClima);
      formData.append("resumen", resumen.trim());

      termografiaFiles.forEach((file) => {
        formData.append("termografias[]", file);
      });

      if (incluirInforme && reporteFile) {
        formData.append("reporte", reporteFile);
      }

      // Map findings to backend structure
      const backendFindings = novedades.map((n) => ({
        criticidad_id: n.criticidad_id,
        titulo: n.titulo,
        descripcion: n.descripcion || null,
        ubicacion_dentro_elemento: n.ubicacion_dentro_elemento || null,
        temperatura_detectada: n.temperatura_detectada !== "" ? Number(n.temperatura_detectada) : null,
        accion_recomendada: n.accion_recomendada || null,
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

  // ─── Navegación entre pasos ───

  function canAdvanceFrom(step: number): boolean {
    if (step === 0) return Boolean(elementoId) && Boolean(fechaInspeccion);
    if (step === 1) return termografiaFiles.length > 0 && (!incluirInforme || reporteFile !== null);
    return true; // step 2 (findings) y step 3 (review) siempre OK
  }

  function goNext() {
    setError(null);
    if (currentStep === 0 && !canAdvanceFrom(0)) {
      setError("Seleccioná un elemento y la fecha de inspección.");
      return;
    }
    if (currentStep === 1) {
      if (termografiaFiles.length === 0) {
        setError("Adjuntá al menos un archivo térmico (.is2 o .zip).");
        return;
      }
      if (incluirInforme && !reporteFile) {
        setError("Activaste 'Incluir informe formal' pero no seleccionaste archivo.");
        return;
      }
    }
    setCurrentStep((s) => Math.min(STEPS.length - 1, s + 1));
  }

  function goBack() {
    setError(null);
    setCurrentStep((s) => Math.max(0, s - 1));
  }

  // Elemento seleccionado (label para review)
  const elementoSeleccionado = elementosList.find((e) => e.id === Number(elementoId));

  // ─── Footer dinámico del modal ───

  const isLastStep = currentStep === STEPS.length - 1;
  const isFindingsStep = currentStep === 2;

  const footer = (
    <>
      <Button variant="ghost" onClick={onClose} disabled={saving}>
        Cancelar
      </Button>
      <div style={{ flex: 1 }} />
      {currentStep > 0 && (
        <Button
          variant="secondary"
          onClick={goBack}
          disabled={saving}
          leftIcon={<ArrowLeft size={14} />}
        >
          Anterior
        </Button>
      )}
      {isFindingsStep && novedades.length === 0 && !showFindingForm && (
        <Button variant="secondary" onClick={() => setCurrentStep(3)} disabled={saving}>
          Omitir
        </Button>
      )}
      {!isLastStep && (
        <Button
          variant="primary"
          onClick={goNext}
          disabled={saving || !canAdvanceFrom(currentStep)}
          rightIcon={<ArrowRight size={14} />}
        >
          Siguiente
        </Button>
      )}
      {isLastStep && (
        <Button
          variant="primary"
          onClick={handleSubmit}
          disabled={saving}
          leftIcon={<CheckCircle2 size={15} />}
        >
          {saving ? "Registrando…" : "Registrar inspección"}
        </Button>
      )}
    </>
  );

  return (
    <Modal
      open={isOpen}
      onClose={onClose}
      title="Registrar inspección termográfica"
      subtitle="Completá los datos en cada paso. Los hallazgos son opcionales."
      size="xl"
      footer={footer}
    >
      {/* Stepper fijo arriba del cuerpo */}
      <div style={{ margin: "-20px -22px 18px", padding: 0 }}>
        <Stepper steps={STEPS} current={currentStep} />
      </div>

      {error && (
        <div className="tv-notice tv-notice--danger" role="alert" style={{ marginBottom: 14 }}>
          <AlertCircle size={16} />
          <span>{error}</span>
        </div>
      )}

      {loading ? (
        <div style={{ padding: 40, textAlign: "center", color: "var(--tv-text-muted)" }}>
          Cargando información técnica…
        </div>
      ) : (
        <>
          {/* ════════════════════════════════════════════════════════════
              PASO 1 — DATOS DE INSPECCIÓN
              ════════════════════════════════════════════════════════════ */}
          {currentStep === 0 && (
            <div className="tv-step">
              <div className="tv-step__head">
                <div className="tv-step__title">Datos de la inspección</div>
                <div className="tv-step__desc">
                  Indicá el elemento inspeccionado, la fecha y el contexto operativo.
                </div>
              </div>

              <div className="tv-step__grid">
                <Field label="Elemento inspeccionado" required>
                  {(id) => (
                    <SearchableSelect
                      id={id}
                      options={elementosList.map((el) => ({
                        value: el.id,
                        label: `${el.codigo} — ${el.nombre}`,
                      }))}
                      value={elementoId}
                      onChange={(v) => setElementoId(v === "" ? "" : Number(v))}
                      placeholder="Buscar por código o nombre…"
                      required
                    />
                  )}
                </Field>

                <Field label="Fecha de inspección" required>
                  {(id) => (
                    <Input
                      id={id}
                      type="date"
                      value={fechaInspeccion}
                      onChange={(e) => setFechaInspeccion(e.target.value)}
                      required
                    />
                  )}
                </Field>

                <Field label="Integrantes de la cuadrilla">
                  {(id) => (
                    <Input
                      id={id}
                      placeholder="Nombres separados por coma"
                      value={integrantes}
                      onChange={(e) => setIntegrantes(e.target.value)}
                    />
                  )}
                </Field>

                <Field label="Empresa contratista">
                  {(id) => (
                    <Input
                      id={id}
                      value={empresaContratista}
                      onChange={(e) => setEmpresaContratista(e.target.value)}
                    />
                  )}
                </Field>

                <Field label="Condiciones climáticas">
                  {(id) => (
                    <Select
                      id={id}
                      value={condicionesClima}
                      onChange={(e) => setCondicionesClima(e.target.value)}
                    >
                      <option value="Despejado">Despejado</option>
                      <option value="Nublado">Nublado</option>
                      <option value="Parcialmente nublado">Parcialmente nublado</option>
                      <option value="Lluvia">Lluvia</option>
                      <option value="Viento">Viento</option>
                    </Select>
                  )}
                </Field>

                <div className="tv-field--full">
                  <Field label="Descripción / resumen" hint="Contexto general de la inspección (opcional).">
                    {(id) => (
                      <Textarea
                        id={id}
                        rows={3}
                        placeholder="Ej: Inspección programada de rutina sobre subestaciones eléctricas…"
                        value={resumen}
                        onChange={(e) => setResumen(e.target.value)}
                      />
                    )}
                  </Field>
                </div>
              </div>
            </div>
          )}

          {/* ════════════════════════════════════════════════════════════
              PASO 2 — ARCHIVOS
              ════════════════════════════════════════════════════════════ */}
          {currentStep === 1 && (
            <div className="tv-step">
              <div className="tv-step__head">
                <div className="tv-step__title">Archivos de la inspección</div>
                <div className="tv-step__desc">
                  Las termografías son obligatorias. El informe formal (PDF / Word / Excel) es opcional.
                </div>
              </div>

              {/* Termografías — obligatorio */}
              <div>
                <Field label="Termografías (.is2 o .zip)" required hint="Podés arrastrar varios archivos o seleccionarlos del disco.">
                  {() => (
                    <label
                      className={`tv-uploader${dragging ? " is-dragging" : ""}`}
                      onDragOver={(e) => {
                        e.preventDefault();
                        setDragging(true);
                      }}
                      onDragLeave={() => setDragging(false)}
                      onDrop={(e) => {
                        e.preventDefault();
                        setDragging(false);
                        handleAddTermografias(e.dataTransfer.files);
                      }}
                    >
                      <input
                        type="file"
                        multiple
                        accept=".is2,.zip"
                        className="tv-uploader__input"
                        onChange={(e) => handleAddTermografias(e.target.files)}
                      />
                      <span className="tv-uploader__icon">
                        <Thermometer size={20} strokeWidth={1.8} />
                      </span>
                      <span className="tv-uploader__main">
                        Arrastrá tus archivos térmicos o <em>buscalos en el disco</em>
                      </span>
                      <span className="tv-uploader__hint">Acepta .is2 (Fluke) y .zip — hasta 60 MB cada uno</span>
                    </label>
                  )}
                </Field>

                {termografiaFiles.length > 0 && (
                  <div className="tv-file-list">
                    {termografiaFiles.map((file, idx) => (
                      <div key={`${file.name}-${idx}`} className="tv-file">
                        <span className="tv-file__icon tv-file__icon--therm">
                          <FolderArchive size={15} />
                        </span>
                        <div className="tv-file__text">
                          <div className="tv-file__name">{file.name}</div>
                          <div className="tv-file__size">{formatBytes(file.size)}</div>
                        </div>
                        <button
                          type="button"
                          className="tv-file__remove"
                          onClick={() => handleRemoveTermografia(idx)}
                          aria-label="Quitar archivo"
                        >
                          <Trash2 size={15} />
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Informe formal — opcional */}
              <div className="tv-optional-section">
                <div className="tv-optional-section__head">
                  <input
                    type="checkbox"
                    id="incluir-informe"
                    checked={incluirInforme}
                    onChange={(e) => {
                      setIncluirInforme(e.target.checked);
                      if (!e.target.checked) setReporteFile(null);
                    }}
                  />
                  <label htmlFor="incluir-informe" style={{ flex: 1, cursor: "pointer" }}>
                    <div className="tv-optional-section__title">Adjuntar informe formal (opcional)</div>
                    <div className="tv-optional-section__desc">
                      PDF, Word o Excel con el reporte completo del trabajo realizado.
                    </div>
                  </label>
                </div>

                {incluirInforme && (
                  <div className="tv-optional-section__body">
                    {reporteFile ? (
                      <div className="tv-file">
                        <span className="tv-file__icon tv-file__icon--report">
                          <FileText size={15} />
                        </span>
                        <div className="tv-file__text">
                          <div className="tv-file__name">{reporteFile.name}</div>
                          <div className="tv-file__size">{formatBytes(reporteFile.size)}</div>
                        </div>
                        <button
                          type="button"
                          className="tv-file__remove"
                          onClick={() => setReporteFile(null)}
                          aria-label="Quitar informe"
                        >
                          <Trash2 size={15} />
                        </button>
                      </div>
                    ) : (
                      <label
                        className={`tv-uploader${draggingInforme ? " is-dragging" : ""}`}
                        onDragOver={(e) => {
                          e.preventDefault();
                          setDraggingInforme(true);
                        }}
                        onDragLeave={() => setDraggingInforme(false)}
                        onDrop={(e) => {
                          e.preventDefault();
                          setDraggingInforme(false);
                          handleSetInforme(e.dataTransfer.files);
                        }}
                      >
                        <input
                          type="file"
                          accept=".pdf,.doc,.docx,.xls,.xlsx"
                          className="tv-uploader__input"
                          onChange={(e) => handleSetInforme(e.target.files)}
                        />
                        <span className="tv-uploader__icon">
                          <Upload size={20} strokeWidth={1.8} />
                        </span>
                        <span className="tv-uploader__main">
                          Subí el informe formal o <em>buscalo en el disco</em>
                        </span>
                        <span className="tv-uploader__hint">PDF, DOC/DOCX o XLS/XLSX — máximo 10 MB</span>
                      </label>
                    )}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* ════════════════════════════════════════════════════════════
              PASO 3 — HALLAZGOS
              ════════════════════════════════════════════════════════════ */}
          {currentStep === 2 && (
            <div className="tv-step">
              <div className="tv-step__head">
                <div className="tv-step__title">
                  Hallazgos / Novedades termográficas
                  {novedades.length > 0 && (
                    <span style={{ marginLeft: 8, fontSize: 13, color: "var(--tv-text-muted)", fontWeight: 400 }}>
                      · {novedades.length} {novedades.length === 1 ? "hallazgo" : "hallazgos"}
                    </span>
                  )}
                </div>
                <div className="tv-step__desc">
                  Si no detectaste hallazgos, podés omitir este paso. Podés agregar todos los que necesites.
                </div>
              </div>

              {/* Lista de hallazgos existentes */}
              {novedades.length > 0 && (
                <div className="tv-finding-list">
                  {novedades.map((n) => (
                    <div key={n.id} className="tv-finding">
                      <span
                        className="tv-finding__color"
                        style={{ background: n.criticidad_color }}
                        aria-hidden="true"
                      />
                      <div className="tv-finding__main">
                        <div className="tv-finding__head">
                          <span className="tv-finding__title">{n.titulo}</span>
                          <span
                            className="tv-badge tv-badge--solid"
                            style={{ background: n.criticidad_color, color: "white" }}
                          >
                            {n.criticidad_nombre}
                          </span>
                        </div>
                        <div className="tv-finding__meta">
                          {n.ubicacion_dentro_elemento && (
                            <span><MapPin size={12} /> {n.ubicacion_dentro_elemento}</span>
                          )}
                          {n.temperatura_detectada && (
                            <span><Flame size={12} /> {n.temperatura_detectada} °C</span>
                          )}
                        </div>
                        {n.descripcion && (
                          <div className="tv-finding__desc">{n.descripcion}</div>
                        )}
                        {n.accion_recomendada && (
                          <div className="tv-finding__desc">
                            <strong>Acción:</strong> {n.accion_recomendada}
                          </div>
                        )}
                      </div>
                      <button
                        type="button"
                        className="tv-file__remove"
                        onClick={() => handleRemoveFinding(n.id)}
                        aria-label="Eliminar hallazgo"
                      >
                        <Trash2 size={15} />
                      </button>
                    </div>
                  ))}
                </div>
              )}

              {/* Sub-form de hallazgo */}
              {showFindingForm ? (
                <div className="tv-finding-add">
                  <div className="tv-finding-add__head">
                    <span className="tv-finding-add__title">Nuevo hallazgo</span>
                    <Button variant="ghost" size="sm" onClick={() => setShowFindingForm(false)}>
                      Cancelar
                    </Button>
                  </div>

                  <div className="tv-step__grid">
                    <Field label="Título del hallazgo" required>
                      {(id) => (
                        <Input
                          id={id}
                          placeholder="Ej: Punto caliente en borna fase R"
                          value={fTitulo}
                          onChange={(e) => setFTitulo(e.target.value)}
                        />
                      )}
                    </Field>

                    <Field label="Criticidad" required>
                      {(id) => (
                        <Select
                          id={id}
                          value={fCriticidadId}
                          onChange={(e) => setFCriticidadId(e.target.value === "" ? "" : Number(e.target.value))}
                        >
                          <option value="">Seleccionar criticidad</option>
                          {catalogos?.criticidades.map((c) => (
                            <option key={c.id} value={c.id}>{c.nombre}</option>
                          ))}
                        </Select>
                      )}
                    </Field>

                    <Field label="Ubicación dentro del elemento">
                      {(id) => (
                        <Input
                          id={id}
                          placeholder="Ej: Fase R, borna superior"
                          value={fUbicacion}
                          onChange={(e) => setFUbicacion(e.target.value)}
                        />
                      )}
                    </Field>

                    <Field label="Temperatura detectada (°C)">
                      {(id) => (
                        <Input
                          id={id}
                          type="number"
                          step="0.1"
                          placeholder="Ej: 87.5"
                          value={fTemperatura}
                          onChange={(e) => setFTemperatura(e.target.value)}
                        />
                      )}
                    </Field>

                    <div className="tv-field--full">
                      <Field label="Descripción">
                        {(id) => (
                          <Textarea
                            id={id}
                            rows={2}
                            placeholder="Detalle técnico del hallazgo"
                            value={fDescripcion}
                            onChange={(e) => setFDescripcion(e.target.value)}
                          />
                        )}
                      </Field>
                    </div>

                    <div className="tv-field--full">
                      <Field label="Acción recomendada">
                        {(id) => (
                          <Textarea
                            id={id}
                            rows={2}
                            placeholder="Ej: Reapretar borna y reinspeccionar en 30 días."
                            value={fAccion}
                            onChange={(e) => setFAccion(e.target.value)}
                          />
                        )}
                      </Field>
                    </div>
                  </div>

                  <div style={{ display: "flex", justifyContent: "flex-end" }}>
                    <Button variant="primary" onClick={handleAddFinding} leftIcon={<Plus size={15} />}>
                      Guardar hallazgo
                    </Button>
                  </div>
                </div>
              ) : (
                <div>
                  <Button
                    variant="secondary"
                    leftIcon={<Plus size={15} />}
                    onClick={() => setShowFindingForm(true)}
                  >
                    Agregar {novedades.length > 0 ? "otro " : ""}hallazgo
                  </Button>
                </div>
              )}
            </div>
          )}

          {/* ════════════════════════════════════════════════════════════
              PASO 4 — REVISAR Y ENVIAR
              ════════════════════════════════════════════════════════════ */}
          {currentStep === 3 && (
            <div className="tv-step">
              <div className="tv-step__head">
                <div className="tv-step__title">Revisar antes de enviar</div>
                <div className="tv-step__desc">
                  Verificá los datos. Si algo no está bien, podés volver al paso correspondiente.
                </div>
              </div>

              <div className="tv-review">
                {/* Datos de inspección */}
                <div className="tv-review__card">
                  <div className="tv-review__head">
                    <span className="tv-review__title">Datos de inspección</span>
                    <button type="button" className="tv-review__edit" onClick={() => setCurrentStep(0)}>
                      <Pencil size={11} style={{ verticalAlign: "middle", marginRight: 2 }} /> Editar
                    </button>
                  </div>
                  <dl className="tv-review__grid">
                    <dt>Elemento</dt>
                    <dd>
                      {elementoSeleccionado
                        ? `${elementoSeleccionado.codigo} — ${elementoSeleccionado.nombre}`
                        : <span className="tv-review__empty">No seleccionado</span>}
                    </dd>
                    <dt>Fecha</dt>
                    <dd>{fechaInspeccion || <span className="tv-review__empty">No definida</span>}</dd>
                    <dt>Integrantes</dt>
                    <dd>{integrantes || <span className="tv-review__empty">—</span>}</dd>
                    <dt>Contratista</dt>
                    <dd>{empresaContratista || <span className="tv-review__empty">—</span>}</dd>
                    <dt>Clima</dt>
                    <dd>{condicionesClima}</dd>
                    {resumen && (
                      <>
                        <dt>Resumen</dt>
                        <dd>{resumen}</dd>
                      </>
                    )}
                  </dl>
                </div>

                {/* Archivos */}
                <div className="tv-review__card">
                  <div className="tv-review__head">
                    <span className="tv-review__title">Archivos adjuntos</span>
                    <button type="button" className="tv-review__edit" onClick={() => setCurrentStep(1)}>
                      <Pencil size={11} style={{ verticalAlign: "middle", marginRight: 2 }} /> Editar
                    </button>
                  </div>
                  <dl className="tv-review__grid">
                    <dt>Termografías</dt>
                    <dd>{termografiaFiles.length} {termografiaFiles.length === 1 ? "archivo" : "archivos"}</dd>
                    <dt>Informe formal</dt>
                    <dd>
                      {incluirInforme && reporteFile ? (
                        <span><ChevronRight size={11} /> {reporteFile.name}</span>
                      ) : (
                        <span className="tv-review__empty">No incluido</span>
                      )}
                    </dd>
                  </dl>
                </div>

                {/* Hallazgos */}
                <div className="tv-review__card">
                  <div className="tv-review__head">
                    <span className="tv-review__title">Hallazgos</span>
                    <button type="button" className="tv-review__edit" onClick={() => setCurrentStep(2)}>
                      <Pencil size={11} style={{ verticalAlign: "middle", marginRight: 2 }} /> Editar
                    </button>
                  </div>
                  {novedades.length === 0 ? (
                    <span className="tv-review__empty">Sin hallazgos cargados.</span>
                  ) : (
                    <div className="tv-finding-list">
                      {novedades.map((n) => (
                        <div key={n.id} className="tv-finding">
                          <span className="tv-finding__color" style={{ background: n.criticidad_color }} aria-hidden="true" />
                          <div className="tv-finding__main">
                            <div className="tv-finding__head">
                              <span className="tv-finding__title">{n.titulo}</span>
                              <span
                                className="tv-badge tv-badge--solid"
                                style={{ background: n.criticidad_color, color: "white" }}
                              >
                                {n.criticidad_nombre}
                              </span>
                            </div>
                            {(n.ubicacion_dentro_elemento || n.temperatura_detectada) && (
                              <div className="tv-finding__meta">
                                {n.ubicacion_dentro_elemento && (
                                  <span><MapPin size={12} /> {n.ubicacion_dentro_elemento}</span>
                                )}
                                {n.temperatura_detectada && (
                                  <span><Flame size={12} /> {n.temperatura_detectada} °C</span>
                                )}
                              </div>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </Modal>
  );
};

export default InspectionModal;
