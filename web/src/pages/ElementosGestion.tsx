import { ArrowUpRight, Boxes, MapPin, Gauge, Plus, RefreshCw, Search } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { getDashboardOverview } from "../api/dashboard";
import { listElementos } from "../api/elementos";
import { ElementDetailPanel } from "../components/ElementDetailPanel";
import { ElementModal } from "../components/ElementModal";
import { InspectionModal } from "../components/InspectionModal";
import { useAuth } from "../auth/useAuth";
import type { Elemento } from "../types/elemento";

import { Button } from "../components/ui/Button";
import { Badge } from "../components/ui/Badge";
import { KPICard } from "../components/ui/KPICard";
import { Field, Input, Select } from "../components/ui/Field";

interface Props {
  onBack: () => void;
}

export default function ElementosGestion({ onBack: _onBack }: Props) {
  const { user } = useAuth();
  const groups = user?.groups ?? [];
  const isAdmin = groups.includes("admin");
  const [isOwnerSupervisor, setIsOwnerSupervisor] = useState(false);
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
        setIsOwnerSupervisor(Boolean(overview.scope.is_owner_supervisor));
        const empresa = overview.scope.empresa_nombre ?? "sin empresa";
        const yacimientos = overview.scope.assigned_yacimiento_names?.length
          ? overview.scope.assigned_yacimiento_names.join(", ")
          : "según alcance";
        setScopeLabel(`${empresa} / ${yacimientos}`);
      } catch {
        setIsOwnerSupervisor(false);
      }
    })();
  }, []);

  const canManage = isAdmin || isOwnerSupervisor;

  const tipos = useMemo(() => {
    return Array.from(
      new Set(items.map((e) => e.tipo).filter((tipo): tipo is string => Boolean(tipo)))
    ).sort((a, b) => a.localeCompare(b));
  }, [items]);

  const yacimientosCount = useMemo(() => {
    return new Set(items.map((e) => e.yacimiento).filter(Boolean)).size;
  }, [items]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return items.filter((e) => {
      const matchesSearch =
        q === "" ||
        [e.nombre, e.codigo, e.tipo, e.yacimiento, e.funcion, e.tension]
          .filter(Boolean)
          .some((v) => v!.toLowerCase().includes(q));
      const matchesTipo = tipoFilter === "" || e.tipo === tipoFilter;
      return matchesSearch && matchesTipo;
    });
  }, [items, query, tipoFilter]);

  return (
    <>
      {/* ─── Header de página ─── */}
      <div className="tv-page-head">
        <div className="tv-page-head__left">
          <div className="tv-page-head__chips">
            <Badge tone="neutral" variant="outline">Inventario</Badge>
            <Badge tone="neutral" variant="outline">{scopeLabel}</Badge>
            <span style={{ fontSize: 11, color: "#6B7280", textTransform: "uppercase", letterSpacing: "0.05em" }}>
              {canManage ? "Edición habilitada" : "Solo consulta"}
            </span>
          </div>
          <h1 className="tv-page-head__title">Elementos</h1>
          <p className="tv-page-head__subtitle">
            Subestaciones, transformadores y demás equipos que se pueden inspeccionar.
          </p>
        </div>
        <div className="tv-page-head__actions">
          <Button
            variant="secondary"
            size="md"
            leftIcon={<RefreshCw size={14} strokeWidth={2} />}
            onClick={() => void load()}
            disabled={loading}
          >
            Refrescar
          </Button>
          {canManage && (
            <Button
              variant="primary"
              size="md"
              leftIcon={<Plus size={15} strokeWidth={2.2} />}
              onClick={() => {
                setEditElementId(null);
                setModalOpen(true);
              }}
            >
              Agregar elemento
            </Button>
          )}
        </div>
      </div>

      {/* ─── KPIs ─── */}
      <div className="tv-kpi-grid" style={{ gridTemplateColumns: "repeat(3, minmax(0, 1fr))" }}>
        <KPICard
          label="Elementos"
          value={loading ? "—" : filtered.length}
          caption={loading ? "Cargando…" : `${items.length} totales en el inventario`}
          icon={<Boxes size={18} strokeWidth={1.8} />}
          tone="info"
        />
        <KPICard
          label="Yacimientos"
          value={loading ? "—" : yacimientosCount}
          caption="Distintos en el alcance"
          icon={<MapPin size={18} strokeWidth={1.8} />}
          tone="success"
        />
        <KPICard
          label="Tipos"
          value={loading ? "—" : tipos.length}
          caption="Categorías presentes"
          icon={<Gauge size={18} strokeWidth={1.8} />}
          tone="neutral"
        />
      </div>

      {/* ─── Filtros ─── */}
      <section className="tv-filterbar">
        <div className="tv-filterbar__head">
          <div className="tv-filterbar__title">
            <Search size={15} />
            <span>Búsqueda de activos</span>
          </div>
          <div className="tv-filterbar__head-right">
            <span className="tv-filterbar__count">
              {filtered.length} de {items.length} elementos
            </span>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                setQuery("");
                setTipoFilter("");
              }}
            >
              Limpiar
            </Button>
          </div>
        </div>

        <div className="tv-filterbar__grid" style={{ gridTemplateColumns: "minmax(0, 3fr) minmax(0, 1fr)" }}>
          <Field label="Buscar">
            {(id) => (
              <div className="tv-search">
                <span className="tv-search__icon"><Search size={16} /></span>
                <Input
                  id={id}
                  placeholder="Nombre, código, yacimiento, función o tensión"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                />
              </div>
            )}
          </Field>

          <Field label="Tipo">
            {(id) => (
              <Select id={id} value={tipoFilter} onChange={(e) => setTipoFilter(e.target.value)}>
                <option value="">Todos los tipos</option>
                {tipos.map((tipo) => (
                  <option key={tipo} value={tipo}>{tipo}</option>
                ))}
              </Select>
            )}
          </Field>
        </div>
      </section>

      {/* ─── Tabla ─── */}
      <div className="tv-table-wrap">
        <div className="tv-table-head">
          <div>
            <div className="tv-table-head__title">Listado de elementos</div>
            <div className="tv-table-head__sub">Hacé click en uno para ver su ficha</div>
          </div>
          <span className="tv-table-head__count">{filtered.length}</span>
        </div>

        <div className="tv-table-scroll">
          <table className="tv-table">
            <thead>
              <tr>
                <th>Elemento</th>
                <th>Código</th>
                <th>Tipo</th>
                <th>Yacimiento</th>
                <th>Función</th>
                <th>Tensión</th>
                <th aria-label="Acción" />
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={7} className="tv-table__empty">Cargando elementos…</td>
                </tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan={7} className="tv-table__empty">
                    No hay elementos para los filtros aplicados.
                  </td>
                </tr>
              ) : (
                filtered.map((e) => (
                  <tr key={e.id}>
                    <td className="tv-table__main">
                      <div className="tv-table__main-title">
                        <Boxes size={15} strokeWidth={1.8} />
                        <span>{e.nombre}</span>
                      </div>
                      <div className="tv-table__main-sub">Activo #{e.id}</div>
                    </td>
                    <td>
                      <span className="tv-badge tv-badge--outline" style={{ fontFamily: "ui-monospace, SFMono-Regular, monospace" }}>
                        {e.codigo}
                      </span>
                    </td>
                    <td>{e.tipo ?? "—"}</td>
                    <td>{e.yacimiento ?? "—"}</td>
                    <td>{e.funcion ?? "—"}</td>
                    <td>{e.tension ?? <span style={{ color: "var(--tv-text-muted)" }}>No requiere</span>}</td>
                    <td style={{ textAlign: "right" }}>
                      <button
                        type="button"
                        className="tv-table__action"
                        onClick={() => {
                          setDetailId(e.id);
                          setDetailOpen(true);
                        }}
                      >
                        Ver
                        <ArrowUpRight size={14} />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* ─── Modales y drawer ─── */}
      <ElementModal
        isOpen={modalOpen}
        onClose={() => {
          setModalOpen(false);
          setEditElementId(null);
        }}
        elementId={editElementId}
        onSuccess={() => void load()}
      />
      <ElementDetailPanel
        isOpen={detailOpen}
        onClose={() => setDetailOpen(false)}
        elementId={detailId}
        userGroups={canManage ? groups : groups.filter((g) => g !== "supervisor")}
        onEditClick={(id) => {
          setEditElementId(id);
          setModalOpen(true);
        }}
        onDeleteSuccess={() => void load()}
        onNewInspectionClick={(id) => {
          setInspectionElementId(id);
          setInspectionOpen(true);
        }}
      />
      <InspectionModal
        isOpen={inspectionOpen}
        onClose={() => {
          setInspectionOpen(false);
          setInspectionElementId(null);
        }}
        preSelectedElementId={inspectionElementId}
        onSuccess={() => void load()}
      />
    </>
  );
}
