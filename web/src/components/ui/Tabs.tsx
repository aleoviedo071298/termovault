import type { ReactNode } from "react";

interface Tab {
  id: string;
  label: ReactNode;
  count?: number;
}

interface Props {
  tabs: Tab[];
  active: string;
  onChange: (id: string) => void;
}

export function Tabs({ tabs, active, onChange }: Props) {
  return (
    <div className="tv-tabs" role="tablist">
      {tabs.map((tab) => (
        <button
          key={tab.id}
          type="button"
          role="tab"
          aria-selected={tab.id === active}
          className={`tv-tabs__item${tab.id === active ? " is-active" : ""}`}
          onClick={() => onChange(tab.id)}
        >
          <span>{tab.label}</span>
          {tab.count !== undefined && (
            <span className="tv-tabs__count">{tab.count}</span>
          )}
        </button>
      ))}
    </div>
  );
}
