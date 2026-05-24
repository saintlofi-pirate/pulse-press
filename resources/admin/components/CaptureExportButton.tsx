import { useCallback, useRef, useState } from 'preact/hooks';
import { downloadCaptureCsv } from '../api';
import type { MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
}

type Status = 'idle' | 'preparing' | 'success' | 'error';

export function CaptureExportButton({ adminData }: Props) {
  const i18n = adminData.i18n.captureExport;
  const [status, setStatus] = useState<Status>('idle');
  const [error, setError] = useState<string | null>(null);
  const successTimer = useRef<number | null>(null);

  const trigger = useCallback(async () => {
    setStatus('preparing');
    setError(null);
    try {
      const { blob, filename } = await downloadCaptureCsv(adminData.restRoot, adminData.nonce);
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);

      setStatus('success');
      if (successTimer.current !== null) window.clearTimeout(successTimer.current);
      successTimer.current = window.setTimeout(() => {
        setStatus('idle');
        successTimer.current = null;
      }, 1500);
    } catch (err) {
      const message = err instanceof Error ? err.message : i18n.error;
      setError(message);
      setStatus('error');
    }
  }, [adminData.nonce, adminData.restRoot, i18n.error]);

  return (
    <section class="moonfarmer-reactions-lead-capture-export-region" aria-labelledby="moonfarmer-reactions-lead-capture-export-label">
      <div class="moonfarmer-reactions-lead-capture-export-region__head">
        <h3 id="moonfarmer-reactions-lead-capture-export-label">{i18n.label}</h3>
        <p class="moonfarmer-reactions-lead-capture-export-helper">{i18n.helper}</p>
      </div>
      <div class="moonfarmer-reactions-lead-capture-export-region__actions">
        <button
          type="button"
          class="moonfarmer-reactions-lead-capture-submit"
          onClick={() => void trigger()}
          aria-busy={status === 'preparing' ? 'true' : 'false'}
          disabled={status === 'preparing'}
        >
          {status === 'preparing' ? i18n.preparing : i18n.label}
        </button>
        {status === 'success' && (
          <span class="moonfarmer-reactions-lead-capture-export-status moonfarmer-reactions-lead-capture-pill moonfarmer-reactions-lead-capture-pill--saved" role="status" aria-live="polite">
            {i18n.downloadStarted}
          </span>
        )}
      </div>
      {status === 'error' && error !== null && (
        <p class="moonfarmer-reactions-lead-capture-error" role="alert">{error}</p>
      )}
    </section>
  );
}
