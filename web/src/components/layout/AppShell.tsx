import { useState } from "react";
import type { ReactNode } from "react";
import { Menu } from "lucide-react";
import { Sidebar } from "./Sidebar";
import { AppScopeProvider } from "../../auth/AppScope";

export function AppShell({ children }: { children: ReactNode }) {
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <AppScopeProvider>
      <div className="tv-shell">
        <header className="tv-mobile-header">
          <button
            type="button"
            className="tv-mobile-header__burger"
            onClick={() => setMenuOpen(true)}
            aria-label="Abrir menú"
          >
            <Menu size={22} strokeWidth={1.8} />
          </button>
          <span className="tv-mobile-header__brand">TermoVault</span>
          <span className="tv-mobile-header__spacer" />
        </header>

        <Sidebar isOpen={menuOpen} onClose={() => setMenuOpen(false)} />

        <div className="tv-shell__main">
          <main className="tv-page">{children}</main>
        </div>
      </div>
    </AppScopeProvider>
  );
}
