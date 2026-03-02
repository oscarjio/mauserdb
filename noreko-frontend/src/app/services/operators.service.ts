import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class OperatorsService {
  private apiUrl = '/noreko-backend/api.php?action=operators';

  constructor(private http: HttpClient) {}

  getOperators(): Observable<any> {
    return this.http.get<any>(this.apiUrl, { withCredentials: true });
  }

  createOperator(data: { name: string; number: number }): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'create', ...data }, { withCredentials: true });
  }

  updateOperator(data: { id: number; name: string; number: number }): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'update', ...data }, { withCredentials: true });
  }

  deleteOperator(id: number): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'delete', id }, { withCredentials: true });
  }

  toggleActive(id: number): Observable<any> {
    return this.http.post<any>(this.apiUrl, { action: 'toggleActive', id }, { withCredentials: true });
  }

  getStats(): Observable<any> {
    return this.http.get<any>(this.apiUrl + '&run=stats', { withCredentials: true });
  }
}
