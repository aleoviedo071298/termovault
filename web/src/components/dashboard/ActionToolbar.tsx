import { FilePlus2, LogOut, RefreshCw, Settings, UserCog } from "lucide-react";

interface ActionToolbarProps {
  onPrimaryAction: () => void;
  onManageElements: () => void;
  onManageUsers: () => void;
  onRefresh: () => void;
  onLogout: () => void;
  canManageElements: boolean;
  canManageUsers: boolean;
}

export function ActionToolbar({
  onPrimaryAction,
  onManageElements,
  onManageUsers,
  onRefresh,
  onLogout,
  canManageElements,
  canManageUsers,
}: ActionToolbarProps) {
  return (
    <div className="action-toolbar" aria-label="Acciones del dashboard">
      <button className="primary-command" type="button" onClick={onPrimaryAction}>
        <FilePlus2 size={17} />
        Registrar nueva termografia
      </button>

      <div className="secondary-command-group">
        {canManageElements ? (
          <button className="secondary-command" type="button" onClick={onManageElements}>
            <Settings size={15} />
            Elementos
          </button>
        ) : null}

        {canManageUsers ? (
          <button className="secondary-command" type="button" onClick={onManageUsers}>
            <UserCog size={15} />
            Usuarios
          </button>
        ) : null}
      </div>

      <div className="utility-command-group">
        <button className="utility-command" type="button" title="Recargar datos" onClick={onRefresh}>
          <RefreshCw size={15} />
        </button>
        <button className="utility-command" type="button" title="Cerrar sesion" onClick={onLogout}>
          <LogOut size={15} />
        </button>
      </div>
    </div>
  );
}
