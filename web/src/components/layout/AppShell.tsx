import type { ReactNode } from "react";
import { Sidebar } from "./Sidebar";
import { TopBar } from "./TopBar";
import { AppScopeProvider } from "../../auth/AppScope";

/**
 * Wrapper de la app para las pantallas autenticadas.
 * Envuelve `<Routes>` con un grid de 2 columnas: Sidebar + (TopBar + main).
 *
 * Provee también el AppScope context (carga is_owner_supervisor y demás
 * scope del usuario una sola vez tras login). El Sidebar y los guards de
 * ruta usan ese context para mostrar/ocultar opciones según rol REAL.
 */
export function AppShell({ children }: { children: ReactNode }) {
  return (
    <AppScopeProvider>
      <div className="tv-shell">
        <Sidebar />
        <div className="tv-shell__main">
          <TopBar />
          <main className="tv-page">{children}</main>
        </div>
      </div>
    </AppScopeProvider>
  );
}
