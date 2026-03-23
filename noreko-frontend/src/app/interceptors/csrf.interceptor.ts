import { HttpInterceptorFn } from '@angular/common/http';

/**
 * CSRF-interceptor: bifogar X-CSRF-Token-header till alla state-andrande requests
 * (POST, PUT, DELETE). Token hamtas fran sessionStorage dar AuthService sparar den
 * vid login och status-polling.
 */
export const csrfInterceptor: HttpInterceptorFn = (req, next) => {
  const mutatingMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
  if (mutatingMethods.includes(req.method.toUpperCase())) {
    let token: string | null = null;
    try { token = sessionStorage.getItem('csrf_token'); } catch { /* storage otillgänglig */ }
    if (token) {
      req = req.clone({
        setHeaders: { 'X-CSRF-Token': token }
      });
    }
  }
  return next(req);
};
