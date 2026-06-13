import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface TvattOpRank {
  op_id: number;
  operator_namn: string;
  total_ibc: number;
  skift_count: number;
  avg_ibc_per_skift: number;
  ibc_per_h: number;
}

export interface TvattOpSammanfattning {
  total_ibc: number;
  aktiva_operatorer: number;
  snitt_ibc_per_h: number;
  basta_operator: { namn: string; ibc_per_h: number } | null;
}

export interface TvattOpPoangFordelning {
  labels: string[];
  values: number[];
}

@Injectable({ providedIn: 'root' })
export class TvattlinjeOperatorService {
  private api = `${environment.apiUrl}?action=tvattlinje-operator`;

  constructor(private http: HttpClient) {}

  getRanking(period: string): Observable<any> {
    return this.http.get(`${this.api}&run=ranking&period=${period}`);
  }

  getSammanfattning(period: string): Observable<any> {
    return this.http.get(`${this.api}&run=sammanfattning&period=${period}`);
  }

  getTopplista(period: string): Observable<any> {
    return this.http.get(`${this.api}&run=topplista&period=${period}`);
  }

  getPoangfordelning(period: string): Observable<any> {
    return this.http.get(`${this.api}&run=poangfordelning&period=${period}`);
  }
}
