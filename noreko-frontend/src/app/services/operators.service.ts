import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class OperatorsService {
  private apiUrl = `${environment.apiUrl}?action=operators`;

  constructor(private http: HttpClient) {}

  getOperators(): Observable<any> {
    return this.http.get<any>(this.apiUrl, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }

  createOperator(data: { name: string; number: number }): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'create', ...data }, { withCredentials: true }).pipe(
      timeout(15000), catchError(err => { console.error('createOperator failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); })
    );
  }

  updateOperator(data: { id: number; name: string; number: number }): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'update', ...data }, { withCredentials: true }).pipe(
      timeout(15000), catchError(err => { console.error('updateOperator failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); })
    );
  }

  deleteOperator(id: number): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'delete', id }, { withCredentials: true }).pipe(
      timeout(15000), catchError(err => { console.error('deleteOperator failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); })
    );
  }

  toggleActive(id: number): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'toggleActive', id }, { withCredentials: true }).pipe(
      timeout(15000), catchError(err => { console.error('toggleActive failed', err); return of({ success: false, error: err?.error?.error || 'Nätverksfel' }); })
    );
  }

  getStats(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}&run=stats`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }

  getTrend(opNumber: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}&run=trend&op_number=${opNumber}`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }

  getPairs(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}&run=pairs`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }

  getMachineCompatibility(days: number = 90): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}&run=machine-compatibility&days=${days}`, { withCredentials: true }).pipe(
      timeout(15000), retry(1), catchError(() => of(null))
    );
  }
}
