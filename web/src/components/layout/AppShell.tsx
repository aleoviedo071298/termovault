import type { ReactNode } from "react";
import { Sidebar } from "./Sidebar";
import { TopBar } from "./TopBar";

/**
 * Wrapper de la app para las pantallas autenticadas.
 * Envuelve `<Routes>` con un grid de 2 columnas: Sidebar + (TopBar + main).
 *
 * No introduce navegación nueva: cada NavLink del Sidebar apunta a una ruta
 * que YA está definida en App.tsx con sus propios guards.
 */
export function AppShell({ children }: { children: ReactNode }) {
  return (
    <div className="tv-shell">
      <Sidebar />
      <div className="tv-shell__main">
        <TopBar />
        <main className="tv-page">{children}</main>
      </div>
    </div>
  );
}
