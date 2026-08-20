/**
 * Konfiguracja środowiska produkcyjnego.
 * Wartości mogą być nadpisane przez zmienne środowiskowe w procesie CI/CD.
 */

export const environment = {
  production: true,
  apiUrl: 'https://pbs-api.adammz.pl/api/v1',
  frontendUrl: 'https://pbs.adammz.pl',
  httpTimeout: 20000, // 20 s — ciężkie agregacje (np. /employees/summary) przy zdalnej bazie
  httpRetryAttempts: 1,
  cacheTtl: 60000,
  refreshBeforeExpirySeconds: 60,
};