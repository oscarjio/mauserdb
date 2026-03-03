import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';

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

  // Filter
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

  // Lägg till-formulär
  showAddForm = false;
  addForm = {
    op_number: null as number | null,
    line: '',
    certified_date: new Date().toISOString().split('T')[0],
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
    this.http.get<any>('/noreko-backend/api.php?action=certifications&run=all', { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(err => {
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
    this.http.get<any>('/noreko-backend/api.php?action=operators&run=list', { withCredentials: true })
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

  // ====== KPI-beräkningar ======

  get totalCertifiedOperators(): number {
    return this.operators.filter(o =>
      o.certifications.some(c => c.active === 1)
    ).length;
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

  // ====== Filtrering ======

  get filteredOperators(): OperatorCerts[] {
    return this.operators
      .map(op => {
        let certs = op.certifications.filter(c => c.active === 1);
        if (this.activeLineFilter !== 'alla') {
          certs = certs.filter(c => c.line === this.activeLineFilter);
        }
        return { ...op, certifications: certs };
      })
      .filter(op => op.certifications.length > 0);
  }

  setLineFilter(key: string) {
    this.activeLineFilter = key;
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
      '/noreko-backend/api.php?action=certifications&run=revoke',
      { id: certId },
      { withCredentials: true }
    ).pipe(
      timeout(5000),
      catchError(() => of({ success: false, error: 'Nätverksfel' })),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.loadCertifications();
      } else {
        alert(res?.error || 'Kunde inte återkalla certifiering');
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
      '/noreko-backend/api.php?action=certifications&run=add',
      payload,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of({ success: false, error: 'Nätverksfel' })),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.addLoading = false;
      if (res?.success) {
        this.addSuccess = 'Certifiering tillagd.';
        this.addForm = {
          op_number: null,
          line: '',
          certified_date: new Date().toISOString().split('T')[0],
          expires_date: '',
          notes: ''
        };
        this.loadCertifications();
      } else {
        this.addError = res?.error || 'Kunde inte lägga till certifiering';
      }
    });
  }

  formatDate(dateStr: string | null): string {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('sv-SE');
  }
}
