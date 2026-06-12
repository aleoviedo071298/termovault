import type { HTMLAttributes, ReactNode } from "react";

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  children: ReactNode;
}

export function Card({ children, className, ...rest }: CardProps) {
  return (
    <div className={`tv-card ${className ?? ""}`} {...rest}>
      {children}
    </div>
  );
}

export function CardHeader({
  title,
  subtitle,
  actions,
}: {
  title: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <div className="tv-card__header">
      <div className="tv-card__header-text">
        <div className="tv-card__title">{title}</div>
        {subtitle && <div className="tv-card__subtitle">{subtitle}</div>}
      </div>
      {actions && <div className="tv-card__actions">{actions}</div>}
    </div>
  );
}

export function CardBody({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={`tv-card__body ${className ?? ""}`}>{children}</div>;
}

export function CardFooter({ children }: { children: ReactNode }) {
  return <div className="tv-card__footer">{children}</div>;
}
