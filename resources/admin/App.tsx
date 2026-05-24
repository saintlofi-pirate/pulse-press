import { useCallback, useEffect, useMemo, useState } from 'preact/hooks';
import { ExtensionMount } from './components/ExtensionMount';
import { LivePreview } from './components/LivePreview';
import { MoonfarmerReactionsLeadCaptureLayout } from './components/MoonfarmerReactionsLeadCaptureLayout';
import { SectionNav } from './components/SectionNav';
import { useSettingsState } from './hooks/useSettingsState';
import { AnalyticsSection } from './sections/AnalyticsSection';
import { CaptureSection } from './sections/CaptureSection';
import { DisplaySection } from './sections/DisplaySection';
import { PrivacySection } from './sections/PrivacySection';
import { ReactionsSection } from './sections/ReactionsSection';
import type { ExtensionTab, MoonfarmerReactionsLeadCaptureAdminData } from './types';

const FREE_TAB_IDS = new Set(['display', 'analytics', 'reactions', 'capture', 'privacy']);

function pickDefaultTab(tabs: ExtensionTab[]): string {
  const raw = window.location.hash.replace(/^#/, '');
  if (raw && tabs.some((t) => t.id === raw)) return raw;
  return tabs[0]?.id ?? 'display';
}

interface Props {
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
}

export function App({ adminData }: Props) {
  const state = useSettingsState(adminData);
  const tabs: ExtensionTab[] = useMemo(() => {
    if (Array.isArray(adminData.tabs) && adminData.tabs.length > 0) return adminData.tabs;
    return [
      { id: 'display', label: adminData.i18n.tabs.display, order: 10 },
      { id: 'analytics', label: adminData.i18n.tabs.analytics, order: 20 },
      { id: 'reactions', label: adminData.i18n.tabs.reactions, order: 30 },
      { id: 'capture', label: adminData.i18n.tabs.capture, order: 40 },
      { id: 'privacy', label: adminData.i18n.tabs.privacy, order: 50 },
    ];
  }, [adminData]);

  const [activeTab, setActiveTab] = useState<string>(() => pickDefaultTab(tabs));

  useEffect(() => {
    const sync = () => setActiveTab(pickDefaultTab(tabs));
    window.addEventListener('hashchange', sync);
    return () => window.removeEventListener('hashchange', sync);
  }, [tabs]);

  useEffect(() => {
    if (!tabs.some((t) => t.id === activeTab)) {
      setActiveTab(tabs[0]?.id ?? 'display');
    }
  }, [tabs, activeTab]);

  const changeTab = useCallback((next: string) => {
    if (next === activeTab) return;
    window.location.hash = next;
    setActiveTab(next);
  }, [activeTab]);

  const renderPanel = () => {
    if (!FREE_TAB_IDS.has(activeTab)) {
      return <ExtensionMount kind="tab" id={activeTab} adminData={adminData} />;
    }
    switch (activeTab) {
      case 'analytics':
        return <AnalyticsSection adminData={adminData} />;
      case 'reactions':
        return <ReactionsSection state={state} adminData={adminData} />;
      case 'capture':
        return <CaptureSection state={state} adminData={adminData} />;
      case 'privacy':
        return <PrivacySection state={state} adminData={adminData} />;
      case 'display':
      default:
        return <DisplaySection state={state} adminData={adminData} />;
    }
  };

  return (
    <MoonfarmerReactionsLeadCaptureLayout
      adminData={adminData}
      nav={<SectionNav tabs={tabs} active={activeTab} onChange={changeTab} />}
      panel={
        <div
          id={`moonfarmer-reactions-lead-capture-panel-${activeTab}`}
          role="tabpanel"
          aria-labelledby={`moonfarmer-reactions-lead-capture-tab-${activeTab}`}
          tabIndex={0}
          class="moonfarmer-reactions-lead-capture-panel"
        >
          {renderPanel()}
        </div>
      }
      preview={<LivePreview settings={state.settings} adminData={adminData} />}
    />
  );
}
