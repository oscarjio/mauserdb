import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError, retry, timer } from 'rxjs';
import { Router } from '@angular/router';
import { ToastService } from '../services/toast.service';
import { AuthService } from '../services/auth.service';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const toast = inject(ToastService);
  const router = inject(Router);
  const auth = inject(AuthService);

  // Bara GET/HEAD/OPTIONS ar idempotenta — POST/PUT/DELETE ska INTE retry:as
  // (kan skapa dubbletter eller utfora oavsiktliga sidoeffekter)
  const safeToRetry = ['GET', 'HEAD', 'OPTIONS'].includes(req.method.toUpperCase());

  // Endpoints som hanterar sina egna fel och inte ska retry:as via interceptorn.
  // feature-flags: loadFlags() har redan retry(1)+catchError i sin service.
  // Att retry:a via interceptorn dessutom skapar 4–8 requests per app-init vid 503 → pool-utmattning.
  const skipRetry = req.url.includes('action=feature-flags');

  return next(req).pipe(
    // Retry vid natverksfel (status 0) eller 502/503/504 — enbart idempotenta metoder (GET/HEAD/OPTIONS).
    // 503: exponentiell backoff upp till 3 forsok (500ms, 1000ms, 2000ms).
    // 0/502/504: en forsok med 1s fast delay.
    retry({
      count: 3,
      delay: (err: HttpErrorResponse, retryCount: number) => {
        if (!safeToRetry || skipRetry) {
          return throwError(() => err);
        }
        if (err.status === 503) {
          return timer(retryCount * 500);
        }
        if (err.status === 0 || err.status === 502 || err.status === 504) {
          // Bara ett forsok for ovriga gateway-fel
          if (retryCount > 1) {
            return throwError(() => err);
          }
          return timer(1000);
        }
        return throwError(() => err);
      },
    }),
    catchError((error: HttpErrorResponse) => {
      // Centraliserad loggning med kontext — logga ALLA HTTP-fel for diagnostik
      const method = req.method;
      const url = req.url.replace(/^https?:\/\/[^/]+/, ''); // Strip host for readability
      const status = error.status;
      console.error(
        `[HTTP ${status}] ${method} ${url}`,
        { status, statusText: error.statusText, timestamp: new Date().toISOString(), errorBody: error.error }
      );

      // Skip toast for status check (polling) and requests with custom skip header
      if (req.url.includes('action=status') || req.headers.has('X-Skip-Error-Toast')) {
        return throwError(() => error);
      }

      let message = 'Ett oväntat fel uppstod';

      if (error.status === 0) {
        message = 'Ingen kontakt med servern. Kontrollera nätverksanslutningen.';
      } else if (error.status === 401) {
        // Rensa auth-state och stoppa polling via AuthService
        auth.clearSession();
        if (!router.url.includes('/login')) {
          message = 'Sessionen har gått ut. Logga in igen.';
          // Bevara nuvarande URL som returnUrl så användaren kan komma tillbaka efter login
          router.navigate(['/login'], { queryParams: { returnUrl: router.url } });
        } else {
          // On login page, don't show session expired - show specific error
          return throwError(() => error);
        }
      } else if (error.status === 403) {
        message = 'Åtkomst nekad. Du har inte behörighet.';
      } else if (error.status === 404) {
        message = 'Resursen hittades inte (404).';
      } else if (error.status === 408) {
        message = 'Förfrågan tog för lång tid (timeout). Försök igen.';
      } else if (error.status === 429) {
        message = 'För många förfrågningar. Försök igen om en stund.';
      } else if (error.status >= 500) {
        // Prioritera serverns eget felmeddelande om det finns — annars generiskt
        message = error.error?.error || ('Serverfel (' + error.status + '). Försök igen senare.');
      } else if (error.error?.error) {
        // Övriga statuskoder (t.ex. 409, 422) med serverns felmeddelande
        message = error.error.error;
      }

      toast.error(message);
      return throwError(() => error);
    })
  );
};
