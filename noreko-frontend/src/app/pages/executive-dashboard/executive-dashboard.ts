import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, Subscription } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { RebotlingService, ExecDashboardResponse, MaintenanceStatsResponse, FeedbackSummaryResponse, FeedbackSummaryDayEntry } from '../../services/rebotling.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

interface NewsSnippet {
  id: number;
  title: string;
  category: string;
  created_at: string;
}

@Component({
  selector: 'app-executive-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './executive-dashboard.html',
  styleUrls: ['./executive-dashboard.css']
})
export class ExecutiveDashboardPage implements OnInit, OnDestroy {
  Math = Math;
  loggedIn = false;
  isAdmin = false;
  loading = true;
  isFetching = false;

  dashData: ExecDashboardResponse['data'] | null = null;
  lastRefresh: Date = new Date();
  lastUpdated: Date | null = null;

  alerts: { type: 'danger' | 'warning' | 'info'; message: string; detail: string }[] = [];

  // Certifikat-utgångvarning
  certExpiryCount: number = 0;

  // Serviceintervall-varning
  serviceWarnings: { maskin_namn: string; procent_kvar: number; kvar: number; status: string }[] = [];

  // Multi-line status
  allLinesStatus: any[] = [];
  private isFetchingLines = false;
  private linesStatusInterval: any = null;
  private linesSub: Subscription | null = null;

  // Senaste nyheter
  latestNews: NewsSnippet[] = [];
  private isFetchingNews = false;

  // Underhållskostnad KPI
  maintenanceCost: number = 0;
  maintenanceCount: number = 0;
  maintenanceDurationMin: number = 0;
  private isFetchingMaintenance = false;

  // Teamstämning
  feedbackAvgStamning: number | null = null;
  feedbackPerDag: FeedbackSummaryDayEntry[] = [];
  private isFetchingFeedback = false;
  private moodChart: Chart | null = null;
  private moodChartTimer: any = null;

