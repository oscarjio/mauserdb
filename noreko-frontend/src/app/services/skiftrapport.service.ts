import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class SkiftrapportService {
  private api = `${environment.apiUrl}?action=skiftrapport`;

  constructor(private http: HttpClient) {}

  getSkiftrapporter(): Observable<any> {
    return this.http.get<any>(this.api, { withCredentials: true }).pipe(
      timeout(15000), catchError(() => of(null))
    );
  }

  getProducts(): Observable<any> {
    return this.http.get<any>(`${environment.apiUrl}?action=rebotlingproduct`, { withCredentials: true }).pipe(
      timeout(10000), catchError(() => of(null))
    );
  }

  createSkiftrapport(report: any): Observable<any> {
    return this.http.post<any>(this.api, {
      action: 'create',
      ...report
    }, { withCredentials: true }).pipe(
      timeout(15000), catchError(() => of({ success: false, error: 'Natverksfel' }))
    );
  }

  deleteSkiftrapport(id: number): Observable<any> {
    return this.http.post<any>(this.api, {
      action: 'delete',
      id
    }, { withCredentials: true }).pipe(
      timeout(10000), catchError(() => of({ success: false, error: 'Natverksfel' }))
    );
  }

  bulkDelete(ids: number[]): Observable<any> {
    return this.http.post<any>(this.api, {
      action: 'bulkDelete',
      ids
    }, { withCredentials: true }).pipe(
      timeout(15000), catchError(() => of({ success: false, error: 'Natverksfel' }))
    );
  }

  updateInlagd(id: number, inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.api, {
      action: 'updateInlagd',
      id,
      inlagd
    }, { withCredentials: true }).pipe(
      timeout(10000), catchError(() => of({ success: false, error: 'Natverksfel' }))
    );
  }

  bulkUpdateInlagd(ids: number[], inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.api, {
      action: 'bulkUpdateInlagd',
      ids,
      inlagd
    }, { withCredentials: true }).pipe(
      timeout(15000), catchError(() => of({ success: false, error: 'Natverksfel' }))
    );
  }

  updateSkiftrapport(id: number, data: any): Observable<any> {
    return this.http.post<any>(this.api, {
      action: 'update',
      id,
      ...data
    }, { withCredentials: true }).pipe(
      timeout(15000), catchError(() => of({ success: false, error: 'Natverksfel' }))
    );
  }

  getLopnummer(skiftraknare: number, datum?: string): Observable<any> {
    let url = `${this.api}&run=lopnummer&skiftraknare=${skiftraknare}`;
    if (datum) url += `&datum=${encodeURIComponent(datum)}`;
    return this.http.get<any>(url, { withCredentials: true }).pipe(
      timeout(10000), catchError(() => of(null))
    );
  }
}
