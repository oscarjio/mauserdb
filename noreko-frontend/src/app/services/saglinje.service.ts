import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';

@Injectable({ providedIn: 'root' })
export class SaglinjeService {
  private readonly apiBase = '/noreko-backend/api.php';

  constructor(private http: HttpClient) {}

  /** Hämta driftsinställningar */
  getSettings(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=saglinje&run=settings`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  /** Spara driftsinställningar */
  saveSettings(settings: any): Observable<any> {
    return this.http.post<any>(
      `${this.apiBase}?action=saglinje&run=settings`,
      settings,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  /** Hämta veckodagsmål */
  getWeekdayGoals(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=saglinje&run=weekday-goals`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  /** Spara veckodagsmål */
  saveWeekdayGoals(payload: { goals: { weekday: number; mal: number }[] }): Observable<any> {
    return this.http.post<any>(
      `${this.apiBase}?action=saglinje&run=weekday-goals`,
      payload,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)));
  }

  /** Hämta systemstatus */
  getSystemStatus(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=saglinje&run=system-status`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  /** Hämta OEE-trend för statistiksidan */
  getOeeTrend(dagar: number = 30): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=saglinje&run=oee-trend&dagar=${dagar}`,
      { withCredentials: true }
    ).pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }
}
