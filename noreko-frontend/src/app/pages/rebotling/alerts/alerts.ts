import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import {
  AlertsService,
  Alert,
  AlertSettings,
  AlertType,
} from '../../../services/alerts.service';
import { parseLocalDate } from '../../../utils/date-utils';

@Component({
  standalone: true,
  selector: 'app-alerts',
  templateUrl: './alerts.html',
  imports: [CommonModule, FormsModule],
})
export class AlertsPage implements OnInit, OnDestroy {
  // Flikar
  activeTab: 'active' | 'history' | 'settings' = 'active';

  // Aktiva alerts
  activeAlerts: Alert[] = [];
  activeLoading = true;
  activeError: string | null = null;

  // Historik
  historyAlerts: Alert[] = [];
  historyLoading = false;
  historyError: string | null = null;
  historyDays = 30;
  historyFilterType: string = '';
  historyFilterSeverity: string = '';

  // Inställningar
  settings: AlertSettings = {
    oee_low:    { threshold_value: 60, enabled: true, updated_at: null },
    stop_long:  { threshold_value: 30, enabled: true, updated_at: null },
    scrap_high: { threshold_value: 10, enabled: true, updated_at: null },
  };
  settingsLoading = false;
  settingsError: string | null = null;
  settingsSaved = false;
  savingSettings = false;

  // Alert-check
  checkLoading = false;
  checkResult: string | null = null;

  // Acknowledging
  acknowledgingIds = new Set<number>();

  private destroy$ = new Subject<void>();
  private pollTimer: ReturnType<typeof setInterval> | null = null;

  constructor(private alertsService: AlertsService) {}

  ngOnInit(): void {
    this.loadActiveAlerts();
    this.loadSettings();

    // Poll var 60:e sekund
    this.pollTimer = setInterval(() => {
      if (this.activeTab === 'active') {
        this.loadActiveAlerts(false);
      }
    }, 60_000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollTimer !== null) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
  }

  // ----------------------------------------------------------------
  // Tab-hantering
  // ----------------------------------------------------------------

  setTab(tab: 'active' | 'history' | 'settings'): void {
    this.activeTab = tab;
    if (tab === 'history' && this.historyAlerts.length === 0) {
      this.loadHistory();
    }
  }

  // ----------------------------------------------------------------
  // Aktiva alerts
  // ----------------------------------------------------------------

  loadActiveAlerts(showLoading = true): void {
    if (showLoading) this.activeLoading = true;
    this.activeError = null;

    this.alertsService.getActiveAlerts()
      .pipe(
        timeout(10_000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.activeLoading = false;
        if (res?.success) {
          this.activeAlerts = res.data?.alerts ?? [];
        } else {
          this.activeError = 'Kunde inte hämta aktiva varningar.';
        }
      });
  }

  acknowledge(alert: Alert): void {
    this.acknowledgingIds.add(alert.id);

    this.alertsService.acknowledgeAlert(alert.id)
      .pipe(
        timeout(10_000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.acknowledgingIds.delete(alert.id);
        if (res?.success) {
          this.activeAlerts = this.activeAlerts.filter(a => a.id !== alert.id);
        }
      });
  }

  runCheck(): void {
    this.checkLoading = true;
    this.checkResult = null;

    this.alertsService.checkAlerts()
      .pipe(
        timeout(15_000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.checkLoading = false;
        if (res?.success) {
          const n = res.data?.alerts_created ?? 0;
          this.checkResult = n === 0
            ? 'Inga nya varningar skapades.'
            : `${n} ny${n !== 1 ? 'a' : ''} varning${n !== 1 ? 'ar' : ''} skapades.`;
          this.loadActiveAlerts(false);
        } else {
          this.checkResult = 'Kontroll misslyckades.';
        }
      });
  }

  // ----------------------------------------------------------------
  // Historik
  // ----------------------------------------------------------------

  loadHistory(): void {
    this.historyLoading = true;
    this.historyError = null;

    this.alertsService.getAlertHistory(this.historyDays)
      .pipe(
        timeout(10_000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.historyLoading = false;
        if (res?.success) {
          this.historyAlerts = res.data?.alerts ?? [];
        } else {
          this.historyError = 'Kunde inte hämta historik.';
        }
      });
  }

  get filteredHistory(): Alert[] {
    return this.historyAlerts.filter(a => {
      if (this.historyFilterType && a.type !== this.historyFilterType) return false;
      if (this.historyFilterSeverity && a.severity !== this.historyFilterSeverity) return false;
      return true;
    });
  }

  // ----------------------------------------------------------------
  // Inställningar
  // ----------------------------------------------------------------

  loadSettings(): void {
    this.settingsLoading = true;
    this.settingsError = null;

    this.alertsService.getAlertSettings()
      .pipe(
        timeout(10_000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.settingsLoading = false;
        if (res?.success && res.data?.settings) {
          this.settings = res.data.settings;
        }
      });
  }

  saveSettings(): void {
    this.savingSettings = true;
    this.settingsSaved = false;
    this.settingsError = null;

    this.alertsService.saveAlertSettings(this.settings)
      .pipe(
        timeout(10_000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.savingSettings = false;
        if (res?.success) {
          this.settingsSaved = true;
          setTimeout(() => (this.settingsSaved = false), 3000);
        } else {
          this.settingsError = 'Kunde inte spara inställningar.';
        }
      });
  }

  // ----------------------------------------------------------------
  // Hjälpmetoder för template
  // ----------------------------------------------------------------

  typeLabel(type: AlertType | string): string {
    return this.alertsService.typeLabel(type as AlertType);
  }

  typeIcon(type: AlertType | string): string {
    return this.alertsService.typeIcon(type as AlertType);
  }

  severityClass(severity: string): string {
    return severity === 'critical' ? 'danger' : 'warning';
  }

  severityLabel(severity: string): string {
    return severity === 'critical' ? 'Kritisk' : 'Varning';
  }

  isAcknowledging(id: number): boolean {
    return this.acknowledgingIds.has(id);
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '—';
    const d = parseLocalDate(dateStr);
    return d.toLocaleString('sv-SE', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit',
    });
  }

  timeAgo(dateStr: string): string {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - parseLocalDate(dateStr).getTime()) / 1000);
    if (diff < 60)  return `${diff} sek sedan`;
    if (diff < 3600) return `${Math.floor(diff / 60)} min sedan`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} tim sedan`;
    return `${Math.floor(diff / 86400)} dagar sedan`;
  }

  thresholdUnit(type: string): string {
    if (type === 'stop_long') return 'min';
    return '%';
  }

  thresholdDescription(type: string): string {
    const desc: Record<string, string> = {
      oee_low:    'Varning om OEE sjunker under detta värde',
      stop_long:  'Varning om ett stopp pågår längre än detta antal minuter',
      scrap_high: 'Varning om kassationsrate överstiger detta värde',
    };
    return desc[type] ?? '';
  }
  trackByIndex(index: number): number { return index; }
}
