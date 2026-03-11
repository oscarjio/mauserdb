import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

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
  private base = '/noreko-backend/api.php?action=stopporsak-reg';

  constructor(private http: HttpClient) {}

  getCategories(): Observable<{ success: boolean; data: StopporsakKategori[] }> {
    return this.http.get<any>(`${this.base}&run=categories`, { withCredentials: true });
  }

  registerStop(categoryId: number, kommentar?: string, linje: string = 'rebotling'): Observable<{ success: boolean; message: string; id?: number }> {
    return this.http.post<any>(
      `${this.base}&run=register`,
      { category_id: categoryId, kommentar: kommentar ?? '', linje },
      { withCredentials: true }
    );
  }

  getActiveStops(linje: string = 'rebotling'): Observable<{ success: boolean; data: StopporsakRegistrering[] }> {
    return this.http.get<any>(`${this.base}&run=active&linje=${linje}`, { withCredentials: true });
  }

  endStop(id: number): Observable<{ success: boolean; message: string; end_time?: string }> {
    return this.http.post<any>(
      `${this.base}&run=end-stop`,
      { id },
      { withCredentials: true }
    );
  }

  getRecent(limit: number = 20, linje: string = 'rebotling'): Observable<{ success: boolean; data: StopporsakRegistrering[] }> {
    return this.http.get<any>(`${this.base}&run=recent&limit=${limit}&linje=${linje}`, { withCredentials: true });
  }
}
