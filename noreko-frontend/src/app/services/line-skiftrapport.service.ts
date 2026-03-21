import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export type LineName = 'tvattlinje' | 'saglinje' | 'klassificeringslinje';

@Injectable({ providedIn: 'root' })
export class LineSkiftrapportService {
  private baseUrl = `${environment.apiUrl}?action=lineskiftrapport`;

  constructor(private http: HttpClient) {}

  private url(line: LineName): string {
    return `${this.baseUrl}&line=${line}`;
  }

  getReports(line: LineName): Observable<any> {
    return this.http.get<any>(this.url(line), { withCredentials: true })
      .pipe(timeout(15000), retry(1), catchError(() => of(null)));
  }

  createReport(line: LineName, data: any): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'create', ...data }, { withCredentials: true })
      .pipe(timeout(15000), catchError(err => { console.error('createReport failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }

  updateReport(line: LineName, id: number, data: any): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'update', id, ...data }, { withCredentials: true })
      .pipe(timeout(15000), catchError(err => { console.error('updateReport failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }

  deleteReport(line: LineName, id: number): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'delete', id }, { withCredentials: true })
      .pipe(timeout(15000), catchError(err => { console.error('deleteReport failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }

  updateInlagd(line: LineName, id: number, inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'updateInlagd', id, inlagd }, { withCredentials: true })
      .pipe(timeout(15000), catchError(err => { console.error('updateInlagd failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }

  bulkDelete(line: LineName, ids: number[]): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'bulkDelete', ids }, { withCredentials: true })
      .pipe(timeout(15000), catchError(err => { console.error('bulkDelete failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }

  bulkUpdateInlagd(line: LineName, ids: number[], inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'bulkUpdateInlagd', ids, inlagd }, { withCredentials: true })
      .pipe(timeout(15000), catchError(err => { console.error('bulkUpdateInlagd failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); }));
  }
}
