import { useEffect, useId, useRef, useState, type KeyboardEvent } from "react";

export interface SearchableOption {
  value: number | string;
  label: string;
}

interface SearchableSelectProps {
  options: SearchableOption[];
  value: number | string | "";
  onChange: (value: number | string | "") => void;
  placeholder?: string;
  id?: string;
  required?: boolean;
  disabled?: boolean;
}

export function SearchableSelect({
  options,
  value,
  onChange,
  placeholder = "Buscar…",
  id: externalId,
  required,
  disabled,
}: SearchableSelectProps) {
  const internalId = useId();
  const id = externalId ?? internalId;
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLUListElement>(null);

  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [activeIdx, setActiveIdx] = useState(-1);

  const selectedOption = options.find((o) => o.value === value);

  const filtered = query.trim() === ""
    ? options
    : options.filter((o) =>
        o.label.toLowerCase().includes(query.toLowerCase())
      );

  const visibleOptions = filtered.slice(0, 80);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
        setQuery("");
        setActiveIdx(-1);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  useEffect(() => {
    if (open && activeIdx >= 0 && listRef.current) {
      const item = listRef.current.children[activeIdx] as HTMLElement | undefined;
      item?.scrollIntoView({ block: "nearest" });
    }
  }, [activeIdx, open]);

  function handleSelect(opt: SearchableOption) {
    onChange(opt.value);
    setOpen(false);
    setQuery("");
    setActiveIdx(-1);
  }

  function handleInputFocus() {
    if (disabled) return;
    setOpen(true);
    setQuery("");
    setActiveIdx(-1);
  }

  function handleKeyDown(e: KeyboardEvent) {
    if (!open) {
      if (e.key === "ArrowDown" || e.key === "Enter") {
        e.preventDefault();
        setOpen(true);
      }
      return;
    }

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        setActiveIdx((prev) => (prev < visibleOptions.length - 1 ? prev + 1 : prev));
        break;
      case "ArrowUp":
        e.preventDefault();
        setActiveIdx((prev) => (prev > 0 ? prev - 1 : 0));
        break;
      case "Enter":
        e.preventDefault();
        if (activeIdx >= 0 && activeIdx < visibleOptions.length) {
          handleSelect(visibleOptions[activeIdx]);
        }
        break;
      case "Escape":
        e.preventDefault();
        setOpen(false);
        setQuery("");
        setActiveIdx(-1);
        break;
    }
  }

  const displayValue = open ? query : (selectedOption?.label ?? "");

  return (
    <div className="tv-searchable-select" ref={containerRef}>
      <input
        id={id}
        ref={inputRef}
        type="text"
        className="tv-input"
        value={displayValue}
        placeholder={selectedOption ? selectedOption.label : placeholder}
        onChange={(e) => {
          setQuery(e.target.value);
          setActiveIdx(-1);
          if (!open) setOpen(true);
        }}
        onFocus={handleInputFocus}
        onKeyDown={handleKeyDown}
        required={required && !value}
        disabled={disabled}
        role="combobox"
        aria-expanded={open}
        aria-autocomplete="list"
        aria-controls={`${id}-listbox`}
        aria-activedescendant={activeIdx >= 0 ? `${id}-opt-${activeIdx}` : undefined}
        autoComplete="off"
      />
      {value && !open && !disabled && (
        <button
          type="button"
          className="tv-searchable-select__clear"
          onClick={() => {
            onChange("");
            inputRef.current?.focus();
          }}
          aria-label="Limpiar selección"
          tabIndex={-1}
        >
          ×
        </button>
      )}
      {open && (
        <ul
          id={`${id}-listbox`}
          ref={listRef}
          className="tv-searchable-select__dropdown"
          role="listbox"
        >
          {visibleOptions.length === 0 ? (
            <li className="tv-searchable-select__empty">Sin resultados</li>
          ) : (
            visibleOptions.map((opt, idx) => (
              <li
                key={opt.value}
                id={`${id}-opt-${idx}`}
                role="option"
                aria-selected={opt.value === value}
                className={
                  "tv-searchable-select__option" +
                  (idx === activeIdx ? " tv-searchable-select__option--active" : "") +
                  (opt.value === value ? " tv-searchable-select__option--selected" : "")
                }
                onMouseDown={(e) => {
                  e.preventDefault();
                  handleSelect(opt);
                }}
                onMouseEnter={() => setActiveIdx(idx)}
              >
                {opt.label}
              </li>
            ))
          )}
          {filtered.length > 80 && (
            <li className="tv-searchable-select__empty">
              {filtered.length - 80} más — escribí para filtrar
            </li>
          )}
        </ul>
      )}
    </div>
  );
}
