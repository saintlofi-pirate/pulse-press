import { useEffect, useMemo, useRef } from 'preact/hooks';
import { getRegistry, useExtensionTick } from '../extensions/registry';
import type { ExtensionKind, ExtensionRenderer, PulsePressAdminData } from '../types';

interface Props {
  kind: ExtensionKind;
  id: string;
  adminData: PulsePressAdminData;
  data?: unknown;
  fallback?: string;
  ariaLabel?: string;
}

export function ExtensionMount({ kind, id, adminData, data, fallback, ariaLabel }: Props) {
  const tick = useExtensionTick();
  const renderer: ExtensionRenderer | undefined = useMemo(
    () => getRegistry().getRenderer(kind, id),
    [kind, id, tick],
  );

  const rootRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!renderer || !rootRef.current) return;
    const node = rootRef.current;
    let cleanup: void | (() => void);
    try {
      cleanup = renderer(node, { id, kind, data, adminData });
    } catch (err) {
      console.error(`[PulsePress] extension renderer for ${kind}:${id} threw on mount`, err);
      node.innerHTML = '';
      return undefined;
    }
    return () => {
      try {
        if (typeof cleanup === 'function') cleanup();
      } catch (err) {
        console.error(`[PulsePress] extension cleanup for ${kind}:${id} threw on unmount`, err);
      }
      if (node) node.innerHTML = '';
    };
  }, [renderer, id, kind, data, adminData]);

  if (!renderer) {
    const message = fallback ?? adminData.i18n.extension.fallback;
    return (
      <div
        class="pulsepress-extension-fallback"
        role="status"
        aria-live="polite"
        data-extension-id={id}
        data-extension-kind={kind}
      >
        {message}
      </div>
    );
  }

  return (
    <div
      ref={rootRef}
      class="pulsepress-extension-mount"
      role="region"
      aria-label={ariaLabel ?? adminData.i18n.extension.sectionLabel}
      data-extension-id={id}
      data-extension-kind={kind}
    />
  );
}
