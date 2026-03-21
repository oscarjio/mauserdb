import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { localToday, parseLocalDate } from '../../utils/date-utils';
import { environment } from '../../../environments/environment';

interface Certification {
  id: number;
  line: string;
  certified_date: string;
  expires_date: string | null;
  notes: string | null;
  active: number;
  days_until_expiry: number | null;
  created_at: string;
}

interface OperatorCerts {
  op_number: number;
  name: string;
  certifications: Certification[];
}

interface OperatorOption {
  id: number;
  name: string;
  number: number;
  active: number;
}

// Matris-typer
interface MatrixOperator {
  id: number;
  number: number;
  name: string;
}

interface MatrixLine {
  key: string;
  label: string;
}

interface MatrixCell {
  status: 'valid' | 'expiring' | 'expired';
  certified_date: string;
  expires_date: string | null;
  days_left: number | null;
}

interface MatrixData {
  operators: MatrixOperator[];
  lines: MatrixLine[];
  matrix: { [opNumber: number]: { [lineKey: string]: MatrixCell | null } };
}

@Component({
  standalone: true,
  selector: 'app-certifications',
  imports: [CommonModule, FormsModule],
  templateUrl: './certifications.html',
  styleUrl: './certifications.css'
})
export class CertificationsPage implements OnInit, OnDestroy {
  operators: OperatorCerts[] = [];
  operatorOptions: OperatorOption[] = [];
  loading = false;
  error = '';

  // Aktiv flik: 'lista' | 'matris'
  activeTab: 'lista' | 'matris' = 'lista';

  // Matris
  matrixData: MatrixData | null = null;
  matrixLoading = false;
  matrixError = '';

  // Linje-filter
  activeLineFilter = 'alla';
  readonly lineFilters = [
    { key: 'alla', label: 'Alla' },
    { key: 'rebotling', label: 'Rebotling' },
    { key: 'tvattlinje', label: 'Tvättlinje' },
    { key: 'saglinje', label: 'Såglinje' },
    { key: 'klassificeringslinje', label: 'Klassificeringslinje' }
  ];

  readonly lineLabels: Record<string, string> = {
    rebotling: 'Rebotling',
    tvattlinje: 'Tvättlinje',
    saglinje: 'Såglinje',
    klassificeringslinje: 'Klassificeringslinje'
  };

  // Statusfilter
  statusFilter: 'all' | 'active' | 'expiring_soon' | 'expired' = 'all';
  readonly statusFilters: { key: 'all' | 'active' | 'expiring_soon' | 'expired'; label: string }[] = [
    { key: 'all',           label: 'Alla' },
    { key: 'active',        label: 'Aktiva' },
    { key: 'expiring_soon', label: 'Upphör snart' },
    { key: 'expired',       label: 'Utgångna' }
  ];

  // Sortering
  sortBy: 'name' | 'expiry' = 'name';

  // Lägg till-formulär
  showAddForm = false;
  addForm = {
    op_number: null as number | null,
    line: '',
    certified_date: localToday(),
    expires_date: '',
    notes: ''
  };
  addLoading = false;
  addError = '';
  addSuccess = '';

  private destroy$ = new Subject<void>();

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadCertifications();
    this.loadOperatorOptions();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadCertifications() {
    this.loading = true;
    this.error = '';
    this.http.get<any>(`${environment.apiUrl}?action=certifications&run=all`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => {
          this.error = 'Kunde inte hämta certifieringar. Försök igen.';
          this.loading = false;
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loading = false;
        if (res?.success) {
          this.operators = res.operators ?? [];
        } else if (res) {
          this.error = res.error || 'Okänt fel';
        }
      });
  }

