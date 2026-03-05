import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-saglinje-skiftrapport',
  imports: [CommonModule, FormsModule, DatePipe, DecimalPipe],
  templateUrl: './saglinje-skiftrapport.html',
  styleUrl: './saglinje-skiftrapport.css'
})
export class SaglinjeSkiftrapportPage implements OnInit, OnDestroy {
  readonly line: LineName = 'saglinje';
  readonly lineName = 'Såglinje';

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

  getTotalIbc(): number { return this.filteredReports.reduce((s, r) => s + ((r.antal_ok || 0) + (r.antal_ej_ok || 0)), 0); }
  getTotalOk(): number { return this.filteredReports.reduce((s, r) => s + (r.antal_ok || 0), 0); }
  getTotalEjOk(): number { return this.filteredReports.reduce((s, r) => s + (r.antal_ej_ok || 0), 0); }
  getAvgQuality(): number {
    const tot = this.getTotalIbc();
    if (tot === 0) return 0;
    return Math.round((this.getTotalOk() / tot) * 1000) / 10;
  }
  getAvgIbcPerSkift(): number {
    const n = this.filteredReports.length;
    if (n === 0) return 0;
    return this.getTotalIbc() / n;
  }
  toggleSelect(id: number) { this.selectedIds.has(id) ? this.selectedIds.delete(id) : this.selectedIds.add(id); }
  toggleSelectAll() { const v = this.filteredReports; if (this.selectedIds.size === v.length && v.length > 0) this.selectedIds.clear(); else v.forEach(r => this.selectedIds.add(r.id)); }
  isSelected(id: number) { return this.selectedIds.has(id); }
  isOwner(r: any) { return this.user && r.user_id === this.user.id; }
  canEdit(r: any) { return this.isAdmin || this.isOwner(r); }
  toggleExpand(id: number) { this.expanded[id] = !this.expanded[id]; }

