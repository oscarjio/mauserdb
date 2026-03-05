import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class KlassificeringslinjeService {
  private readonly apiBase = '/noreko-backend/api.php';

  constructor(private http: HttpClient) {}

  /** Hämta driftsinställningar */
  getSettings(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=settings`,
      { withCredentials: true }
    );
  }

  /** Spara driftsinställningar */
  saveSettings(settings: any): Observable<any> {
    return this.http.post<any>(
      `${this.apiBase}?action=klassificeringslinje&run=settings`,
      settings,
      { withCredentials: true }
    );
  }

  /** Hämta veckodagsmål */
  getWeekdayGoals(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=weekday-goals`,
      { withCredentials: true }
    );
  }

  /** Spara veckodagsmål */
  saveWeekdayGoals(payload: { goals: { weekday: number; mal: number }[] }): Observable<any> {
    return this.http.post<any>(
      `${this.apiBase}?action=klassificeringslinje&run=weekday-goals`,
      payload,
      { withCredentials: true }
    );
  }

  /** Hämta systemstatus */
  getSystemStatus(): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=system-status`,
      { withCredentials: true }
    );
  }

  /** Hämta OEE-trend för statistiksidan */
  getOeeTrend(dagar: number = 30): Observable<any> {
    return this.http.get<any>(
      `${this.apiBase}?action=klassificeringslinje&run=oee-trend&dagar=${dagar}`,
      { withCredentials: true }
    );
  }
}
