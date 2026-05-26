import React, { useEffect, useState } from "react";
import { X, AlertCircle, Save, Loader2 } from "lucide-react";
import {
  getCatalogos,
  getElemento,
  createElemento,
  updateElemento,
  type Catalogos,
  type ElementoDetail
} from "../api/elementos";

interface ElementModalProps {
  isOpen: boolean;
  onClose: () => void;
  elementId?: number | null;
  onSuccess: () => void;
}

export const ElementModal: React.FC<ElementModalProps> = ({
  isOpen,
  onClose,
  elementId,
  onSuccess
}) => {
  const [catalogos, setCatalogos] = useState<Catalogos | null>(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form states
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

  // Determine if selected element type requires tension
  const requiresTension = React.useMemo(() => {
    if (!catalogos || !tipoElementoId) return false;
    const selectedType = catalogos.tipos_elemento.find(t => t.id === tipoElementoId);
    return selectedType?.requiere_tension ?? false;
  }, [catalogos, tipoElementoId]);

  // Load catalog options and element data if editing
  useEffect(() => {
    if (!isOpen) return;

    async function loadData() {
      try {
        setLoading(true);
        setError(null);
        const cats = await getCatalogos();
        setCatalogos(cats);

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
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : "Error al cargar datos del formulario");
      } finally {
        setLoading(false);
      }
    }

    void loadData();
  }, [isOpen, elementId]);

  // Reset tension field if the selected element type no longer requires it
  useEffect(() => {
    if (!requiresTension) {
      setNivelTensionId("");
    }
  }, [requiresTension]);

  if (!isOpen) return null;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!nombre.trim() || !codigo.trim() || !yacimientoId || !tipoElementoId) {
      setError("Por favor completa los campos obligatorios (*)");
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

  return (
    <div className="modal-backdrop">
      <div className="modal-content">
        <header className="modal-header">
          <h2>{elementId ? "Editar Elemento" : "Nuevo Elemento"}</h2>
          <button className="close-btn" onClick={onClose} type="button" aria-label="Cerrar modal">
            <X size={20} />
          </button>
        </header>

        {loading ? (
          <div className="modal-loading">
            <Loader2 className="animate-spin" size={32} />
            <p>Cargando información...</p>
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
              {/* Obligatorios */}
              <div className="form-group col-span-2">
                <label htmlFor="form-nombre">Nombre *</label>
                <input
                  id="form-nombre"
                  type="text"
                  value={nombre}
                  onChange={(e) => setNombre(e.target.value)}
                  placeholder="Ej: Banco Capacitores PIAS ZR2"
                  required
                />
              </div>

              <div className="form-group">
                <label htmlFor="form-codigo">Código único *</label>
                <input
                  id="form-codigo"
                  type="text"
                  value={codigo}
                  onChange={(e) => setCodigo(e.target.value)}
                  placeholder="Ej: BCAP-PIAS-ZR2"
                  required
                />
              </div>

              <div className="form-group">
                <label htmlFor="form-yacimiento">Yacimiento *</label>
                <select
                  id="form-yacimiento"
                  value={yacimientoId}
                  onChange={(e) => setYacimientoId(e.target.value ? Number(e.target.value) : "")}
                  required
                >
                  <option value="">Seleccione yacimiento</option>
                  {catalogos?.yacimientos.map(y => (
                    <option key={y.id} value={y.id}>{y.nombre} ({y.codigo})</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="form-tipo">Tipo de Elemento *</label>
                <select
                  id="form-tipo"
                  value={tipoElementoId}
                  onChange={(e) => setTipoElementoId(e.target.value ? Number(e.target.value) : "")}
                  required
                >
                  <option value="">Seleccione tipo</option>
                  {catalogos?.tipos_elemento.map(t => (
                    <option key={t.id} value={t.id}>{t.nombre}</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="form-funcion">Función (Opcional)</label>
                <input
                  id="form-funcion"
                  type="text"
                  value={funcion}
                  onChange={(e) => setFuncion(e.target.value)}
                  placeholder="Ej: PIAS, SET, ETR, etc."
                />
              </div>

              {/* Nivel Tensión condicional */}
              <div className="form-group">
                <label htmlFor="form-tension">
                  Nivel de Tensión {requiresTension && "*"}
                </label>
                <select
                  id="form-tension"
                  value={nivelTensionId}
                  onChange={(e) => setNivelTensionId(e.target.value ? Number(e.target.value) : "")}
                  disabled={!requiresTension}
                  required={requiresTension}
                >
                  <option value="">
                    {requiresTension ? "Seleccione tensión" : "No requiere tensión"}
                  </option>
                  {catalogos?.niveles_tension.map(t => (
                    <option key={t.id} value={t.id}>{t.etiqueta} ({t.kv} kV)</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="form-criticidad">Criticidad</label>
                <select
                  id="form-criticidad"
                  value={criticidadId}
                  onChange={(e) => setCriticidadId(e.target.value ? Number(e.target.value) : "")}
                >
                  <option value="">Sin asignar</option>
                  {catalogos?.criticidades.map(c => (
                    <option key={c.id} value={c.id}>{c.nombre}</option>
                  ))}
                </select>
              </div>

              {/* Datos Técnicos */}
              <div className="form-group">
                <label htmlFor="form-marca">Marca</label>
                <input
                  id="form-marca"
                  type="text"
                  value={marca}
                  onChange={(e) => setMarca(e.target.value)}
                  placeholder="Ej: ABB, Siemens"
                />
              </div>

              <div className="form-group">
                <label htmlFor="form-modelo">Modelo</label>
                <input
                  id="form-modelo"
                  type="text"
                  value={modelo}
                  onChange={(e) => setModelo(e.target.value)}
                  placeholder="Ej: Resibloc"
                />
              </div>

              <div className="form-group">
                <label htmlFor="form-nserie">Número de Serie</label>
                <input
                  id="form-nserie"
                  type="text"
                  value={nSerie}
                  onChange={(e) => setNSerie(e.target.value)}
                  placeholder="Ej: SN-938102"
                />
              </div>

              <div className="form-group col-span-2">
                <label htmlFor="form-estado-operativo">Estado Operativo</label>
                <select
                  id="form-estado-operativo"
                  value={estadoOperativo}
                  onChange={(e) => setEstadoOperativo(e.target.value)}
                >
                  <option value="operativo">Operativo</option>
                  <option value="mantenimiento">En Mantenimiento</option>
                  <option value="fuera_de_servicio">Fuera de Servicio</option>
                </select>
              </div>

              <div className="form-group col-span-2">
                <label htmlFor="form-observaciones">Observaciones Generales</label>
                <textarea
                  id="form-observaciones"
                  rows={3}
                  value={observacionesGenerales}
                  onChange={(e) => setObservacionesGenerales(e.target.value)}
                  placeholder="Detalles adicionales sobre el estado, historial, etc."
                />
              </div>
            </div>

            <footer className="modal-actions">
              <button className="cancel-btn" onClick={onClose} type="button" disabled={saving}>
                Cancelar
              </button>
              <button className="submit-btn" type="submit" disabled={saving}>
                {saving ? (
                  <>
                    <Loader2 className="animate-spin" size={18} />
                    Guardando...
                  </>
                ) : (
                  <>
                    <Save size={18} />
                    Guardar Elemento
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