  fetchReports(silent = false) {
    if (!silent) this.loading = true;
    this.fetchSub?.unsubscribe();
    this.fetchSub = this.service.getReports(this.line).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => { console.error('Fetch reports failed:', err); return of({ success: false, message: 'Kunde inte hämta rapporter', data: [] }); })).subscribe({
      next: (res) => {
        if (!silent) this.loading = false;
        if (res.success) {
          const nr = res.data || [];
          if (silent) { const ec = { ...this.expanded }; const sc = new Set(this.selectedIds); this.reports = nr; this.expanded = ec; this.selectedIds = new Set(Array.from(sc).filter(id => nr.some((r: any) => r.id === id))); }
          else this.reports = nr;
        } else this.errorMessage = res.message || 'Fel';
      }
    });
  }

  addReport() {
    this.errorMessage = '';
    if (!this.newReport.datum) { this.errorMessage = 'Datum krävs'; return; }
    this.loading = true;
    this.service.createReport(this.line, this.newReport).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => { console.error('Create report failed:', err); return of({ success: false, message: 'Kunde inte skapa rapport' }); })).subscribe({
      next: (res) => { this.loading = false; if (res.success) { this.fetchReports(); this.newReport = { datum: new Date().toISOString().split('T')[0], antal_ok: 0, antal_ej_ok: 0, kommentar: '' }; this.showAddForm = false; this.showSuccess('Rapport tillagd'); } else this.errorMessage = res.message || 'Fel'; }
    });
  }

  saveReport(report: any) {
    const datum = (report.datum || '').split(' ')[0];
    this.service.updateReport(this.line, report.id, { datum, antal_ok: parseInt(report.antal_ok, 10) || 0, antal_ej_ok: parseInt(report.antal_ej_ok, 10) || 0, kommentar: report.kommentar || '' }).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => { console.error('Update report failed:', err); return of({ success: false, message: 'Kunde inte uppdatera rapport' }); })).subscribe({
      next: (res) => { if (res.success) { report.totalt = (parseInt(report.antal_ok, 10) || 0) + (parseInt(report.antal_ej_ok, 10) || 0); report.datum = datum; this.expanded[report.id] = false; this.fetchReports(); this.showSuccess('Rapport uppdaterad'); } else this.errorMessage = res.message || 'Fel'; }
    });
  }

  deleteReport(id: number) {
    if (!confirm('Ta bort rapport?')) return;
    this.service.deleteReport(this.line, id).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => { console.error('Delete report failed:', err); return of({ success: false, message: 'Kunde inte ta bort rapport' }); })).subscribe({ next: (res) => { if (res.success) { this.reports = this.reports.filter(r => r.id !== id); this.selectedIds.delete(id); this.showSuccess('Borttagen'); } else this.errorMessage = res.message || 'Fel'; } });
  }

  bulkDelete() {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    if (!confirm(`Ta bort ${this.selectedIds.size}?`)) return;
    this.service.bulkDelete(this.line, Array.from(this.selectedIds)).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => { console.error('Bulk delete failed:', err); return of({ success: false, message: 'Kunde inte ta bort rapporter' }); })).subscribe({ next: (res) => { if (res.success) { this.reports = this.reports.filter(r => !this.selectedIds.has(r.id)); this.selectedIds.clear(); this.showSuccess(res.message); } } });
  }

  toggleInlagd(report: any) {
    const v = !report.inlagd;
    this.service.updateInlagd(this.line, report.id, v).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => { console.error('Update inlagd failed:', err); return of({ success: false }); })).subscribe({ next: (res) => { if (res.success) { report.inlagd = v ? 1 : 0; this.showSuccess('Status uppdaterad'); } } });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    this.service.bulkUpdateInlagd(this.line, Array.from(this.selectedIds), inlagd).pipe(takeUntil(this.destroy$), timeout(8000), catchError(err => { console.error('Bulk update inlagd failed:', err); return of({ success: false }); })).subscribe({ next: (res) => { if (res.success) { this.reports.forEach(r => { if (this.selectedIds.has(r.id)) r.inlagd = inlagd ? 1 : 0; }); this.selectedIds.clear(); this.showSuccess(res.message); } } });
  }

  exportCSV() {
    if (!this.filteredReports.length) return;
    const h = ['ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt', 'Kvalitet %', 'Kommentar', 'Användare', 'Inlagd'];
    const rows = this.filteredReports.map(r => [r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt, this.getQualityPct(r) ?? '', r.kommentar || '', r.user_name || '', r.inlagd == 1 ? 'Ja' : 'Nej']);
    const csv = [h, ...rows].map(row => row.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = `saglinje-skiftrapport-${new Date().toISOString().split('T')[0]}.csv`; a.click(); URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (!this.filteredReports.length) return;
    import('xlsx').then(XLSX => {
      const headers = [
        'ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt',
        'Kvalitet %', 'Kommentar', 'Användare', 'Inlagd'
      ];
      const rows = this.filteredReports.map(r => [
        r.id,
        r.datum,
        r.antal_ok,
        r.antal_ej_ok,
        r.totalt,
        this.getQualityPct(r) ?? '',
        r.kommentar || '',
        r.user_name || '',
        r.inlagd == 1 ? 'Ja' : 'Nej'
      ]);
      const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);

      // Kolumnbredder
      ws['!cols'] = [
        { wch: 6  },  // ID
        { wch: 12 },  // Datum
        { wch: 10 },  // Antal OK
        { wch: 12 },  // Antal ej OK
        { wch: 8  },  // Totalt
        { wch: 11 },  // Kvalitet %
        { wch: 40 },  // Kommentar
        { wch: 16 },  // Användare
        { wch: 8  },  // Inlagd
      ];

      // Frys header-rad (rad 1)
      ws['!freeze'] = { xSplit: 0, ySplit: 1 };

      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Skiftrapporter');
      XLSX.writeFile(wb, `saglinje-skiftrapport-${new Date().toISOString().split('T')[0]}.xlsx`);
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
            { text: 'Skiftrapport – Såglinje', style: 'header' },
            { text: report.datum + '  |  Skift av ' + (report.user_name || '-'), style: 'subheader' },
            { text: '\n' },
            { text: 'Produktion', style: 'sectionHeader' },
            {
              table: { widths: ['*', '*', '*', '*'],
                body: [
                  [{ text: 'Antal OK', bold: true, fillColor: '#eeeeee' }, { text: 'Antal ej OK', bold: true, fillColor: '#eeeeee' }, { text: 'Totalt', bold: true, fillColor: '#eeeeee' }, { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' }],
                  [{ text: String(report.antal_ok), alignment: 'center' }, { text: String(report.antal_ej_ok), alignment: 'center' }, { text: String(report.totalt), bold: true, alignment: 'center' }, { text: q != null ? q + '%' : '–', alignment: 'center', color: q != null && q >= 90 ? 'green' : (q != null && q < 70 ? 'red' : 'black') }]
                ]
              }, layout: 'lightHorizontalLines'
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
        }).download(`saglinje-skiftrapport-${report.datum}-${report.id}.pdf`);
      });
    });
  }

  showSuccess(msg: string) {
    this.successMessage = msg; this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => { if (!this.destroy$.closed) this.showSuccessMessage = false; }, 3000);
  }
}
