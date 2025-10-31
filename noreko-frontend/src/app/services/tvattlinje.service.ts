import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface LineStatusResponse {
  success: boolean;
  data: {
    running: boolean;
    lastUpdate: string | null;
  };
}

@Injectable({ providedIn: 'root' })
export class TvattlinjeService {
  constructor(private http: HttpClient) {}

  getRunningStatus(): Observable<LineStatusResponse> {
    return this.http.get<LineStatusResponse>(
      '/noreko-backend/api.php?action=tvattlinje&run=status',
      { withCredentials: true }
    );
  }
}

