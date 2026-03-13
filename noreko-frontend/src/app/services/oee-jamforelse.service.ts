import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface OeeVecka {
  vecka: number;
  ar: number;
  vecko_label: string;
  from_date: string;
  to_date: string;
  oee_pct: number;
  tillganglighet_pct: number;
  prestanda_pct: number;
  kvalitet_pct: number;
  drifttid_h: number;
  stopptid_h: number;
  planerad_h: number;
  total_ibc: number;
  ok_ibc: number;
  kasserade_ibc: number;
  arbetsdagar: number;
  forandring: number | null;
  forandring_pil: 'up' | 'down' | 'flat';
}

export interface WeeklyOeeData {
  veckor: number;
  mal_oee: number;
  aktuell_vecka: OeeVecka | null;
  forra_vecka: OeeVecka | null;
  forandring: number;
  forandring_pil: 'up' | 'down' | 'flat';
  veckodata: OeeVecka[];
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class OeeJamforelseService {
  private api = `${environment.apiUrl}?action=oee-jamforelse`;

  constructor(private http: HttpClient) {}

  getWeeklyOee(veckor: number = 12): Observable<ApiResponse<WeeklyOeeData> | null> {
    return this.http.get<ApiResponse<WeeklyOeeData>>(
      `${this.api}&run=weekly-oee&veckor=${veckor}`,
      { withCredentials: true }
    ).pipe(timeout(30000), catchError(() => of(null)));
  }
}
