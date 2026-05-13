import type { ComponentChildren } from 'preact';
import { useId } from 'preact/hooks';
import { FieldShell } from './Field';

interface Props {
  label: ComponentChildren;
  helper?: ComponentChildren;
  checked: boolean;
  onChange: (next: boolean) => void;
  status?: ComponentChildren;
  error?: string | null;
}

export function ToggleField({ label, helper, checked, onChange, status, error }: Props) {
  const id     = useId();
  const helpId = `${id}-help`;
  const errId  = `${id}-error`;
  return (
    <FieldShell
      label={label}
      helper={helper}
      error={error}
      status={status}
      htmlFor={id}
      describedById={helpId}
      errorId={errId}
    >
      <label class="pulsepress-toggle">
        <input
          id={id}
          type="checkbox"
          role="switch"
          checked={checked}
          aria-checked={checked ? 'true' : 'false'}
          aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
          onChange={(e) => onChange((e.target as HTMLInputElement).checked)}
        />
        <span class="pulsepress-toggle__slider" aria-hidden="true" />
        <span class="pulsepress-toggle__state">{checked ? 'On' : 'Off'}</span>
      </label>
    </FieldShell>
  );
}