  loadOperatorOptions() {
    this.http.get<any>(`${environment.apiUrl}?action=operators&run=list`, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (res?.operators) {
          this.operatorOptions = res.operators.filter((o: OperatorOption) => o.active !== 0);
        }
      });
  }

  loadMatrix() {
    this.matrixLoading = true;
    this.matrixError = '';
    this.http.get<any>(`${environment.apiUrl}?action=certifications&run=matrix`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => {
          this.matrixError = 'Kunde inte hämta kompetensmatris.';
          this.matrixLoading = false;
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.matrixLoading = false;
        if (res?.success) {
          this.matrixData = res as MatrixData;
        } else if (res) {
          this.matrixError = res.error || 'Okänt fel';
        }
      });
  }

  setTab(tab: 'lista' | 'matris') {
    this.activeTab = tab;
    if (tab === 'matris' && !this.matrixData && !this.matrixLoading) {
      this.loadMatrix();
    }
  }

  // ====== KPI-beräkningar ======

  get totalCertifications(): number {
    let count = 0;
    for (const op of this.operators) {
      for (const c of op.certifications) {
        if (c.active === 1) count++;
      }
    }
    return count;
  }

  get totalCertifiedOperators(): number {
    return this.operators.filter(o =>
      o.certifications.some(c => c.active === 1)
    ).length;
  }

  get validCount(): number {
    let count = 0;
    for (const op of this.operators) {
      for (const c of op.certifications) {
        if (c.active === 1 && (c.days_until_expiry === null || c.days_until_expiry > 30)) {
          count++;
        }
      }
    }
    return count;
  }

  get expiringSoon(): number {
    let count = 0;
    for (const op of this.operators) {
      for (const c of op.certifications) {
        if (c.active === 1 && c.days_until_expiry !== null && c.days_until_expiry >= 0 && c.days_until_expiry <= 30) {
          count++;
        }
      }
    }
    return count;
  }

  get expired(): number {
    let count = 0;
    for (const op of this.operators) {
      for (const c of op.certifications) {
        if (c.active === 1 && c.days_until_expiry !== null && c.days_until_expiry < 0) {
          count++;
        }
      }
    }
    return count;
  }

  get hasWarnings(): boolean {
    return this.expiringSoon > 0 || this.expired > 0;
  }

  // Alias-getters för summary-badges
  get expiredCount(): number { return this.expired; }
  get expiringSoonCount(): number { return this.expiringSoon; }
  get activeCount(): number { return this.validCount; }

  // ====== "Snart utgångna" lista (< 30 dagar) ======

  get expiringSoonList(): { opName: string; cert: Certification }[] {
    const list: { opName: string; cert: Certification }[] = [];
    for (const op of this.operators) {
      for (const c of op.certifications) {
        if (c.active === 1 && c.days_until_expiry !== null && c.days_until_expiry <= 30) {
          list.push({ opName: op.name, cert: c });
        }
      }
    }
    // Sortera: utgångna först, sedan snart utgångna stigande
    list.sort((a, b) => {
      const da = a.cert.days_until_expiry ?? 999;
      const db = b.cert.days_until_expiry ?? 999;
      return da - db;
    });
    return list;
  }

  // ====== Statusfilter-hantering ======

  setStatusFilter(key: 'all' | 'active' | 'expiring_soon' | 'expired') {
    this.statusFilter = key;
  }

  /**
   * Kontrollerar om ett certifikat matchar nuvarande statusfilter
   */
  private certMatchesStatusFilter(cert: Certification): boolean {
    const d = cert.days_until_expiry;
    switch (this.statusFilter) {
      case 'active':
        // Aktivt = inget utgångsdatum ELLER mer än 30 dagar kvar
        return d === null || d > 30;
      case 'expiring_soon':
        // Upphör snart = 0–30 dagar kvar
        return d !== null && d >= 0 && d <= 30;
      case 'expired':
        // Utgångna = negativt antal dagar
        return d !== null && d < 0;
      default:
        return true;
    }
  }

  // ====== Filtrering och sortering ======

  get filteredOperators(): OperatorCerts[] {
    let result = this.operators
      .map(op => {
        let certs = op.certifications.filter(c => c.active === 1);
        // Linje-filter
        if (this.activeLineFilter !== 'alla') {
          certs = certs.filter(c => c.line === this.activeLineFilter);
        }
        // Statusfilter
        if (this.statusFilter !== 'all') {
          certs = certs.filter(c => this.certMatchesStatusFilter(c));
        }
        return { ...op, certifications: certs };
      })
      .filter(op => op.certifications.length > 0);

    if (this.sortBy === 'expiry') {
      result = result.map(op => {
        const sorted = [...op.certifications].sort((a, b) => {
          const da = a.days_until_expiry ?? 9999;
          const db = b.days_until_expiry ?? 9999;
          return da - db;
        });
        return { ...op, certifications: sorted };
      });
    }

    return result;
  }

  setLineFilter(key: string) {
    this.activeLineFilter = key;
  }

  setSortBy(val: 'name' | 'expiry') {
    this.sortBy = val;
  }

  // ====== Rad-klass för visuell highlight ======

  certRowClass(cert: Certification): string {
    if (cert.days_until_expiry === null) return 'cert-valid';
    if (cert.days_until_expiry < 0) return 'cert-expired';
    if (cert.days_until_expiry <= 30) return 'cert-expiring-soon';
    return 'cert-valid';
  }

  certDaysLeft(cert: Certification): string {
    if (cert.days_until_expiry === null) return '—';
    if (cert.days_until_expiry < 0) return `${Math.abs(cert.days_until_expiry)} dagar sedan`;
    if (cert.days_until_expiry === 0) return 'Idag';
    return `${cert.days_until_expiry} dagar kvar`;
  }

  certDaysLeftBadgeClass(cert: Certification): string {
    if (cert.days_until_expiry === null) return 'days-badge days-badge-valid';
    if (cert.days_until_expiry < 0) return 'days-badge days-badge-expired';
    if (cert.days_until_expiry <= 30) return 'days-badge days-badge-warning';
    return 'days-badge days-badge-valid';
  }

  // ====== Badge-klassificering ======

  getBadgeClass(cert: Certification): string {
    if (cert.days_until_expiry === null) return 'badge-valid';
    if (cert.days_until_expiry < 0) return 'badge-expired';
    if (cert.days_until_expiry <= 30) return 'badge-warning';
    return 'badge-valid';
  }

  getBadgeLabel(cert: Certification): string {
    const line = this.lineLabels[cert.line] ?? cert.line;
    if (cert.days_until_expiry === null) return line;
    if (cert.days_until_expiry < 0) return line + ' (utgången)';
    if (cert.days_until_expiry === 0) return line + ' (sista dag)';
    if (cert.days_until_expiry <= 30) return line + ' (' + cert.days_until_expiry + ' d kvar)';
    return line;
  }

  // ====== Matris-hjälpfunktioner ======

  getMatrixCellClass(cell: MatrixCell | null): string {
    if (!cell) return 'matrix-cell-none';
    if (cell.status === 'expired') return 'matrix-cell-expired';
    if (cell.status === 'expiring') return 'matrix-cell-expiring';
    return 'matrix-cell-valid';
  }

  getMatrixCellIcon(cell: MatrixCell | null): string {
    if (!cell) return 'fa-times-circle';
    if (cell.status === 'expired') return 'fa-times-circle';
    if (cell.status === 'expiring') return 'fa-exclamation-circle';
    return 'fa-check-circle';
  }

  getMatrixCellTitle(cell: MatrixCell | null, opName: string, lineLabel: string): string {
    if (!cell) return opName + ': ej certifierad för ' + lineLabel;
    const base = opName + ': ' + lineLabel;
    if (cell.status === 'expired') return base + ' — certifiering utgången ' + this.formatDate(cell.expires_date);
    if (cell.status === 'expiring') return base + ' — utgår om ' + cell.days_left + ' dagar (' + this.formatDate(cell.expires_date) + ')';
    return base + ' — giltig, certifierad ' + this.formatDate(cell.certified_date);
  }

  getMatrixCell(opNumber: number, lineKey: string): MatrixCell | null {
    if (!this.matrixData?.matrix) return null;
    const opRow = this.matrixData.matrix[opNumber];
    if (!opRow) return null;
    return opRow[lineKey] ?? null;
  }

  // ====== Avatar ======

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

  // ====== Återkalla certifiering ======

  revoke(certId: number) {
    if (!confirm('Är du säker på att du vill återkalla denna certifiering?')) return;

    this.http.post<any>(
      `${environment.apiUrl}?action=certifications&run=revoke`,
      { id: certId },
      { withCredentials: true }
    ).pipe(
      timeout(5000),
      catchError(err => { console.error('revoke failed', err); return of({ success: false, error: 'Nätverksfel' }); }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.loadCertifications();
        // Ladda om matrisen om den är aktiv
        if (this.activeTab === 'matris') {
          this.loadMatrix();
        } else {
          this.matrixData = null; // Tvinga omladdning nästa gång
        }
      } else {
        this.error = res?.error || 'Kunde inte återkalla certifiering';
      }
    });
  }

  // ====== Lägg till certifiering ======

  toggleAddForm() {
    this.showAddForm = !this.showAddForm;
    this.addError = '';
    this.addSuccess = '';
  }

  submitAdd() {
    this.addError = '';
    this.addSuccess = '';

    if (!this.addForm.op_number) {
      this.addError = 'Välj en operatör.';
      return;
    }
    if (!this.addForm.line) {
      this.addError = 'Välj en linje.';
      return;
    }
    if (!this.addForm.certified_date) {
      this.addError = 'Certifieringsdatum krävs.';
      return;
    }

    const payload: any = {
      op_number: this.addForm.op_number,
      line: this.addForm.line,
      certified_date: this.addForm.certified_date
    };
    if (this.addForm.expires_date) payload.expires_date = this.addForm.expires_date;
    if (this.addForm.notes.trim()) payload.notes = this.addForm.notes.trim();

    this.addLoading = true;
    this.http.post<any>(
      `${environment.apiUrl}?action=certifications&run=add`,
      payload,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(err => { console.error('submitAdd failed', err); return of({ success: false, error: 'Nätverksfel' }); }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.addLoading = false;
      if (res?.success) {
        this.addSuccess = 'Certifiering tillagd.';
        this.addForm = {
          op_number: null,
          line: '',
          certified_date: localToday(),
          expires_date: '',
          notes: ''
        };
        this.loadCertifications();
        this.matrixData = null; // Tvinga omladdning av matris
      } else {
        this.addError = res?.error || 'Kunde inte lägga till certifiering';
      }
    });
  }

  // ====== CSV-export (respekterar statusfilter + linjefilter) ======

  exportCSV(): void {
    const headers = ['Operatör', 'Certifikat', 'Utfärdat', 'Utgångsdatum', 'Dagar kvar', 'Status'];

    const rows: string[][] = this.filteredOperators.flatMap(op =>
      op.certifications.map(cert => {
        const line = this.lineLabels[cert.line] ?? cert.line;
        const certDate = this.formatDate(cert.certified_date);
        const expiryDate = this.formatDate(cert.expires_date);
        const daysLeft = cert.days_until_expiry !== null ? String(cert.days_until_expiry) : '';
        let status = 'Giltig';
        if (cert.days_until_expiry !== null && cert.days_until_expiry < 0) {
          status = 'Utgånget';
        } else if (cert.days_until_expiry !== null && cert.days_until_expiry <= 30) {
          status = 'Upphör snart';
        }
        return [op.name, line, certDate, expiryDate, daysLeft, status];
      })
    );

    const csv = [headers, ...rows]
      .map(r => r.map(cell => '"' + String(cell).replace(/"/g, '""') + '"').join(';'))
      .join('\n');

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `certifieringar-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  formatDate(dateStr: string | null): string {
    if (!dateStr) return '—';
    const d = parseLocalDate(dateStr);
    return d.toLocaleDateString('sv-SE');
  }
  trackByIndex(index: number): number { return index; }
}
