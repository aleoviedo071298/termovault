import { Fragment } from "react";
import { Check } from "lucide-react";

export interface Step {
  id: string;
  label: string;
  caption?: string;
}

interface Props {
  steps: Step[];
  current: number; // index of active step (0-based)
  done?: number[]; // explicit list of completed steps (by index)
}

/**
 * Stepper horizontal usado en wizards (modales con varios pasos).
 * Solo presentación — no controla la navegación, eso lo hace el modal.
 */
export function Stepper({ steps, current, done }: Props) {
  const isDone = (idx: number) =>
    done ? done.includes(idx) : idx < current;

  return (
    <div className="tv-stepper" role="list">
      {steps.map((step, idx) => {
        const active = idx === current;
        const completed = isDone(idx);
        const cls = `tv-stepper__step${active ? " is-active" : ""}${completed ? " is-done" : ""}`;
        return (
          <Fragment key={step.id}>
            <div className={cls} role="listitem" aria-current={active ? "step" : undefined}>
              <span className="tv-stepper__circle">
                {completed ? <Check size={14} strokeWidth={2.5} /> : idx + 1}
              </span>
              <span className="tv-stepper__label">
                {step.caption && <span className="tv-stepper__caption">{step.caption}</span>}
                <span className="tv-stepper__name">{step.label}</span>
              </span>
            </div>
            {idx < steps.length - 1 && <span className="tv-stepper__line" />}
          </Fragment>
        );
      })}
    </div>
  );
}
