import { ArrowLeft, ArrowUpRight, Boxes, Factory, Gauge, MapPin, Plus, RefreshCw, Search, Settings2, SlidersHorizontal, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { getDashboardOverview } from "../api/dashboard";
import { listElementos } from "../api/elementos";
import { ElementDetailPanel } from "../components/ElementDetailPanel";
import { ElementModal } from "../components/ElementModal";
import { InspectionModal } from "../components/InspectionModal";
import { useAuth } from "../auth/useAuth";
import type { Elemento } from "../types/elemento";

interface Props {
  onBack: () => void;
}

export default function ElementosGestion({ onBack }: Props) {
  const { user } = useAuth();
  const groups = user?.groups ?? [];
  const isAdmin = groups.includes("admin");
  const [isPaeSupervisor, setIsPaeSupervisor] = useState(false);
  const [scopeLabel, setScopeLabel] = useState("Alcance operativo");
  const [items, setItems] = useState<Elemento[]>([]);
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState("");
  const [tipoFilter, setTipoFilter] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [editElementId, setEditElementId] = useState<number | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [inspectionOpen, setInspectionOpen] = useState(false);
  const [inspectionElementId, setInspectionElementId] = useState<number | null>(null);

  async function load() {
    try {
      setLoading(true);
      setItems(await listElementos());
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  useEffect(() => {
    void (async () => {
      try {
        const overview = await getDashboardOverview();
        setIsPaeSupervisor(Boolean(overview.scope.is_pae_supervisor));
        const empresa = overview.scope.empresa_nombre ?? "sin empresa";
        const yacimientos = overview.scope.assigned_yacimiento_names?.length
          ? overview.scope.assigned_yacimiento_names.join(", ")
          : "segun alcance";
        setScopeLabel(`${empresa} / ${yacimientos}`);
      } catch {
        setIsPaeSupervisor(false);
      }
    })();
  }, []);

  const canManage = isAdmin || isPaeSupervisor;

  const tipos = useMemo(() => {
    return Array.from(new Set(items.map((e) => e.tipo).filter((tipo): tipo is string => Boolean(tipo))))
      .sort((a, b) => a.localeCompare(b));
  }, [items]);

  const yacimientosCount = useMemo(() => {
    return new Set(items.map((e) => e.yacimiento).filter(Boolean)).size;
  }, [items]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return items.filter((e) => {
      const matchesSearch = q === "" || [e.nombre, e.codigo, e.tipo, e.yacimiento, e.funcion, e.tension]
        .filter(Boolean)
        .some((v) => v!.toLowerCase().includes(q));
      const matchesTipo = tipoFilter === "" || e.tipo === tipoFilter;
      return matchesSearch && matchesTipo;
    });
  }, [items, query, tipoFilter]);

  return (
    <main className="app-shell element-management-shell">
      <section className="element-hero dashboard-section">
        <div className="hero-gridline" />
        <div className="element-hero-main">
          <div className="hero-kicker">
            <span className="system-dot" />
            Inventario tecnico / activos termograficos
          </div>
          <h1>Gestion de elementos</h1>
          <p>Catalogo operativo de subestaciones, transformadores y activos inspeccionables por alcance.</p>
          <div className="element-scope-row">
            <span><Factory size={14} /> {scopeLabel}</span>
            <span><Settings2 size={14} /> {canManage ? "Edicion habilitada" : "Solo consulta"}</span>
          </div>
        </div>

        <div className="element-hero-panel">
          <div className="element-metrics">
            <article>
              <Boxes size={16} />
              <span>Elementos</span>
              <strong>{loading ? "--" : filtered.length}</strong>
            </article>
            <article>
              <MapPin size={16} />
              <span>Yacimientos</span>
              <strong>{loading ? "--" : yacimientosCount}</strong>
            </article>
            <article>
              <Gauge size={16} />
              <span>Tipos</span>
              <strong>{loading ? "--" : tipos.length}</strong>
            </article>
          </div>

          <div className="element-actions">
            <button className="secondary-command" type="button" onClick={onBack}>
              <ArrowLeft size={15} />
              Dashboard
            </button>
            {canManage ? (
              <button className="primary-command" type="button" onClick={() => { setEditElementId(null); setModalOpen(true); }}>
                <Plus size={16} />
                Agregar elemento
              </button>
            ) : null}
            <button className="utility-command" type="button" onClick={() => void load()} title="Recargar elementos">
              <RefreshCw size={15} />
            </button>
          </div>
        </div>
      </section>

      <section className="dashboard-section element-filter-bar">
        <div className="filter-head">
          <div className="filter-title">
            <SlidersHorizontal size={16} />
            <span>Busqueda de activos</span>
          </div>
          <div className="filter-actions">
            <small>{filtered.length} de {items.length} elementos</small>
            <button className="ghost-action" type="button" onClick={() => { setQuery(""); setTipoFilter(""); }}>
              <X size={14} />
              Limpiar
            </button>
          </div>
        </div>

        <div className="element-filter-grid">
          <label className="filter-input search">
            <Search size={16} />
            <input
              placeholder="Buscar por nombre, codigo, yacimiento, funcion o tension"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </label>

          <label className="filter-input">
            <span>Tipo</span>
            <select value={tipoFilter} onChange={(e) => setTipoFilter(e.target.value)}>
              <option value="">Todos los tipos</option>
              {tipos.map((tipo) => (
                <option key={tipo} value={tipo}>{tipo}</option>
              ))}
            </select>
          </label>
        </div>
      </section>

      <section className="dashboard-section element-table-panel">
        <div className="section-heading report-heading">
          <div>
            <span>Inventario de elementos</span>
            <small>Tabla compacta para exploracion y apertura de ficha tecnica</small>
          </div>
          <strong>{filtered.length}</strong>
        </div>

        <div className="report-table-wrap">
          <table className="report-table element-table">
            <thead>
              <tr>
                <th>Elemento</th>
                <th>Codigo</th>
                <th>Tipo</th>
                <th>Yacimiento</th>
                <th>Funcion</th>
                <th>Tension</th>
                <th aria-label="Accion" />
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7} className="empty-cell">Cargando elementos...</td></tr>
              ) : filtered.length === 0 ? (
                <tr><td colSpan={7} className="empty-cell">No hay elementos para los filtros aplicados.</td></tr>
              ) : filtered.map((e) => (
                <tr key={e.id}>
                  <td className="main-report-cell">
                    <div className="report-title-line">
                      <Boxes size={16} />
                      <strong>{e.nombre}</strong>
                    </div>
                    <span>Activo #{e.id}</span>
                  </td>
                  <td><span className="code-chip">{e.codigo}</span></td>
                  <td>{e.tipo ?? "-"}</td>
                  <td>{e.yacimiento ?? "-"}</td>
                  <td>{e.funcion ?? "-"}</td>
                  <td>{e.tension ?? "No requiere"}</td>
                  <td className="action-cell">
                    <button className="table-action" type="button" onClick={() => { setDetailId(e.id); setDetailOpen(true); }}>
                      Ver
                      <ArrowUpRight size={14} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <ElementModal
        isOpen={modalOpen}
        onClose={() => { setModalOpen(false); setEditElementId(null); }}
        elementId={editElementId}
        onSuccess={() => void load()}
      />
      <ElementDetailPanel
        isOpen={detailOpen}
        onClose={() => setDetailOpen(false)}
        elementId={detailId}
        userGroups={canManage ? groups : groups.filter((g) => g !== "supervisor")}
        onEditClick={(id) => { setEditElementId(id); setModalOpen(true); }}
        onDeleteSuccess={() => void load()}
        onNewInspectionClick={(id) => { setInspectionElementId(id); setInspectionOpen(true); }}
      />
      <InspectionModal
        isOpen={inspectionOpen}
        onClose={() => { setInspectionOpen(false); setInspectionElementId(null); }}
        preSelectedElementId={inspectionElementId}
        onSuccess={() => void load()}
      />
    </main>
  );
}
