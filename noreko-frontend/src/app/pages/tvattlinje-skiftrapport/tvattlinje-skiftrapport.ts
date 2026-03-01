import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-tvattlinje-skiftrapport',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './tvattlinje-skiftrapport.html',
  styleUrl: './tvattlinje-skiftrapport.css'
})
export class TvattlinjeSkiftrapportPage implements OnInit, OnDestroy {
  readonly line: LineName = 'tvattlinje';
  readonly lineName = 'Tvättlinje';
  readonly lineColor = 'primary';

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

  constructor(
    private service: LineSkiftrapportService,
    private auth: AuthService
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(v => this.loggedIn = v);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(u => {
      this.user = u;
      this.isAdmin = u?.role === 'admin';
    });
    this.fetchReports();
    this.updateInterval = setInterval(() => {
      if (!this.destroy$.closed) this.fetchReports(true);
    }, 15000);
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

  getQualityPct(r: any): number | null {
    if (!r.totalt) return null;
    return Math.round((r.antal_ok / r.totalt) * 100);
  }

  fetchReports(silent = false) {
    if (!silent) this.loading = true;
    this.fetchSub?.unsubscribe();
    this.fetchSub = this.service.getReports(this.line)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (!silent) this.loading = false;
          if (res.success) {
            const newReports = res.data || [];
            if (silent) {
              const expandedCopy = { ...this.expanded };
              const selCopy = new Set(this.selectedIds);
              this.reports = newReports;
              this.expanded = expandedCopy;
              this.selectedIds = new Set(Array.from(selCopy).filter(id => newReports.some((r: any) => r.id === id)));
            } else {
              this.reports = newReports;
            }
          } else {
            this.errorMessage = res.message || 'Kunde inte hämta rapporter';
          }
        },
        error: (err) => {
          if (!silent) this.loading = false;
          this.errorMessage = err.error?.message || 'Fel vid hämtning';
        }
      });
  }

  toggleSelect(id: number) {
    this.selectedIds.has(id) ? this.selectedIds.delete(id) : this.selectedIds.add(id);
  }

  toggleSelectAll() {
    const visible = this.filteredReports;
    if (this.selectedIds.size === visible.length && visible.length > 0) {
      this.selectedIds.clear();
    } else {
      visible.forEach(r => this.selectedIds.add(r.id));
    }
  }

  isSelected(id: number) { return this.selectedIds.has(id); }
  isOwner(r: any) { return this.user && r.user_id === this.user.id; }
  canEdit(r: any) { return this.isAdmin || this.isOwner(r); }
  toggleExpand(id: number) { this.expanded[id] = !this.expanded[id]; }

  addReport() {
    this.errorMessage = '';
    if (!this.newReport.datum) { this.errorMessage = 'Datum krävs'; return; }
    this.loading = true;
    this.service.createReport(this.line, this.newReport).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.fetchReports();
          this.newReport = { datum: new Date().toISOString().split('T')[0], antal_ok: 0, antal_ej_ok: 0, kommentar: '' };
          this.showAddForm = false;
          this.showSuccess('Rapport tillagd');
        } else {
          this.errorMessage = res.message || 'Kunde inte lägga till';
        }
      },
      error: (err) => { this.loading = false; this.errorMessage = err.error?.message || 'Fel'; }
    });
  }

  saveReport(report: any) {
    let datum = (report.datum || '').split(' ')[0];
    this.service.updateReport(this.line, report.id, {
      datum, antal_ok: parseInt(report.antal_ok, 10) || 0,
      antal_ej_ok: parseInt(report.antal_ej_ok, 10) || 0,
      kommentar: report.kommentar || ''
    }).subscribe({
      next: (res) => {
        if (res.success) {
          report.totalt = (parseInt(report.antal_ok, 10) || 0) + (parseInt(report.antal_ej_ok, 10) || 0);
          report.datum = datum;
          this.expanded[report.id] = false;
          this.fetchReports();
          this.showSuccess('Rapport uppdaterad');
        } else { this.errorMessage = res.message || 'Kunde inte uppdatera'; }
      },
      error: (err) => { this.errorMessage = err.error?.message || 'Fel'; }
    });
  }

  deleteReport(id: number) {
    if (!confirm('Är du säker på att du vill ta bort denna rapport?')) return;
    this.service.deleteReport(this.line, id).subscribe({
      next: (res) => {
        if (res.success) { this.reports = this.reports.filter(r => r.id !== id); this.selectedIds.delete(id); this.showSuccess('Rapport borttagen'); }
        else { this.errorMessage = res.message || 'Kunde inte ta bort'; }
      },
      error: (err) => { this.errorMessage = err.error?.message || 'Fel'; }
    });
  }

  bulkDelete() {
    if (this.selectedIds.size === 0) { this.errorMessage = 'Inga rader valda'; return; }
    if (!confirm(`Ta bort ${this.selectedIds.size} rapport(er)?`)) return;
    const ids = Array.from(this.selectedIds);
    this.service.bulkDelete(this.line, ids).subscribe({
      next: (res) => {
        if (res.success) { this.reports = this.reports.filter(r => !this.selectedIds.has(r.id)); this.selectedIds.clear(); this.showSuccess(res.message); }
        else { this.errorMessage = res.message || 'Fel'; }
      }
    });
  }

  toggleInlagd(report: any) {
    const newVal = !report.inlagd;
    this.service.updateInlagd(this.line, report.id, newVal).subscribe({
      next: (res) => { if (res.success) { report.inlagd = newVal ? 1 : 0; this.showSuccess('Status uppdaterad'); } }
    });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (this.selectedIds.size === 0) { this.errorMessage = 'Inga rader valda'; return; }
    const ids = Array.from(this.selectedIds);
    this.service.bulkUpdateInlagd(this.line, ids, inlagd).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports.forEach(r => { if (this.selectedIds.has(r.id)) r.inlagd = inlagd ? 1 : 0; });
          this.selectedIds.clear(); this.showSuccess(res.message);
        }
      }
    });
  }

  exportCSV() {
    if (this.filteredReports.length === 0) return;
    const header = ['ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt', 'Kvalitet %', 'Kommentar', 'Användare', 'Inlagd'];
    const rows = this.filteredReports.map(r => [
      r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt,
      this.getQualityPct(r) ?? '', r.kommentar || '', r.user_name || '', r.inlagd == 1 ? 'Ja' : 'Nej'
    ]);
    const csv = [header, ...rows].map(row => row.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `${this.line}-skiftrapport-${new Date().toISOString().split('T')[0]}.csv`;
    a.click(); URL.revokeObjectURL(url);
  }

  showSuccess(msg: string) {
    this.successMessage = msg;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
}
