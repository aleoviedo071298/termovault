import { Search, SlidersHorizontal, X } from "lucide-react";
import { Field, Input, Select } from "../ui/Field";
import { Button } from "../ui/Button";
import type { FilterBarProps } from "./types";

export function FilterBar({
  filters,
  onChange,
  onClear,
  filteredCount,
  totalCount,
}: FilterBarProps) {
  return (
    <section className="tv-filterbar">
      <div className="tv-filterbar__head">
        <div className="tv-filterbar__title">
          <SlidersHorizontal size={15} />
          <span>Filtros</span>
        </div>
        <div className="tv-filterbar__head-right">
          <span className="tv-filterbar__count">
            {filteredCount} de {totalCount} informes
          </span>
          <Button variant="ghost" size="sm" leftIcon={<X size={13} />} onClick={onClear}>
            Limpiar
          </Button>
        </div>
      </div>

      <div className="tv-filterbar__grid">
        <Field label="Buscar">
          {(id) => (
            <div className="tv-search tv-filterbar__search">
              <span className="tv-search__icon"><Search size={16} /></span>
              <Input
                id={id}
                placeholder="Técnico, empresa, yacimiento, elemento o estado"
                value={filters.q}
                onChange={(e) => onChange({ q: e.target.value })}
              />
            </div>
          )}
        </Field>

        <Field label="Desde">
          {(id) => (
            <Input
              id={id}
              type="date"
              value={filters.fecha_desde}
              onChange={(e) => onChange({ fecha_desde: e.target.value })}
            />
          )}
        </Field>

        <Field label="Hasta">
          {(id) => (
            <Input
              id={id}
              type="date"
              value={filters.fecha_hasta}
              onChange={(e) => onChange({ fecha_hasta: e.target.value })}
            />
          )}
        </Field>

        <Field label="Estado">
          {(id) => (
            <Select
              id={id}
              value={filters.estado}
              onChange={(e) => onChange({ estado: e.target.value })}
            >
              <option value="">Todos</option>
              <option value="enviada">Enviada</option>
              <option value="revisada">Revisada</option>
              <option value="cerrada">Cerrada</option>
            </Select>
          )}
        </Field>

        <Field label="Criticidad">
          {(id) => (
            <Select
              id={id}
              value={filters.criticidad}
              onChange={(e) => onChange({ criticidad: e.target.value })}
            >
              <option value="">Todas</option>
              <option value="Crítica">Crítica</option>
              <option value="Alta">Alta</option>
              <option value="Media">Media</option>
              <option value="Baja">Baja</option>
              <option value="Normal">Normal</option>
            </Select>
          )}
        </Field>
      </div>
    </section>
  );
}
