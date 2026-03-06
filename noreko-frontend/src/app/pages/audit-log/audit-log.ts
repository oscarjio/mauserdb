import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { AuditService, AuditEntry, AuditStats } from '../../services/audit.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-audit-log',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './audit-log.html',
  styleUrl: './audit-log.css'
})
export class AuditLogPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  isAdmin = false;
  user: any = null;

  logs: AuditEntry[] = [];
  stats: AuditStats | null = null;
  loading = false;
  exportingAll = false;
  errorMessage = '';

  // Filters
  selectedPeriod = 'month';
  filterAction = '';
  filterUser = '';
  searchText = '';
  fromDate = '';
  toDate = '';
  showDateRange = false;

  // Dynamic action list from API
  availableActions: string[] = [];

  // Pagination
  currentPage = 1;
  totalPages = 1;
  totalCount = 0;
  hasMore = false;
  loadingMore = false;
  readonly pageSize = 50;

  activeTab: 'log' | 'stats' = 'log';
  expandedId: number | null = null;

  private activityChart: Chart | null = null;
  private chartTimer: any = null;
  private searchTimer: any = null;

  constructor(
    private auth: AuthService,
    private auditService: AuditService
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe((val: any) => {
      this.user = val;
      const wasAdmin = this.isAdmin;
      this.isAdmin = val?.role === 'admin';
      if (this.isAdmin && !wasAdmin) {
        this.loadLogs();
        this.loadAvailableActions();
      }
    });
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    clearTimeout(this.chartTimer);
    clearTimeout(this.searchTimer);
    try { this.activityChart?.destroy(); } catch (e) {}
    this.activityChart = null;
  }

  loadAvailableActions() {
    this.auditService.getActions().pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) this.availableActions = res.data;
      },
      error: () => {}
    });
  }

  buildParams() {
    const params: any = {
      page: this.currentPage,
      limit: this.pageSize,
      filter_action: this.filterAction,
      filter_user: this.filterUser,
      search: this.searchText
    };
    if (this.showDateRange && (this.fromDate || this.toDate)) {
      params.period = 'custom';
      if (this.fromDate) params.from_date = this.fromDate;
      if (this.toDate)   params.to_date   = this.toDate;
    } else {
      params.period = this.selectedPeriod;
    }
    return params;
  }

  loadLogs() {
    this.loading = true;
    this.errorMessage = '';
    this.auditService.getLogs(this.buildParams()).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.logs = res.data;
          this.totalCount = res.total;
          this.totalPages = res.pages;
          this.hasMore = res.hasMore ?? false;
        }
        this.loading = false;
      },
      error: () => { this.errorMessage = 'Kunde inte hämta loggar. Försök igen.'; this.loading = false; }
    });
  }

  loadMore() {
    if (!this.hasMore || this.loadingMore) return;
    this.loadingMore = true;
    this.currentPage++;
    this.auditService.getLogs(this.buildParams()).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.logs = [...this.logs, ...res.data];
          this.totalCount = res.total;
          this.totalPages = res.pages;
          this.hasMore = res.hasMore ?? false;
        }
        this.loadingMore = false;
      },
      error: () => this.loadingMore = false
    });
  }

  loadStats() {
    const period = (!this.showDateRange) ? this.selectedPeriod : 'month';
    this.auditService.getStats(period).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.stats = res.data;
          clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.buildActivityChart();
          }, 100);
        }
      },
      error: () => {}
    });
  }

  switchTab(tab: 'log' | 'stats') {
    this.activeTab = tab;
    if (tab === 'stats') this.loadStats();
  }

  onFilterChange() {
    this.currentPage = 1;
    this.loadLogs();
    if (this.activeTab === 'stats') this.loadStats();
  }

  onSearchInput() {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      if (!this.destroy$.closed) this.onFilterChange();
    }, 350);
  }

  toggleDateRange() {
    this.showDateRange = !this.showDateRange;
    this.onFilterChange();
  }

  goToPage(page: number) {
    if (page < 1 || page > this.totalPages) return;
    this.currentPage = page;
    this.loadLogs();
  }

  toggleExpand(id: number) {
    this.expandedId = this.expandedId === id ? null : id;
  }

  /** Export ALL matching records (no pagination) as CSV */
  exportCSV() {
    this.exportingAll = true;
    const params = { ...this.buildParams(), page: 1, limit: 2000 };
    this.auditService.getLogs(params).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        this.exportingAll = false;
        const entries: AuditEntry[] = res.success ? res.data : this.logs;
        if (entries.length === 0) return;
        const header = ['ID', 'Tidpunkt', 'Användare', 'Åtgärd', 'Entitet', 'Entitet-ID', 'Beskrivning', 'IP'];
        const rows = entries.map(e => [
          e.id,
          (e.created_at || '').substring(0, 19).replace('T', ' '),
          e.user,
          this.getActionLabel(e.action),
          e.entity_type,
          e.entity_id ?? '',
          e.description || '',
          e.ip_address || ''
        ]);
        const csv = [header, ...rows]
          .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';'))
          .join('\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = `audit-log-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        URL.revokeObjectURL(url);
      },
      error: () => {
        this.exportingAll = false;
      }
    });
  }

  // ----------------------------------------------------------------
  // Badge helpers
  // ----------------------------------------------------------------

  /** Returns CSS class name for action badge */
  getActionBadgeClass(action: string): string {
    const a = action.toLowerCase();
    if (a === 'login')        return 'badge-grey';
    if (a === 'logout')       return 'badge-grey';
    if (a === 'login_failed') return 'badge-orange';
    if (a.includes('delete') || a.includes('bulk_delete')) return 'badge-red';
    if (a.includes('create') || a.includes('register'))    return 'badge-green';
    if (a.includes('update') || a.includes('toggle') || a.includes('set') || a.includes('approve')) return 'badge-blue';
    return 'badge-grey';
  }

  getActionLabel(action: string): string {
    const labels: Record<string, string> = {
      'create_user':                   'Skapa användare',
      'delete_user':                   'Ta bort användare',
      'toggle_admin':                  'Ändra admin-status',
      'toggle_active':                 'Ändra aktiv-status',
      'update_user':                   'Uppdatera användare',
      'update_weights':                'Uppdatera vikter',
      'set_targets':                   'Ändra mål',
      'approve_bonuses':               'Godkänn bonus',
      'create_skiftrapport':           'Skapa skiftrapport',
      'delete_skiftrapport':           'Ta bort skiftrapport',
      'bulk_delete_skiftrapport':      'Massbort skiftrapporter',
      'update_skiftrapport':           'Uppdatera skiftrapport',
      'create_rapport':                'Skapa linjerapport',
      'update_rapport':                'Uppdatera linjerapport',
      'delete_rapport':                'Ta bort linjerapport',
      'bulk_delete_rapport':           'Massbort linjerapporter',
      'create_stoppage':               'Registrera stoppost',
      'update_stoppage':               'Uppdatera stoppost',
      'delete_stoppage':               'Ta bort stoppost',
      'vpn_update':                    'Uppdatera VPN',
      'product_create':                'Skapa produkt',
      'product_update':                'Uppdatera produkt',
      'product_delete':                'Ta bort produkt',
      'register':                      'Registrering',
      'update_profile':                'Uppdatera profil',
      'update_tvattlinje_settings':    'Uppdatera tvättlinjeinst.',
      'update_inlagd':                 'Markera inlagd',
      'bulk_update_inlagd':            'Massmarkera inlagd',
      'login':                         'Inloggning',
      'logout':                        'Utloggning',
      'login_failed':                  'Misslyckad inloggning'
    };
    return labels[action] || action;
  }

  getActionIcon(action: string): string {
    const icons: Record<string, string> = {
      'create_user':            'fa-user-plus',
      'delete_user':            'fa-user-minus',
      'toggle_admin':           'fa-user-shield',
      'toggle_active':          'fa-user-check',
      'update_user':            'fa-user-edit',
      'update_weights':         'fa-balance-scale',
      'set_targets':            'fa-bullseye',
      'approve_bonuses':        'fa-check-double',
      'create_skiftrapport':    'fa-plus-circle',
      'delete_skiftrapport':    'fa-trash',
      'bulk_delete_skiftrapport': 'fa-trash-alt',
      'update_skiftrapport':    'fa-edit',
      'create_rapport':         'fa-plus-circle',
      'update_rapport':         'fa-edit',
      'delete_rapport':         'fa-trash',
      'bulk_delete_rapport':    'fa-trash-alt',
      'create_stoppage':        'fa-exclamation-triangle',
      'update_stoppage':        'fa-edit',
      'delete_stoppage':        'fa-trash',
      'vpn_update':             'fa-network-wired',
      'product_create':         'fa-box',
      'product_update':         'fa-box',
      'product_delete':         'fa-box',
      'register':               'fa-user-plus',
      'update_profile':         'fa-id-card',
      'update_tvattlinje_settings': 'fa-sliders-h',
      'update_inlagd':          'fa-check-circle',
      'bulk_update_inlagd':     'fa-check-double',
      'login':                  'fa-sign-in-alt',
      'logout':                 'fa-sign-out-alt',
      'login_failed':           'fa-ban'
    };
    return icons[action] || 'fa-cog';
  }

  getActionColor(action: string): string {
    const cls = this.getActionBadgeClass(action);
    switch (cls) {
      case 'badge-red':    return '#ef4444';
      case 'badge-green':  return '#22c55e';
      case 'badge-blue':   return '#3b82f6';
      case 'badge-orange': return '#f97316';
      default:             return '#a0aec0';
    }
  }

  parseJson(value: string | null): any {
    if (!value) return null;
    try { return JSON.parse(value); } catch { return value; }
  }

  formatJsonKeys(obj: any): string[] {
    if (!obj || typeof obj !== 'object') return [];
    return Object.keys(obj);
  }

  get auditStats(): { total: number; today: number; lastUser: string } {
    const today = new Date().toISOString().slice(0, 10);
    const todayLogs = (this.logs || []).filter((l: any) => (l.created_at || '').startsWith(today));
    const lastUser = this.logs?.[0]?.user || '—';
    return { total: this.logs?.length || 0, today: todayLogs.length, lastUser };
  }

  get pageNumbers(): number[] {
    const total = this.totalPages;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const cur = this.currentPage;
    const pages = new Set<number>([1, total, cur]);
    if (cur > 1)     pages.add(cur - 1);
    if (cur < total) pages.add(cur + 1);
    return Array.from(pages).sort((a, b) => a - b);
  }

  private buildActivityChart() {
    try { this.activityChart?.destroy(); } catch (e) {}
    this.activityChart = null;
    const canvas = document.getElementById('activityChart') as HTMLCanvasElement;
    if (!canvas || !this.stats) return;

    const daily = this.stats.daily;
    this.activityChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: daily.map(d => d.dag),
        datasets: [{
          label: 'Aktiviteter per dag',
          data: daily.map(d => d.count),
          backgroundColor: 'rgba(0, 212, 255, 0.5)',
          borderColor: '#00d4ff',
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          title: { display: true, text: 'Aktivitet per dag', color: '#e2e8f0' },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              title: (items) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx >= 0 && daily[idx]) {
                  const d = new Date(daily[idx].dag + 'T00:00:00');
                  const dayNames = ['Son', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor'];
                  return `${dayNames[d.getDay()]} ${daily[idx].dag}`;
                }
                return '';
              },
              label: (item) => {
                const count = item.parsed.y;
                if (count == null) return '';
                return `  ${count} aktivitet${count !== 1 ? 'er' : ''}`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0', stepSize: 1 }, grid: { color: '#2d3748' }, beginAtZero: true }
        }
      }
    });
  }
}
