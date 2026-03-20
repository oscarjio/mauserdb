import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, filter, switchMap } from 'rxjs/operators';
import { OperatorsService } from '../../services/operators.service';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { parseLocalDate } from '../../utils/date-utils';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

type SortField = 'name' | 'ibc_per_hour' | 'avg_quality' | 'shifts' | 'senaste_aktivitet';
type SortDir = 'asc' | 'desc';
type ActivityStatus = 'active' | 'recent' | 'inactive' | 'never';

@Component({
  standalone: true,
  selector: 'app-operators',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operators.html',
  styleUrl: './operators.css'
})
export class OperatorsPage implements OnInit, OnDestroy {
  operators: any[] = [];
  expanded: { [id: number]: boolean } = {};
  loading = false;
  error = '';
  showAddForm = false;
  addForm: { name: string; number: number | null } = { name: '', number: null };

  // Stats / ranking
  opStats: any[] = [];
  opStatsLoading = false;
  statsLoaded = false;

  // Korrelationsanalys — operatörspar
  pairsData: any[] = [];
  pairsLoading = false;
  showPairs = false;

  // Kompatibilitetsmatris — operatör × produkt
  compatData: any[] = [];
  compatLoading = false;
  showCompat = false;
  compatOperators: { id: number; namn: string }[] = [];
  compatProducts: { id: number; namn: string }[] = [];
  compatMatrix: { [key: string]: any } = {};
  compatGlobalMaxIbc = 0;
  compatGlobalMinIbc = 999;

  // Sök + sortering
  searchText = '';
  filterStatus: 'all' | 'active' | 'inactive' = 'all';
  sortField: SortField = 'ibc_per_hour';
  sortDir: SortDir = 'desc';

  // Detaljvy
  expandedStatId: number | null = null;
  trendData: { [id: number]: any[] } = {};
  trendLoading: { [id: number]: boolean } = {};
  private trendCharts: { [id: number]: Chart | null } = {};
  private trendTimers: { [id: number]: any } = {};

  private destroy$ = new Subject<void>();

  constructor(
    private operatorsService: OperatorsService,
    private auth: AuthService,
    private router: Router,
    private toast: ToastService
  ) {}

  ngOnInit() {
    this.auth.initialized$.pipe(
      filter(init => init === true),
      switchMap(() => this.auth.user$),
      takeUntil(this.destroy$)
    ).subscribe(user => {
      if (!user || user.role !== 'admin') {
        this.router.navigate(['/']);
      }
    });
    this.fetchOperators();
    this.loadOpStats();
    this.loadPairs();
    this.loadCompatibility();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    // Förstör alla trenddiagram
    Object.values(this.trendCharts).forEach(c => { try { c?.destroy(); } catch (e) {} });
    Object.values(this.trendTimers).forEach(t => clearTimeout(t));
  }

  // ======== Filtrera + sortera stats ========

  get filteredStats(): any[] {
    let result = this.opStats;

    if (this.searchText.trim()) {
      const q = this.searchText.toLowerCase();
      result = result.filter(s =>
        (s.name || '').toLowerCase().includes(q) ||
        String(s.number || '').includes(q)
      );
    }

    result = [...result].sort((a, b) => {
      let aVal: any;
      let bVal: any;
      switch (this.sortField) {
        case 'name':
          aVal = (a.name || '').toLowerCase();
          bVal = (b.name || '').toLowerCase();
          break;
        case 'ibc_per_hour':
          aVal = a.ibc_per_hour ?? -1;
          bVal = b.ibc_per_hour ?? -1;
          break;
        case 'avg_quality':
          aVal = a.avg_quality ?? -1;
          bVal = b.avg_quality ?? -1;
          break;
        case 'shifts':
          aVal = a.shifts ?? 0;
          bVal = b.shifts ?? 0;
          break;
        case 'senaste_aktivitet':
          aVal = a.senaste_aktivitet ? new Date(a.senaste_aktivitet).getTime() : -1;
          bVal = b.senaste_aktivitet ? new Date(b.senaste_aktivitet).getTime() : -1;
          break;
        default:
          aVal = a.ibc_per_hour ?? -1;
          bVal = b.ibc_per_hour ?? -1;
      }
      if (aVal < bVal) return this.sortDir === 'asc' ? -1 : 1;
      if (aVal > bVal) return this.sortDir === 'asc' ? 1 : -1;
      return 0;
    });

    return result;
  }

  // ======== Filtrera operatörslistan (admin-listan) ========

  get filteredOperators(): any[] {
    let result = this.operators;
    if (this.filterStatus === 'active') {
      result = result.filter(op => op.active == 1);
    } else if (this.filterStatus === 'inactive') {
      result = result.filter(op => op.active == 0);
    }
    if (this.searchText.trim()) {
      const q = this.searchText.toLowerCase();
      result = result.filter(op =>
        (op.name || '').toLowerCase().includes(q) ||
        String(op.number || '').includes(q)
      );
    }
    return result;
  }

