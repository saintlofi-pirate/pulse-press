import type { ComponentChildren } from 'preact';

interface FieldShellProps {
  label: ComponentChildren;
  helper?: ComponentChildren;
  error?: string | null;
  status?: ComponentChildren;
  htmlFor?: string;
  describedById?: string;
  errorId?: string;
  children: ComponentChildren;
}

export function FieldShell({ label, helper, error, status, htmlFor, describedById, errorId, children }: FieldShellProps) {
  return (
    <div class="moonfarmer-reactions-lead-capture-field">
      <div class="moonfarmer-reactions-lead-capture-field__head">
        {htmlFor !== undefined ? (
          <label class="moonfarmer-reactions-lead-capture-field__label" for={htmlFor}>{label}</label>
        ) : (
          <span class="moonfarmer-reactions-lead-capture-field__label">{label}</span>
        )}
        {status}
      </div>
      {children}
      {helper !== undefined && (
        <p class="moonfarmer-reactions-lead-capture-field__helper" id={describedById}>{helper}</p>
      )}
      {error && (
        <p class="moonfarmer-reactions-lead-capture-field__error" id={errorId} role="alert">{error}</p>
      )}
    </div>
  );
}
