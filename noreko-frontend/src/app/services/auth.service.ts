import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, interval, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  loggedIn$ = new BehaviorSubject<boolean>(false);
  user$ = new BehaviorSubject<any>(undefined);
  /** Sätts till true när första status-anropet är klart (oavsett resultat). */
  initialized$ = new BehaviorSubject<boolean>(false);

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
    this.fetchStatus();
    interval(60000).subscribe(() => this.fetchStatus());
  }

  fetchStatus() {
    this.http.get<any>('/noreko-backend/api.php?action=status', { withCredentials: true }).pipe(
      timeout(8000),
      catchError(() => of({ loggedIn: false, user: null }))
    ).subscribe(res => {
      const loggedIn = !!res?.loggedIn;
      this.loggedIn$.next(loggedIn);
      this.user$.next(res?.user || null);
      this.initialized$.next(true);
      if (loggedIn && res?.user) {
        sessionStorage.setItem('auth_user', JSON.stringify(res.user));
      } else {
        sessionStorage.removeItem('auth_user');
      }
    });
  }

  logout() {
    sessionStorage.removeItem('auth_user');
    this.http.get('/noreko-backend/api.php?action=login&run=logout', { withCredentials: true }).subscribe(() => {
      this.loggedIn$.next(false);
      this.user$.next(null);
    });
  }
}
