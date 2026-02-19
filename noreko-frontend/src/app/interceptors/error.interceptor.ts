import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { ToastService } from '../services/toast.service';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const toast = inject(ToastService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      // Don't show toast for requests that already handle errors (e.g. forkJoin with catchError)
      // We check for a custom header to skip
      if (req.headers.has('X-Skip-Error-Toast')) {
        return throwError(() => error);
      }

      let message = 'Ett oväntat fel uppstod';

      if (error.status === 0) {
        message = 'Ingen kontakt med servern. Kontrollera nätverksanslutningen.';
      } else if (error.status === 401) {
        message = 'Sessionen har gått ut. Logga in igen.';
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
