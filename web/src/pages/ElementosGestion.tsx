import { ArrowLeft, Plus, RefreshCw, Search, Settings } from "lucide-react";
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
  const [items, setItems] = useState<Elemento[]>([]);
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState("");
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
      } catch {
        setIsPaeSupervisor(false);
      }
    })();
  }, []);

  const canManage = isAdmin || isPaeSupervisor;

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return items;
    return items.filter((e) => [e.nombre, e.codigo, e.tipo, e.yacimiento].filter(Boolean).some((v) => v!.toLowerCase().includes(q)));
  }, [items, query]);

  return (
    <main className="app-shell">
      <section className="toolbar">
        <div className="brand-mark"><Settings size={20} /></div>
        <div className="title-stack">
          <p>TermoVault</p>
          <h1>Gestión de Elementos</h1>
        </div>
        <div className="metric"><span>{loading ? "--" : filtered.length}</span><small>elementos</small></div>
        <div className="search-box">
          <Search size={16} />
          <input placeholder="Buscar por nombre, código, tipo, yacimiento..." value={query} onChange={(e) => setQuery(e.target.value)} />
        </div>
        <div style={{ display: "flex", gap: 8 }}>
          <button className="icon-button" type="button" onClick={onBack} title="Volver al dashboard"><ArrowLeft size={16} /></button>
          {canManage ? (
            <button className="add-element-btn" type="button" onClick={() => { setEditElementId(null); setModalOpen(true); }}><Plus size={16} /><span>Agregar elemento</span></button>
          ) : null}
          <button className="icon-button" type="button" onClick={() => void load()}><RefreshCw size={16} /></button>
        </div>
      </section>

      <section className="table-frame" style={{ marginTop: 16 }}>
        <table>
          <thead><tr><th>Nombre</th><th>Código</th><th>Tipo</th><th>Yacimiento</th><th>Criticidad</th><th>Acción</th></tr></thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={6} className="empty-cell">Cargando elementos...</td></tr>
            ) : filtered.length === 0 ? (
              <tr><td colSpan={6} className="empty-cell">No hay elementos para el filtro.</td></tr>
            ) : filtered.map((e) => (
              <tr key={e.id}>
                <td>{e.nombre}</td>
                <td>{e.codigo}</td>
                <td>{e.tipo ?? "-"}</td>
                <td>{e.yacimiento ?? "-"}</td>
                <td>{e.criticidad ?? "-"}</td>
                <td><button className="icon-button" type="button" onClick={() => { setDetailId(e.id); setDetailOpen(true); }}>Ver</button></td>
              </tr>
            ))}
          </tbody>
        </table>
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
