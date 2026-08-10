import { ApplicationConfig, provideZoneChangeDetection, inject, APP_INITIALIZER, LOCALE_ID } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors, withFetch } from '@angular/common/http';
import { provideServiceWorker } from '@angular/service-worker';
import { registerLocaleData } from '@angular/common';
import localePl from '@angular/common/locales/pl';

import { environment } from '../environments/environment';
import { routes } from './app.routes';
import { httpInterceptor } from './services/http.interceptor';
import { TranslateService } from './services/translate.service';
import { IndexedDbService } from './services/indexed-db.service';

// Rejestracja polskiej lokalizacji (np. nazwy dni/miesięcy w DatePipe)
registerLocaleData(localePl);

// Import plików lokalizacji (statyczny import dla języka domyślnego)
import commonPl from '../locales/pl/common.json';
import dashboardPl from '../locales/pl/dashboard.json';
import pracownicyPl from '../locales/pl/pracownicy.json';
import sprzetPl from '../locales/pl/sprzet.json';
import terminalePl from '../locales/pl/terminale.json';
import harmonogramPl from '../locales/pl/harmonogram.json';
import analitykaPl from '../locales/pl/analityka.json';
import ustawieniaPl from '../locales/pl/ustawienia.json';
import awariaPl from '../locales/pl/awaria.json';
import raportowaniePl from '../locales/pl/raportowanie.json';

/**
 * Factory do inicjalizacji tłumaczeń i IndexedDB przy starcie aplikacji.
 */
function initializeApp(): () => Promise<void> {
  const translateService = inject(TranslateService);
  const indexedDb = inject(IndexedDbService);

  return async () => {
    translateService.registerMany({
      common: commonPl,
      dashboard: dashboardPl,
      pracownicy: pracownicyPl,
      sprzet: sprzetPl,
      terminale: terminalePl,
      harmonogram: harmonogramPl,
      analityka: analitykaPl,
      ustawienia: ustawieniaPl,
      awaria: awariaPl,
      raportowanie: raportowaniePl,
    });
    await indexedDb.init();
  };
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),
    provideHttpClient(withInterceptors([httpInterceptor]), withFetch()),
    provideServiceWorker('ngsw-worker.js', {
      enabled: environment.production,
      registrationStrategy: 'registerWhenStable:30000',
    }),
    { provide: LOCALE_ID, useValue: 'pl-PL' },
    {
      provide: APP_INITIALIZER,
      useFactory: initializeApp,
      multi: true,
    },
  ],
};