import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class KlassificeringslinjeService {
  private readonly apiBase = environment.apiUrl;

  constructor(private http: HttpClient) {}

  /** Hämta driftsinställningar */
  getSettings(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=settings`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  /** Spara driftsinställningar */
  saveSettings(settings: any): Observable<any> {
    return this.http.post<any>(
      `${this.apiBase}?action=klassificeringslinje&run=settings`,
      settings,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(err => { console.error('saveSettings failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }

  /** Hämta veckodagsmål */
  getWeekdayGoals(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=weekday-goals`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  /** Spara veckodagsmål */
  saveWeekdayGoals(payload: { goals: { weekday: number; mal: number }[] }): Observable<any> {
    return this.http.post<any>(
      `${this.apiBase}?action=klassificeringslinje&run=weekday-goals`,
      payload,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(err => { console.error('saveWeekdayGoals failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }

  /** Hämta systemstatus */
  getSystemStatus(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=system-status`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  /** Hämta OEE-trend för statistiksidan */
  getOeeTrend(dagar: number = 30): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=oee-trend&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
