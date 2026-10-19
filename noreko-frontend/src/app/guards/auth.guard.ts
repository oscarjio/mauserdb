import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { map, filter, take, switchMap } from 'rxjs';
import { Observable } from 'rxjs';

export const authGuard: CanActivateFn = (_route, state): Observable<boolean | UrlTree> => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // Vänta tills första status-anropet är klart innan vi avgör om användaren är inloggad.
  // Utan detta skulle guard:en se det initiala false-värdet och omedelbart omdirigera till /login.
  // Returnerar UrlTree istället för router.navigate() + false — Angular best practice som
  // säkerställer att routern hanterar omdirigering atomärt och undviker dubbla navigationer.
  return auth.initialized$.pipe(
    filter(init => init === true),
    take(1),
    switchMap(() => auth.loggedIn$.pipe(take(1))),
    map(loggedIn => {
      if (loggedIn) return true;
      // N-bug #3: om senaste statuskollen själv misslyckades (503/nätverksfel vid boot,
      // t.ex. en ny flik som bootar precis under en backend-överbelastning) är
      // inloggningsstatusen OKÄND — inte definitivt nej. Tvinga inte till /login på en
      // giltig session bara pga en tillfällig hicka; släpp igenom optimistiskt och låt
      // sidans egna API-anrop 401:a (errorInterceptor skickar till /login då) om
      // sessionen verkligen är ogiltig.
      if (auth.lastStatusCheckFailed) return true;
      // Spara önskad URL så login-sidan kan redirecta tillbaka efter inloggning
      return router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
    })
  );
};

export const adminGuard: CanActivateFn = (_route, state): Observable<boolean | UrlTree> => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // Vänta tills status-anropet är klart (precis som authGuard) — annars
  // ser guard:en user$=null (initialt undefined → null vid langsammt fetch)
  // och redirectar till / trots att användaren är inloggad som admin.
  //
  // Använd user$ som enda källa — user$ sätts alltid EFTER loggedIn$ i fetchStatus(),
  // så user$ !== null/undefined innebär att loggedIn$ redan är true.
  // Detta undviker race condition där loggedIn$ och user$ är ur synk.
  // Returnerar UrlTree istället för router.navigate() + false — Angular best practice.
  return auth.initialized$.pipe(
    filter(init => init === true),
    take(1),
    switchMap(() => auth.user$.pipe(take(1))),
    map(user => {
      if (user?.role === 'admin' || user?.role === 'developer') return true;
      if (!user) {
        // N-bug #3: samma resonemang som authGuard — en misslyckad statuskoll är OKÄND
        // status, inte definitivt "ej inloggad". Släpp igenom, låt API-anropen 401:a.
        if (auth.lastStatusCheckFailed) return true;
        // Ej inloggad — skicka till login med returnUrl
        return router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
      }
      // Inloggad men ej admin/developer — skicka till startsidan
      return router.createUrlTree(['/']);
    })
  );
};