  sortBy(field: SortField) {
    if (this.sortField === field) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortField = field;
      this.sortDir = field === 'name' ? 'asc' : 'desc';
    }
  }

  sortIcon(field: SortField): string {
    if (this.sortField !== field) return 'fa-sort';
    return this.sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
  }

  // ======== Operatörs-initialer (avatar) ========

  getInitials(name: string): string {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
  }

  getAvatarColor(name: string): string {
    const colors = [
      '#4299e1', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea',
      '#00b5d8', '#d69e2e', '#38a169', '#e53e3e', '#667eea'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  // ======== Status-badge ========

  getActivityStatus(s: any): ActivityStatus {
    return (s.activity_status as ActivityStatus) || 'never';
  }

  getActivityLabel(s: any): string {
    switch (this.getActivityStatus(s)) {
      case 'active':   return 'Aktiv';
      case 'recent':   return 'Nyligen aktiv';
      case 'inactive': return 'Inaktiv';
      default:         return 'Aldrig jobbat';
    }
  }

  getActivityClass(s: any): string {
    switch (this.getActivityStatus(s)) {
      case 'active':   return 'status-active';
      case 'recent':   return 'status-recent';
      case 'inactive': return 'status-inactive';
      default:         return 'status-never';
    }
  }

  // ======== Detaljvy toggle ========

  toggleStatDetail(s: any) {
    const id = s.id;
    if (this.expandedStatId === id) {
      this.expandedStatId = null;
      return;
    }
    this.expandedStatId = id;
    if (!this.trendData[id]) {
      this.loadTrend(s);
    } else {
      // Bygg om diagram när detaljvyn öppnas (canvas kan ha återskapats)
      clearTimeout(this.trendTimers[id]);
      this.trendTimers[id] = setTimeout(() => {
        if (this.destroy$.closed) return;
        if (this.expandedStatId === id) this.buildTrendChart(id);
      }, 100);
    }
  }

  private loadTrend(s: any) {
    const id = s.id;
    this.trendLoading[id] = true;
    this.operatorsService.getTrend(s.number).pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of({ success: false, data: [] }))).subscribe({
      next: (res) => {
        this.trendLoading[id] = false;
        this.trendData[id] = res.success ? res.data : [];
        clearTimeout(this.trendTimers[id]);
        this.trendTimers[id] = setTimeout(() => {
          if (this.destroy$.closed) return;
          if (this.expandedStatId === id) this.buildTrendChart(id);
        }, 100);
      },
      error: () => {
        this.trendLoading[id] = false;
        this.trendData[id] = [];
      }
    });
  }

  private buildTrendChart(id: number) {
    if (this.trendCharts[id]) {
      try { this.trendCharts[id]?.destroy(); } catch (e) {}
      this.trendCharts[id] = null;
    }

    const canvas = document.getElementById('trendChart_' + id) as HTMLCanvasElement;
    const data = this.trendData[id];
    if (!canvas || !data || data.length === 0) return;

    const labels = data.map(d => 'V' + d.week_num);
    const ibcH   = data.map(d => d.ibc_per_hour ?? 0);
    const qual   = data.map(d => d.avg_quality ?? null);

    this.trendCharts[id] = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h',
            data: ibcH,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointBackgroundColor: '#4299e1',
            yAxisID: 'y'
          },
          {
            label: 'Kvalitet %',
            data: qual,
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72,187,120,0.08)',
            fill: false,
            tension: 0.35,
            pointRadius: 4,
            pointBackgroundColor: '#48bb78',
            yAxisID: 'y1',
            spanGaps: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ctx.dataset.label + ': ' + v;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: '#2d3748' }
          },
          y: {
            position: 'left',
            beginAtZero: true,
            ticks: { color: '#4299e1', font: { size: 11 } },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'IBC/h', color: '#4299e1', font: { size: 10 } }
          },
          y1: {
            position: 'right',
            min: 0,
            max: 100,
            ticks: { color: '#48bb78', font: { size: 11 } },
            grid: { display: false },
            title: { display: true, text: 'Kvalitet %', color: '#48bb78', font: { size: 10 } }
          }
        }
      }
    });
  }

  // ======== Operatörslistan (admin-hantering) ========

  fetchOperators() {
    this.loading = true;
    this.operatorsService.getOperators().pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null))).subscribe({
      next: (res) => {
        if (!res) { this.error = 'Kunde inte hämta operatörer.'; this.loading = false; return; }
        this.operators = res.operators || [];
        this.loading = false;
      },
      error: () => {
        this.error = 'Kunde inte hämta operatörer.';
        this.loading = false;
      }
    });
  }

  toggleExpand(id: number) {
    this.expanded[id] = !this.expanded[id];
  }

  saveOperator(op: any) {
    this.operatorsService.updateOperator({ id: op.id, name: op.name, number: op.number }).pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null))).subscribe({
      next: (res) => {
        if (!res) { this.toast.error('Kunde inte spara operatör.'); return; }
        if (res.success) {
          this.expanded[op.id] = false;
          this.toast.success('Operatör sparad');
          this.fetchOperators();
          this.loadOpStats();
        } else {
          this.toast.error('Kunde inte spara: ' + (res.message || 'Okänt fel'));
        }
      },
      error: (err: any) => {
        this.toast.error(err.error?.message || 'Kunde inte spara operatör.');
      }
    });
  }

  deleteOperator(op: any) {
    if (!confirm(`Är du säker på att du vill ta bort operatören "${op.name}"?`)) {
      return;
    }
    this.operatorsService.deleteOperator(op.id).pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null))).subscribe({
      next: (res) => {
        if (!res) { this.toast.error('Kunde inte ta bort operatör'); return; }
        if (res.success) {
          this.toast.success('Operatör borttagen');
          this.fetchOperators();
          this.loadOpStats();
        } else {
          this.toast.error(res.message || 'Kunde inte ta bort operatör');
        }
      },
      error: (err: any) => {
        this.toast.error(err.error?.message || 'Kunde inte ta bort operatör');
      }
    });
  }

  toggleActive(op: any) {
    this.operatorsService.toggleActive(op.id).pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null))).subscribe({
      next: (res) => {
        if (!res) { this.toast.error('Kunde inte ändra status'); return; }
        if (res.success) {
          op.active = res.active;
          this.fetchOperators();
        } else {
          this.toast.error(res.message || 'Kunde inte ändra status');
        }
      },
      error: (err: any) => {
        this.toast.error(err.error?.message || 'Kunde inte ändra status');
      }
    });
  }

  loadOpStats() {
    this.opStatsLoading = true;
    this.operatorsService.getStats().pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null))).subscribe({
      next: (res) => {
        if (!res) { this.opStatsLoading = false; return; }
        this.opStats = res.stats || [];
        this.opStatsLoading = false;
        this.statsLoaded = true;
      },
      error: () => {
        this.opStatsLoading = false;
      }
    });
  }

  loadPairs() {
    this.pairsLoading = true;
    this.operatorsService.getPairs().pipe(
      takeUntil(this.destroy$),
      timeout(8000),
      catchError(() => of({ success: false, pairs: [] }))
    ).subscribe({
      next: (res) => {
        this.pairsData = res.pairs || [];
        this.pairsLoading = false;
      },
      error: () => {
        this.pairsData = [];
        this.pairsLoading = false;
      }
    });
  }

  createOperator() {
    if (!this.addForm.name.trim() || !this.addForm.number || this.addForm.number <= 0) {
      this.toast.error('Namn och giltigt nummer krävs');
      return;
    }
    this.operatorsService.createOperator({ name: this.addForm.name.trim(), number: this.addForm.number }).pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null))).subscribe({
      next: (res) => {
        if (!res) { this.toast.error('Kunde inte skapa operatör.'); return; }
        if (res.success) {
          this.toast.success('Operatör skapad');
          this.addForm = { name: '', number: null };
          this.showAddForm = false;
          this.fetchOperators();
          this.loadOpStats();
        } else {
          this.toast.error('Kunde inte skapa: ' + (res.message || 'Okänt fel'));
        }
      },
      error: (err: any) => {
        this.toast.error(err.error?.message || 'Kunde inte skapa operatör.');
      }
    });
  }

  // ======== Senaste aktivitet — hjälpfunktioner ========

  formatSenasteAktivitet(datum: string | null): string {
    if (!datum) return 'Aldrig';
    return parseLocalDate(datum).toLocaleDateString('sv-SE');
  }

  getSenasteAktivitetClass(datum: string | null): string {
    if (!datum) return 'activity-never';
    const days = (Date.now() - parseLocalDate(datum).getTime()) / (1000 * 60 * 60 * 24);
    if (days < 7) return 'activity-green';
    if (days <= 30) return 'activity-yellow';
    return 'activity-red';
  }

  // ======== CSV-export ========

  exportToCSV() {
    const rows = this.filteredOperators.map(op => ({
      ID: op.id,
      Namn: op.name,
      Nummer: op.number,
      Status: op.active == 1 ? 'Aktiv' : 'Inaktiv',
      'Senast aktiv': op.senaste_aktivitet
        ? parseLocalDate(op.senaste_aktivitet).toLocaleDateString('sv-SE')
        : 'Aldrig',
      'Aktiva dagar (30d)': op.aktiva_dagar_30d ?? 0
    }));
    if (rows.length === 0) return;
    const headers = Object.keys(rows[0]).join(';');
    const csvRows = rows.map(r =>
      Object.values(r).map(v => '"' + String(v).replace(/"/g, '""') + '"').join(';')
    );
    const csv = [headers, ...csvRows].join('\n');
    const bom = '\uFEFF';
    const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'operatorer.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  // ======== Kompatibilitetsmatris ========

  loadCompatibility() {
    this.compatLoading = true;
    this.operatorsService.getMachineCompatibility(90).pipe(
      takeUntil(this.destroy$),
      timeout(8000),
      catchError(() => of({ success: false, data: [] }))
    ).subscribe({
      next: (res) => {
        this.compatLoading = false;
        this.compatData = res.data || [];
        this.buildCompatMatrix();
      },
      error: () => {
        this.compatLoading = false;
        this.compatData = [];
      }
    });
  }

  private buildCompatMatrix() {
    const opMap = new Map<number, string>();
    const prodMap = new Map<number, string>();
    this.compatMatrix = {};
    let maxIbc = 0;
    let minIbc = 999;

    for (const row of this.compatData) {
      opMap.set(row.operator_id, row.operator_namn);
      prodMap.set(row.produkt_id, row.produkt_namn);
      const key = row.operator_id + '_' + row.produkt_id;
      this.compatMatrix[key] = row;
      if (row.avg_ibc_per_h != null) {
        if (row.avg_ibc_per_h > maxIbc) maxIbc = row.avg_ibc_per_h;
        if (row.avg_ibc_per_h < minIbc) minIbc = row.avg_ibc_per_h;
      }
    }

    this.compatGlobalMaxIbc = maxIbc;
    this.compatGlobalMinIbc = minIbc > maxIbc ? 0 : minIbc;

    this.compatOperators = Array.from(opMap.entries())
      .map(([id, namn]) => ({ id, namn }))
      .sort((a, b) => a.namn.localeCompare(b.namn, 'sv'));

    this.compatProducts = Array.from(prodMap.entries())
      .map(([id, namn]) => ({ id, namn }))
      .sort((a, b) => a.namn.localeCompare(b.namn, 'sv'));
  }

  getCompatCell(opId: number, prodId: number): any | null {
    return this.compatMatrix[opId + '_' + prodId] || null;
  }

  getCompatCellColor(opId: number, prodId: number): string {
    const cell = this.getCompatCell(opId, prodId);
    if (!cell || cell.avg_ibc_per_h == null) return 'transparent';
    const range = this.compatGlobalMaxIbc - this.compatGlobalMinIbc;
    if (range <= 0) return 'rgba(72, 187, 120, 0.4)';
    const ratio = (cell.avg_ibc_per_h - this.compatGlobalMinIbc) / range;
    if (ratio >= 0.66) {
      const intensity = 0.25 + (ratio - 0.66) / 0.34 * 0.35;
      return 'rgba(72, 187, 120, ' + intensity.toFixed(2) + ')';
    } else if (ratio >= 0.33) {
      const intensity = 0.25 + (ratio - 0.33) / 0.33 * 0.3;
      return 'rgba(236, 201, 75, ' + intensity.toFixed(2) + ')';
    } else {
      const intensity = 0.2 + ratio / 0.33 * 0.3;
      return 'rgba(229, 62, 62, ' + intensity.toFixed(2) + ')';
    }
  }

  getCompatTooltip(opId: number, prodId: number): string {
    const cell = this.getCompatCell(opId, prodId);
    if (!cell) return 'Ingen data';
    const parts: string[] = [];
    parts.push('IBC/h: ' + (cell.avg_ibc_per_h != null ? cell.avg_ibc_per_h : '\u2013'));
    parts.push('Kvalitet: ' + (cell.avg_kvalitet != null ? cell.avg_kvalitet + '%' : '\u2013'));
    parts.push('OEE: ' + (cell.oee != null ? cell.oee : '\u2013'));
    parts.push('Skift: ' + cell.antal_skift);
    return parts.join(' | ');
  }

    // ======== KPI-hjälpfunktioner ========

  getIbcClass(val: number | null): string {
    if (val == null) return 'kpi-neutral';
    if (val >= 10) return 'kpi-good';
    if (val >= 5)  return 'kpi-warn';
    return 'kpi-bad';
  }

  getQualClass(val: number | null): string {
    if (val == null) return 'kpi-neutral';
    if (val >= 90) return 'kpi-good';
    if (val >= 70) return 'kpi-warn';
    return 'kpi-bad';
  }

  getRankMedal(idx: number): string {
    if (idx === 0) return 'gold';
    if (idx === 1) return 'silver';
    if (idx === 2) return 'bronze';
    return '';
  }
  trackByIndex(index: number): number { return index; }
}
