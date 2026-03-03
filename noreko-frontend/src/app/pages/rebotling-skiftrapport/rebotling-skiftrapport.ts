import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { SkiftrapportService } from '../../services/skiftrapport.service';
import { AuthService } from '../../services/auth.service';

type SortField = 'datum' | 'product_name' | 'user_name' | 'ibc_ok' | 'bur_ej_ok' | 'ibc_ej_ok' | 'totalt' | 'kvalitet' | 'ibc_per_h';
type SortDir   = 'asc' | 'desc';

@Component({
  standalone: true,
  selector: 'app-rebotling-skiftrapport',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './rebotling-skiftrapport.html',
  styleUrl: './rebotling-skiftrapport.css'
})
export class RebotlingSkiftrapportPage implements OnInit, OnDestroy {
  Math = Math;

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

  // Date + shift filter
  filterFrom  = '';
  filterTo    = '';
  filterSkift = ''; // förmiddag | eftermiddag | natt | ''

  // Search
  searchText = '';

  // Sort
  sortField: SortField = 'datum';
  sortDir: SortDir     = 'desc';

  // Löpnummer lazy-load
  lopnummerMap: { [reportId: number]: string } = {};
  lopnummerLoading: { [reportId: number]: boolean } = {};

  // Settings for bonus estimate
  private settings: any = { rebotlingTarget: 1000, shiftHours: 8.0 };

  // ---- Skiftjämförelse ----
  compareDateA = '';
  compareDateB = '';
  compareLoading = false;
  compareError   = '';
  compareResult: { a: any; b: any } | null = null;

  // ---- Skiftkommentar ----
  kommentarMap: { [reportId: number]: string } = {};
  kommentarLoading: { [reportId: number]: boolean } = {};
  redigerarKommentar: { [reportId: number]: boolean } = {};
  spararKommentar: { [reportId: number]: boolean } = {};
  editKommentar: { [reportId: number]: string } = {};

  private destroy$ = new Subject<void>();
  private fetchSub: Subscription | null = null;
  private updateInterval: any = null;
  private successTimerId: any = null;