  private pollInterval: any;
  private dataSub: Subscription | null = null;
  private barChart: Chart | null = null;
  private barChartTimer: any = null;
  private destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private rebotlingService: RebotlingService,
    private http: HttpClient
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe((val: any) => {
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit(): void {
    this.loadData();
    this.loadAllLinesStatus();
    this.loadCertExpiry();
    this.loadServiceWarnings();
    this.loadLatestNews();
    this.loadMaintenanceStats();
    this.loadFeedbackSummary();
    this.pollInterval = setInterval(() => {
      this.loadData();
      this.loadMaintenanceStats();
      this.loadFeedbackSummary();
    }, 30000);
    this.linesStatusInterval = setInterval(() => this.loadAllLinesStatus(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) clearInterval(this.pollInterval);
    if (this.linesStatusInterval) clearInterval(this.linesStatusInterval);
    clearTimeout(this.barChartTimer);
    clearTimeout(this.moodChartTimer);
    this.dataSub?.unsubscribe();
    this.linesSub?.unsubscribe();
    try { if (this.barChart) this.barChart.destroy(); } catch (e) {}
    this.barChart = null;
    try { if (this.moodChart) this.moodChart.destroy(); } catch (e) {}
    this.moodChart = null;
  }

  loadData(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.dataSub?.unsubscribe();
    this.dataSub = this.rebotlingService.getExecDashboard()
      .pipe(timeout(8000), catchError(() => of(null)))
      .subscribe({
        next: (res) => {
          if (res?.success && res.data) {
            this.dashData = res.data;
            this.lastRefresh = new Date();
            this.lastUpdated = new Date();
            this.computeAlerts();
            clearTimeout(this.barChartTimer);
            this.barChartTimer = setTimeout(() => {
              if (!this.destroy$.closed) this.buildBarChart();
            }, 100);
          }
          this.loading = false;
          this.isFetching = false;
        },
        error: () => {
          this.loading = false;
          this.isFetching = false;
        }
      });
  }

  loadAllLinesStatus(): void {
    if (this.isFetchingLines) return;
    this.isFetchingLines = true;

    this.linesSub?.unsubscribe();
    this.linesSub = this.rebotlingService.getAllLinesStatus()
      .pipe(timeout(8000), catchError(() => of(null)))
      .subscribe({
        next: (res) => {
          if (res?.success && res.lines) {
            this.allLinesStatus = res.lines;
          }
          this.isFetchingLines = false;
        },
        error: () => {
          this.isFetchingLines = false;
        }
      });
  }

  loadCertExpiry(): void {
    this.http.get<any>('/noreko-backend/api.php?action=certification&run=expiry-count',
      { withCredentials: true })
      .pipe(timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) this.certExpiryCount = res.count ?? 0;
      });
  }

  loadServiceWarnings(): void {
    this.http.get<any>('/noreko-backend/api.php?action=maintenance&run=service-intervals',
      { withCredentials: true })
      .pipe(timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success && res.intervals) {
          this.serviceWarnings = res.intervals
            .filter((s: any) => s.procent_kvar <= 25)
            .map((s: any) => ({
              maskin_namn: s.maskin_namn,
              procent_kvar: s.procent_kvar,
              kvar: s.kvar,
              status: s.status
            }));
        }
      });
  }

  loadLatestNews(): void {
    if (this.isFetchingNews) return;
    this.isFetchingNews = true;
    this.http.get<any>('/noreko-backend/api.php?action=news&run=admin-list',
      { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetchingNews = false;
        if (res?.success && res.news) {
          this.latestNews = (res.news as NewsSnippet[]).slice(0, 3);
        }
      });
  }

  // ---- Veckoframgångsmätare ----

  get veckoUppfyllnadPct(): number {
    if (!this.dashData?.week) return 0;
    const diff = this.dashData.week.week_diff_pct ?? 0;
    // Normalisera: förra veckan = 100%, denna vecka relativt
    // Om diff är +10% => denna vecka = 110% av förra => framgångspct = min(100, 110) = 100
    // Om diff är -20% => denna vecka = 80% av förra => framgångspct = 80
    return Math.min(100, Math.max(0, 100 + diff));
  }

  get veckoStatusClass(): string {
    const pct = this.veckoUppfyllnadPct;
    if (pct >= 95) return 'success';
    if (pct >= 80) return 'warning';
    return 'danger';
  }

  get veckoStatusText(): string {
    const diff = this.dashData?.week?.week_diff_pct ?? 0;
    if (diff >= 5) return 'Stark vecka';
    if (diff >= 0) return 'Stabil vecka';
    if (diff >= -10) return 'Under förra veckan';
    return 'Svag vecka';
  }

  get veckoTrendIcon(): string {
    const diff = this.dashData?.week?.week_diff_pct ?? 0;
    if (diff > 1) return 'fa-arrow-trend-up';
    if (diff < -1) return 'fa-arrow-trend-down';
    return 'fa-minus';
  }

  get veckoTrendClass(): string {
    const diff = this.dashData?.week?.week_diff_pct ?? 0;
    if (diff > 1) return 'text-success';
    if (diff < -1) return 'text-danger';
    return 'text-muted';
  }

  // ---- Nyhets-helpers ----

  getCategoryBadgeClass(category: string): string {
    const map: { [key: string]: string } = {
      produktion: 'bg-success',
      bonus: 'bg-warning text-dark',
      viktig: 'bg-danger',
      system: 'bg-info text-dark',
      info: 'bg-secondary'
    };
    return map[category] ?? 'bg-secondary';
  }

  getCategoryLabel(category: string): string {
    const map: { [key: string]: string } = {
      produktion: 'Produktion',
      bonus: 'Bonus',
      viktig: 'Viktig',
      system: 'System',
      info: 'Info'
    };
    return map[category] ?? category;
  }

  // ---- Linjestatus helpers ----

  getLineIconClass(line: any): string {
    if (line.ej_i_drift) return 'fas fa-circle text-secondary';
    if (!line.kor) return 'fas fa-circle text-secondary';
    if (line.senaste_data_min != null && line.senaste_data_min > 5) return 'fas fa-circle text-warning';
    return 'fas fa-circle text-success';
  }

  getLineRoute(line: any): string {
    return '/' + line.id + '/live';
  }

  // ---- Alert-beräkning ----

  private computeAlerts(): void {
    this.alerts = [];
    if (!this.dashData) return;

    const oee  = this.dashData.today?.oee_today ?? 0;
    const ibc  = this.dashData.today?.ibc ?? 0;
    const goal = this.dashData.today?.target ?? 0;
    const pct  = goal > 0 ? (ibc / goal * 100) : 100;

    if (oee < 70) {
      this.alerts.push({
        type: 'danger',
        message: 'OEE under kritisk nivå',
        detail: `OEE är ${oee.toFixed(1)}% — målet är ≥70%. Kontrollera stopptider och maskinprestanda.`
      });
    } else if (oee < 80) {
      this.alerts.push({
        type: 'warning',
        message: 'OEE under målnivå',
        detail: `OEE är ${oee.toFixed(1)}% — normalt ≥80%. Håll koll på produktionstakten.`
      });
    }

    if (pct < 60) {
      this.alerts.push({
        type: 'danger',
        message: 'Produktion kraftigt under mål',
        detail: `Endast ${ibc} IBC av ${goal} planerade (${pct.toFixed(0)}%). Åtgärd krävs.`
      });
    } else if (pct < 80) {
      this.alerts.push({
        type: 'warning',
        message: 'Produktion under mål',
        detail: `${ibc} IBC av ${goal} planerade (${pct.toFixed(0)}%). Håll ögonen på takten.`
      });
    }
  }

  // ---- Status helpers ----

  getTodayStatusClass(): string {
    const pct = this.dashData?.today?.pct ?? 0;
    if (pct >= 80) return 'status-green';
    if (pct >= 60) return 'status-yellow';
    return 'status-red';
  }

  getTodayStatusText(): string {
    const pct = this.dashData?.today?.pct ?? 0;
    if (pct >= 80) return 'Bra produktion';
    if (pct >= 60) return 'Under mål';
    return 'Kritiskt lag';
  }

  getCircleOffset(): number {
    // SVG circle: r=54, circumference = 2*PI*54 ≈ 339.3
    const circumference = 339.3;
    const pct = Math.min(this.dashData?.today?.pct ?? 0, 100);
    return circumference - (pct / 100) * circumference;
  }

  getCircleColor(): string {
    const pct = this.dashData?.today?.pct ?? 0;
    if (pct >= 80) return '#48bb78';
    if (pct >= 60) return '#f6c90e';
    return '#e53e3e';
  }

  getOeeTrendIcon(): string {
    const today = this.dashData?.today?.oee_today ?? 0;
    const yesterday = this.dashData?.today?.oee_yesterday ?? 0;
    if (today > yesterday + 1) return 'fa-arrow-up';
    if (today < yesterday - 1) return 'fa-arrow-down';
    return 'fa-minus';
  }

  getOeeTrendClass(): string {
    const today = this.dashData?.today?.oee_today ?? 0;
    const yesterday = this.dashData?.today?.oee_yesterday ?? 0;
    if (today > yesterday + 1) return 'text-success';
    if (today < yesterday - 1) return 'text-danger';
    return 'text-muted';
  }

  getOeeClass(oee: number): string {
    if (oee >= 80) return 'text-success';
    if (oee >= 60) return 'text-warning';
    return 'text-danger';
  }

  getWeekDiffClass(): string {
    const diff = this.dashData?.week?.week_diff_pct ?? 0;
    if (diff >= 0) return 'text-success';
    return 'text-danger';
  }

  getWeekDiffIcon(): string {
    const diff = this.dashData?.week?.week_diff_pct ?? 0;
    if (diff > 1) return 'fa-arrow-up';
    if (diff < -1) return 'fa-arrow-down';
    return 'fa-minus';
  }

  getQualityClass(q: number): string {
    if (q >= 95) return 'text-success';
    if (q >= 85) return 'text-warning';
    return 'text-danger';
  }

  getBonusClass(bonus: number): string {
    if (bonus >= 90) return 'text-success';
    if (bonus >= 70) return 'text-info';
    if (bonus >= 50) return 'text-warning';
    return 'text-danger';
  }

  formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    const days = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return days[d.getDay()] + ' ' + d.getDate() + '/' + (d.getMonth() + 1);
  }

  formatNewsDate(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.getDate() + '/' + (d.getMonth() + 1) + ' ' + d.getFullYear();
  }

  // ---- Bar chart ----

  private buildBarChart(): void {
    try {
      if (this.barChart) {
        this.barChart.destroy();
        this.barChart = null;
      }
    } catch (e) { this.barChart = null; }
    const canvas = document.getElementById('days7Chart') as HTMLCanvasElement;
    if (!canvas || !this.dashData?.days7?.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const days = this.dashData.days7;
    const labels = days.map(d => this.formatDate(d.date));
    const ibc = days.map(d => d.ibc);
    const target = days[0]?.target ?? 1000;
    const colors = days.map(d => d.ibc >= target ? '#48bb78' : '#e53e3e');
    const dimColors = days.map(d => d.ibc >= target ? 'rgba(72,187,120,0.75)' : 'rgba(229,62,62,0.75)');

    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC',
            data: ibc,
            backgroundColor: dimColors,
            borderColor: colors,
            borderWidth: 2,
            borderRadius: 6
          },
          {
            label: 'Dagsmål',
            data: new Array(days.length).fill(target),
            type: 'line',
            borderColor: '#667eea',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
            tension: 0
          } as any
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 } }
          },
          tooltip: {
            callbacks: {
              afterBody: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? 0;
                const d = days[idx];
                const pct = d.target > 0 ? Math.round(d.ibc / d.target * 100) : 0;
                return [`${pct}% av dagsmål`];
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096' },
            grid: { color: '#2d3748' }
          },
          y: {
            ticks: { color: '#718096' },
            grid: { color: '#2d3748' },
            beginAtZero: true
          }
        }
      }
    });
  }

  // ---- Underhållskostnad ----

  loadMaintenanceStats(): void {
    if (this.isFetchingMaintenance) return;
    this.isFetchingMaintenance = true;

    this.rebotlingService.getMaintenanceStats()
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetchingMaintenance = false;
        if (res?.success && res.stats) {
          this.maintenanceCost = +res.stats.total_cost || 0;
          this.maintenanceCount = +res.stats.total_events || 0;
          this.maintenanceDurationMin = +res.stats.total_minutes || 0;
        }
      });
  }

  formatMaintenanceCost(): string {
    return this.maintenanceCost.toLocaleString('sv-SE', { maximumFractionDigits: 0 });
  }

  formatDuration(): string {
    const h = Math.floor(this.maintenanceDurationMin / 60);
    const m = this.maintenanceDurationMin % 60;
    return h + ':' + (m < 10 ? '0' : '') + m;
  }

  // ---- Teamstämning ----

  loadFeedbackSummary(): void {
    if (this.isFetchingFeedback) return;
    this.isFetchingFeedback = true;

    this.rebotlingService.getFeedbackSummary()
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetchingFeedback = false;
        if (res?.success) {
          this.feedbackAvgStamning = res.avg_stamning;
          this.feedbackPerDag = res.per_dag ?? [];
          clearTimeout(this.moodChartTimer);
          this.moodChartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.buildMoodChart();
          }, 150);
        }
      });
  }

  getStamningEmoji(): string {
    const v = this.feedbackAvgStamning ?? 0;
    if (v >= 3.5) return '\u{1F60A}';
    if (v >= 2.5) return '\u{1F642}';
    if (v >= 1.5) return '\u{1F610}';
    return '\u{1F61F}';
  }

  getStamningBgClass(): string {
    const v = this.feedbackAvgStamning ?? 0;
    if (v > 3.0) return 'stamning-bg-green';
    if (v >= 2.0) return 'stamning-bg-yellow';
    return 'stamning-bg-red';
  }

  private buildMoodChart(): void {
    try {
      if (this.moodChart) {
        this.moodChart.destroy();
        this.moodChart = null;
      }
    } catch (e) { this.moodChart = null; }

    const canvas = document.getElementById('moodTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.feedbackPerDag.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Sort ascending by date
    const sorted = [...this.feedbackPerDag].sort((a, b) => a.datum.localeCompare(b.datum));
    const labels = sorted.map(d => {
      const dt = new Date(d.datum);
      return dt.getDate() + '/' + (dt.getMonth() + 1);
    });
    const data = sorted.map(d => +d.snitt);

    this.moodChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Stämning',
          data,
          borderColor: '#667eea',
          backgroundColor: 'rgba(102,126,234,0.15)',
          borderWidth: 2,
          pointRadius: 2,
          pointHoverRadius: 5,
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            ticks: { color: '#718096', font: { size: 10 }, maxTicksLimit: 10 },
            grid: { color: '#2d3748' }
          },
          y: {
            min: 1,
            max: 4,
            ticks: { color: '#718096', stepSize: 1 },
            grid: { color: '#2d3748' }
          }
        }
      }
    });
  }

  printDashboard(): void {
    window.print();
  }
}
