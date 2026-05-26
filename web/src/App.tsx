import { AlertCircle, LogOut, RefreshCw, Search, ThermometerSun } from "lucide-react";
import React, { useEffect, useMemo, useState } from "react";
import { listElementos } from "./api/elementos";
import { ProtectedRoute } from "./auth/ProtectedRoute";
import { useAuth } from "./auth/useAuth";
import { Login } from "./pages/Login";
import type { Elemento } from "./types/elemento";

function Dashboard() {
  const { logout } = useAuth();
  const [elementos, setElementos] = useState<Elemento[]>([]);
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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
    const value = query.trim().toLowerCase();

    if (!value) {
      return elementos;
    }

    return elementos.filter((elemento) =>
      [elemento.nombre, elemento.tipo, elemento.funcion, elemento.codigo]
        .filter(Boolean)
        .some((field) => field!.toLowerCase().includes(value))
    );
  }, [elementos, query]);

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
          <span>{loading ? "--" : elementos.length}</span>
          <small>seeded</small>
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
                <tr key={elemento.id}>
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
