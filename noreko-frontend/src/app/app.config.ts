import { ApplicationConfig, APP_INITIALIZER, provideBrowserGlobalErrorListeners, provideZoneChangeDetection } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';

import { routes } from './app.routes';
import { errorInterceptor } from './interceptors/error.interceptor';
import { AuthService } from './services/auth.service';

// Säkerställer att AuthService alltid instansieras direkt vid app-start,
// oavsett vilken route som laddas. Utan detta anropas fetchStatus() aldrig
// förrän en guard eller Menu-komponenten injicerar AuthService — vilket på
// login/live-sidor (där Menu döljs) kan vara länge eller aldrig.
function initAuth(auth: AuthService) {
  return () => {}; // Konstruktorn gör jobbet (fetchStatus + interval)
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),
    provideHttpClient(withInterceptors([errorInterceptor])),
    { provide: APP_INITIALIZER, useFactory: initAuth, deps: [AuthService], multi: true }
  ]
};
