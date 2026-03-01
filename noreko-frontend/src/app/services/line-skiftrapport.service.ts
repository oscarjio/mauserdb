import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export type LineName = 'tvattlinje' | 'saglinje' | 'klassificeringslinje';

@Injectable({ providedIn: 'root' })
export class LineSkiftrapportService {
  private baseUrl = '/noreko-backend/api.php?action=lineskiftrapport';

  constructor(private http: HttpClient) {}

  private url(line: LineName): string {
    return `${this.baseUrl}&line=${line}`;
  }

  getReports(line: LineName): Observable<any> {
    return this.http.get<any>(this.url(line), { withCredentials: true });
  }

  createReport(line: LineName, data: any): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'create', ...data }, { withCredentials: true });
  }

  updateReport(line: LineName, id: number, data: any): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'update', id, ...data }, { withCredentials: true });
  }

  deleteReport(line: LineName, id: number): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'delete', id }, { withCredentials: true });
  }

  updateInlagd(line: LineName, id: number, inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'updateInlagd', id, inlagd }, { withCredentials: true });
  }

  bulkDelete(line: LineName, ids: number[]): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'bulkDelete', ids }, { withCredentials: true });
  }

  bulkUpdateInlagd(line: LineName, ids: number[], inlagd: boolean): Observable<any> {
    return this.http.post<any>(this.url(line), { action: 'bulkUpdateInlagd', ids, inlagd }, { withCredentials: true });
  }
}
