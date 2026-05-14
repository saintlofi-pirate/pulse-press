import { useId } from 'preact/hooks';

interface Props {
  title: string;
  value: string;
  helper?: string;
  emphasis?: boolean;
}

export function MetricCard({ title, value, helper, emphasis = false }: Props) {
  const titleId = useId();
  return (
    <section class="pulsepress-metric-card" data-emphasis={emphasis ? 'true' : 'false'} role="region" aria-labelledby={titleId}>
      <h3 class="pulsepress-metric-card__title" id={titleId}>{title}</h3>
      <p class="pulsepress-metric-card__value">{value}</p>
      {helper ? <p class="pulsepress-metric-card__helper">{helper}</p> : null}
    </section>
  );
}
