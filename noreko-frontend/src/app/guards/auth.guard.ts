import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { map, filter, take, switchMap } from 'rxjs';

export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // Vänta tills första status-anropet är klart innan vi avgör om användaren är inloggad.
  // Utan detta skulle guard:en se det initiala false-värdet och omedelbart omdirigera till /login.
  return auth.initialized$.pipe(
    filter(init => init === true),
    take(1),
    switchMap(() => auth.loggedIn$.pipe(take(1))),
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
