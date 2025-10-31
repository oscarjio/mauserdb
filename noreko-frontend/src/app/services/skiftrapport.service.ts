import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class SkiftrapportService {
  constructor(private http: HttpClient) {}

  getSkiftrapporter(): Observable<any> {
    return this.http.get<any>('/noreko-backend/api.php?action=skiftrapport', { withCredentials: true });
  }

  getProducts(): Observable<any> {
    return this.http.get<any>('/noreko-backend/api.php?action=rebotlingproduct', { withCredentials: true });
  }

  createSkiftrapport(report: any): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=skiftrapport', {
      action: 'create',
      ...report
    }, { withCredentials: true });
  }

  deleteSkiftrapport(id: number): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=skiftrapport', {
      action: 'delete',
      id
    }, { withCredentials: true });
  }

  bulkDelete(ids: number[]): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=skiftrapport', {
      action: 'bulkDelete',
      ids
    }, { withCredentials: true });
  }

  updateInlagd(id: number, inlagd: boolean): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=skiftrapport', {
      action: 'updateInlagd',
      id,
      inlagd
    }, { withCredentials: true });
  }

  bulkUpdateInlagd(ids: number[], inlagd: boolean): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=skiftrapport', {
      action: 'bulkUpdateInlagd',
      ids,
      inlagd
    }, { withCredentials: true });
  }

  updateSkiftrapport(id: number, data: any): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=skiftrapport', {
      action: 'update',
      id,
      ...data
    }, { withCredentials: true });
  }
}

