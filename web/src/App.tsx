import React from "react";
import { ProtectedRoute } from "./auth/ProtectedRoute";
import { useAuth } from "./auth/useAuth";
import { Login } from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import ElementosGestion from "./pages/ElementosGestion";
import AdminUsuariosPage from "./pages/AdminUsuariosPage";

function App() {
  const { user } = useAuth();
  const path = window.location.pathname;
  const groups = user?.groups ?? [];
  const isAdmin = groups.includes("admin");
  const isSupervisor = groups.includes("supervisor");

  function navigate(pathname: string) {
    window.history.pushState({}, "", pathname);
    window.dispatchEvent(new PopStateEvent("popstate"));
  }

  const [currentPath, setCurrentPath] = React.useState(path);
  React.useEffect(() => {
    const onPop = () => setCurrentPath(window.location.pathname);
    window.addEventListener("popstate", onPop);
    return () => window.removeEventListener("popstate", onPop);
  }, []);

  if (!user) {
    return <Login />;
  }

  return (
    <ProtectedRoute allowedGroups={["admin", "supervisor", "tecnico"]} fallback={<Login />}>
      {currentPath === "/elementos/gestion" ? (
        (isAdmin || isSupervisor) ? (
          <ElementosGestion onBack={() => navigate("/")} />
        ) : (
          <Dashboard onOpenElementosGestion={() => navigate("/elementos/gestion")} onOpenAdminUsuarios={() => navigate("/admin/usuarios")} />
        )
      ) : currentPath === "/admin/usuarios" ? (
        isAdmin ? (
          <AdminUsuariosPage onBack={() => navigate("/")} />
        ) : (
          <Dashboard onOpenElementosGestion={() => navigate("/elementos/gestion")} onOpenAdminUsuarios={() => navigate("/admin/usuarios")} />
        )
      ) : (
        <Dashboard onOpenElementosGestion={() => navigate("/elementos/gestion")} onOpenAdminUsuarios={() => navigate("/admin/usuarios")} />
      )}
    </ProtectedRoute>
  );
}

export default App;
