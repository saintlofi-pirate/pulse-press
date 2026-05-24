import { useId, useMemo } from 'preact/hooks';
import type { MetricsEnvelope, MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  series: MetricsEnvelope['dailySeries'];
  from: string;
  to: string;
  i18n: MoonfarmerReactionsLeadCaptureAdminData['i18n']['analytics'];
}

const VIEW_W = 400;
const VIEW_H = 120;
const PADDING = 8;

function eachDay(from: string, to: string): string[] {
  if (!from || !to) return [];
  const start = new Date(`${from}T00:00:00Z`);
  const end   = new Date(`${to}T00:00:00Z`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return [];
  const out: string[] = [];
  for (let d = new Date(start); d <= end; d.setUTCDate(d.getUTCDate() + 1)) {
    out.push(d.toISOString().slice(0, 10));
  }
  return out;
}

export function DailySeriesChart({ series, from, to, i18n }: Props) {
  const labelId = useId();

  const bars = useMemo(() => {
    const days = eachDay(from, to);
    if (days.length === 0) return [];
    return days.map((date) => {
      const dayTotals = series[date] ?? {};
      const total = Object.values(dayTotals).reduce((acc, n) => acc + Number(n || 0), 0);
      return { date, total };
    });
  }, [from, to, series]);

  if (bars.length === 0) {
    return null;
  }

  const max = Math.max(1, ...bars.map((b) => b.total));
  const innerW = VIEW_W - PADDING * 2;
  const innerH = VIEW_H - PADDING * 2;
  const barW   = innerW / bars.length;

  return (
    <div class="moonfarmer-reactions-lead-capture-chart">
      <svg
        viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
        role="img"
        aria-labelledby={labelId}
        preserveAspectRatio="none"
      >
        <text id={labelId} class="moonfarmer-reactions-lead-capture-sr-only">{i18n.chartLabel}</text>
        {bars.map((bar, i) => {
          const h = (bar.total / max) * innerH;
          const x = PADDING + i * barW;
          const y = VIEW_H - PADDING - h;
          return (
            <g key={bar.date}>
              <rect
                x={x}
                y={y}
                width={Math.max(1, barW - 2)}
                height={h}
                rx={2}
                ry={2}
                class="moonfarmer-reactions-lead-capture-chart__bar"
                aria-label={`${bar.date}: ${bar.total}`}
              >
                <title>{`${bar.date}: ${bar.total}`}</title>
              </rect>
            </g>
          );
        })}
      </svg>
    </div>
  );
}
