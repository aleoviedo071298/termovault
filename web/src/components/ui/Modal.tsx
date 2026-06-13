import { useEffect, type ReactNode } from "react";
import { X } from "lucide-react";

interface Props {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  subtitle?: ReactNode;
  size?: "sm" | "md" | "lg" | "xl";
  footer?: ReactNode;
  children: ReactNode;
}

/**
 * Modal base. Maneja overlay + escape + cierre.
 * Las páginas existentes que ya tienen sus propios modales (InspectionModal,
 * ElementModal, etc.) pueden migrar a este componente sin tocar su lógica:
 * solo cambian el JSX del contenedor.
 */
export function Modal({
  open,
  onClose,
  title,
  subtitle,
  size = "md",
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
    <div className="tv-modal__overlay" onClick={onClose} role="presentation">
      <div
        className={`tv-modal tv-modal--${size}`}
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
      >
        <header className="tv-modal__header">
          <div className="tv-modal__header-text">
            <div className="tv-modal__title">{title}</div>
            {subtitle && <div className="tv-modal__subtitle">{subtitle}</div>}
          </div>
          <button
            type="button"
            className="tv-modal__close"
            onClick={onClose}
            aria-label="Cerrar"
          >
            <X size={18} />
          </button>
        </header>

        <div className="tv-modal__body">{children}</div>

        {footer && <footer className="tv-modal__footer">{footer}</footer>}
      </div>
    </div>
  );
}
