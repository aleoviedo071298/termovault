import { useEffect, type ReactNode } from "react";
import { X } from "lucide-react";

interface Props {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  subtitle?: ReactNode;
  width?: number;
  footer?: ReactNode;
  children: ReactNode;
}

/**
 * Drawer lateral derecho. Usado para fichas técnicas / detalles.
 */
export function Drawer({
  open,
  onClose,
  title,
  subtitle,
  width = 480,
  footer,
  children,
}: Props) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = "";
      document.removeEventListener("keydown", onKey);
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="tv-drawer__overlay" onClick={onClose} role="presentation">
      <aside
        className="tv-drawer"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        style={{ width }}
      >
        <header className="tv-drawer__header">
          <div className="tv-drawer__header-text">
            <div className="tv-drawer__title">{title}</div>
            {subtitle && <div className="tv-drawer__subtitle">{subtitle}</div>}
          </div>
          <button
            type="button"
            className="tv-drawer__close"
            onClick={onClose}
            aria-label="Cerrar"
          >
            <X size={18} />
          </button>
        </header>

        <div className="tv-drawer__body">{children}</div>

        {footer && <footer className="tv-drawer__footer">{footer}</footer>}
      </aside>
    </div>
  );
}
