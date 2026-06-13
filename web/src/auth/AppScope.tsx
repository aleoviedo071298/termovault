import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { getDashboardOverview } from "../api/dashboard";
import { useAuth } from "./useAuth";

/**
 * AppScope: información de alcance del usuario autenticado que no viene en el
 * JWT/Cognito. La distinción supervisor-owner vs supervisor-contratista es
 * crítica para mostrar/ocultar opciones de UI sin filtrar información que el
 * backend NO permite ver (aunque el backend igualmente bloquea por scope).
 *
 * Se carga una sola vez después del login (al montar el AppShell), llamando a
 * `/api/dashboard/overview` que ya devuelve `scope.is_owner_supervisor`.
 *
 * Política fail-closed: mientras no esté cargado o falle, `canSeeInventory`
 * devuelve false para supervisores. Admin siempre ve todo.
 */

interface AppScopeData {
  loading: boolean;
  loaded: boolean;
  error: string | null;
  isOwnerSupervisor: boolean;
  empresaNombre: string | null;
  assignedYacimientoNames: string[];
  reload: () => Promise<void>;
}

const Ctx = createContext<AppScopeData | null>(null);

const DEFAULT_DATA: Omit<AppScopeData, "reload"> = {
  loading: true,
  loaded: false,
  error: null,
  isOwnerSupervisor: false,
  empresaNombre: null,
  assignedYacimientoNames: [],
};

export function AppScopeProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const [state, setState] = useState<Omit<AppScopeData, "reload">>(DEFAULT_DATA);

  async function load() {
    if (!user) {
      setState({ ...DEFAULT_DATA, loading: false, loaded: false });
      return;
    }
    try {
      setState((prev) => ({ ...prev, loading: true, error: null }));
      const overview = await getDashboardOverview();
      setState({
        loading: false,
        loaded: true,
        error: null,
        isOwnerSupervisor: Boolean(overview.scope.is_owner_supervisor),
        empresaNombre: overview.scope.empresa_nombre,
        assignedYacimientoNames: overview.scope.assigned_yacimiento_names ?? [],
      });
    } catch (err) {
      setState({
        loading: false,
        loaded: false,
        error: err instanceof Error ? err.message : "No se pudo cargar el alcance del usuario.",
        isOwnerSupervisor: false,
        empresaNombre: null,
        assignedYacimientoNames: [],
      });
    }
  }

  useEffect(() => {
    void load();
    // recargar si cambia el usuario (logout/login)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.email]);

  const value = useMemo<AppScopeData>(
    () => ({ ...state, reload: load }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [state]
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAppScope(): AppScopeData {
  const v = useContext(Ctx);
  if (!v) throw new Error("useAppScope must be used within AppScopeProvider");
  return v;
}

/**
 * Helpers de visibilidad. Fail-closed: si el scope aún no cargó,
 * los supervisores NO ven Inventario (la duda se resuelve hacia "no").
 */
export function canSeeInventory(groups: string[], scope: AppScopeData): boolean {
  if (groups.includes("admin")) return true;
  if (groups.includes("supervisor")) {
    return scope.loaded && scope.isOwnerSupervisor === true;
  }
  return false;
}

export function canSeeUsers(groups: string[]): boolean {
  return groups.includes("admin");
}
