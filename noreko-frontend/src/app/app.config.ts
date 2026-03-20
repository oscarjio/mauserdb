import { ApplicationConfig, APP_INITIALIZER, LOCALE_ID, provideBrowserGlobalErrorListeners, provideZoneChangeDetection } from '@angular/core';
import { provideRouter, withPreloading, PreloadAllModules } from '@angular/router';
import { provideHttpClient, withInterceptors, withFetch } from '@angular/common/http';
import { registerLocaleData } from '@angular/common';
import localeSv from '@angular/common/locales/sv';
import { firstValueFrom } from 'rxjs';

import { routes } from './app.routes';
import { csrfInterceptor } from './interceptors/csrf.interceptor';
import { errorInterceptor } from './interceptors/error.interceptor';
import { AuthService } from './services/auth.service';
import { FeatureFlagService } from './services/feature-flag.service';

registerLocaleData(localeSv);

// APP_INITIALIZER returnerar en Promise som Angular VÄNTAR på innan routing startar.
// Laddar auth-status och feature flags parallellt.
function initApp(auth: AuthService, ff: FeatureFlagService) {
  return () => Promise.all([
    firstValueFrom(auth.fetchStatus()),
    ff.loadFlags()
  ]);
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes, withPreloading(PreloadAllModules)),
    provideHttpClient(withInterceptors([csrfInterceptor, errorInterceptor]), withFetch()),
    { provide: APP_INITIALIZER, useFactory: initApp, deps: [AuthService, FeatureFlagService], multi: true },
    { provide: LOCALE_ID, useValue: 'sv' }
  ]
};
