import { environment } from '../../environments/environment';

/**
 * Web Vitals reporting (dokumentacja 14.8).
 *
 * Zbiera metryki Core Web Vitals (LCP, FID, CLS) i wysyła je do backendu
 * (endpoint metryk) lub loguje do konsoli w trybie dev. Cele:
 *  - LCP < 2.5 s
 *  - FID < 100 ms
 *  - CLS < 0.1
 *
 * Uwaga: metryki są zbierane tylko w produkcji (bez wpływu na dev).
 */

interface WebVitalMetric {
  name: string;
  value: number;
  rating: 'good' | 'needs-improvement' | 'poor';
}

const RATINGS: Record<string, { good: number; poor: number }> = {
  'LCP': { good: 2500, poor: 4000 },
  'FID': { good: 100, poor: 300 },
  'CLS': { good: 0.1, poor: 0.25 },
  'INP': { good: 200, poor: 500 },
};

function ratingFor(name: string, value: number): WebVitalMetric['rating'] {
  const thresholds = RATINGS[name];
  if (!thresholds) {
    return 'good';
  }
  if (value <= thresholds.good) {
    return 'good';
  }
  if (value <= thresholds.poor) {
    return 'needs-improvement';
  }

  return 'poor';
}

function report(metric: WebVitalMetric): void {
  if (!environment.production) {
    console.info(`[WebVitals] ${metric.name}: ${metric.value} (${metric.rating})`);
    return;
  }

  // W produkcji wysyłamy do backendu (endpoint metryk) — best-effort, bez blokowania.
  try {
    fetch(`${environment.apiUrl}/metrics/web-vitals`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(metric),
      keepalive: true,
    }).catch(() => {
      /* best-effort — ignorujemy błędy sieci */
    });
  } catch {
    /* ignore */
  }
}

/**
 * Rejestruje obserwatorów Web Vitals. Wymaga `web-vitals` w zależnościach.
 * Gdy biblioteka nie jest dostępna, funkcja jest no-op (graceful degradation).
 */
export function initWebVitals(): void {
  if (typeof window === 'undefined') {
    return;
  }

  // Dynamiczny import — biblioteka ładowana tylko w produkcji (tree-shaking).
  if (!environment.production) {
    return;
  }

  import('web-vitals').then(({ onLCP, onFID, onCLS, onINP }) => {
    onLCP((m) => report({ name: 'LCP', value: m.value, rating: ratingFor('LCP', m.value) }));
    onFID((m) => report({ name: 'FID', value: m.value, rating: ratingFor('FID', m.value) }));
    onCLS((m) => report({ name: 'CLS', value: m.value, rating: ratingFor('CLS', m.value) }));
    onINP((m) => report({ name: 'INP', value: m.value, rating: ratingFor('INP', m.value) }));
  }).catch(() => {
    /* biblioteka niedostępna — pomijamy */
  });
}
