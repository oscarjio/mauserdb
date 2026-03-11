import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface PulsItem {
  id: number;
  datum: string;
  operator: string;
  produkt: string;
  cykeltid: number | null;
  target_cykeltid: number | null;
  kasserad: boolean;
  over_target: boolean;
  ibc_nr: number;
  skift: number;
}

export interface PulsLatestResponse {
  success: boolean;
  data: PulsItem[];
}

export interface HourData {
  ibc_count: number;
  godkanda: number;
  kasserade: number;
  snitt_cykeltid: number | null;
}

export interface PulsHourlyResponse {
  success: boolean;
  current: HourData;
  previous: HourData;
}

@Injectable({ providedIn: 'root' })
export class ProduktionspulsService {
  private api = '/noreko-backend/api.php?action=produktionspuls';

  constructor(private http: HttpClient) {}

  getLatest(limit = 50): Observable<PulsLatestResponse> {
    return this.http.get<PulsLatestResponse>(
      `${this.api}&run=latest&limit=${limit}`,
      { withCredentials: true }
    );
  }

  getHourlyStats(): Observable<PulsHourlyResponse> {
    return this.http.get<PulsHourlyResponse>(
      `${this.api}&run=hourly-stats`,
      { withCredentials: true }
    );
  }
}
