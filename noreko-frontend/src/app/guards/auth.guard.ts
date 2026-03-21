import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { map, filter, take, switchMap } from 'rxjs';

export const authGuard: CanActivateFn = (_route, state) => {
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
      // Spara önskad URL så login-sidan kan redirecta tillbaka efter inloggning
      router.navigate(['/login'], { queryParams: { returnUrl: state.url } });
      return false;
    })
  );
};

export const adminGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // Vänta tills status-anropet är klart (precis som authGuard) — annars
  // ser guard:en user$=null (initialt undefined → null vid langsammt fetch)
  // och redirectar till / trots att användaren är inloggad som admin.
  //
  // Använd user$ som enda källa — user$ sätts alltid EFTER loggedIn$ i fetchStatus(),
  // så user$ !== null/undefined innebär att loggedIn$ redan är true.
  // Detta undviker race condition där loggedIn$ och user$ är ur synk.
  return auth.initialized$.pipe(
    filter(init => init === true),
    take(1),
    switchMap(() => auth.user$.pipe(take(1))),
    map(user => {
      if (user?.role === 'admin' || user?.role === 'developer') return true;
      if (!user) {
        // Ej inloggad — skicka till login med returnUrl
        router.navigate(['/login'], { queryParams: { returnUrl: state.url } });
      } else {
        // Inloggad men ej admin/developer — skicka till startsidan
        router.navigate(['/']);
      }
      return false;
    })
  );
};
