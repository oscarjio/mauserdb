import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ---- Interfaces ----

export interface SkiftOverview {
  operatorer_totalt: number;
  bemanningsgrad: number;
  underbemanning: number;
  nasta_skiftbyte: string;
  aktivt_skift: string;
}

export interface SkiftOverviewResponse {
  success: boolean;
  data: SkiftOverview;
}

export interface SkiftOperator {
  id: number;
  operator_id: number;
  namn: string;
}

export interface SkiftDag {
  datum: string;
  operatorer: SkiftOperator[];
  antal: number;
  status: 'gron' | 'gul' | 'rod';
}

export interface SkiftRad {
  skift_typ: string;
  start_tid: string;
  slut_tid: string;
  min_bemanning: number;
  max_bemanning: number;
  dagar: SkiftDag[];
}

export interface DagInfo {
  datum: string;
  dag_namn: string;
}

export interface ScheduleResponse {
  success: boolean;
  vecka: string;
  monday: string;
  sunday: string;
  dagar: DagInfo[];
  schema: SkiftRad[];
}

export interface ShiftDetailOperator {
  schema_id: number;
  operator_id: number;
  namn: string;
}

export interface ShiftDetailResponse {
  success: boolean;
  skift_typ: string;
  datum: string;
  start_tid: string;
  slut_tid: string;
  min_bemanning: number;
  max_bemanning: number;
  operatorer: ShiftDetailOperator[];
  antal_bemanning: number;
  status: string;
  planerad_kapacitet: number;
  faktisk_produktion: number | null;
}

export interface DagKapacitet {
  datum: string;
  dag_namn: string;
  bemanning: number;
  min_krav: number;
  bemanningsgrad: number;
}

export interface CapacityResponse {
  success: boolean;
  dag_data: DagKapacitet[];
  min_per_dag: number;
  ibc_per_timme: number;
  skift_konfiguration: any[];
}

export interface OperatorItem {
  id: number;
  namn: string;
}

export interface OperatorsResponse {
  success: boolean;
  operatorer: OperatorItem[];
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class SkiftplaneringService {
  private api = `${environment.apiUrl}?action=skiftplanering`;

  constructor(private http: HttpClient) {}

  getOverview(): Observable<SkiftOverviewResponse | null> {
    return this.http.get<SkiftOverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  getSchedule(week?: string): Observable<ScheduleResponse | null> {
    let url = `${this.api}&run=schedule`;
    if (week) url += `&week=${week}`;
    return this.http.get<ScheduleResponse>(
      url,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  getShiftDetail(shift: string, date: string): Observable<ShiftDetailResponse | null> {
    return this.http.get<ShiftDetailResponse>(
      `${this.api}&run=shift-detail&shift=${shift}&date=${date}`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  getCapacity(): Observable<CapacityResponse | null> {
    return this.http.get<CapacityResponse>(
      `${this.api}&run=capacity`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  getOperators(): Observable<OperatorsResponse | null> {
    return this.http.get<OperatorsResponse>(
      `${this.api}&run=operators`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  assignOperator(operatorId: number, skiftTyp: string, datum: string): Observable<any> {
    return this.http.post(
      `${this.api}&run=assign`,
      { operator_id: operatorId, skift_typ: skiftTyp, datum },
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }

  unassignOperator(schemaId: number): Observable<any> {
    return this.http.post(
      `${this.api}&run=unassign`,
      { schema_id: schemaId },
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }
}
