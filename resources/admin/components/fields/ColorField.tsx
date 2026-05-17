import type { ComponentChildren } from 'preact';
import { useEffect, useId, useState } from 'preact/hooks';
import { FieldShell } from './Field';

interface Props {
  label: ComponentChildren;
  helper?: ComponentChildren;
  value: string;
  onChange: (next: string) => void;
  status?: ComponentChildren;
  error?: string | null;
}

const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;

export function ColorField({ label, helper, value, onChange, status, error }: Props) {
  const id     = useId();
  const helpId = `${id}-help`;
  const errId  = `${id}-error`;
  const [draft, setDraft] = useState(value);

  useEffect(() => {
    setDraft(value);
  }, [value]);

  const commit = (next: string) => {
    setDraft(next);
    if (HEX_COLOR.test(next)) {
      onChange(next.toLowerCase());
    }
  };

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
      <div class="pulsepress-color-control">
        <input
          id={id}
          class="pulsepress-color-control__picker"
          type="color"
          value={HEX_COLOR.test(value) ? value : '#2563eb'}
          aria-label={typeof label === 'string' ? label : undefined}
          onInput={(e) => commit((e.target as HTMLInputElement).value)}
        />
        <input
          class="pulsepress-input pulsepress-color-control__text"
          type="text"
          value={draft}
          maxLength={7}
          inputMode="text"
          pattern="#[0-9a-fA-F]{6}"
          placeholder="#2563eb"
          aria-invalid={error ? 'true' : 'false'}
          aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
          onInput={(e) => commit((e.target as HTMLInputElement).value)}
          onBlur={() => {
            if (!HEX_COLOR.test(draft)) {
              setDraft(value);
            }
          }}
        />
        <span
          class="pulsepress-color-control__swatch"
          style={`background:${HEX_COLOR.test(value) ? value : '#2563eb'}`}
          aria-hidden="true"
        />
      </div>
    </FieldShell>
  );
}
