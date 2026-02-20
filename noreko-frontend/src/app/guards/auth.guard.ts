import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { map, filter, take } from 'rxjs';

export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.loggedIn$.pipe(
    filter(val => val !== null),
    take(1),
    map(loggedIn => {
      if (loggedIn) return true;
      router.navigate(['/login']);
      return false;
    })
  );
};

export const adminGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.user$.pipe(
    filter(val => val !== undefined),
    take(1),
    map(user => {
      if (user?.role === 'admin') return true;
      router.navigate(['/']);
      return false;
    })
  );
};
