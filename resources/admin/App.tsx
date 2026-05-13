import { useCallback, useEffect, useState } from 'preact/hooks';
import { LivePreview } from './components/LivePreview';
import { PulsePressLayout } from './components/PulsePressLayout';
import { SectionNav } from './components/SectionNav';
import { useSettingsState } from './hooks/useSettingsState';
import { CaptureSection } from './sections/CaptureSection';
import { DisplaySection } from './sections/DisplaySection';
import { PrivacySection } from './sections/PrivacySection';
import { ReactionsSection } from './sections/ReactionsSection';
import type { PulsePressAdminData, TabId } from './types';

const TAB_IDS: TabId[] = ['display', 'reactions', 'capture', 'privacy'];

function hashToTab(): TabId {
  const raw = window.location.hash.replace(/^#/, '');
  return (TAB_IDS as string[]).includes(raw) ? (raw as TabId) : 'display';
}

interface Props {
  adminData: PulsePressAdminData;
}

export function App({ adminData }: Props) {
  const state = useSettingsState(adminData);
  const [activeTab, setActiveTab] = useState<TabId>(hashToTab);

  useEffect(() => {
    const sync = () => setActiveTab(hashToTab());
    window.addEventListener('hashchange', sync);
    return () => window.removeEventListener('hashchange', sync);
  }, []);

  const changeTab = useCallback((next: TabId) => {
    if (next === activeTab) return;
    window.location.hash = next;
    setActiveTab(next);
  }, [activeTab]);

  const tabs = TAB_IDS.map((id) => ({ id, label: adminData.i18n.tabs[id] }));

  const renderPanel = () => {
    switch (activeTab) {
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
    <PulsePressLayout
      adminData={adminData}
      nav={<SectionNav tabs={tabs} active={activeTab} onChange={changeTab} />}
      panel={
        <div
          id={`pulsepress-panel-${activeTab}`}
          role="tabpanel"
          aria-labelledby={`pulsepress-tab-${activeTab}`}
          tabIndex={0}
          class="pulsepress-panel"
        >
          {renderPanel()}
        </div>
      }
      preview={<LivePreview settings={state.settings} adminData={adminData} />}
    />
  );
}
