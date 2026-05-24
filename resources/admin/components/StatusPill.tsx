import type { MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  status?: 'saving' | 'saved' | 'error';
  i18n: MoonfarmerReactionsLeadCaptureAdminData['i18n'];
}

export function StatusPill({ status, i18n }: Props) {
  if (!status) return null;
  if (status === 'saving') {
    return <span class="moonfarmer-reactions-lead-capture-pill moonfarmer-reactions-lead-capture-pill--saving" aria-hidden="true">{i18n.saving}</span>;
  }
  if (status === 'saved') {
    return <span class="moonfarmer-reactions-lead-capture-pill moonfarmer-reactions-lead-capture-pill--saved" role="status" aria-live="polite">{i18n.saved}</span>;
  }
  return null;
}
