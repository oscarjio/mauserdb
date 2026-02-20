import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { Router } from '@angular/router';
import { ToastService } from '../services/toast.service';
import { AuthService } from '../services/auth.service';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const toast = inject(ToastService);
  const router = inject(Router);
  const auth = inject(AuthService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      // Skip toast for status check (polling) and requests with custom skip header
      if (req.url.includes('action=status') || req.headers.has('X-Skip-Error-Toast')) {
        return throwError(() => error);
      }

      let message = 'Ett oväntat fel uppstod';

      if (error.status === 0) {
        message = 'Ingen kontakt med servern. Kontrollera nätverksanslutningen.';
      } else if (error.status === 401) {
        // Clear auth state and redirect to login
        auth.loggedIn$.next(false);
        auth.user$.next(null);
        if (!router.url.includes('/login')) {
          message = 'Sessionen har gått ut. Logga in igen.';
          router.navigate(['/login']);
        } else {
          // On login page, don't show session expired - show specific error
          return throwError(() => error);
        }
      } else if (error.status === 403) {
        message = 'Åtkomst nekad. Du har inte behörighet.';
      } else if (error.status === 404) {
        message = 'Resursen hittades inte (404).';
      } else if (error.status === 429) {
        message = 'För många förfrågningar. Försök igen om en stund.';
      } else if (error.status >= 500) {
        message = 'Serverfel (' + error.status + '). Försök igen senare.';
      } else if (error.error?.error) {
        message = error.error.error;
      }

      toast.error(message);
      return throwError(() => error);
    })
  );
};