  constructor(
    private skiftrapportService: SkiftrapportService,
    private auth: AuthService,
    private http: HttpClient
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
    this.loadSettings();

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

  private loadSettings() {
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=admin-settings', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.settings = res.data;
          }
        },
        error: () => {}
      });
  }

  // ========== Shift helper ==========
  private getShiftForReport(r: any): string {
    if (!r.datum) return '';
    const timeStr = (r.datum || '').substring(11, 16);
    if (!timeStr) return '';
    const [hh, mm] = timeStr.split(':').map(Number);
    const minutes = hh * 60 + mm;
    if (minutes >= 6 * 60 && minutes < 14 * 60)  return 'förmiddag';
    if (minutes >= 14 * 60 && minutes < 22 * 60) return 'eftermiddag';
    return 'natt';
  }

  // ========== Date filter ==========
  get filteredReports(): any[] {
    let result = this.reports.filter(r => {
      const d = (r.datum || '').substring(0, 10);
      if (this.filterFrom && d < this.filterFrom) return false;
      if (this.filterTo   && d > this.filterTo)   return false;
      if (this.filterSkift) {
        if (this.getShiftForReport(r) !== this.filterSkift) return false;
      }
      if (this.searchText) {
        const q = this.searchText.toLowerCase();
        const searchable = [
          r.datum || '',
          r.product_name || '',
          r.user_name || '',
          String(r.ibc_ok ?? ''),
          String(r.totalt ?? '')
        ].join(' ').toLowerCase();
        if (!searchable.includes(q)) return false;
      }
      return true;
    });

    // Sort
    result = [...result].sort((a, b) => {
      let aVal: any;
      let bVal: any;
      switch (this.sortField) {
        case 'datum':        aVal = a.datum ?? ''; bVal = b.datum ?? ''; break;
        case 'product_name': aVal = (a.product_name ?? '').toLowerCase(); bVal = (b.product_name ?? '').toLowerCase(); break;
        case 'user_name':    aVal = (a.user_name ?? '').toLowerCase(); bVal = (b.user_name ?? '').toLowerCase(); break;
        case 'ibc_ok':       aVal = a.ibc_ok ?? 0;     bVal = b.ibc_ok ?? 0; break;
        case 'bur_ej_ok':    aVal = a.bur_ej_ok ?? 0;  bVal = b.bur_ej_ok ?? 0; break;
        case 'ibc_ej_ok':    aVal = a.ibc_ej_ok ?? 0;  bVal = b.ibc_ej_ok ?? 0; break;
        case 'totalt':       aVal = a.totalt ?? 0;      bVal = b.totalt ?? 0; break;
        case 'kvalitet':     aVal = this.getQualityPct(a) ?? -1; bVal = this.getQualityPct(b) ?? -1; break;
        case 'ibc_per_h':    aVal = this.getIbcPerHour(a) ?? -1; bVal = this.getIbcPerHour(b) ?? -1; break;
        default:             aVal = a.datum ?? ''; bVal = b.datum ?? '';
      }
      if (aVal < bVal) return this.sortDir === 'asc' ? -1 : 1;
      if (aVal > bVal) return this.sortDir === 'asc' ?  1 : -1;
      return 0;
    });

    return result;
  }

  sortBy(field: SortField) {
    if (this.sortField === field) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortField = field;
      this.sortDir   = field === 'datum' ? 'desc' : 'asc';
    }
  }

  sortIcon(field: SortField): string {
    if (this.sortField !== field) return 'fa-sort';
    return this.sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
  }

  clearFilter() {
    this.filterFrom  = '';
    this.filterTo    = '';
    this.filterSkift = '';
    this.searchText  = '';
  }

  // ========== Summary KPIs (filtered set) ==========
  get summaryTotalIbc(): number {
    return this.filteredReports.reduce((s, r) => s + (r.totalt || 0), 0);
  }

  get summaryAvgQuality(): number | null {
    const reports = this.filteredReports.filter(r => r.totalt > 0);
    if (!reports.length) return null;
    const totalOk  = reports.reduce((s, r) => s + (r.ibc_ok || 0), 0);
    const totalAll = reports.reduce((s, r) => s + (r.totalt || 0), 0);
    return totalAll > 0 ? Math.round((totalOk / totalAll) * 100) : null;
  }

  get summaryAvgOee(): number | null {
    const reports = this.filteredReports.filter(r => (r.drifttid || 0) + (r.rasttime || 0) > 0 && r.totalt > 0);
    if (!reports.length) return null;
    // OEE simplified: (quality * efficiency) average over reports with PLC data
    const oeeSum = reports.reduce((s, r) => {
      const q = r.totalt > 0 ? (r.ibc_ok / r.totalt) : 0;
      const planned = (r.drifttid || 0) + (r.rasttime || 0);
      const avail   = planned > 0 ? Math.min((r.drifttid || 0) / planned, 1) : 0;
      return s + q * avail * 100;
    }, 0);
    return Math.round(oeeSum / reports.length);
  }

  get summaryTotalRast(): number {
    return this.filteredReports.reduce((s, r) => s + (r.rasttime || 0), 0);
  }

  get summaryTotalDrift(): number {
    return this.filteredReports.reduce((s, r) => s + (r.drifttid || 0), 0);
  }

  get summaryDeltaVsPrev(): number | null {
    const all = this.filteredReports;
    if (all.length < 2) return null;
    const current  = all[0]?.totalt;
    const previous = all[1]?.totalt;
    if (current == null || previous == null) return null;
    return current - previous;
  }

  get summaryBonusEstimate(): number | null {
    if (!this.filteredReports.length) return null;
    const latestReport = this.filteredReports[0];
    return this.getBonusEstimate(latestReport);
  }

  // ========== Computed KPIs per row ==========
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

  getAvgCycleTime(r: any): number | null {
    if (!r.drifttid || !r.ibc_ok) return null;
    return Math.round((r.drifttid / r.ibc_ok) * 10) / 10;
  }

  getStopCount(r: any): number {
    // PLC data not available per row, return 0 unless we have it
    return 0;
  }

  getBonusEstimate(r: any): number | null {
    if (!r || !r.totalt) return null;
    const target = this.settings?.rebotlingTarget || 1000;
    const quality = this.getQualityPct(r);
    if (quality == null) return null;
    // Simple bonus estimate: (totalt / target) * quality% * 100kr
    const pct = Math.min((r.totalt / target) * 100, 100);
    if (pct < 80) return 0;
    return Math.round((pct / 100) * (quality / 100) * 500);
  }

  getDeltaIbc(index: number): number | null {
    const reports = this.filteredReports;
    if (index >= reports.length - 1) return null;
    const current  = reports[index]?.totalt;
    const previous = reports[index + 1]?.totalt;
    if (current == null || previous == null) return null;
    return current - previous;
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
              const expandedCopy    = { ...this.expanded };
              const selectedIdsCopy = new Set(this.selectedIds);
              this.reports    = newReports;
              this.expanded   = expandedCopy;
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
      datum:       this.newReport.datum,
      product_id:  this.newReport.product_id,
      ibc_ok:      this.newReport.ibc_ok,
      bur_ej_ok:   this.newReport.bur_ej_ok,
      ibc_ej_ok:   this.newReport.ibc_ej_ok,
      totalt:      totalt
    }).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.fetchReports();
          this.newReport = {
            datum:      new Date().toISOString().split('T')[0],
            product_id: null,
            ibc_ok:     0,
            bur_ej_ok:  0,
            ibc_ej_ok:  0
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
      if (report && this.kommentarMap[id] === undefined) {
        this.laddaKommentar(report);
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
      datum:      datum,
      product_id: report.product_id,
      ibc_ok:     parseInt(report.ibc_ok,     10) || 0,
      bur_ej_ok:  parseInt(report.bur_ej_ok,  10) || 0,
      ibc_ej_ok:  parseInt(report.ibc_ej_ok,  10) || 0
    }).subscribe({
      next: (res) => {
        if (res.success) {
          report.totalt = (parseInt(report.ibc_ok, 10) || 0)
                        + (parseInt(report.bur_ej_ok, 10) || 0)
                        + (parseInt(report.ibc_ej_ok, 10) || 0);
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

  // ========== Kommentarer ==========
  getShiftNr(r: any): number {
    const timeStr = (r.datum || '').substring(11, 16);
    if (!timeStr) return 1;
    const [hh] = timeStr.split(':').map(Number);
    const minutes = hh * 60 + (parseInt(timeStr.split(':')[1] || '0', 10));
    if (minutes >= 6 * 60 && minutes < 14 * 60)  return 1; // förmiddag
    if (minutes >= 14 * 60 && minutes < 22 * 60) return 2; // eftermiddag
    return 3; // natt
  }

  laddaKommentar(report: any) {
    const id = report.id;
    const datum = (report.datum || '').substring(0, 10);
    const skiftNr = this.getShiftNr(report);
    this.kommentarLoading[id] = true;
    this.http.get<any>(
      `/noreko-backend/api.php?action=rebotling&run=skift-kommentar&datum=${datum}&skift_nr=${skiftNr}`,
      { withCredentials: true }
    )
    .pipe(takeUntil(this.destroy$))
    .subscribe({
      next: (res) => {
        this.kommentarLoading[id] = false;
        if (res.success && res.data) {
          this.kommentarMap[id] = res.data.kommentar || '';
          this.editKommentar[id] = res.data.kommentar || '';
        } else {
          this.kommentarMap[id] = '';
          this.editKommentar[id] = '';
        }
      },
      error: () => {
        this.kommentarLoading[id] = false;
        this.kommentarMap[id] = '';
        this.editKommentar[id] = '';
      }
    });
  }

  sparaKommentar(report: any) {
    const id = report.id;
    const datum = (report.datum || '').substring(0, 10);
    const skiftNr = this.getShiftNr(report);
    const text = (this.editKommentar[id] || '').substring(0, 500);
    this.spararKommentar[id] = true;
    this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=set-skift-kommentar',
      { datum, skift_nr: skiftNr, kommentar: text },
      { withCredentials: true }
    )
    .pipe(takeUntil(this.destroy$))
    .subscribe({
      next: (res) => {
        this.spararKommentar[id] = false;
        if (res.success) {
          this.kommentarMap[id] = text;
          this.redigerarKommentar[id] = false;
          this.showSuccess('Kommentar sparad');
        } else {
          this.errorMessage = res.error || 'Kunde inte spara kommentar';
        }
      },
      error: () => {
        this.spararKommentar[id] = false;
        this.errorMessage = 'Serverfel vid sparande av kommentar';
      }
    });
  }

  // ========== Export ==========
  exportCSV() {
    if (this.filteredReports.length === 0) return;

    const header = ['ID', 'Datum', 'Produkt', 'Användare', 'IBC OK', 'Bur ej OK', 'IBC ej OK', 'Totalt', 'Kvalitet%', 'IBC/h', 'Drifttid(min)', 'Rasttid(min)', 'Inlagd'];
    const rows = this.filteredReports.map((r: any) => [
      r.id,
      r.datum,
      r.product_name || '-',
      r.user_name || '-',
      r.ibc_ok,
      r.bur_ej_ok,
      r.ibc_ej_ok,
      r.totalt,
      this.getQualityPct(r) ?? '',
      this.getIbcPerHour(r) ?? '',
      r.drifttid ?? '',
      r.rasttime ?? '',
      r.inlagd == 1 ? 'Ja' : 'Nej'
    ]);

    const csvContent = [header, ...rows]
      .map(row => row.map(cell => `"${cell}"`).join(';'))
      .join('\n');

    const BOM  = '\uFEFF';
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href     = url;
    link.download = `skiftrapport-${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (this.filteredReports.length === 0) return;
    import('xlsx').then(XLSX => {
      const summaryData = [
        { 'Sammanfattning': '', 'Värde': '' },
        { 'Sammanfattning': 'Total IBC',  'Värde': this.summaryTotalIbc },
        { 'Sammanfattning': 'Kvalitet %', 'Värde': this.summaryAvgQuality ?? '' },
        { 'Sammanfattning': 'OEE %',      'Värde': this.summaryAvgOee ?? '' },
        { 'Sammanfattning': 'Drifttid (min)', 'Värde': this.summaryTotalDrift },
        { 'Sammanfattning': 'Rasttid (min)',  'Värde': this.summaryTotalRast },
        { 'Sammanfattning': '', 'Värde': '' },
      ];
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
        'Kassation %':     this.getDefectPct(r) ?? '',
        'Snitt cykeltid':  this.getAvgCycleTime(r) ?? '',
        'Bonus-estimat':   this.getBonusEstimate(r) ?? '',
        'Op1':             this.getOpLabel(r, 'op1'),
        'Op2':             this.getOpLabel(r, 'op2'),
        'Op3':             this.getOpLabel(r, 'op3'),
        'Drifttid (min)':  r.drifttid ?? '',
        'Rasttid (min)':   r.rasttime ?? '',
        'Löpnummer':       r.lopnummer ?? '',
        'Skifträknare':    r.skiftraknare ?? '',
        'Inlagd':          r.inlagd == 1 ? 'Ja' : 'Nej'
      }));
      const wsSummary = XLSX.utils.json_to_sheet(summaryData);
      const wsData    = XLSX.utils.json_to_sheet(data);
      const wb        = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, wsSummary, 'Sammanfattning');
      XLSX.utils.book_append_sheet(wb, wsData,    'Skiftrapporter');
      XLSX.writeFile(wb, `skiftrapporter-${new Date().toISOString().split('T')[0]}.xlsx`);
    });
  }

  exportPDF(report: any) {
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake  = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs    = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const docDef   = this.buildPDFDocDef(report);
        pdfMake.createPdf(docDef).download(`skiftrapport-${report.datum}-${report.id}.pdf`);
      });
    });
  }

  private buildPDFDocDef(r: any): any {
    const qualPct   = this.getQualityPct(r);
    const effPct    = this.getEfficiencyPct(r);
    const ibcH      = this.getIbcPerHour(r);
    const defPct    = this.getDefectPct(r);
    const avgCycle  = this.getAvgCycleTime(r);
    const bonus     = this.getBonusEstimate(r);
    const target    = this.settings?.rebotlingTarget || 1000;
    const achievePct = target > 0 ? Math.round((r.totalt / target) * 100) : null;

    return {
      content: [
        { text: 'Skiftrapport – Rebotling', style: 'header' },
        { text: `${r.datum}  |  ${r.product_name || '-'}  |  Skiftansvarig: ${r.user_name || '-'}`, style: 'subheader' },
        { text: '\n' },
        // --- Sammanfattningskort ---
        { text: 'Sammanfattning', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*', '*'],
            body: [
              [
                { text: 'Total IBC',      bold: true, fillColor: '#eeeeee' },
                { text: 'Dagsmål',        bold: true, fillColor: '#eeeeee' },
                { text: 'Uppfyllnad',     bold: true, fillColor: '#eeeeee' },
                { text: 'Kvalitet',       bold: true, fillColor: '#eeeeee' },
                { text: 'Bonus-estimat',  bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: String(r.totalt),                                        bold: true,  alignment: 'center' },
                { text: String(target),                                          alignment: 'center' },
                { text: achievePct != null ? achievePct + '%' : '–',             alignment: 'center',
                  color: achievePct != null && achievePct >= 100 ? 'green' : (achievePct != null && achievePct >= 80 ? 'orange' : 'red') },
                { text: qualPct != null ? qualPct + '%' : '–',                   alignment: 'center',
                  color: qualPct != null && qualPct >= 90 ? 'green' : 'black' },
                { text: bonus != null ? bonus + ' kr' : '–',                     alignment: 'center', color: 'darkblue' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        // --- Produktion ---
        { text: 'Produktion', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*'],
            body: [
              [
                { text: 'IBC OK',     bold: true, fillColor: '#eeeeee' },
                { text: 'Bur ej OK',  bold: true, fillColor: '#eeeeee' },
                { text: 'IBC ej OK',  bold: true, fillColor: '#eeeeee' },
                { text: 'Totalt',     bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: String(r.ibc_ok),     alignment: 'center' },
                { text: String(r.bur_ej_ok),  alignment: 'center' },
                { text: String(r.ibc_ej_ok),  alignment: 'center' },
                { text: String(r.totalt),     bold: true, alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        // --- Nyckeltal ---
        { text: 'Nyckeltal', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*', '*'],
            body: [
              [
                { text: 'Kvalitet',     bold: true, fillColor: '#eeeeee' },
                { text: 'Effektivitet', bold: true, fillColor: '#eeeeee' },
                { text: 'IBC/timme',    bold: true, fillColor: '#eeeeee' },
                { text: 'Kassation',    bold: true, fillColor: '#eeeeee' },
                { text: 'Snitt cykeltid', bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: qualPct  != null ? qualPct  + '%'    : '–', alignment: 'center', color: qualPct != null && qualPct >= 90 ? 'green' : 'black' },
                { text: effPct   != null ? effPct   + '%'    : '–', alignment: 'center' },
                { text: ibcH     != null ? ibcH     + ' st/h': '–', alignment: 'center' },
                { text: defPct   != null ? defPct   + '%'    : '–', alignment: 'center', color: defPct != null && defPct > 10 ? 'red' : 'black' },
                { text: avgCycle != null ? avgCycle + ' min' : '–', alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        // --- PLC-data ---
        { text: 'PLC-data', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*', '*', '*'],
            body: [
              [
                { text: 'Tvättplats',    bold: true, fillColor: '#eeeeee' },
                { text: 'Kontrollstation', bold: true, fillColor: '#eeeeee' },
                { text: 'Truckförare',   bold: true, fillColor: '#eeeeee' },
                { text: 'Drifttid',      bold: true, fillColor: '#eeeeee' },
                { text: 'Rasttid',       bold: true, fillColor: '#eeeeee' },
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
        { text: 'Skiftkommentar: ' + (this.kommentarMap[r.id] || '–'), style: 'meta' },
        { text: 'Skiftansvarig: ' + (r.user_name || '-'), style: 'meta' },
        { text: 'Inlagd i system: ' + (r.inlagd == 1 ? 'Ja' : 'Nej'), style: 'meta' },
        { text: 'Genererad: ' + new Date().toLocaleString('sv-SE'), style: 'meta' }
      ],
      styles: {
        header:        { fontSize: 22, bold: true, margin: [0, 0, 0, 4] },
        subheader:     { fontSize: 11, color: '#555555', margin: [0, 0, 0, 10] },
        sectionHeader: { fontSize: 13, bold: true, margin: [0, 10, 0, 4] },
        meta:          { fontSize: 10, color: '#777777', margin: [0, 2, 0, 0] }
      },
      defaultStyle: { fontSize: 11 },
      pageMargins: [40, 50, 40, 50]
    };
  }

  // ========== Skiftjämförelse ==========
  compareShifts() {
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(this.compareDateA) || !dateRegex.test(this.compareDateB)) {
      this.compareError = 'Ange giltiga datum (ÅÅÅÅ-MM-DD) för båda fälten';
      return;
    }
    if (this.compareDateA === this.compareDateB) {
      this.compareError = 'Välj två olika datum för att jämföra';
      return;
    }
    this.compareError   = '';
    this.compareResult  = null;
    this.compareLoading = true;
    this.http.get<any>(
      `/noreko-backend/api.php?action=rebotling&run=shift-compare&date_a=${this.compareDateA}&date_b=${this.compareDateB}`,
      { withCredentials: true }
    )
    .pipe(takeUntil(this.destroy$))
    .subscribe({
      next: (res) => {
        this.compareLoading = false;
        if (res.success) {
          this.compareResult = { a: res.data.a, b: res.data.b };
        } else {
          this.compareError = res.error || 'Kunde inte hämta jämförelsedata';
        }
      },
      error: () => {
        this.compareLoading = false;
        this.compareError = 'Serverfel vid jämförelse';
      }
    });
  }

  clearCompare() {
    this.compareResult  = null;
    this.compareError   = '';
    this.compareDateA   = '';
    this.compareDateB   = '';
  }

  compareDiff(fieldA: number | null, fieldB: number | null): number | null {
    if (fieldA == null || fieldB == null) return null;
    return Math.round((fieldB - fieldA) * 10) / 10;
  }

  compareIsImprovement(field: string, diff: number | null): boolean {
    if (diff == null) return false;
    // För rasttid är lägre bättre
    if (field === 'rasttime') return diff < 0;
    return diff > 0;
  }

  compareIsWorse(field: string, diff: number | null): boolean {
    if (diff == null) return false;
    if (field === 'rasttime') return diff > 0;
    return diff < 0;
  }

  formatMinutes(min: number | null): string {
    if (min == null || min === 0) return '–';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h ${m}min` : `${m}min`;
  }

  // ========== Toast ==========
  showSuccess(message: string) {
    this.successMessage      = message;
    this.showSuccessMessage  = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
}
