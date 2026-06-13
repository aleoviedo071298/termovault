import { NavLink, useLocation } from "react-router-dom";
import { LayoutDashboard, Boxes, Users, LogOut, X } from "lucide-react";
import { useEffect } from "react";
import { useAuth } from "../../auth/useAuth";
import { useAppScope, canSeeInventory, canSeeUsers } from "../../auth/AppScope";

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

interface Props {
  isOpen: boolean;
  onClose: () => void;
}

export function Sidebar({ isOpen, onClose }: Props) {
  const { user, logout } = useAuth();
  const scope = useAppScope();
  const location = useLocation();
  const groups = user?.groups ?? [];
  const email = user?.email ?? "";
  const role = pickRole(groups);
  const initials = pickInitials(email);

  // Close drawer on route change
  useEffect(() => {
    onClose();
  }, [location.pathname]);

  const items: { label: string; to: string; icon: typeof LayoutDashboard; end?: boolean }[] = [];
  items.push({ label: "Dashboard", to: "/", icon: LayoutDashboard, end: true });
  if (canSeeInventory(groups, scope)) {
    items.push({ label: "Inventario", to: "/elementos/gestion", icon: Boxes });
  }
  if (canSeeUsers(groups)) {
    items.push({ label: "Usuarios", to: "/admin/usuarios", icon: Users });
  }

  return (
    <>
      {isOpen && (
        <div
          className="tv-sidebar__overlay"
          onClick={onClose}
          aria-hidden="true"
        />
      )}
      <aside
        className={`tv-sidebar${isOpen ? " is-open" : ""}`}
        aria-label="Navegación principal"
      >
        <div className="tv-sidebar__brand">
          <div className="tv-sidebar__brand-mark" aria-hidden="true">
            <img src="/logo.svg" alt="" />
          </div>
          <div className="tv-sidebar__brand-text">
            <span className="tv-sidebar__brand-name">TermoVault</span>
            <span className="tv-sidebar__brand-subtitle">
              Inspecciones termográficas
            </span>
          </div>
          <button
            type="button"
            className="tv-sidebar__close"
            onClick={onClose}
            aria-label="Cerrar menú"
          >
            <X size={18} />
          </button>
        </div>

        <nav className="tv-sidebar__nav">
          <div className="tv-sidebar__section">General</div>
          {items.map((item) => {
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
    </>
  );
}
