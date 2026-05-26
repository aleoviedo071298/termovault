import { AlertCircle, LogOut, RefreshCw, Search, ThermometerSun, Plus } from "lucide-react";
import React, { useEffect, useMemo, useState } from "react";
import { listElementos } from "./api/elementos";
import { ProtectedRoute } from "./auth/ProtectedRoute";
import { useAuth } from "./auth/useAuth";
import { Login } from "./pages/Login";
import type { Elemento } from "./types/elemento";

// Import components
import { ElementModal } from "./components/ElementModal";
import { ElementDetailPanel } from "./components/ElementDetailPanel";

function Dashboard() {
  const { logout, user } = useAuth();
  const [elementos, setElementos] = useState<Elemento[]>([]);
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filter tab state
  const [activeTab, setActiveTab] = useState<"todas" | "subestacion" | "estacion_transformadora" | "banco_capacitores" | "reconectador" | "seccionador_132" | "seccionador_33" | "otros">("todas");

  // Modal and detail panel states
  const [modalOpen, setModalOpen] = useState(false);
  const [editElementId, setEditElementId] = useState<number | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [selectedElementId, setSelectedElementId] = useState<number | null>(null);

  const userGroups = user?.groups ?? [];
  const canWrite = userGroups.includes("admin") || userGroups.includes("supervisor");

  async function loadElementos() {
    try {
      setLoading(true);
      setError(null);
      setElementos(await listElementos());
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo cargar la lista.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadElementos();
  }, []);

  const filtered = useMemo(() => {
    let result = elementos;

    // Filter by type tab
    if (activeTab === "subestacion") {
      result = result.filter((e) => e.tipo === "Subestación");
    } else if (activeTab === "estacion_transformadora") {
      result = result.filter((e) => e.tipo === "Estación Transformadora");
    } else if (activeTab === "banco_capacitores") {
      result = result.filter((e) => e.tipo === "Banco de Capacitores");
    } else if (activeTab === "reconectador") {
      result = result.filter((e) => e.tipo === "Reconectador");
    } else if (activeTab === "seccionador_132") {
      result = result.filter((e) => e.tipo === "Seccionador" && (e.tension === "13,2 kV" || e.nivel_tension_id === 2));
    } else if (activeTab === "seccionador_33") {
      result = result.filter((e) => e.tipo === "Seccionador" && (e.tension === "33 kV" || e.nivel_tension_id === 3));
    } else if (activeTab === "otros") {
      result = result.filter(
        (e) =>
          e.tipo !== "Subestación" &&
          e.tipo !== "Estación Transformadora" &&
          e.tipo !== "Banco de Capacitores" &&
          e.tipo !== "Reconectador" &&
          e.tipo !== "Seccionador"
      );
    }

    // Filter by search query
    const value = query.trim().toLowerCase();
    if (!value) {
      return result;
    }

    return result.filter((elemento) =>
      [elemento.nombre, elemento.tipo, elemento.funcion, elemento.codigo]
        .filter(Boolean)
        .some((field) => field!.toLowerCase().includes(value))
    );
  }, [elementos, activeTab, query]);

  function handleRowClick(id: number) {
    setSelectedElementId(id);
    setDetailOpen(true);
  }

  return (
    <main className="app-shell">
      <section className="toolbar" aria-label="Resumen de elementos">
        <div className="brand-mark" aria-hidden="true">
          <ThermometerSun size={28} strokeWidth={1.8} />
        </div>
        <div className="title-stack">
          <p>TermoVault</p>
          <h1>Elementos inspeccionables</h1>
        </div>
        <div className="metric">
          <span>{loading ? "--" : filtered.length}</span>
          <small>elementos</small>
        </div>
        <div className="search-box">
          <Search size={18} aria-hidden="true" />
          <input
            aria-label="Buscar elementos"
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Buscar por nombre, codigo o funcion"
            value={query}
          />
        </div>
        <div style={{ display: "flex", gap: "8px" }}>
          {canWrite && (
            <button
              className="add-element-btn"
              onClick={() => {
                setEditElementId(null);
                setModalOpen(true);
              }}
              title="Agregar elemento"
              type="button"
            >
              <Plus size={18} aria-hidden="true" />
              <span>Agregar</span>
            </button>
          )}
          <button
            className="icon-button"
            disabled={loading}
            onClick={() => void loadElementos()}
            title="Recargar elementos"
            type="button"
          >
            <RefreshCw size={18} aria-hidden="true" />
          </button>
          <button
            className="icon-button"
            onClick={logout}
            title="Cerrar sesión"
            type="button"
          >
            <LogOut size={18} aria-hidden="true" />
          </button>
        </div>
      </section>

      {/* Tabs list filtering */}
      <div className="tabs-container" style={{ display: "flex", flexWrap: "wrap", gap: "4px" }}>
        <button
          className={`tab-btn ${activeTab === "todas" ? "active" : ""}`}
          onClick={() => setActiveTab("todas")}
          type="button"
        >
          Todas
        </button>
        <button
          className={`tab-btn ${activeTab === "subestacion" ? "active" : ""}`}
          onClick={() => setActiveTab("subestacion")}
          type="button"
        >
          Subestaciones
        </button>
        <button
          className={`tab-btn ${activeTab === "estacion_transformadora" ? "active" : ""}`}
          onClick={() => setActiveTab("estacion_transformadora")}
          type="button"
        >
          ETRs
        </button>
        <button
          className={`tab-btn ${activeTab === "banco_capacitores" ? "active" : ""}`}
          onClick={() => setActiveTab("banco_capacitores")}
          type="button"
        >
          Bancos de Capacitores
        </button>
        <button
          className={`tab-btn ${activeTab === "reconectador" ? "active" : ""}`}
          onClick={() => setActiveTab("reconectador")}
          type="button"
        >
          Reconectadores
        </button>
        <button
          className={`tab-btn ${activeTab === "seccionador_132" ? "active" : ""}`}
          onClick={() => setActiveTab("seccionador_132")}
          type="button"
        >
          Seccionadores 13.2 kV
        </button>
        <button
          className={`tab-btn ${activeTab === "seccionador_33" ? "active" : ""}`}
          onClick={() => setActiveTab("seccionador_33")}
          type="button"
        >
          Seccionadores 33 kV
        </button>
        <button
          className={`tab-btn ${activeTab === "otros" ? "active" : ""}`}
          onClick={() => setActiveTab("otros")}
          type="button"
        >
          Otros
        </button>
      </div>

      {error ? (
        <section className="notice" role="alert">
          <AlertCircle size={20} aria-hidden="true" />
          <span>{error}</span>
        </section>
      ) : null}

      <section className="table-frame" aria-label="Listado de elementos">
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Tipo</th>
              <th>Funcion</th>
              <th>Criticidad</th>
              <th>Yacimiento</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan={5} className="empty-cell">
                  Cargando elementos...
                </td>
              </tr>
            ) : filtered.length ? (
              filtered.map((elemento) => (
                <tr
                  key={elemento.id}
                  onClick={() => handleRowClick(elemento.id)}
                  className="cursor-pointer"
                >
                  <td>
                    <strong>{elemento.nombre}</strong>
                    <span>{elemento.codigo}</span>
                  </td>
                  <td>{elemento.tipo ?? "-"}</td>
                  <td>{elemento.funcion ?? "-"}</td>
                  <td>
                    <span
                      className="badge"
                      style={{
                        "--badge-color": elemento.criticidad_color ?? "#718096"
                      } as React.CSSProperties}
                    >
                      {elemento.criticidad ?? "Sin asignar"}
                    </span>
                  </td>
                  <td>{elemento.yacimiento ?? "-"}</td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={5} className="empty-cell">
                  No hay elementos para ese filtro.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </section>

      {/* Slide-over details panel */}
      <ElementDetailPanel
        isOpen={detailOpen}
        onClose={() => setDetailOpen(false)}
        elementId={selectedElementId}
        onEditClick={(id) => {
          setEditElementId(id);
          setModalOpen(true);
        }}
        userGroups={userGroups}
        onDeleteSuccess={() => void loadElementos()}
      />

      {/* Modal for Create/Edit */}
      <ElementModal
        isOpen={modalOpen}
        onClose={() => {
          setModalOpen(false);
          setEditElementId(null);
        }}
        elementId={editElementId}
        onSuccess={() => {
          void loadElementos();
          // Force refresh of details panel if it's editing the currently opened item
          if (editElementId === selectedElementId) {
            const currentId = selectedElementId;
            setSelectedElementId(null);
            setTimeout(() => setSelectedElementId(currentId), 50);
          }
        }}
      />
    </main>
  );
}

function App() {
  const { user } = useAuth();

  if (!user) {
    return <Login />;
  }

  return (
    <ProtectedRoute allowedGroups={["admin", "supervisor", "tecnico"]} fallback={<Login />}>
      <Dashboard />
    </ProtectedRoute>
  );
}

export default App;
