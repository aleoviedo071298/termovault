import React, { useEffect, useState } from "react";
import { AlertCircle, ArrowLeft, ArrowRight, Save } from "lucide-react";
import {
  getCatalogos,
  getElementoYacimientos,
  getElemento,
  createElemento,
  updateElemento,
  type Catalogos,
  type ElementoDetail,
} from "../api/elementos";
import { useAuth } from "../auth/useAuth";

import { Modal } from "./ui/Modal";
import { Button } from "./ui/Button";
import { Field, Input, Select, Textarea } from "./ui/Field";
import { Stepper, type Step } from "./ui/Stepper";

interface ElementModalProps {
  isOpen: boolean;
  onClose: () => void;
  elementId?: number | null;
  onSuccess: () => void;
}

const STEPS: Step[] = [
  { id: "id",   caption: "Paso 1", label: "Identificación" },
  { id: "tech", caption: "Paso 2", label: "Especificaciones técnicas" },
];

export const ElementModal: React.FC<ElementModalProps> = ({
  isOpen,
  onClose,
  elementId,
  onSuccess,
}) => {
  const { user } = useAuth();
  const isAdmin = (user?.groups ?? []).some((g) => g.toLowerCase() === "admin");
  const [catalogos, setCatalogos] = useState<Catalogos | null>(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form states (idénticos a la versión anterior)
  const [nombre, setNombre] = useState("");
  const [codigo, setCodigo] = useState("");
  const [yacimientoId, setYacimientoId] = useState<number | "">("");
  const [tipoElementoId, setTipoElementoId] = useState<number | "">("");
  const [funcion, setFuncion] = useState("");
  const [nivelTensionId, setNivelTensionId] = useState<number | "">("");
  const [criticidadId, setCriticidadId] = useState<number | "">("");
  const [marca, setMarca] = useState("");
  const [modelo, setModelo] = useState("");
  const [nSerie, setNSerie] = useState("");
  const [estadoOperativo, setEstadoOperativo] = useState("operativo");
  const [observacionesGenerales, setObservacionesGenerales] = useState("");

  // UI: paso del wizard
  const [currentStep, setCurrentStep] = useState(0);

  // Determine if selected element type requires tension
  const requiresTension = React.useMemo(() => {
    if (!catalogos || !tipoElementoId) return false;
    const selectedType = catalogos.tipos_elemento.find((t) => t.id === tipoElementoId);
    return selectedType?.requiere_tension ?? false;
  }, [catalogos, tipoElementoId]);

  // Load catalog options and element data if editing (idéntico)
  useEffect(() => {
    if (!isOpen) return;
    setCurrentStep(0);

    async function loadData() {
      try {
        setLoading(true);
        setError(null);
        const [cats, yacimientos] = await Promise.all([getCatalogos(), getElementoYacimientos()]);
        const formCatalogos: Catalogos = { ...cats, yacimientos };
        setCatalogos(formCatalogos);

        if (elementId) {
          const { elemento } = await getElemento(elementId);
          setNombre(elemento.nombre);
          setCodigo(elemento.codigo);
          setYacimientoId(elemento.yacimiento_id);
          setTipoElementoId(elemento.tipo_elemento_id);
          setFuncion(elemento.funcion ?? "");
          setNivelTensionId(elemento.nivel_tension_id ?? "");
          setCriticidadId(elemento.criticidad_id ?? "");
          setMarca(elemento.marca ?? "");
          setModelo(elemento.modelo ?? "");
          setNSerie(elemento.n_serie ?? "");
          setEstadoOperativo(elemento.estado_operativo ?? "operativo");
          setObservacionesGenerales(elemento.observaciones ?? "");
        } else {
          // Reset form for creation
          setNombre("");
          setCodigo("");
          setYacimientoId("");
          setTipoElementoId("");
          setFuncion("");
          setNivelTensionId("");
          setCriticidadId("");
          setMarca("");
          setModelo("");
          setNSerie("");
          setEstadoOperativo("operativo");
          setObservacionesGenerales("");
          if (!isAdmin && formCatalogos.yacimientos.length === 1) {
            setYacimientoId(formCatalogos.yacimientos[0].id);
          }
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : "Error al cargar datos del formulario");
      } finally {
        setLoading(false);
      }
    }

    void loadData();
  }, [isOpen, elementId, isAdmin]);

  // Reset tension field if the selected element type no longer requires it
  useEffect(() => {
    if (!requiresTension) {
      setNivelTensionId("");
    }
  }, [requiresTension]);

  async function handleSubmit() {
    if (!nombre.trim() || !codigo.trim() || !yacimientoId || !tipoElementoId) {
      setError("Por favor completá los campos obligatorios (*)");
      setCurrentStep(0);
      return;
    }

    try {
      setSaving(true);
      setError(null);

      const payload: Partial<ElementoDetail> = {
        nombre: nombre.trim(),
        codigo: codigo.trim(),
        yacimiento_id: Number(yacimientoId),
        tipo_elemento_id: Number(tipoElementoId),
        funcion: funcion.trim() || null,
        nivel_tension_id: requiresTension && nivelTensionId ? Number(nivelTensionId) : null,
        criticidad_id: criticidadId ? Number(criticidadId) : null,
        marca: marca.trim() || null,
        modelo: modelo.trim() || null,
        n_serie: nSerie.trim() || null,
        estado_operativo: estadoOperativo,
        observaciones_generales: observacionesGenerales.trim() || null,
      };

      if (elementId) {
        await updateElemento(elementId, payload);
      } else {
        await createElemento(payload);
      }

      onSuccess();
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al guardar el elemento");
    } finally {
      setSaving(false);
    }
  }

  function canAdvanceFrom(step: number): boolean {
    if (step === 0) {
      return Boolean(nombre.trim()) && Boolean(codigo.trim()) && Boolean(yacimientoId) && Boolean(tipoElementoId);
    }
    return true;
  }

  function goNext() {
    setError(null);
    if (currentStep === 0 && !canAdvanceFrom(0)) {
      setError("Completá yacimiento, tipo, código y nombre antes de continuar.");
      return;
    }
    if (requiresTension && !nivelTensionId && currentStep === 1) {
      setError("El tipo de elemento seleccionado requiere nivel de tensión.");
      return;
    }
    setCurrentStep((s) => Math.min(STEPS.length - 1, s + 1));
  }

  function goBack() {
    setError(null);
    setCurrentStep((s) => Math.max(0, s - 1));
  }

  const isLastStep = currentStep === STEPS.length - 1;

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
          disabled={saving || (requiresTension && !nivelTensionId)}
          leftIcon={<Save size={14} />}
        >
          {saving ? "Guardando…" : elementId ? "Guardar cambios" : "Guardar elemento"}
        </Button>
      )}
    </>
  );

  return (
    <Modal
      open={isOpen}
      onClose={onClose}
      title={elementId ? "Editar elemento" : "Nuevo elemento"}
      subtitle="Completá la información en pasos. Lo opcional se puede saltar."
      size="lg"
      footer={footer}
    >
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
          Cargando información…
        </div>
      ) : (
        <>
          {/* ─── PASO 1: Identificación ─── */}
          {currentStep === 0 && (
            <div className="tv-step">
              <div className="tv-step__head">
                <div className="tv-step__title">Identificación del elemento</div>
                <div className="tv-step__desc">
                  Indicá dónde está, qué tipo es y cómo lo identificamos en el inventario.
                </div>
              </div>

              <div className="tv-step__grid">
                <Field label="Yacimiento" required>
                  {(id) => (
                    <Select
                      id={id}
                      value={yacimientoId}
                      onChange={(e) => setYacimientoId(e.target.value ? Number(e.target.value) : "")}
                      disabled={!isAdmin && (catalogos?.yacimientos.length ?? 0) <= 1}
                      required
                    >
                      <option value="">Seleccionar yacimiento</option>
                      {catalogos?.yacimientos.map((y) => (
                        <option key={y.id} value={y.id}>
                          {y.nombre} ({y.codigo})
                        </option>
                      ))}
                    </Select>
                  )}
                </Field>

                <Field label="Tipo de elemento" required>
                  {(id) => (
                    <Select
                      id={id}
                      value={tipoElementoId}
                      onChange={(e) => setTipoElementoId(e.target.value ? Number(e.target.value) : "")}
                      required
                    >
                      <option value="">Seleccionar tipo</option>
                      {catalogos?.tipos_elemento.map((t) => (
                        <option key={t.id} value={t.id}>{t.nombre}</option>
                      ))}
                    </Select>
                  )}
                </Field>

                <Field label="Código único" required hint="Identificador interno (ej: BCAP-PIAS-ZR2).">
                  {(id) => (
                    <Input
                      id={id}
                      value={codigo}
                      onChange={(e) => setCodigo(e.target.value)}
                      placeholder="Ej: BCAP-PIAS-ZR2"
                      required
                    />
                  )}
                </Field>

                <Field label="Nombre" required>
                  {(id) => (
                    <Input
                      id={id}
                      value={nombre}
                      onChange={(e) => setNombre(e.target.value)}
                      placeholder="Ej: Banco Capacitores PIAS ZR2"
                      required
                    />
                  )}
                </Field>

                <div className="tv-field--full">
                  <Field label="Función (opcional)" hint="Rol operativo. Ej: PIAS, SET, ETR.">
                    {(id) => (
                      <Input
                        id={id}
                        value={funcion}
                        onChange={(e) => setFuncion(e.target.value)}
                        placeholder="Ej: PIAS"
                      />
                    )}
                  </Field>
                </div>
              </div>
            </div>
          )}

          {/* ─── PASO 2: Especificaciones técnicas ─── */}
          {currentStep === 1 && (
            <div className="tv-step">
              <div className="tv-step__head">
                <div className="tv-step__title">Especificaciones técnicas</div>
                <div className="tv-step__desc">
                  Datos eléctricos, fabricante y estado actual. Todo opcional excepto la tensión si el tipo la requiere.
                </div>
              </div>

              <div className="tv-step__grid">
                <Field
                  label={`Nivel de tensión${requiresTension ? "" : " (no requiere)"}`}
                  required={requiresTension}
                >
                  {(id) => (
                    <Select
                      id={id}
                      value={nivelTensionId}
                      onChange={(e) => setNivelTensionId(e.target.value ? Number(e.target.value) : "")}
                      disabled={!requiresTension}
                      required={requiresTension}
                    >
                      <option value="">
                        {requiresTension ? "Seleccionar tensión" : "El tipo no la requiere"}
                      </option>
                      {catalogos?.niveles_tension.map((t) => (
                        <option key={t.id} value={t.id}>
                          {t.etiqueta} ({t.kv} kV)
                        </option>
                      ))}
                    </Select>
                  )}
                </Field>

                <Field label="Criticidad">
                  {(id) => (
                    <Select
                      id={id}
                      value={criticidadId}
                      onChange={(e) => setCriticidadId(e.target.value ? Number(e.target.value) : "")}
                    >
                      <option value="">Sin asignar</option>
                      {catalogos?.criticidades.map((c) => (
                        <option key={c.id} value={c.id}>{c.nombre}</option>
                      ))}
                    </Select>
                  )}
                </Field>

                <Field label="Marca">
                  {(id) => (
                    <Input
                      id={id}
                      value={marca}
                      onChange={(e) => setMarca(e.target.value)}
                      placeholder="Ej: ABB, Siemens"
                    />
                  )}
                </Field>

                <Field label="Modelo">
                  {(id) => (
                    <Input
                      id={id}
                      value={modelo}
                      onChange={(e) => setModelo(e.target.value)}
                      placeholder="Ej: Resibloc"
                    />
                  )}
                </Field>

                <Field label="Número de serie">
                  {(id) => (
                    <Input
                      id={id}
                      value={nSerie}
                      onChange={(e) => setNSerie(e.target.value)}
                      placeholder="Ej: SN-938102"
                    />
                  )}
                </Field>

                <Field label="Estado operativo">
                  {(id) => (
                    <Select
                      id={id}
                      value={estadoOperativo}
                      onChange={(e) => setEstadoOperativo(e.target.value)}
                    >
                      <option value="operativo">Operativo</option>
                      <option value="mantenimiento">En mantenimiento</option>
                      <option value="fuera_de_servicio">Fuera de servicio</option>
                    </Select>
                  )}
                </Field>

                <div className="tv-field--full">
                  <Field label="Observaciones generales (opcional)">
                    {(id) => (
                      <Textarea
                        id={id}
                        rows={3}
                        value={observacionesGenerales}
                        onChange={(e) => setObservacionesGenerales(e.target.value)}
                        placeholder="Detalles adicionales sobre el estado, historial, etc."
                      />
                    )}
                  </Field>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </Modal>
  );
};

export default ElementModal;
