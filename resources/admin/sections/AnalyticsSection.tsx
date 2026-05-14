import { useCallback, useEffect, useState } from 'preact/hooks';
import { fetchAnalytics } from '../api';
import { DailySeriesChart } from '../components/DailySeriesChart';
import { MetricCard } from '../components/MetricCard';
import { TopPostsTable } from '../components/TopPostsTable';
import type { MetricsEnvelope, PulsePressAdminData } from '../types';

interface Props {
  adminData: PulsePressAdminData;
}

type Status = 'loading' | 'ready' | 'error';

function formatPercent(value: number | null, fallback: string): string {
  if (value === null) return fallback;
  return `${Math.round(value * 100)}%`;
}

function formatInt(value: number): string {
  return value.toLocaleString();
}

function dominantReaction(series: MetricsEnvelope['dailySeries']): string | null {
  const totals: Record<string, number> = {};
  for (const day of Object.values(series)) {
    for (const [type, count] of Object.entries(day)) {
      totals[type] = (totals[type] ?? 0) + Number(count || 0);
    }
  }
  let best: { type: string; count: number } | null = null;
  for (const [type, count] of Object.entries(totals)) {
    if (best === null || count > best.count) {
      best = { type, count };
    }
  }
  return best?.type ?? null;
}

export function AnalyticsSection({ adminData }: Props) {
  const i18n = adminData.i18n.analytics;
  const [status, setStatus]   = useState<Status>('loading');
  const [data, setData]       = useState<MetricsEnvelope | null>(null);
  const [error, setError]     = useState<string | null>(null);

  const load = useCallback(async () => {
    setStatus('loading');
    setError(null);
    try {
      const result = await fetchAnalytics(adminData.restRoot, adminData.nonce);
      setData(result);
      setStatus('ready');
    } catch (err) {
      const message = err instanceof Error ? err.message : i18n.errorState;
      setError(message);
      setStatus('error');
    }
  }, [adminData.nonce, adminData.restRoot, i18n.errorState]);

  useEffect(() => { void load(); }, [load]);

  if (status === 'loading') {
    return (
      <section class="pulsepress-section" aria-busy="true">
        <header class="pulsepress-section__header">
          <h2>{adminData.i18n.sections.analyticsTitle}</h2>
          <p class="pulsepress-section__helper">{adminData.i18n.sections.analyticsHelper}</p>
        </header>
        <p class="pulsepress-loading-skeleton">{i18n.loadingState}</p>
      </section>
    );
  }

  if (status === 'error' || data === null) {
    return (
      <section class="pulsepress-section">
        <header class="pulsepress-section__header">
          <h2>{adminData.i18n.sections.analyticsTitle}</h2>
        </header>
        <p role="alert" class="pulsepress-error">{error ?? i18n.errorState}</p>
        <button type="button" class="pulsepress-submit" onClick={() => void load()}>{i18n.retry}</button>
      </section>
    );
  }

  const isEmpty = data.totalReactions === 0 && data.totalCaptures === 0;
  const dominant = dominantReaction(data.dailySeries);
  const sentimentInsight = dominant && data.sentimentRate !== null
    ? i18n.sentimentInsightTemplate
        .replace('{type}', dominant.charAt(0).toUpperCase() + dominant.slice(1))
        .replace('{percent}', String(Math.round(data.sentimentRate * 100)))
    : i18n.sentimentInsightFallback;

  return (
    <section class="pulsepress-section" aria-labelledby="pulsepress-section-analytics-title">
      <header class="pulsepress-section__header">
        <h2 id="pulsepress-section-analytics-title">{adminData.i18n.sections.analyticsTitle}</h2>
        <p class="pulsepress-section__helper">
          {adminData.i18n.sections.analyticsHelper}
          {data.clamped && (
            <>
              {' '}
              <span class="pulsepress-clamped-notice">{i18n.clampedNotice}</span>
            </>
          )}
        </p>
      </header>

      {isEmpty ? (
        <p class="pulsepress-empty-state">{i18n.emptyState}</p>
      ) : (
        <div class="pulsepress-section__body">
          <div class="pulsepress-metric-grid">
            <MetricCard title={i18n.totalReactionsLabel} value={formatInt(data.totalReactions)} helper={i18n.totalReactionsHelper} emphasis />
            <MetricCard title={i18n.totalCapturesLabel}  value={formatInt(data.totalCaptures)}  helper={i18n.totalCapturesHelper} />
            <MetricCard title={i18n.sentimentRateLabel}  value={formatPercent(data.sentimentRate, '—')} helper={i18n.sentimentRateHelper} />
            <MetricCard title={i18n.captureRateLabel}    value={formatPercent(data.captureRate, '—')}   helper={i18n.captureRateHelper} />
          </div>

          <p class="pulsepress-insight" role="status">{sentimentInsight}</p>

          <DailySeriesChart series={data.dailySeries} from={data.from} to={data.to} i18n={i18n} />

          <TopPostsTable rows={data.topPosts} titles={data.postTitles} i18n={i18n} />
        </div>
      )}
    </section>
  );
}
