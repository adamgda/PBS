import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { AppComponent } from './app/app.component';
import { initWebVitals } from './app/services/web-vitals.service';

// Web Vitals reporting (dokumentacja 14.8) — tylko produkcja, best-effort.
initWebVitals();

bootstrapApplication(AppComponent, appConfig)
  .catch((err) => console.error(err));
