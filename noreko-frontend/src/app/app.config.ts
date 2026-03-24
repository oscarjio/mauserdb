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
 *
 * Hanterar både webpack-stil ("Loading chunk X failed") och esbuild-stil
 * ("Failed to fetch dynamically imported module") samt generiska TypeError
 * från nätverksfel vid dynamisk import.
 */
class GlobalErrorHandler implements ErrorHandler {
  handleError(error: any): void {
    const chunkFailedMessage = /Loading chunk [\d]+ failed|ChunkLoadError|Failed to fetch dynamically imported module|dynamically imported module/i;
    const innerError = error?.rejection ?? error;
    const msg = innerError?.message || '';
    if (chunkFailedMessage.test(msg)) {
      // Förhindra oändlig reload-loop: kontrollera om vi redan laddade om nyligen
      const lastReload = sessionStorage.getItem('chunk_reload_ts');
      const now = Date.now();
      if (!lastReload || now - Number(lastReload) > 10000) {
        sessionStorage.setItem('chunk_reload_ts', String(now));
        window.location.reload();
        return;
      }
      // Reload-loop skydd: visa felmeddelande istället för tyst console.error
      this.showChunkErrorOverlay();
      return;
    }
    // Fallback: logga övriga fel till konsolen
    console.error(error);
  }

  /**
   * Visar ett overlay-meddelande på svenska som informerar användaren om att
   * sidan inte kunde laddas och erbjuder en knapp för att ladda om.
   */
  private showChunkErrorOverlay(): void {
    // Undvik duplicerade overlays
    if (document.getElementById('chunk-error-overlay')) return;
    const overlay = document.createElement('div');
    overlay.id = 'chunk-error-overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(26,32,44,0.92)';
    overlay.innerHTML = `
      <div style="background:#2d3748;color:#e2e8f0;border-radius:12px;padding:2rem 2.5rem;max-width:420px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,0.4)">
        <h2 style="margin:0 0 0.75rem;font-size:1.25rem">Sidan kunde inte laddas</h2>
        <p style="margin:0 0 1.25rem;font-size:0.95rem;opacity:0.85">En ny version finns tillgänglig eller så uppstod ett nätverksfel. Ladda om sidan för att fortsätta.</p>
        <button onclick="sessionStorage.removeItem('chunk_reload_ts');window.location.reload()"
          style="background:#4299e1;color:#fff;border:none;border-radius:6px;padding:0.6rem 1.5rem;font-size:1rem;cursor:pointer">
          Ladda om sidan
        </button>
      </div>`;
    document.body.appendChild(overlay);
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
