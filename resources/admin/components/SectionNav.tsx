import { useCallback, useEffect, useRef } from 'preact/hooks';
import type { TabId } from '../types';

interface NavTab {
  id: TabId;
  label: string;
}

interface Props {
  tabs: NavTab[];
  active: TabId;
  onChange: (next: TabId) => void;
}

export function SectionNav({ tabs, active, onChange }: Props) {
  const refs = useRef<Map<TabId, HTMLButtonElement | null>>(new Map());

  useEffect(() => {
    const node = refs.current.get(active);
    node?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
  }, [active]);

  const focusAt = useCallback(
    (id: TabId) => {
      onChange(id);
      window.setTimeout(() => refs.current.get(id)?.focus(), 0);
    },
    [onChange]
  );

  const handleKey = useCallback(
    (event: KeyboardEvent) => {
      const index = tabs.findIndex((t) => t.id === active);
      if (index === -1) return;
      switch (event.key) {
        case 'ArrowRight':
        case 'ArrowDown':
          event.preventDefault();
          focusAt(tabs[(index + 1) % tabs.length].id);
          break;
        case 'ArrowLeft':
        case 'ArrowUp':
          event.preventDefault();
          focusAt(tabs[(index - 1 + tabs.length) % tabs.length].id);
          break;
        case 'Home':
          event.preventDefault();
          focusAt(tabs[0].id);
          break;
        case 'End':
          event.preventDefault();
          focusAt(tabs[tabs.length - 1].id);
          break;
      }
    },
    [active, focusAt, tabs]
  );

  return (
    <div class="pulsepress-tabs" role="tablist" aria-label="Settings sections" onKeyDown={handleKey}>
      {tabs.map((tab) => {
        const isActive = tab.id === active;
        return (
          <button
            key={tab.id}
            ref={(node) => { refs.current.set(tab.id, node); }}
            type="button"
            role="tab"
            id={`pulsepress-tab-${tab.id}`}
            aria-selected={isActive ? 'true' : 'false'}
            aria-controls={`pulsepress-panel-${tab.id}`}
            tabIndex={isActive ? 0 : -1}
            class="pulsepress-tab"
            data-active={isActive ? 'true' : 'false'}
            onClick={() => onChange(tab.id)}
          >
            {tab.label}
          </button>
        );
      })}
    </div>
  );
}
