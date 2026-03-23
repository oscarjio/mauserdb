import { ApplicationConfig, APP_INITIALIZER, ErrorHandler, LOCALE_ID, provideBrowserGlobalErrorListeners, provideZoneChangeDetection } from '@angular/core';
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

/**
 * Global ErrorHandler som fångar ChunkLoadError (uppstår när lazy-loaded
 * chunks inte kan hämtas, t.ex. efter en ny deploy medan användaren har
 * gammal version cachad). Laddar om sidan en gång för att hämta nya chunks.
 */
class GlobalErrorHandler implements ErrorHandler {
  handleError(error: any): void {
    const chunkFailedMessage = /Loading chunk [\d]+ failed|ChunkLoadError/;
    const innerError = error?.rejection ?? error;
    if (chunkFailedMessage.test(innerError?.message || '')) {
      // Förhindra oändlig reload-loop: kontrollera om vi redan laddade om nyligen
      const lastReload = sessionStorage.getItem('chunk_reload_ts');
      const now = Date.now();
      if (!lastReload || now - Number(lastReload) > 10000) {
        sessionStorage.setItem('chunk_reload_ts', String(now));
        window.location.reload();
        return;
      }
    }
    // Fallback: logga övriga fel till konsolen
    console.error(error);
  }
}

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
    { provide: LOCALE_ID, useValue: 'sv' },
    { provide: ErrorHandler, useClass: GlobalErrorHandler }
  ]
};
