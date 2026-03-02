import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, interval } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  loggedIn$ = new BehaviorSubject<boolean>(false);
  user$ = new BehaviorSubject<any>(undefined);
  /** Sätts till true när första status-anropet är klart (oavsett resultat). */
  initialized$ = new BehaviorSubject<boolean>(false);

  constructor(private http: HttpClient) {
    this.fetchStatus();
    interval(60000).subscribe(() => this.fetchStatus());
  }

  fetchStatus() {
    this.http.get<any>('/noreko-backend/api.php?action=status', { withCredentials: true }).subscribe({
      next: (res) => {
        this.loggedIn$.next(res.loggedIn);
        this.user$.next(res.user || null);
        this.initialized$.next(true);
      },
      error: () => {
        this.loggedIn$.next(false);
        this.user$.next(null);
        this.initialized$.next(true);
      }
    });
  }

  logout() {
    this.http.get('/noreko-backend/api.php?action=login&run=logout', { withCredentials: true }).subscribe(() => {
      this.loggedIn$.next(false);
      this.user$.next(null);
    });
  }
}
