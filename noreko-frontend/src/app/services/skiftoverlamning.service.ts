import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

const API = '/noreko-backend/api.php?action=skiftoverlamning';

export interface SkiftNote {
  id: number;
  skiftraknare: number;
  linje: string;
  note_text: string;
  user_id: number | null;
  username: string | null;
  created_at: string;
}

export interface SkiftSummary {
  success: boolean;
  error?: string;
  skiftraknare: number;
  skift_datum: string;
  skift_start: string;
  skift_slut: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  ibc_total: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  cykeltid_sek: number;
  drifttid_min: number;
  stopptid_min: number;
  stopptid_pct: number;
  rast_min: number;
  notes: SkiftNote[];
  prev_skift: number | null;
  next_skift: number | null;
}

export interface SkiftHistoryItem {
  skiftraknare: number;
  skift_datum: string;
  skift_start: string;
  skift_slut: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  ibc_total: number;
  kvalitet_pct: number;
  ibc_per_timme: number;
  drifttid_min: number;
}

export interface SkiftHistoryResponse {
  success: boolean;
  error?: string;
  history: SkiftHistoryItem[];
}

export interface AddNoteResponse {
  success: boolean;
  error?: string;
  note?: SkiftNote;
}

export interface NotesResponse {
  success: boolean;
  error?: string;
  notes: SkiftNote[];
}

@Injectable({ providedIn: 'root' })
export class SkiftoverlamningService {
  constructor(private http: HttpClient) {}

  getSummary(skiftraknare?: number): Observable<SkiftSummary> {
    const params = skiftraknare ? `&skiftraknare=${skiftraknare}` : '';
    return this.http.get<SkiftSummary>(`${API}&run=summary${params}`, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Nätverksfel' } as SkiftSummary))
    );
  }

  getNotes(skiftraknare: number, linje = 'rebotling'): Observable<NotesResponse> {
    return this.http.get<NotesResponse>(
      `${API}&run=notes&skiftraknare=${skiftraknare}&linje=${linje}`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Nätverksfel', notes: [] } as NotesResponse))
    );
  }

  addNote(skiftraknare: number, noteText: string, linje = 'rebotling'): Observable<AddNoteResponse> {
    return this.http.post<AddNoteResponse>(
      `${API}&run=add-note`,
      { skiftraknare, note_text: noteText, linje },
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Nätverksfel' } as AddNoteResponse))
    );
  }

  getHistory(days = 7): Observable<SkiftHistoryResponse> {
    return this.http.get<SkiftHistoryResponse>(
      `${API}&run=history&days=${days}`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Nätverksfel', history: [] } as SkiftHistoryResponse))
    );
  }
}
