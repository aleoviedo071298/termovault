import { useLocation } from "react-router-dom";
import { useAuth } from "../../auth/useAuth";

const TITLES: Record<string, string> = {
  "/": "Panel principal",
  "/elementos/gestion": "Inventario",
  "/admin/usuarios": "Usuarios",
};

function pickRole(groups: string[]): string {
  if (groups.includes("admin")) return "Admin";
  if (groups.includes("supervisor")) return "Supervisor";
  if (groups.includes("tecnico")) return "Técnico";
  return groups[0] ?? "";
}

export function TopBar() {
  const location = useLocation();
  const { user } = useAuth();
  const title = TITLES[location.pathname] ?? "TermoVault";
  const role = pickRole(user?.groups ?? []);

  return (
    <header className="tv-topbar">
      <div className="tv-topbar__left">
        <span className="tv-topbar__title">{title}</span>
      </div>
      <div className="tv-topbar__right">
        {role && (
          <span className="tv-topbar__role-pill" aria-label="Rol activo">
            {role}
          </span>
        )}
      </div>
    </header>
  );
}
