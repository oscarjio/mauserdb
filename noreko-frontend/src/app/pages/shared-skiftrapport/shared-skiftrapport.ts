import { Component, OnInit, OnDestroy, Input } from '@angular/core';
import { CommonModule, DatePipe, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';
import { AuthService } from '../../services/auth.service';
import { localToday } from '../../utils/date-utils';

/**
 * Konfiguration per linje — skickas in via wrapper-komponenten.
 */
export interface LineSkiftrapportConfig {
  line: LineName;
  lineName: string;            // Visningsnamn, t.ex. "Tvättlinje"
  liveUrl: string | null;      // URL till live-sida, t.ex. "/tvattlinje/live"
  themeColor: string;          // Bootstrap-färgklass: "primary" | "warning" | "success"
  accentHex: string;           // Hex-accentfärg för CSS-variabler
  emptyText: string;           // Text vid tom-tillstånd
}

@Component({
  standalone: true,
  selector: 'app-shared-skiftrapport',
  imports: [CommonModule, FormsModule, DatePipe, DecimalPipe],
  templateUrl: './shared-skiftrapport.html',
  styleUrl: './shared-skiftrapport.css'
})
export class SharedSkiftrapportComponent implements OnInit, OnDestroy {
  @Input() config!: LineSkiftrapportConfig;

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
  addingReport = false;

  filterFrom = '';
  filterTo = '';

  newReport = {
    datum: localToday(),
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
    return Math.round((r.antal_ok / r.totalt) * 1000) / 10;
  }

  getTotalIbc(): number {
    return this.filteredReports.reduce((s, r) => s + ((r.antal_ok || 0) + (r.antal_ej_ok || 0)), 0);
  }

  getTotalOk(): number {
    return this.filteredReports.reduce((s, r) => s + (r.antal_ok || 0), 0);
  }

  getTotalEjOk(): number {
    return this.filteredReports.reduce((s, r) => s + (r.antal_ej_ok || 0), 0);
  }

  getAvgQuality(): number {
    const tot = this.getTotalIbc();
    if (tot === 0) return 0;
    return Math.round((this.getTotalOk() / tot) * 1000) / 10;
  }

  getAvgIbcPerSkift(): number {
    const n = this.filteredReports.length;
    if (n === 0) return 0;
    return Math.round((this.getTotalIbc() / n) * 10) / 10;
  }

  toggleSelect(id: number) {
    this.selectedIds.has(id) ? this.selectedIds.delete(id) : this.selectedIds.add(id);
  }

  toggleSelectAll() {
    const v = this.filteredReports;
    if (this.selectedIds.size === v.length && v.length > 0) this.selectedIds.clear();
    else v.forEach(r => this.selectedIds.add(r.id));
  }

  isSelected(id: number) { return this.selectedIds.has(id); }
  isOwner(r: any) { return this.user && r.user_id === this.user.id; }
  canEdit(r: any) { return this.isAdmin || this.isOwner(r); }
  toggleExpand(id: number) { this.expanded[id] = !this.expanded[id]; }

  fetchReports(silent = false) {
    if (!silent) this.loading = true;
    this.fetchSub?.unsubscribe();
    this.fetchSub = this.service.getReports(this.config.line)
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(err => {
          console.error('Fel vid hämtning av rapporter:', err);
          return of({ success: false, error: 'Kunde inte hämta rapporter', data: [] });
        })
      )
      .subscribe({
        next: (res) => {
          if (!silent) this.loading = false;
          if (res.success) {
            const nr = res.data || [];
            if (silent) {
              const ec = { ...this.expanded };
              const sc = new Set(this.selectedIds);
              this.reports = nr;
              this.expanded = ec;
              this.selectedIds = new Set(Array.from(sc).filter(id => nr.some((r: any) => r.id === id)));
            } else {
              this.reports = nr;
            }
          } else {
            this.errorMessage = res.error || 'Kunde inte hämta rapporter';
          }
        }
      });
  }

  addReport() {
    this.errorMessage = '';
    if (!this.newReport.datum) { this.errorMessage = 'Datum krävs'; return; }
    if (this.addingReport) return;
    this.addingReport = true;
    this.loading = true;
    this.service.createReport(this.config.line, this.newReport)
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => {
        console.error('Fel vid skapande av rapport:', err);
        return of({ success: false, error: 'Kunde inte skapa rapport' });
      }))
      .subscribe({
        next: (res) => {
          this.loading = false;
          this.addingReport = false;
          if (res.success) {
            this.fetchReports();
            this.newReport = { datum: localToday(), antal_ok: 0, antal_ej_ok: 0, kommentar: '' };
            this.showAddForm = false;
            this.showSuccess('Rapport tillagd');
          } else {
            this.errorMessage = res.error || 'Kunde inte lägga till';
          }
        }
      });
  }

  saveReport(report: any) {
    const datum = (report.datum || '').split(' ')[0];
    this.service.updateReport(this.config.line, report.id, {
      datum,
      antal_ok: parseInt(report.antal_ok, 10) || 0,
      antal_ej_ok: parseInt(report.antal_ej_ok, 10) || 0,
      kommentar: report.kommentar || ''
    }).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => {
      console.error('Fel vid uppdatering av rapport:', err);
      return of({ success: false, error: 'Kunde inte uppdatera rapport' });
    })).subscribe({
      next: (res) => {
        if (res.success) {
          report.totalt = (parseInt(report.antal_ok, 10) || 0) + (parseInt(report.antal_ej_ok, 10) || 0);
          report.datum = datum;
          this.expanded[report.id] = false;
          this.fetchReports();
          this.showSuccess('Rapport uppdaterad');
        } else {
          this.errorMessage = res.error || 'Kunde inte uppdatera';
        }
      }
    });
  }

  deleteReport(id: number) {
    if (!confirm('Ta bort rapport?')) return;
    this.service.deleteReport(this.config.line, id)
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => {
        console.error('Fel vid borttagning av rapport:', err);
        return of({ success: false, error: 'Kunde inte ta bort rapport' });
      }))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.reports = this.reports.filter(r => r.id !== id);
            this.selectedIds.delete(id);
            this.showSuccess('Rapport borttagen');
          } else {
            this.errorMessage = res.error || 'Kunde inte ta bort';
          }
        }
      });
  }

  bulkDelete() {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    if (!confirm(`Ta bort ${this.selectedIds.size} rapport(er)?`)) return;
    this.service.bulkDelete(this.config.line, Array.from(this.selectedIds))
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => {
        console.error('Fel vid massborttagning:', err);
        return of({ success: false, error: 'Kunde inte ta bort rapporter' });
      }))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.reports = this.reports.filter(r => !this.selectedIds.has(r.id));
            this.selectedIds.clear();
            this.showSuccess(res.message);
          } else {
            this.errorMessage = res.error || 'Fel';
          }
        }
      });
  }

  toggleInlagd(report: any) {
    const v = !report.inlagd;
    this.service.updateInlagd(this.config.line, report.id, v)
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => {
        console.error('Fel vid uppdatering av inlagd-status:', err);
        return of({ success: false });
      }))
      .subscribe({
        next: (res) => {
          if (res.success) { report.inlagd = v ? 1 : 0; this.showSuccess('Status uppdaterad'); }
        }
      });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    this.service.bulkUpdateInlagd(this.config.line, Array.from(this.selectedIds), inlagd)
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => {
        console.error('Fel vid massuppdatering av inlagd-status:', err);
        return of({ success: false });
      }))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.reports.forEach(r => { if (this.selectedIds.has(r.id)) r.inlagd = inlagd ? 1 : 0; });
            this.selectedIds.clear();
            this.showSuccess(res.message);
          }
        }
      });
  }

  exportCSV() {
    if (!this.filteredReports.length) return;
    const h = ['ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt', 'Kvalitet %', 'Kommentar', 'Anvandare', 'Inlagd'];
    const rows = this.filteredReports.map(r => [
      r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt,
      this.getQualityPct(r) ?? '', r.kommentar || '', r.user_name || '',
      r.inlagd == 1 ? 'Ja' : 'Nej'
    ]);
    const csv = [h, ...rows].map(row => row.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${this.config.line}-skiftrapport-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (!this.filteredReports.length) return;
    import('xlsx').then(XLSX => {
      const headers = [
        'ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt',
        'Kvalitet %', 'Kommentar', 'Anvandare', 'Inlagd'
      ];
      const rows = this.filteredReports.map(r => [
        r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt,
        this.getQualityPct(r) ?? '', r.kommentar || '', r.user_name || '',
        r.inlagd == 1 ? 'Ja' : 'Nej'
      ]);
      const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
      ws['!cols'] = [
        { wch: 6 }, { wch: 12 }, { wch: 10 }, { wch: 12 },
        { wch: 8 }, { wch: 11 }, { wch: 40 }, { wch: 16 }, { wch: 8 }
      ];
      ws['!freeze'] = { xSplit: 0, ySplit: 1 };
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Skiftrapporter');
      XLSX.writeFile(wb, `${this.config.line}-skiftrapport-${localToday()}.xlsx`);
    });
  }

  exportPDF(report: any) {
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const q = this.getQualityPct(report);
        pdfMake.createPdf({
          content: [
            { text: 'Skiftrapport – ' + this.config.lineName, style: 'header' },
            { text: report.datum + '  |  Skift av ' + (report.user_name || '-'), style: 'subheader' },
            { text: '\n' },
            { text: 'Produktion', style: 'sectionHeader' },
            {
              table: {
                widths: ['*', '*', '*', '*'],
                body: [
                  [
                    { text: 'Antal OK', bold: true, fillColor: '#eeeeee' },
                    { text: 'Antal ej OK', bold: true, fillColor: '#eeeeee' },
                    { text: 'Totalt', bold: true, fillColor: '#eeeeee' },
                    { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' }
                  ],
                  [
                    { text: String(report.antal_ok), alignment: 'center' },
                    { text: String(report.antal_ej_ok), alignment: 'center' },
                    { text: String(report.totalt), bold: true, alignment: 'center' },
                    { text: q != null ? q + '%' : '\u2013', alignment: 'center', color: q != null && q >= 90 ? 'green' : (q != null && q < 70 ? 'red' : 'black') }
                  ]
                ]
              },
              layout: 'lightHorizontalLines'
            },
            { text: '\n' },
            ...(report.kommentar ? [{ text: 'Kommentar: ' + report.kommentar, style: 'meta' }, { text: '\n' }] : []),
            { text: 'Skiftansvarig: ' + (report.user_name || '-'), style: 'meta' },
            { text: 'Inlagd: ' + (report.inlagd == 1 ? 'Ja' : 'Nej'), style: 'meta' },
            { text: 'Genererad: ' + new Date().toLocaleString('sv-SE'), style: 'meta' }
          ],
          styles: {
            header: { fontSize: 20, bold: true, margin: [0, 0, 0, 4] },
            subheader: { fontSize: 12, color: '#555', margin: [0, 0, 0, 10] },
            sectionHeader: { fontSize: 13, bold: true, margin: [0, 8, 0, 4] },
            meta: { fontSize: 10, color: '#777', margin: [0, 2, 0, 0] }
          },
          defaultStyle: { fontSize: 11 }
        }).download(`${this.config.line}-skiftrapport-${report.datum}-${report.id}.pdf`);
      });
    });
  }

  showSuccess(msg: string) {
    this.successMessage = msg;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
  trackByIndex(index: number): number { return index; }
}
