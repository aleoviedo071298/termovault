import { Navigate, Route, Routes, useNavigate } from "react-router-dom";
import { ProtectedRoute } from "./auth/ProtectedRoute";
import { useAuth } from "./auth/useAuth";
import { useAppScope, canSeeInventory } from "./auth/AppScope";
import { Login } from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import ElementosGestion from "./pages/ElementosGestion";
import AdminUsuariosPage from "./pages/AdminUsuariosPage";
import { AppShell } from "./components/layout/AppShell";

function DashboardRoute() {
  const navigate = useNavigate();

  return (
    <Dashboard
      onOpenElementosGestion={() => navigate("/elementos/gestion")}
      onOpenAdminUsuarios={() => navigate("/admin/usuarios")}
    />
  );
}

function ElementosGestionRoute() {
  const { user } = useAuth();
  const scope = useAppScope();
  const navigate = useNavigate();
  const groups = user?.groups ?? [];

  // Espera a que el scope cargue antes de decidir (fail-closed durante carga).
  if (scope.loading && !scope.loaded) {
    return (
      <div style={{ padding: 24, color: "var(--tv-text-muted)", fontSize: 13 }}>
        Cargando alcance…
      </div>
    );
  }

  // Admin: siempre. Supervisor: solo si es owner. El resto: redirigir.
  if (!canSeeInventory(groups, scope)) {
    return <Navigate to="/" replace />;
  }

  return <ElementosGestion onBack={() => navigate("/")} />;
}

function AdminUsuariosRoute() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const groups = user?.groups ?? [];

  if (!groups.includes("admin")) {
    return <Navigate to="/" replace />;
  }

  return <AdminUsuariosPage onBack={() => navigate("/")} />;
}

function App() {
  const { user } = useAuth();

  if (!user) {
    return <Login />;
  }

  return (
    <ProtectedRoute allowedGroups={["admin", "supervisor", "tecnico"]} fallback={<Login />}>
      <AppShell>
        <Routes>
          <Route path="/" element={<DashboardRoute />} />
          <Route path="/elementos/gestion" element={<ElementosGestionRoute />} />
          <Route path="/admin/usuarios" element={<AdminUsuariosRoute />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AppShell>
    </ProtectedRoute>
  );
}

export default App;
