/**
 * Konfiguracja środowiska produkcyjnego.
 * Wartości mogą być nadpisane przez zmienne środowiskowe w procesie CI/CD.
 */

export const environment = {
  production: true,
  apiUrl: '/api/v1',
  frontendUrl: '',
  httpTimeout: 10000,
  httpRetryAttempts: 1,
  cacheTtl: 60000,
  refreshBeforeExpirySeconds: 60,
};