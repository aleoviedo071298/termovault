import { Search, SlidersHorizontal, X } from "lucide-react";
import type { FilterBarProps } from "./types";

export function FilterBar({ filters, onChange, onClear, filteredCount, totalCount }: FilterBarProps) {
  return (
    <section className="dashboard-section filter-bar">
      <div className="filter-head">
        <div className="filter-title">
          <SlidersHorizontal size={16} />
          <span>Consola de filtros</span>
        </div>
        <div className="filter-actions">
          <small>{filteredCount} de {totalCount} informes</small>
          <button type="button" className="ghost-action" onClick={onClear}>
            <X size={14} />
            Limpiar
          </button>
        </div>
      </div>

      <div className="filter-grid">
        <label className="filter-input search">
          <Search size={16} />
          <input
            placeholder="Buscar tecnico, empresa, yacimiento, elemento o estado"
            value={filters.q}
            onChange={(e) => onChange({ q: e.target.value })}
          />
        </label>

        <label className="filter-input">
          <span>Desde</span>
          <input type="date" value={filters.fecha_desde} onChange={(e) => onChange({ fecha_desde: e.target.value })} />
        </label>

        <label className="filter-input">
          <span>Hasta</span>
          <input type="date" value={filters.fecha_hasta} onChange={(e) => onChange({ fecha_hasta: e.target.value })} />
        </label>

        <label className="filter-input">
          <span>Estado</span>
          <select value={filters.estado} onChange={(e) => onChange({ estado: e.target.value })}>
            <option value="">Todos</option>
            <option value="enviada">Enviada</option>
            <option value="revisada">Revisada</option>
            <option value="cerrada">Cerrada</option>
          </select>
        </label>

        <label className="filter-input">
          <span>Criticidad</span>
          <select value={filters.criticidad} onChange={(e) => onChange({ criticidad: e.target.value })}>
            <option value="">Todas</option>
            <option value="Crítica">Critica</option>
            <option value="Alta">Alta</option>
            <option value="Media">Media</option>
            <option value="Baja">Baja</option>
            <option value="Normal">Normal</option>
          </select>
        </label>
      </div>
    </section>
  );
}
