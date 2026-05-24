import type { ComponentChildren } from 'preact';
import type { MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
  nav: ComponentChildren;
  panel: ComponentChildren;
  preview: ComponentChildren;
}

export function MoonfarmerReactionsLeadCaptureLayout({ adminData, nav, panel, preview }: Props) {
  return (
    <div class="moonfarmer-reactions-lead-capture-admin">
      <header class="moonfarmer-reactions-lead-capture-admin__header">
        <div class="moonfarmer-reactions-lead-capture-admin__brand">
          <span class="moonfarmer-reactions-lead-capture-admin__wordmark">Moonfarmer Reactions Lead Capture</span>
          <span class="moonfarmer-reactions-lead-capture-admin__version" aria-label={`Version ${adminData.version}`}>v{adminData.version}</span>
        </div>
        <p class="moonfarmer-reactions-lead-capture-admin__tagline">{adminData.i18n.pageTitle}</p>
      </header>
      <div class="moonfarmer-reactions-lead-capture-admin__nav">{nav}</div>
      <main class="moonfarmer-reactions-lead-capture-admin__layout">
        <div class="moonfarmer-reactions-lead-capture-admin__main">{panel}</div>
        <div class="moonfarmer-reactions-lead-capture-admin__preview">{preview}</div>
      </main>
    </div>
  );
}
