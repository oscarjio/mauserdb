import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';

// ---- Interfaces ----

export interface MaskinOverview {
  antal_maskiner: number;
  kommande_inom_sju: number;
  forsenade: number;
  snitt_intervall_dagar: number;
}

export interface MaskinOverviewResponse {
  success: boolean;
  data: MaskinOverview;
}

export interface MaskinItem {
  id: number;
  namn: string;
  beskrivning: string | null;
  service_intervall_dagar: number;
  senaste_service: string | null;
  senaste_utfort_av: string | null;
  senaste_typ: string | null;
  nasta_service: string | null;
  dagar_kvar: number | null;
  status: 'gron' | 'gul' | 'rod';
}

export interface MaskinListResponse {
  success: boolean;
  maskiner: MaskinItem[];
}

export interface ServiceHistoryItem {
  id: number;
  maskin_id: number;
  service_datum: string;
  service_typ: 'planerat' | 'akut' | 'inspektion';
  service_typ_label: string;
  beskrivning: string | null;
  utfort_av: string | null;
  nasta_planerad_datum: string | null;
  created_at: string;
}

export interface MachineHistoryResponse {
  success: boolean;
  maskin: {
    id: number;
    namn: string;
    beskrivning: string | null;
    service_intervall_dagar: number;
  };
  historik: ServiceHistoryItem[];
}

export interface TimelineItem {
  maskin_id: number;
  namn: string;
  intervall: number;
  dagar_sedan: number | null;
  dagar_kvar: number | null;
  senaste_service: string | null;
  nasta_service: string | null;
  forbrukad_pct: number;
  status: 'gron' | 'gul' | 'rod';
}

export interface TimelineResponse {
  success: boolean;
  items: TimelineItem[];
}

export interface AddServiceData {
  maskin_id: number;
  service_datum: string;
  service_typ: 'planerat' | 'akut' | 'inspektion';
  beskrivning?: string;
  utfort_av?: string;
  nasta_planerad_datum?: string;
}

export interface AddMachineData {
  namn: string;
  beskrivning?: string;
  service_intervall_dagar: number;
}

// ---- Service ----

@Injectable({ providedIn: 'root' })
export class MaskinunderhallService {
  private api = '../../noreko-backend/api.php?action=maskinunderhall';

  constructor(private http: HttpClient) {}

  getOverview(): Observable<MaskinOverviewResponse | null> {
    return this.http.get<MaskinOverviewResponse>(
      `${this.api}&run=overview`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  getMachines(): Observable<MaskinListResponse | null> {
    return this.http.get<MaskinListResponse>(
      `${this.api}&run=machines`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  getMachineHistory(maskinId: number): Observable<MachineHistoryResponse | null> {
    return this.http.get<MachineHistoryResponse>(
      `${this.api}&run=machine-history&maskin_id=${maskinId}`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  getTimeline(): Observable<TimelineResponse | null> {
    return this.http.get<TimelineResponse>(
      `${this.api}&run=timeline`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of(null))
    );
  }

  addService(data: AddServiceData): Observable<any> {
    return this.http.post(
      `${this.api}&run=add-service`,
      data,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }

  addMachine(data: AddMachineData): Observable<any> {
    return this.http.post(
      `${this.api}&run=add-machine`,
      data,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Okänt fel' }))
    );
  }
}
