import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { BehaviorSubject, Subscription, interval, of, Observable } from 'rxjs';
import { timeout, catchError, retry, tap, map, switchMap } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface AuthUser {
  id: number;
  role: string;
  username?: string;
  name?: string;
  email?: string;
  operator_id?: number;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  loggedIn$ = new BehaviorSubject<boolean>(false);
  user$ = new BehaviorSubject<AuthUser | null | undefined>(undefined);
  /** Sätts till true när första status-anropet är klart (oavsett resultat). */
  initialized$ = new BehaviorSubject<boolean>(false);

  private pollSub: Subscription | null = null;
  private logoutSub: Subscription | null = null;
  private router = inject(Router);

  constructor(private http: HttpClient) {
    // Återställ cachad auth-status direkt så guards inte redirectar vid sidomladdning
    const cached = sessionStorage.getItem('auth_user');
    if (cached) {
      try {
        const data = JSON.parse(cached);
        this.loggedIn$.next(true);
        this.user$.next(data);
        this.initialized$.next(true);
      } catch (_) {
        sessionStorage.removeItem('auth_user');
      }
    }
    // Det initiala fetchStatus()-anropet görs av APP_INITIALIZER (se app.config.ts)
    // så att Angular väntar på svaret innan routing startar.
    // Här sätter vi bara upp polling för efterföljande kontroller.
    this.startPolling();
  }

  /** Starta status-polling (anropas vid konstruktion och kan återstartas efter login). */
  private startPolling(): void {
    this.stopPolling();
    this.pollSub = interval(60000).pipe(
      switchMap(() => this.fetchStatus())
    ).subscribe();
  }

  /** Stoppa status-polling (anropas vid logout). */
  private stopPolling(): void {
    if (this.pollSub) {
      this.pollSub.unsubscribe();
      this.pollSub = null;
    }
  }

  fetchStatus(): Observable<void> {
    return this.http.get<{ loggedIn?: boolean; user?: AuthUser | null; csrfToken?: string }>(`${environment.apiUrl}?action=status`, { withCredentials: true }).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of(null)), // null = transient error, ändra inte auth-state
      tap(res => {
        if (res === null) {
          // Transienta fel (timeout, nätverksfel) loggar inte ut användaren.
          // Sätt initialized så guards inte fastnar.
          this.initialized$.next(true);
          return;
        }
        const loggedIn = !!res?.loggedIn;
        this.loggedIn$.next(loggedIn);
        this.user$.next(res?.user || null);
        this.initialized$.next(true);
        if (loggedIn && res?.user) {
          sessionStorage.setItem('auth_user', JSON.stringify(res.user));
          if (res.csrfToken) {
            sessionStorage.setItem('csrf_token', res.csrfToken);
          }
        } else {
          sessionStorage.removeItem('auth_user');
          sessionStorage.removeItem('csrf_token');
        }
      }),
      map(() => void 0)
    );
  }

  logout(): void {
    // Rensa state och stoppa polling INNAN HTTP-anropet — säkerställer
    // att polling inte fortsätter om logout-anropet misslyckas.
    this.stopPolling();
    sessionStorage.removeItem('auth_user');
    sessionStorage.removeItem('csrf_token');
    this.loggedIn$.next(false);
    this.user$.next(null);

    this.logoutSub?.unsubscribe();
    this.logoutSub = this.http.get(`${environment.apiUrl}?action=login&run=logout`, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null))
    ).subscribe(() => {
      this.router.navigate(['/login']);
    });
  }

  /**
   * Rensa auth-state och stoppa polling.
   * Anropas av error interceptor vid 401 (session expired).
   */
  clearSession(): void {
    this.stopPolling();
    sessionStorage.removeItem('auth_user');
    sessionStorage.removeItem('csrf_token');
    this.loggedIn$.next(false);
    this.user$.next(null);
  }

  /** Anropas efter lyckad inloggning för att återstarta polling. */
  onLoginSuccess(): void {
    this.startPolling();
  }
}
