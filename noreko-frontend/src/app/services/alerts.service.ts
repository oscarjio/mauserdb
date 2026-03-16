import { Injectable, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, Subject, timer } from 'rxjs';
import { catchError, of, switchMap, takeUntil, timeout } from 'rxjs';
import { environment } from '../../environments/environment';

// ================================================================
// Interfaces
// ================================================================

export type AlertType = 'oee_low' | 'stop_long' | 'scrap_high';
export type AlertSeverity = 'warning' | 'critical';

export interface Alert {
  id: number;
  type: AlertType;
  message: string;
  value: number | null;
  threshold: number | null;
  severity: AlertSeverity;
  acknowledged?: boolean;
  acknowledged_at?: string | null;
  acknowledged_by_name?: string | null;
  created_at: string;
}

export interface AlertSetting {
  threshold_value: number;
  enabled: boolean;
  updated_at: string | null;
}

export interface AlertSettings {
  oee_low: AlertSetting;
  stop_long: AlertSetting;
  scrap_high: AlertSetting;
}

export interface ActiveAlertsResponse {
  success: boolean;
  data: {
    alerts: Alert[];
    count: number;
  };
  timestamp: string;
}

export interface AlertHistoryResponse {
  success: boolean;
  data: {
    alerts: Alert[];
    count: number;
    days: number;
    since: string;
  };
  timestamp: string;
}

export interface AlertSettingsResponse {
  success: boolean;
  data: {
    settings: AlertSettings;
  };
  timestamp: string;
}

export interface AlertCheckResponse {
  success: boolean;
  data: {
    checked: boolean;
    alerts_created: number;
    active_count: number;
  };
  timestamp: string;
}

// ================================================================
// Service
// ================================================================

@Injectable({ providedIn: 'root' })
export class AlertsService implements OnDestroy {
  private readonly base = `${environment.apiUrl}?action=alerts`;
  private readonly POLL_INTERVAL_MS = 60_000; // 60 sekunder

  /** BehaviorSubject med aktiva alerts — uppdateras var 60:e sekund */
  readonly activeAlerts$ = new BehaviorSubject<Alert[]>([]);

  /** Antal aktiva alerts — bekvämlighetsproperty */
  readonly activeCount$ = new BehaviorSubject<number>(0);

  private destroy$ = new Subject<void>();
  private pollStarted = false;

  constructor(private http: HttpClient) {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ----------------------------------------------------------------
  // Polling — starta automatisk uppdatering av activeAlerts$
  // ----------------------------------------------------------------

  startPolling(): void {
    if (this.pollStarted) return;
    this.pollStarted = true;

    // Kör omedelbart, sedan var 60:e sekund
    timer(0, this.POLL_INTERVAL_MS)
      .pipe(
        takeUntil(this.destroy$),
        switchMap(() =>
          this.getActiveAlerts().pipe(catchError(() => of(null)))
        )
      )
      .subscribe((res) => {
        if (res?.success) {
          const alerts = res.data?.alerts ?? [];
          this.activeAlerts$.next(alerts);
          this.activeCount$.next(alerts.length);
        }
      });
  }

  stopPolling(): void {
    this.destroy$.next();
    this.pollStarted = false;
  }

  // ----------------------------------------------------------------
  // API-metoder
  // ----------------------------------------------------------------

  getActiveAlerts(): Observable<ActiveAlertsResponse> {
    return this.http.get<ActiveAlertsResponse>(
      `${this.base}&run=active`,
      { withCredentials: true }
    ).pipe(timeout(10_000));
  }

  getAlertHistory(days: number = 30): Observable<AlertHistoryResponse> {
    return this.http.get<AlertHistoryResponse>(
      `${this.base}&run=history&days=${days}`,
      { withCredentials: true }
    ).pipe(timeout(10_000));
  }

  acknowledgeAlert(id: number): Observable<any> {
    return this.http.post<any>(
      `${this.base}&run=acknowledge&id=${id}`,
      {},
      { withCredentials: true }
    ).pipe(timeout(10_000));
  }

  getAlertSettings(): Observable<AlertSettingsResponse> {
    return this.http.get<AlertSettingsResponse>(
      `${this.base}&run=settings`,
      { withCredentials: true }
    ).pipe(timeout(10_000));
  }

  saveAlertSettings(settings: Partial<AlertSettings>): Observable<any> {
    return this.http.post<any>(
      `${this.base}&run=settings`,
      settings,
      { withCredentials: true }
    ).pipe(timeout(10_000));
  }

  checkAlerts(): Observable<AlertCheckResponse> {
    return this.http.get<AlertCheckResponse>(
      `${this.base}&run=check`,
      { withCredentials: true }
    ).pipe(timeout(10_000));
  }

  // ----------------------------------------------------------------
  // Hjälpmetoder
  // ----------------------------------------------------------------

  /** Human-readable etikett för alerttyp */
  typeLabel(type: AlertType): string {
    const labels: Record<AlertType, string> = {
      oee_low:    'Låg OEE',
      stop_long:  'Lång stopptid',
      scrap_high: 'Hög kassationsrate',
    };
    return labels[type] ?? type;
  }

  /** Ikon-klass (Font Awesome) för alerttyp */
  typeIcon(type: AlertType): string {
    const icons: Record<AlertType, string> = {
      oee_low:    'fas fa-chart-line',
      stop_long:  'fas fa-pause-circle',
      scrap_high: 'fas fa-trash-alt',
    };
    return icons[type] ?? 'fas fa-bell';
  }
}
