import { useCallback, useEffect, useRef } from 'preact/hooks';

interface NavTab {
  id: string;
  label: string;
}

interface Props {
  tabs: NavTab[];
  active: string;
  onChange: (next: string) => void;
}

export function SectionNav({ tabs, active, onChange }: Props) {
  const refs = useRef<Map<string, HTMLButtonElement | null>>(new Map());

  useEffect(() => {
    const node = refs.current.get(active);
    node?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
  }, [active]);

  const focusAt = useCallback(
    (id: string) => {
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
    <div class="moonfarmer-reactions-lead-capture-tabs" role="tablist" aria-label="Settings sections" onKeyDown={handleKey}>
      {tabs.map((tab) => {
        const isActive = tab.id === active;
        return (
          <button
            key={tab.id}
            ref={(node) => { refs.current.set(tab.id, node); }}
            type="button"
            role="tab"
            id={`moonfarmer-reactions-lead-capture-tab-${tab.id}`}
            aria-selected={isActive ? 'true' : 'false'}
            aria-controls={`moonfarmer-reactions-lead-capture-panel-${tab.id}`}
            tabIndex={isActive ? 0 : -1}
            class="moonfarmer-reactions-lead-capture-tab"
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
