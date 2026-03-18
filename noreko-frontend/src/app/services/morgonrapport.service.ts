import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface RapportInfo {
  datum: string;
  prev_week_datum: string;
  avg30_start: string;
  avg30_end: string;
  genererad: string;
}

export interface ProduktionData {
  totalt_ibc: number;
  mal: number;
  uppfyllnad_pct: number;
  prev_week_ibc: number;
  andring_vs_prev_vecka: number;
  snitt_30d: number;
  andring_vs_30d: number;
  under_mal: boolean;
}

export interface EffektivitetData {
  ibc_per_timme: number;
  prev_ibc_per_timme: number;
  andring_ibc_per_timme: number;
  total_drifttid_h: number;
  tillganglig_tid_h: number;
  utnyttjandegrad_pct: number;
}

export interface StoppOrsak {
  orsak: string;
  antal: number;
  timmar: number;
}

export interface StoppData {
  totalt_antal: number;
  totalt_timmar: number;
  top3_orsaker: StoppOrsak[];
  prev_week_antal: number;
  andring_pct: number;
}

export interface KvalitetData {
  kassationsgrad_pct: number;
  kasserade_antal: number;
  totalt_producerade: number;
  topp_orsak: string;
  prev_week_kassationsgrad: number;
  andring_pct: number;
}

export interface TrenderData {
  daglig_ibc: Record<string, number>;
  glidande_7d: Record<string, number>;
  prev_week_datum: string;
}

export interface Highlight {
  basta_timme: number | null;
  basta_timme_label: string | null;
  basta_timme_antal: number;
  snabbast_operator: string | null;
  snabbast_antal: number;
}

export interface Varning {
  typ: string;
  severity: 'gron' | 'gul' | 'rod';
  meddelande: string;
}

export interface MorgonrapportData {
  rapport_info: RapportInfo;
  produktion: ProduktionData;
  effektivitet: EffektivitetData;
  stopp: StoppData;
  kvalitet: KvalitetData;
  trender: TrenderData;
  highlights: Highlight;
  varningar: Varning[];
}

export interface MorgonrapportResponse {
  success: boolean;
  data: MorgonrapportData;
  timestamp: string;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class MorgonrapportService {
  private api = `${environment.apiUrl}?action=morgonrapport`;

  constructor(private http: HttpClient) {}

  getRapport(date?: string): Observable<MorgonrapportResponse | null> {
    let url = `${this.api}&run=rapport`;
    if (date) {
      url += `&date=${encodeURIComponent(date)}`;
    }
    return this.http.get<MorgonrapportResponse>(url, { withCredentials: true }).pipe(
      timeout(20000),
      retry(1),
      catchError(() => of(null))
    );
  }
}
