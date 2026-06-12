import { NavLink } from "react-router-dom";
import { LayoutDashboard, Boxes, Users, LogOut } from "lucide-react";
import { useAuth } from "../../auth/useAuth";

/**
 * Sidebar de navegación principal.
 *
 * IMPORTANTE: las condiciones de visibilidad de cada item replican
 * EXACTAMENTE los guards de App.tsx (ElementosGestionRoute y
 * AdminUsuariosRoute). No introducimos lógica nueva: si un usuario sin
 * permiso consigue clickear de alguna forma, el guard de ruta sigue
 * siendo la fuente de verdad y lo redirige a `/`.
 */
type NavItem = {
  label: string;
  to: string;
  icon: typeof LayoutDashboard;
  end?: boolean;
  visible: (groups: string[]) => boolean;
};

const NAV_ITEMS: NavItem[] = [
  {
    label: "Dashboard",
    to: "/",
    icon: LayoutDashboard,
    end: true,
    visible: () => true,
  },
  {
    label: "Inventario",
    to: "/elementos/gestion",
    icon: Boxes,
    visible: (g) => g.includes("admin") || g.includes("supervisor"),
  },
  {
    label: "Usuarios",
    to: "/admin/usuarios",
    icon: Users,
    visible: (g) => g.includes("admin"),
  },
];

function pickRole(groups: string[]): string {
  if (groups.includes("admin")) return "Administrador";
  if (groups.includes("supervisor")) return "Supervisor";
  if (groups.includes("tecnico")) return "Técnico";
  return groups[0] ?? "Usuario";
}

function pickInitials(email: string): string {
  const local = email.split("@")[0] ?? "";
  const parts = local.split(/[._-]/).filter(Boolean);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return (local[0] ?? "U").toUpperCase();
}

export function Sidebar() {
  const { user, logout } = useAuth();
  const groups = user?.groups ?? [];
  const email = user?.email ?? "";
  const role = pickRole(groups);
  const initials = pickInitials(email);

  return (
    <aside className="tv-sidebar" aria-label="Navegación principal">
      <div className="tv-sidebar__brand">
        <div className="tv-sidebar__brand-mark" aria-hidden="true">
          TV
        </div>
        <div className="tv-sidebar__brand-text">
          <span className="tv-sidebar__brand-name">TermoVault</span>
          <span className="tv-sidebar__brand-subtitle">
            Inspecciones termográficas
          </span>
        </div>
      </div>

      <nav className="tv-sidebar__nav">
        <div className="tv-sidebar__section">General</div>
        {NAV_ITEMS.filter((item) => item.visible(groups)).map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `tv-sidebar__item${isActive ? " is-active" : ""}`
              }
            >
              <Icon size={18} strokeWidth={1.8} />
              <span>{item.label}</span>
            </NavLink>
          );
        })}
      </nav>

      <div className="tv-sidebar__footer">
        <div className="tv-sidebar__avatar" aria-hidden="true">
          {initials}
        </div>
        <div className="tv-sidebar__user">
          <div className="tv-sidebar__user-name" title={email}>
            {email || "Sin sesión"}
          </div>
          <div className="tv-sidebar__user-role">{role}</div>
        </div>
        <button
          type="button"
          className="tv-sidebar__signout"
          onClick={logout}
          title="Cerrar sesión"
          aria-label="Cerrar sesión"
        >
          <LogOut size={16} />
        </button>
      </div>
    </aside>
  );
}
