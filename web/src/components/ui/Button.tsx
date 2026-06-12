import type { ButtonHTMLAttributes, ReactNode } from "react";

type Variant = "primary" | "secondary" | "ghost" | "danger";
type Size = "sm" | "md";

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  full?: boolean;
}

/**
 * Botón base del design system.
 *
 * Mantiene la API estándar de <button>: onClick, type, disabled, etc.
 * NO introduce ninguna lógica funcional — solo estilos.
 */
export function Button({
  variant = "primary",
  size = "md",
  leftIcon,
  rightIcon,
  full,
  className,
  children,
  ...rest
}: Props) {
  const classes = [
    "tv-btn",
    `tv-btn--${variant}`,
    `tv-btn--${size}`,
    full ? "tv-btn--full" : "",
    className ?? "",
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <button className={classes} {...rest}>
      {leftIcon && <span className="tv-btn__icon">{leftIcon}</span>}
      <span className="tv-btn__label">{children}</span>
      {rightIcon && <span className="tv-btn__icon">{rightIcon}</span>}
    </button>
  );
}
