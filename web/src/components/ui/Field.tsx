import type {
  InputHTMLAttributes,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
  ReactNode,
} from "react";
import { useId } from "react";

interface FieldProps {
  label?: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  required?: boolean;
  children: (id: string) => ReactNode;
}

/**
 * Wrapper de un campo de formulario. Genera id, conecta label, hint y error.
 */
export function Field({ label, hint, error, required, children }: FieldProps) {
  const id = useId();
  return (
    <div className={`tv-field${error ? " tv-field--error" : ""}`}>
      {label && (
        <label htmlFor={id} className="tv-label">
          {label}
          {required && <span className="tv-label__req" aria-hidden="true">*</span>}
        </label>
      )}
      {children(id)}
      {error ? (
        <div className="tv-field__msg tv-field__msg--error" role="alert">{error}</div>
      ) : (
        hint && <div className="tv-field__msg">{hint}</div>
      )}
    </div>
  );
}

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  const { className, ...rest } = props;
  return <input className={`tv-input ${className ?? ""}`} {...rest} />;
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  const { className, children, ...rest } = props;
  return (
    <select className={`tv-select ${className ?? ""}`} {...rest}>
      {children}
    </select>
  );
}

export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const { className, rows, ...rest } = props;
  return <textarea className={`tv-textarea ${className ?? ""}`} rows={rows ?? 4} {...rest} />;
}

export function Checkbox({
  label,
  ...rest
}: InputHTMLAttributes<HTMLInputElement> & { label?: ReactNode }) {
  return (
    <label className="tv-check">
      <input type="checkbox" {...rest} />
      {label && <span>{label}</span>}
    </label>
  );
}
