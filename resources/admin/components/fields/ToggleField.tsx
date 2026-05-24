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
  labels?: { on: string; off: string };
}

export function ToggleField({ label, helper, checked, onChange, status, error, labels }: Props) {
  const id     = useId();
  const helpId = `${id}-help`;
  const errId  = `${id}-error`;
  const onText  = labels?.on ?? 'On';
  const offText = labels?.off ?? 'Off';
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
      <label class="moonfarmer-reactions-lead-capture-toggle">
        <input
          id={id}
          type="checkbox"
          role="switch"
          checked={checked}
          aria-checked={checked ? 'true' : 'false'}
          aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
          onChange={(e) => onChange((e.target as HTMLInputElement).checked)}
        />
        <span class="moonfarmer-reactions-lead-capture-toggle__slider" aria-hidden="true" />
        <span class="moonfarmer-reactions-lead-capture-toggle__state">{checked ? onText : offText}</span>
      </label>
    </FieldShell>
  );
}
