import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { timeout } from 'rxjs/operators';

// ================================================================
// Interfaces
// ================================================================

export interface ProduktTypSummaryItem {
  produkt_id: number;
  produkt_namn: string;
  antal_skift: number;
  antal_ibc: number;
  antal_ej_ok: number;
  snitt_cykeltid_sek: number | null;
  kvalitet_pct: number | null;
  ibc_per_timme: number | null;
  snitt_bonus: number;
}

export interface ProduktTypSummaryResponse {
  success: boolean;
  data: {
    days: number;
    from: string;
    to: string;
    produkter: ProduktTypSummaryItem[];
    total_ibc: number;
    total_ej_ok: number;
    har_data: boolean;
  };
  timestamp: string;
}

export interface ChartDataset {
  label: string;
  produktId: number;
  data: (number | null)[];
  backgroundColor?: string;
  borderColor?: string;
  borderWidth?: number;
  fill?: boolean;
  tension?: number;
  spanGaps?: boolean;
}

export interface ProduktTypTrendResponse {
  success: boolean;
  data: {
    days: number;
    from: string;
    to: string;
    labels: string[];
    datasets_ibc: ChartDataset[];
    datasets_cykeltid: ChartDataset[];
    har_data: boolean;
  };
  timestamp: string;
}

export interface ProduktTypComparisonItem {
  produkt_id: number;
  produkt_namn: string;
  antal_skift: number;
  antal_ibc: number;
  antal_ej_ok: number;
  snitt_cykeltid_sek: number | null;
  kvalitet_pct: number | null;
  ibc_per_timme: number | null;
  snitt_bonus: number;
}

export interface ProduktTypComparisonResponse {
  success: boolean;
  data: {
    days: number;
    from: string;
    to: string;
    a: ProduktTypComparisonItem;
    b: ProduktTypComparisonItem;
    diff_pct: {
      snitt_cykeltid_sek: number | null;
      kvalitet_pct: number | null;
      ibc_per_timme: number | null;
      snitt_bonus: number | null;
    };
    har_data: boolean;
  };
  timestamp: string;
}

// ================================================================
// Service
// ================================================================

@Injectable({ providedIn: 'root' })
export class ProduktTypEffektivitetService {
  private readonly base = '/noreko-backend/api.php?action=produkttyp-effektivitet';

  constructor(private http: HttpClient) {}

  getSummary(days: number): Observable<ProduktTypSummaryResponse> {
    return this.http.get<ProduktTypSummaryResponse>(
      `${this.base}&run=summary&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15_000));
  }

  getTrend(days: number): Observable<ProduktTypTrendResponse> {
    return this.http.get<ProduktTypTrendResponse>(
      `${this.base}&run=trend&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15_000));
  }

  getComparison(a: number, b: number, days: number): Observable<ProduktTypComparisonResponse> {
    return this.http.get<ProduktTypComparisonResponse>(
      `${this.base}&run=comparison&a=${a}&b=${b}&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(15_000));
  }
}
