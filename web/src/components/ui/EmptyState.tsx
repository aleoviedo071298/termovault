import type { ReactNode } from "react";

interface Props {
  icon?: ReactNode;
  title: string;
  description?: ReactNode;
  action?: ReactNode;
}

export function EmptyState({ icon, title, description, action }: Props) {
  return (
    <div className="tv-empty">
      {icon && <div className="tv-empty__icon" aria-hidden="true">{icon}</div>}
      <div className="tv-empty__title">{title}</div>
      {description && <div className="tv-empty__desc">{description}</div>}
      {action && <div className="tv-empty__action">{action}</div>}
    </div>
  );
}
