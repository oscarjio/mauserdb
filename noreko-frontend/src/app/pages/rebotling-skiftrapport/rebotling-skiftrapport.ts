import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { SkiftrapportService } from '../../services/skiftrapport.service';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-skiftrapport',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './rebotling-skiftrapport.html',
  styleUrl: './rebotling-skiftrapport.css'
})
export class RebotlingSkiftrapportPage implements OnInit, OnDestroy {
  reports: any[] = [];
  products: any[] = [];
  selectedIds: Set<number> = new Set();
  expanded: { [id: number]: boolean } = {};
  loading = false;
  errorMessage = '';
  successMessage = '';
  showSuccessMessage = false;
  isAdmin = false;
  user: any = null;
  showAddReportForm = false;
  loggedIn = false;

  // Date filter
  filterFrom = '';
  filterTo = '';

  // Löpnummer lazy-load
  lopnummerMap: { [reportId: number]: string } = {};
  lopnummerLoading: { [reportId: number]: boolean } = {};

  private destroy$ = new Subject<void>();
  private fetchSub: Subscription | null = null;
  private updateInterval: any = null;
  private successTimerId: any = null;

  constructor(
    private skiftrapportService: SkiftrapportService,
    private auth: AuthService
  ) {}

  newReport = {
    datum: new Date().toISOString().split('T')[0],
    product_id: null as number | null,
    ibc_ok: 0,
    bur_ej_ok: 0,
    ibc_ej_ok: 0
  };

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      this.user = user;
      this.isAdmin = user?.role === 'admin';
    });
    this.fetchReports();
    this.fetchProducts();

    // Uppdatera tabellen var 10:e sekund
    this.updateInterval = setInterval(() => {
      if (!this.destroy$.closed) this.fetchReports(true);
    }, 10000);
  }

  ngOnDestroy() {
    clearInterval(this.updateInterval);
    clearTimeout(this.successTimerId);
    this.fetchSub?.unsubscribe();
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ========== Date filter ==========
  get filteredReports(): any[] {
    return this.reports.filter(r => {
      const d = (r.datum || '').substring(0, 10);
      if (this.filterFrom && d < this.filterFrom) return false;
      if (this.filterTo && d > this.filterTo) return false;
      return true;
    });
  }

  clearFilter() {
    this.filterFrom = '';
    this.filterTo = '';
  }

  // ========== Computed KPIs ==========
  getQualityPct(r: any): number | null {
    if (!r.totalt) return null;
    return Math.round((r.ibc_ok / r.totalt) * 100);
  }

  getEfficiencyPct(r: any): number | null {
    const total = (r.drifttid || 0) + (r.rasttime || 0);
    if (!total) return null;
    return Math.round((r.drifttid / total) * 100);
  }

  getIbcPerHour(r: any): number | null {
    if (!r.drifttid) return null;
    return Math.round((r.ibc_ok / (r.drifttid / 60)) * 10) / 10;
  }

  getDefectPct(r: any): number | null {
    if (!r.totalt) return null;
    return Math.round(((r.bur_ej_ok + r.ibc_ej_ok) / r.totalt) * 100);
  }

  getOpLabel(r: any, field: 'op1' | 'op2' | 'op3'): string {
    const nameField = field + '_name';
    return r[nameField] || (r[field] ? String(r[field]) : '–');
  }

  // ========== Fetch ==========
  fetchProducts() {
    this.skiftrapportService.getProducts().subscribe({
      next: (res) => {
        if (res.success) {
          this.products = res.data || [];
        }
      },
      error: (error) => {
        console.error('Error fetching products:', error);
      }
    });
  }

  fetchReports(silent: boolean = false) {
    if (!silent) {
      this.loading = true;
    }
    this.errorMessage = '';

    const tableContainer = document.querySelector('.table-responsive');
    const scrollTop = tableContainer ? tableContainer.scrollTop : 0;

    this.fetchSub?.unsubscribe();
    this.fetchSub = this.skiftrapportService.getSkiftrapporter()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (!silent) {
            this.loading = false;
          }
          if (res.success) {
            const newReports = res.data || [];

            if (silent) {
              const expandedCopy = { ...this.expanded };
              const selectedIdsCopy = new Set(this.selectedIds);
              this.reports = newReports;
              this.expanded = expandedCopy;
              this.selectedIds = new Set(
                Array.from(selectedIdsCopy).filter(id =>
                  newReports.some((r: any) => r.id === id)
                )
              );
              if (tableContainer) {
                setTimeout(() => {
                  if (!this.destroy$.closed) tableContainer.scrollTop = scrollTop;
                }, 0);
              }
            } else {
              this.reports = newReports;
            }
          } else {
            this.errorMessage = res.message || 'Kunde inte hämta skiftrapporter';
          }
        },
        error: (error) => {
          if (!silent) {
            this.loading = false;
          }
          this.errorMessage = error.error?.message || 'Ett fel uppstod vid hämtning av skiftrapporter';
        }
      });
  }

  // ========== Selection ==========
  toggleSelect(id: number) {
    if (this.selectedIds.has(id)) {
      this.selectedIds.delete(id);
    } else {
      this.selectedIds.add(id);
    }
  }

  toggleSelectAll() {
    const visible = this.filteredReports;
    if (this.selectedIds.size === visible.length && visible.length > 0) {
      this.selectedIds.clear();
    } else {
      visible.forEach(r => this.selectedIds.add(r.id));
    }
  }

  isSelected(id: number): boolean {
    return this.selectedIds.has(id);
  }

  isOwner(report: any): boolean {
    return this.user && report.user_id === this.user.id;
  }

  canEdit(report: any): boolean {
    return this.isAdmin || this.isOwner(report);
  }

  // ========== Inlagd ==========
  toggleInlagd(report: any) {
    const newInlagd = !report.inlagd;
    this.skiftrapportService.updateInlagd(report.id, newInlagd).subscribe({
      next: (res) => {
        if (res.success) {
          report.inlagd = newInlagd ? 1 : 0;
          this.showSuccess('Status uppdaterad');
        } else {
          this.errorMessage = res.message || 'Kunde inte uppdatera status';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (this.selectedIds.size === 0) {
      this.errorMessage = 'Inga rader valda';
      return;
    }

    const ids = Array.from(this.selectedIds);
    this.skiftrapportService.bulkUpdateInlagd(ids, inlagd).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports.forEach(r => {
            if (this.selectedIds.has(r.id)) {
              r.inlagd = inlagd ? 1 : 0;
            }
          });
          this.selectedIds.clear();
          this.showSuccess(res.message);
        } else {
          this.errorMessage = res.message || 'Kunde inte uppdatera status';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  // ========== CRUD ==========
  deleteReport(id: number) {
    if (!confirm('Är du säker på att du vill ta bort denna skiftrapport?')) {
      return;
    }

    this.skiftrapportService.deleteSkiftrapport(id).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports = this.reports.filter(r => r.id !== id);
          this.selectedIds.delete(id);
          this.showSuccess('Skiftrapport borttagen');
        } else {
          this.errorMessage = res.message || 'Kunde inte ta bort skiftrapport';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  bulkDelete() {
    if (this.selectedIds.size === 0) {
      this.errorMessage = 'Inga rader valda';
      return;
    }

    if (!confirm(`Är du säker på att du vill ta bort ${this.selectedIds.size} skiftrapport(er)?`)) {
      return;
    }

    const ids = Array.from(this.selectedIds);
    this.skiftrapportService.bulkDelete(ids).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports = this.reports.filter(r => !this.selectedIds.has(r.id));
          this.selectedIds.clear();
          this.showSuccess(res.message);
        } else {
          this.errorMessage = res.message || 'Kunde inte ta bort skiftrapporter';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  addReport() {
    this.errorMessage = '';

    if (!this.newReport.datum) {
      this.errorMessage = 'Datum är obligatoriskt';
      return;
    }

    if (!this.newReport.product_id) {
      this.errorMessage = 'Produkt måste väljas';
      return;
    }

    const totalt = this.newReport.ibc_ok + this.newReport.bur_ej_ok + this.newReport.ibc_ej_ok;

    this.loading = true;
    this.skiftrapportService.createSkiftrapport({
      datum: this.newReport.datum,
      product_id: this.newReport.product_id,
      ibc_ok: this.newReport.ibc_ok,
      bur_ej_ok: this.newReport.bur_ej_ok,
      ibc_ej_ok: this.newReport.ibc_ej_ok,
      totalt: totalt
    }).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.fetchReports();
          this.newReport = {
            datum: new Date().toISOString().split('T')[0],
            product_id: null,
            ibc_ok: 0,
            bur_ej_ok: 0,
            ibc_ej_ok: 0
          };
          this.showAddReportForm = false;
          this.showSuccess('Skiftrapport tillagd');
        } else {
          this.errorMessage = res.message || 'Kunde inte lägga till skiftrapport';
        }
      },
      error: (error) => {
        this.loading = false;
        this.errorMessage = error.error?.message || 'Ett fel uppstod vid skapande av skiftrapport';
      }
    });
  }

  toggleExpand(id: number) {
    this.expanded[id] = !this.expanded[id];
    if (this.expanded[id]) {
      const report = this.reports.find(r => r.id === id);
      if (report && report.skiftraknare && this.lopnummerMap[id] === undefined) {
        this.loadLopnummer(report);
      }
    }
  }

  private loadLopnummer(report: any) {
    const id = report.id;
    this.lopnummerLoading[id] = true;
    this.skiftrapportService.getLopnummer(report.skiftraknare)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.lopnummerLoading[id] = false;
          this.lopnummerMap[id] = res.success ? res.ranges : '–';
        },
        error: () => {
          this.lopnummerLoading[id] = false;
          this.lopnummerMap[id] = '–';
        }
      });
  }

  saveReport(report: any) {
    let datum = report.datum;
    if (datum instanceof Date) {
      datum = datum.toISOString().split('T')[0];
    } else if (typeof datum === 'string') {
      datum = datum.split(' ')[0];
    }

    this.skiftrapportService.updateSkiftrapport(report.id, {
      datum: datum,
      product_id: report.product_id,
      ibc_ok: parseInt(report.ibc_ok, 10) || 0,
      bur_ej_ok: parseInt(report.bur_ej_ok, 10) || 0,
      ibc_ej_ok: parseInt(report.ibc_ej_ok, 10) || 0
    }).subscribe({
      next: (res) => {
        if (res.success) {
          report.totalt = (parseInt(report.ibc_ok, 10) || 0) + (parseInt(report.bur_ej_ok, 10) || 0) + (parseInt(report.ibc_ej_ok, 10) || 0);
          report.datum = datum;
          this.expanded[report.id] = false;
          this.fetchReports();
          this.showSuccess('Skiftrapport uppdaterad');
        } else {
          this.errorMessage = res.message || 'Kunde inte uppdatera skiftrapport';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod vid uppdatering';
      }
    });
  }

  // ========== Export ==========
  exportCSV() {
    if (this.filteredReports.length === 0) return;

    const header = ['ID', 'Datum', 'Produkt', 'Användare', 'IBC OK', 'Bur ej OK', 'IBC ej OK', 'Totalt', 'Inlagd'];
    const rows = this.filteredReports.map((r: any) => [
      r.id,
      r.datum,
      r.product_name || '-',
      r.user_name || '-',
      r.ibc_ok,
      r.bur_ej_ok,
      r.ibc_ej_ok,
      r.totalt,
      r.inlagd == 1 ? 'Ja' : 'Nej'
    ]);

    const csvContent = [header, ...rows]
      .map(row => row.map(cell => `"${cell}"`).join(';'))
      .join('\n');

    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `skiftrapport-${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (this.filteredReports.length === 0) return;
    import('xlsx').then(XLSX => {
      const data = this.filteredReports.map(r => ({
        'ID':              r.id,
        'Datum':           r.datum,
        'Produkt':         r.product_name || '-',
        'Användare':       r.user_name || '-',
        'IBC OK':          r.ibc_ok,
        'Bur ej OK':       r.bur_ej_ok,
        'IBC ej OK':       r.ibc_ej_ok,
        'Totalt':          r.totalt,
        'Kvalitet %':      this.getQualityPct(r) ?? '',
        'Effektivitet %':  this.getEfficiencyPct(r) ?? '',
        'IBC/timme':       this.getIbcPerHour(r) ?? '',
        'Op1':             r.op1 ?? '',
        'Op2':             r.op2 ?? '',
        'Op3':             r.op3 ?? '',
        'Drifttid (min)':  r.drifttid ?? '',
        'Rasttid (min)':   r.rasttime ?? '',
        'Löpnummer':       r.lopnummer ?? '',
        'Skifträknare':    r.skiftraknare ?? '',
        'Inlagd':          r.inlagd == 1 ? 'Ja' : 'Nej'
      }));
      const ws = XLSX.utils.json_to_sheet(data);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Skiftrapporter');
      XLSX.writeFile(wb, `skiftrapporter-${new Date().toISOString().split('T')[0]}.xlsx`);
    });
  }

  exportPDF(report: any) {
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const docDef = this.buildPDFDocDef(report);
        pdfMake.createPdf(docDef).download(`skiftrapport-${report.datum}-${report.id}.pdf`);
      });
    });
  }

  private buildPDFDocDef(r: any): any {
    const qualPct = this.getQualityPct(r);
    const effPct  = this.getEfficiencyPct(r);
    const ibcH    = this.getIbcPerHour(r);
    const defPct  = this.getDefectPct(r);

    return {
      content: [
        { text: 'Skiftrapport', style: 'header' },
        {
          text: `${r.datum}  |  ${r.product_name || '-'}`,
          style: 'subheader'
        },
        { text: '\n' },
        { text: 'Produktion', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*'],
            body: [
              [
                { text: 'IBC OK', bold: true, fillColor: '#eeeeee' },
                { text: 'Bur ej OK', bold: true, fillColor: '#eeeeee' },
                { text: 'IBC ej OK', bold: true, fillColor: '#eeeeee' },
                { text: 'Totalt', bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: String(r.ibc_ok), alignment: 'center' },
                { text: String(r.bur_ej_ok), alignment: 'center' },
                { text: String(r.ibc_ej_ok), alignment: 'center' },
                { text: String(r.totalt), bold: true, alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        { text: 'Nyckeltal', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*'],
            body: [
              [
                { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' },
                { text: 'Effektivitet', bold: true, fillColor: '#eeeeee' },
                { text: 'IBC/timme', bold: true, fillColor: '#eeeeee' },
                { text: 'Kassation', bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: qualPct != null ? qualPct + '%' : '–', alignment: 'center', color: qualPct != null && qualPct >= 90 ? 'green' : 'black' },
                { text: effPct  != null ? effPct + '%'  : '–', alignment: 'center' },
                { text: ibcH    != null ? ibcH + ' st/h': '–', alignment: 'center' },
                { text: defPct  != null ? defPct + '%'  : '–', alignment: 'center', color: defPct != null && defPct > 10 ? 'red' : 'black' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        { text: 'PLC-data', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*', '*', '*'],
            body: [
              [
                { text: 'Tvättplats', bold: true, fillColor: '#eeeeee' },
                { text: 'Kontrollstation', bold: true, fillColor: '#eeeeee' },
                { text: 'Truckförare', bold: true, fillColor: '#eeeeee' },
                { text: 'Drifttid', bold: true, fillColor: '#eeeeee' },
                { text: 'Rasttid', bold: true, fillColor: '#eeeeee' },
                { text: 'Löpnr (sista)', bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: this.getOpLabel(r, 'op1'), alignment: 'center' },
                { text: this.getOpLabel(r, 'op2'), alignment: 'center' },
                { text: this.getOpLabel(r, 'op3'), alignment: 'center' },
                { text: r.drifttid != null ? r.drifttid + ' min' : '–', alignment: 'center' },
                { text: r.rasttime != null ? r.rasttime + ' min' : '–', alignment: 'center' },
                { text: String(r.lopnummer ?? '–'), alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        { text: 'Löpnummer detta skift: ' + (this.lopnummerMap[r.id] ?? '–'), style: 'meta' },
        { text: 'Skiftansvarig: ' + (r.user_name || '-'), style: 'meta' },
        { text: 'Inlagd i system: ' + (r.inlagd == 1 ? 'Ja' : 'Nej'), style: 'meta' },
        { text: 'Genererad: ' + new Date().toLocaleString('sv-SE'), style: 'meta' }
      ],
      styles: {
        header:        { fontSize: 22, bold: true, margin: [0, 0, 0, 4] },
        subheader:     { fontSize: 12, color: '#555555', margin: [0, 0, 0, 10] },
        sectionHeader: { fontSize: 13, bold: true, margin: [0, 10, 0, 4] },
        meta:          { fontSize: 10, color: '#777777', margin: [0, 2, 0, 0] }
      },
      defaultStyle: { fontSize: 11 },
      pageMargins: [40, 50, 40, 50]
    };
  }

  // ========== Toast ==========
  showSuccess(message: string) {
    this.successMessage = message;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
}
