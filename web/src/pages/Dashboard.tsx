import { AlertCircle, FilePlus2, Filter, LogOut, RefreshCw, Search, Settings, Shield, UserCog, Wrench } from "lucide-react";
import React, { useEffect, useMemo, useState } from "react";
import { getDashboardOverview, type DashboardOverview } from "../api/dashboard";
import { useAuth } from "../auth/useAuth";
import { InspectionDetailModal } from "../components/InspectionDetailModal";
import { InspectionModal } from "../components/InspectionModal";

const CRITICIDAD_COLOR: Record<string, string> = {
  Normal: "#4a7a5e",
  Baja: "#22c55e",
  Media: "#eab308",
  Alta: "#f97316",
  "Crítica": "#ef4444"
};

interface Props {
  onOpenElementosGestion: () => void;
  onOpenAdminUsuarios: () => void;
}

export default function Dashboard({ onOpenElementosGestion, onOpenAdminUsuarios }: Props) {
  const { user, logout } = useAuth();
  const groups = user?.groups ?? [];
  const isAdmin = groups.includes("admin");
  const isSupervisor = groups.includes("supervisor");
  const isTecnico = groups.includes("tecnico") && !isAdmin && !isSupervisor;

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<DashboardOverview | null>(null);
  const [filters, setFilters] = useState({ estado: "", q: "", fecha_desde: "", fecha_hasta: "" });

  const [inspectionModalOpen, setInspectionModalOpen] = useState(false);
  const [inspectionElementId, setInspectionElementId] = useState<number | null>(null);
  const [inspectionDetailOpen, setInspectionDetailOpen] = useState(false);
  const [inspectionDetailId, setInspectionDetailId] = useState<number | null>(null);

  const isPaeSupervisor = Boolean(data?.scope.is_pae_supervisor);
  const canManageElements = isAdmin || isPaeSupervisor;
  const canReviewReports = isAdmin || isPaeSupervisor;

  async function loadDashboard() {
    try {
      setLoading(true);
      setError(null);
      const params: Record<string, string> = {};
      if (filters.estado) params.estado = filters.estado;
      if (filters.fecha_desde) params.fecha_desde = filters.fecha_desde;
      if (filters.fecha_hasta) params.fecha_hasta = filters.fecha_hasta;
      setData(await getDashboardOverview(params));
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo cargar el dashboard.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadDashboard();
  }, [filters.estado, filters.fecha_desde, filters.fecha_hasta]);

  const roleTitle = isAdmin ? "Administrador" : isSupervisor ? (isPaeSupervisor ? "Supervisor PAE" : "Supervisor Contratista") : "Tecnico";
  const roleIcon = isAdmin ? <Shield size={22} /> : isSupervisor ? <UserCog size={22} /> : <Wrench size={22} />;

  const cards = useMemo(() => {
    if (!data) return [];
    if (isTecnico) {
      return [
        ["Total enviados", data.stats.total_informes],
        ["Enviados este mes", data.stats.informes_mes],
        ["Ultimo informe", data.stats.ultimo_informe ? new Date(data.stats.ultimo_informe).toLocaleDateString("es-AR") : "-"],
        ["Con adjuntos", data.stats.informes_con_archivos]
      ];
    }
    if (isSupervisor && !isPaeSupervisor) {
      return [
        ["Informes recibidos", data.stats.total_informes],
        ["Informes del mes", data.stats.informes_mes_global],
        ["Tecnicos activos", data.stats.tecnicos_activos],
        ["Elementos termografiados", data.stats.elementos_termografiados]
      ];
    }
    if (isSupervisor && isPaeSupervisor) {
      return [
        ["Informes recibidos", data.stats.total_informes],
        ["Informes del mes", data.stats.informes_mes_global],
        ["Contratistas activas", data.stats.contratistas_activas],
        ["Elementos termografiados", data.stats.elementos_termografiados],
        ["Criticos detectados", data.stats.fallas_criticas]
      ];
    }
    return [
      ["Total informes", data.stats.total_informes],
      ["Informes del mes", data.stats.informes_mes_global],
      ["Tecnicos activos", data.stats.tecnicos_activos],
      ["Empresas", data.stats.empresas],
      ["Yacimientos", data.stats.yacimientos]
    ];
  }, [data, isPaeSupervisor, isSupervisor, isTecnico]);

  const filteredReports = useMemo(() => {
    if (!data) return [];
    const q = filters.q.trim().toLowerCase();
    if (!q) return data.reports;
    return data.reports.filter((r) =>
      [r.elemento, r.tecnico, r.empresa, r.yacimiento, r.estado].some((value) => value.toLowerCase().includes(q))
    );
  }, [data, filters.q]);

  return (
    <main className="app-shell">
      <section className="toolbar">
        <div className="brand-mark">{roleIcon}</div>
        <div className="title-stack">
          <p>TermoVault</p>
          <h1>Inicio {roleTitle}</h1>
        </div>
        <div className="metric">
          <span>{loading || !data ? "--" : data.reports.length}</span>
          <small>ultimos informes</small>
        </div>
        <div className="toolbar-actions">
          <button className="add-element-btn" type="button" onClick={() => { setInspectionElementId(null); setInspectionModalOpen(true); }}>
            <FilePlus2 size={16} />
            <span>Registrar nueva termografia</span>
          </button>
          {canManageElements ? (
            <button className="add-element-btn secondary-action" type="button" title="Gestion de elementos" onClick={onOpenElementosGestion}>
              <Settings size={16} />
              <span>Gestionar elementos</span>
            </button>
          ) : null}
          {isAdmin ? (
            <button className="add-element-btn secondary-action" type="button" title="Gestion de usuarios" onClick={onOpenAdminUsuarios}>
              <span>Gestionar usuarios</span>
            </button>
          ) : null}
          <div className="toolbar-icon-group">
            <button className="icon-button" type="button" title="Recargar" onClick={() => void loadDashboard()}><RefreshCw size={18} /></button>
            <button className="icon-button" type="button" title="Salir" onClick={logout}><LogOut size={18} /></button>
          </div>
        </div>
      </section>

      {error ? <section className="notice"><AlertCircle size={18} /><span>{error}</span></section> : null}

      <section style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(180px,1fr))", gap: 12, marginTop: 16 }}>
        {cards.map(([label, value]) => (
          <article key={String(label)} className="metric" style={{ textAlign: "left" }}>
            <small>{label}</small>
            <span>{value}</span>
          </article>
        ))}
      </section>

      <section className="toolbar toolbar-filters" style={{ marginTop: 16 }}>
        <div className="brand-mark"><Filter size={18} /></div>
        <div className="search-box">
          <Search size={16} />
          <input placeholder="Buscar por tecnico, empresa, yacimiento o estado..." value={filters.q} onChange={(e) => setFilters((f) => ({ ...f, q: e.target.value }))} />
        </div>
        <div className="filters-grid">
          <label className="filter-field">
            Desde
            <input type="date" value={filters.fecha_desde} onChange={(e) => setFilters((f) => ({ ...f, fecha_desde: e.target.value }))} />
          </label>
          <label className="filter-field">
            Hasta
            <input type="date" value={filters.fecha_hasta} onChange={(e) => setFilters((f) => ({ ...f, fecha_hasta: e.target.value }))} />
          </label>
          <label className="filter-field">
            Estado
            <select value={filters.estado} onChange={(e) => setFilters((f) => ({ ...f, estado: e.target.value }))}>
              <option value="">Todos</option>
              <option value="enviada">Enviada</option>
              <option value="revisada">Revisada</option>
              <option value="cerrada">Cerrada</option>
            </select>
          </label>
        </div>
      </section>

      <section className="table-frame" style={{ marginTop: 14 }}>
        <table>
          <thead>
            <tr>
              <th>Fecha</th><th>Tecnico</th><th>Empresa</th><th>Yacimiento</th><th>Subestacion/Elemento</th><th>Estado</th><th>Criticidad</th><th>Accion</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={8} className="empty-cell">Cargando informes...</td></tr>
            ) : filteredReports.length === 0 ? (
              <tr><td colSpan={8} className="empty-cell">No hay resultados.</td></tr>
            ) : filteredReports.map((r) => (
              <tr key={r.id}>
                <td>{new Date(r.fecha_inspeccion).toLocaleDateString("es-AR")}</td>
                <td>{r.tecnico}</td>
                <td>{r.empresa}</td>
                <td>{r.yacimiento}</td>
                <td>{r.elemento}</td>
                <td>{r.estado}</td>
                <td>
                  <span
                    className="badge badge-criticidad"
                    style={{ "--badge-color": CRITICIDAD_COLOR[r.criticidad] ?? "#6b7280" } as React.CSSProperties}
                  >
                    {r.criticidad}
                  </span>
                </td>
                <td><button className="icon-button" type="button" title="Ver detalle" onClick={() => { setInspectionDetailId(r.id); setInspectionDetailOpen(true); }}>Ver</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      <InspectionModal
        isOpen={inspectionModalOpen}
        onClose={() => { setInspectionModalOpen(false); setInspectionElementId(null); }}
        preSelectedElementId={inspectionElementId}
        onSuccess={() => { void loadDashboard(); setInspectionModalOpen(false); setInspectionElementId(null); }}
      />
      <InspectionDetailModal
        isOpen={inspectionDetailOpen}
        inspeccionId={inspectionDetailId}
        onClose={() => setInspectionDetailOpen(false)}
        userGroups={groups}
        canReviewOverride={canReviewReports}
        onStatusChanged={() => void loadDashboard()}
      />
    </main>
  );
}
