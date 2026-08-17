/**
 * Konfiguracja środowiska deweloperskiego.
 * W produkcji używany environment.prod.ts (zastępowany przez fileReplacements w angular.json).
 */

export const environment = {
  production: false,
  apiUrl: 'http://localhost:8080/api/v1',
  frontendUrl: 'http://localhost:4200',
  httpTimeout: 20000, // 20 s — ciężkie agregacje (np. /employees/summary) przy zdalnej bazie wymagają więcej niż 10 s
  httpRetryAttempts: 1,
  cacheTtl: 60000, // 60 s dla GET
  refreshBeforeExpirySeconds: 60, // odśwież access token 60 s przed wygaśnięciem
};