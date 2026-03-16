import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
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
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  createReport(line: LineName, data: any): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'create', ...data }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  updateReport(line: LineName, id: number, data: any): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'update', id, ...data }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  deleteReport(line: LineName, id: number): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'delete', id }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  updateInlagd(line: LineName, id: number, inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'updateInlagd', id, inlagd }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  bulkDelete(line: LineName, ids: number[]): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'bulkDelete', ids }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }

  bulkUpdateInlagd(line: LineName, ids: number[], inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'bulkUpdateInlagd', ids, inlagd }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)));
  }
}
