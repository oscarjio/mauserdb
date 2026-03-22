import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface Favorit {
  id: number;
  route: string;
  label: string;
  icon: string;
  color: string;
  sort_order: number;
}

export interface FavoriterResponse {
  success: boolean;
  data: Favorit[];
  timestamp?: string;
}

export interface FavoritMutationResponse {
  success: boolean;
  data?: any;
  error?: string;
  timestamp?: string;
}

/** Tillgängliga sidor att bokmärka */
export interface AvailablePage {
  route: string;
  label: string;
  icon: string;
  color: string;
}

/** Alla sidor som VD:n kan lägga till som favoriter */
export const AVAILABLE_PAGES: AvailablePage[] = [
  { route: '/rebotling/live', label: 'Rebotling Live', icon: 'fas fa-broadcast-tower', color: '#48bb78' },
  { route: '/rebotling/skiftrapport', label: 'Skiftrapport', icon: 'fas fa-file-alt', color: '#4299e1' },
  { route: '/rebotling/statistik', label: 'Statistik', icon: 'fas fa-chart-bar', color: '#ed8936' },
  { route: '/rebotling/kassationsanalys', label: 'Kassationsanalys', icon: 'fas fa-trash-alt', color: '#fc8181' },
  { route: '/rebotling/produktionspuls', label: 'Produktionspuls', icon: 'fas fa-heartbeat', color: '#e53e3e' },
  { route: '/rebotling/produktionstakt', label: 'Produktionstakt', icon: 'fas fa-tachometer-alt', color: '#4299e1' },
  { route: '/rebotling/benchmarking', label: 'Benchmarking', icon: 'fas fa-trophy', color: '#ecc94b' },
  { route: '/rebotling/daglig-sammanfattning', label: 'Daglig sammanfattning', icon: 'fas fa-tachometer-alt', color: '#63b3ed' },
  { route: '/rebotling/produktionskalender', label: 'Produktionskalender', icon: 'fas fa-calendar-alt', color: '#48bb78' },
  { route: '/rebotling/veckorapport', label: 'Veckorapport', icon: 'fas fa-file-alt', color: '#68d391' },
  { route: '/rebotling/morgonrapport', label: 'Morgonrapport', icon: 'fas fa-sun', color: '#fbd38d' },
  { route: '/rebotling/effektivitet', label: 'Maskineffektivitet', icon: 'fas fa-bolt', color: '#ecc94b' },
  { route: '/rebotling/oee-benchmark', label: 'OEE Benchmark', icon: 'fas fa-chart-pie', color: '#4fd1c5' },
  { route: '/rebotling/pareto', label: 'Pareto-analys', icon: 'fas fa-chart-bar', color: '#f6ad55' },
  { route: '/rebotling/produktions-heatmap', label: 'Produktions-heatmap', icon: 'fas fa-th', color: '#68d391' },
  { route: '/rebotling/oee-waterfall', label: 'OEE-analys', icon: 'fas fa-chart-bar', color: '#4fd1c5' },
  { route: '/rebotling/skiftjamforelse', label: 'Skiftjamforelse', icon: 'fas fa-people-arrows', color: '#ed8936' },
  { route: '/rebotling/malhistorik', label: 'Malhistorik', icon: 'fas fa-bullseye', color: '#4fd1c5' },
  { route: '/rebotling/underhallsprognos', label: 'Underhallsprognos', icon: 'fas fa-tools', color: '#fc5c65' },
  { route: '/rebotling/produktionsmal', label: 'Produktionsmal', icon: 'fas fa-bullseye', color: '#48bb78' },
  { route: '/rebotling/utnyttjandegrad', label: 'Utnyttjandegrad', icon: 'fas fa-gauge-high', color: '#4299e1' },
  { route: '/rebotling/alarm-historik', label: 'Alarm-historik', icon: 'fas fa-bell', color: '#f6ad55' },
  { route: '/rebotling/ranking-historik', label: 'Ranking-historik', icon: 'fas fa-trophy', color: '#ecc94b' },
  { route: '/rebotling/drifttids-timeline', label: 'Drifttids-timeline', icon: 'fas fa-stream', color: '#4fd1c5' },
  { route: '/rebotling/produktionsprognos', label: 'Produktionsprognos', icon: 'fas fa-chart-line', color: '#63b3ed' },
  { route: '/rebotling/operator-jamforelse', label: 'Operatorsjamforelse', icon: 'fas fa-users', color: '#4299e1' },
  { route: '/rebotling/produktionseffektivitet', label: 'Produktionseffektivitet/h', icon: 'fas fa-clock', color: '#68d391' },
  { route: '/oversikt', label: 'VD-oversikt', icon: 'fas fa-tachometer-alt', color: '#63b3ed' },
  { route: '/rapporter/manad', label: 'Manadsrapport', icon: 'fas fa-calendar-alt', color: '#4299e1' },
  { route: '/rapporter/vecka', label: 'Veckorapport', icon: 'fas fa-calendar-week', color: '#68d391' },
  { route: '/tvattlinje/live', label: 'Tvattlinje Live', icon: 'fas fa-broadcast-tower', color: '#4fd1c5' },
  { route: '/saglinje/live', label: 'Saglinje Live', icon: 'fas fa-broadcast-tower', color: '#ed8936' },
  { route: '/klassificeringslinje/live', label: 'Klassificeringslinje Live', icon: 'fas fa-broadcast-tower', color: '#9f7aea' },
  { route: '/rebotling/cykeltid-heatmap', label: 'Cykeltids-heatmap', icon: 'fas fa-th', color: '#63b3ed' },
  { route: '/rebotling/forsta-timme-analys', label: 'Forsta timmen', icon: 'fas fa-stopwatch', color: '#63b3ed' },
  { route: '/rebotling/stopporsak-operator', label: 'Stopporsak per operator', icon: 'fas fa-exclamation-triangle', color: '#fc8181' },
];

@Injectable({ providedIn: 'root' })
export class FavoriterService {
  private api = `${environment.apiUrl}?action=favoriter`;

  constructor(private http: HttpClient) {}

  list(): Observable<FavoriterResponse> {
    return this.http.get<FavoriterResponse>(`${this.api}&run=list`, { withCredentials: true })
      .pipe(timeout(8000), retry(1), catchError(() => of({ success: false, data: [] })));
  }

  add(route: string, label: string, icon: string, color: string): Observable<FavoritMutationResponse> {
    return this.http.post<FavoritMutationResponse>(
      `${this.api}&run=add`,
      { route, label, icon, color },
      { withCredentials: true }
    ).pipe(timeout(8000), catchError(err => of({ success: false, error: err?.error?.error || 'Kunde inte spara' })));
  }

  remove(id: number): Observable<FavoritMutationResponse> {
    return this.http.post<FavoritMutationResponse>(
      `${this.api}&run=remove`,
      { id },
      { withCredentials: true }
    ).pipe(timeout(8000), catchError(err => of({ success: false, error: err?.error?.error || 'Kunde inte ta bort' })));
  }

  reorder(ids: number[]): Observable<FavoritMutationResponse> {
    return this.http.post<FavoritMutationResponse>(
      `${this.api}&run=reorder`,
      { ids },
      { withCredentials: true }
    ).pipe(timeout(8000), catchError(err => of({ success: false, error: err?.error?.error || 'Kunde inte ordna om' })));
  }
}
