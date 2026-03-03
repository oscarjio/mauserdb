import { ApplicationConfig, APP_INITIALIZER, provideBrowserGlobalErrorListeners, provideZoneChangeDetection } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

import { routes } from './app.routes';
import { errorInterceptor } from './interceptors/error.interceptor';
import { AuthService } from './services/auth.service';

// APP_INITIALIZER returnerar en Promise som Angular VÄNTAR på innan routing startar.
// firstValueFrom(auth.fetchStatus()) skickar ett HTTP-anrop till /api.php?action=status
// och resolvar när svaret (eller ett timeout/nätverksfel) är klart.
// På så vis är loggedIn$ och user$ alltid korrekt satta vid första route-navigationen.
function initAuth(auth: AuthService) {
  return () => firstValueFrom(auth.fetchStatus());
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
