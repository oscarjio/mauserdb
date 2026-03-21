import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface StopporsakKategori {
  id: number;
  namn: string;
  ikon: string;
  sort_order: number;
}

export interface StopporsakRegistrering {
  id: number;
  kategori_id: number;
  kategori_namn: string;
  ikon: string;
  linje: string;
  kommentar: string | null;
  user_id: number;
  operator_namn: string | null;
  start_time: string;
  end_time: string | null;
  varaktighet_minuter?: number | null;
}

@Injectable({ providedIn: 'root' })
export class StopporsakRegistreringService {
  private base = `${environment.apiUrl}?action=stopporsak-reg`;

  constructor(private http: HttpClient) {}

  getCategories(): Observable<{ success: boolean; data: StopporsakKategori[] } | null> {
    return this.http.get<{ success: boolean; data: StopporsakKategori[] }>(`${this.base}&run=categories`, { withCredentials: true }).pipe(
      timeout(10000), retry(1), catchError(() => of(null))
    );
  }

  registerStop(categoryId: number, kommentar?: string, linje: string = 'rebotling'): Observable<{ success: boolean; message: string; id?: number } | null> {
    return this.http.post<{ success: boolean; message: string; id?: number }>(
      `${this.base}&run=register`,
      { category_id: categoryId, kommentar: kommentar ?? '', linje },
      { withCredentials: true }
    ).pipe(
      timeout(10000), catchError(err => { console.error('registerStop failed', err); return of({ success: false, message: err?.error?.error || 'Nätverksfel' }); })
    );
  }

  getActiveStops(linje: string = 'rebotling'): Observable<{ success: boolean; data: StopporsakRegistrering[] } | null> {
    return this.http.get<{ success: boolean; data: StopporsakRegistrering[] }>(`${this.base}&run=active&linje=${linje}`, { withCredentials: true }).pipe(
      timeout(10000), retry(1), catchError(() => of(null))
    );
  }

  endStop(id: number): Observable<{ success: boolean; message: string; end_time?: string } | null> {
    return this.http.post<{ success: boolean; message: string; end_time?: string }>(
      `${this.base}&run=end-stop`,
      { id },
      { withCredentials: true }
    ).pipe(
      timeout(10000), catchError(err => { console.error('endStop failed', err); return of({ success: false, message: err?.error?.error || 'Nätverksfel' }); })
    );
  }

  getRecent(limit: number = 20, linje: string = 'rebotling'): Observable<{ success: boolean; data: StopporsakRegistrering[] } | null> {
    return this.http.get<{ success: boolean; data: StopporsakRegistrering[] }>(`${this.base}&run=recent&limit=${limit}&linje=${linje}`, { withCredentials: true }).pipe(
      timeout(10000), retry(1), catchError(() => of(null))
    );
  }
}
