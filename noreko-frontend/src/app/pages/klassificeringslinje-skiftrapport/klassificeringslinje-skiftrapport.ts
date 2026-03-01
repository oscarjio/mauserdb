import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-klassificeringslinje-skiftrapport',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './klassificeringslinje-skiftrapport.html',
  styleUrl: './klassificeringslinje-skiftrapport.css'
})
export class KlassificeringslinjeSkiftrapportPage implements OnInit, OnDestroy {
  readonly line: LineName = 'klassificeringslinje';
  readonly lineName = 'Klassificeringslinje';

  reports: any[] = [];
  selectedIds: Set<number> = new Set();
  expanded: { [id: number]: boolean } = {};
  loading = false;
  errorMessage = '';
  successMessage = '';
  showSuccessMessage = false;
  isAdmin = false;
  user: any = null;
  loggedIn = false;
  showAddForm = false;

  filterFrom = '';
  filterTo = '';

  newReport = {
    datum: new Date().toISOString().split('T')[0],
    antal_ok: 0,
    antal_ej_ok: 0,
    kommentar: ''
  };

  private destroy$ = new Subject<void>();
  private fetchSub: Subscription | null = null;
  private updateInterval: any = null;
  private successTimerId: any = null;

  constructor(private service: LineSkiftrapportService, private auth: AuthService) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(v => this.loggedIn = v);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(u => { this.user = u; this.isAdmin = u?.role === 'admin'; });
    this.fetchReports();
    this.updateInterval = setInterval(() => { if (!this.destroy$.closed) this.fetchReports(true); }, 15000);
  }

  ngOnDestroy() {
    clearInterval(this.updateInterval);
    clearTimeout(this.successTimerId);
    this.fetchSub?.unsubscribe();
    this.destroy$.next();
    this.destroy$.complete();
  }

  get filteredReports(): any[] {
    return this.reports.filter(r => {
      const d = (r.datum || '').substring(0, 10);
      if (this.filterFrom && d < this.filterFrom) return false;
      if (this.filterTo && d > this.filterTo) return false;
      return true;
    });
  }

  clearFilter() { this.filterFrom = ''; this.filterTo = ''; }
  getQualityPct(r: any): number | null { return r.totalt ? Math.round((r.antal_ok / r.totalt) * 100) : null; }
  toggleSelect(id: number) { this.selectedIds.has(id) ? this.selectedIds.delete(id) : this.selectedIds.add(id); }
  toggleSelectAll() { const v = this.filteredReports; if (this.selectedIds.size === v.length && v.length > 0) this.selectedIds.clear(); else v.forEach(r => this.selectedIds.add(r.id)); }
  isSelected(id: number) { return this.selectedIds.has(id); }
  isOwner(r: any) { return this.user && r.user_id === this.user.id; }
  canEdit(r: any) { return this.isAdmin || this.isOwner(r); }
  toggleExpand(id: number) { this.expanded[id] = !this.expanded[id]; }

  fetchReports(silent = false) {
    if (!silent) this.loading = true;
    this.fetchSub?.unsubscribe();
    this.fetchSub = this.service.getReports(this.line).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (!silent) this.loading = false;
        if (res.success) {
          const nr = res.data || [];
          if (silent) { const ec = { ...this.expanded }; const sc = new Set(this.selectedIds); this.reports = nr; this.expanded = ec; this.selectedIds = new Set(Array.from(sc).filter(id => nr.some((r: any) => r.id === id))); }
          else this.reports = nr;
        } else this.errorMessage = res.message || 'Fel';
      },
      error: (err) => { if (!silent) this.loading = false; this.errorMessage = err.error?.message || 'Fel'; }
    });
  }

  addReport() {
    this.errorMessage = '';
    if (!this.newReport.datum) { this.errorMessage = 'Datum krävs'; return; }
    this.loading = true;
    this.service.createReport(this.line, this.newReport).subscribe({
      next: (res) => { this.loading = false; if (res.success) { this.fetchReports(); this.newReport = { datum: new Date().toISOString().split('T')[0], antal_ok: 0, antal_ej_ok: 0, kommentar: '' }; this.showAddForm = false; this.showSuccess('Rapport tillagd'); } else this.errorMessage = res.message || 'Fel'; },
      error: (err) => { this.loading = false; this.errorMessage = err.error?.message || 'Fel'; }
    });
  }

  saveReport(report: any) {
    const datum = (report.datum || '').split(' ')[0];
    this.service.updateReport(this.line, report.id, { datum, antal_ok: parseInt(report.antal_ok, 10) || 0, antal_ej_ok: parseInt(report.antal_ej_ok, 10) || 0, kommentar: report.kommentar || '' }).subscribe({
      next: (res) => { if (res.success) { report.totalt = (parseInt(report.antal_ok, 10) || 0) + (parseInt(report.antal_ej_ok, 10) || 0); report.datum = datum; this.expanded[report.id] = false; this.fetchReports(); this.showSuccess('Rapport uppdaterad'); } else this.errorMessage = res.message || 'Fel'; },
      error: (err) => { this.errorMessage = err.error?.message || 'Fel'; }
    });
  }

  deleteReport(id: number) {
    if (!confirm('Ta bort rapport?')) return;
    this.service.deleteReport(this.line, id).subscribe({ next: (res) => { if (res.success) { this.reports = this.reports.filter(r => r.id !== id); this.selectedIds.delete(id); this.showSuccess('Borttagen'); } else this.errorMessage = res.message || 'Fel'; } });
  }

  bulkDelete() {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    if (!confirm(`Ta bort ${this.selectedIds.size}?`)) return;
    this.service.bulkDelete(this.line, Array.from(this.selectedIds)).subscribe({ next: (res) => { if (res.success) { this.reports = this.reports.filter(r => !this.selectedIds.has(r.id)); this.selectedIds.clear(); this.showSuccess(res.message); } } });
  }

  toggleInlagd(report: any) {
    const v = !report.inlagd;
    this.service.updateInlagd(this.line, report.id, v).subscribe({ next: (res) => { if (res.success) { report.inlagd = v ? 1 : 0; this.showSuccess('Status uppdaterad'); } } });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    this.service.bulkUpdateInlagd(this.line, Array.from(this.selectedIds), inlagd).subscribe({ next: (res) => { if (res.success) { this.reports.forEach(r => { if (this.selectedIds.has(r.id)) r.inlagd = inlagd ? 1 : 0; }); this.selectedIds.clear(); this.showSuccess(res.message); } } });
  }

  exportCSV() {
    if (!this.filteredReports.length) return;
    const h = ['ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt', 'Kvalitet %', 'Kommentar', 'Användare', 'Inlagd'];
    const rows = this.filteredReports.map(r => [r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt, this.getQualityPct(r) ?? '', r.kommentar || '', r.user_name || '', r.inlagd == 1 ? 'Ja' : 'Nej']);
    const csv = [h, ...rows].map(row => row.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = `klassificeringslinje-skiftrapport-${new Date().toISOString().split('T')[0]}.csv`; a.click(); URL.revokeObjectURL(url);
  }

  showSuccess(msg: string) {
    this.successMessage = msg; this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => { if (!this.destroy$.closed) this.showSuccessMessage = false; }, 3000);
  }
}
