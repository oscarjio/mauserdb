import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { environment } from '../../../environments/environment';

interface NewsItem {
  id: number;
  title: string;
  body: string;
  category: string;
  pinned: boolean;
  published: boolean;
  priority: number;
  created_at: string;
  updated_at: string;
}

@Component({
  standalone: true,
  selector: 'app-news-admin',
  imports: [CommonModule, FormsModule],
  template: `
    <div class="news-admin-page">
      <div class="container-fluid py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="page-title mb-0">
            <i class="fas fa-newspaper me-2 text-info"></i>Nyheter Admin
          </h2>
          <button class="btn btn-success" (click)="toggleForm()" *ngIf="!showForm">
            <i class="fas fa-plus me-2"></i>Skapa ny nyhet
          </button>
          <button class="btn btn-secondary" (click)="cancelForm()" *ngIf="showForm">
            <i class="fas fa-times me-2"></i>Stäng formulär
          </button>
        </div>

        <!-- Formulär -->
        <div class="card form-card mb-4" *ngIf="showForm">
          <div class="card-body">
            <h5 class="card-title mb-3">
              {{ editingId ? 'Redigera nyhet' : 'Skapa ny nyhet' }}
            </h5>

            <div class="mb-3">
              <label class="form-label">Rubrik <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control form-control-dark"
                [(ngModel)]="form.title"
                placeholder="Ange rubrik..."
                maxlength="255"
              />
            </div>

            <div class="mb-3">
              <label class="form-label">Innehåll</label>
              <textarea
                class="form-control form-control-dark"
                [(ngModel)]="form.content"
                rows="4"
                placeholder="Ange innehåll..."
              ></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">Typ</label>
              <select class="form-select form-control-dark" [(ngModel)]="form.category">
                <optgroup label="Nyhetstyper">
                  <option value="rekord">Rekord</option>
                  <option value="hog_oee">Hög OEE</option>
                  <option value="certifiering">Certifiering</option>
                  <option value="urgent">Brådskande</option>
                  <option value="info">Info</option>
                </optgroup>
                <optgroup label="Övriga">
                  <option value="produktion">Produktion</option>
                  <option value="bonus">Bonus</option>
                  <option value="system">System</option>
                  <option value="viktig">Viktig</option>
                </optgroup>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Prioritet <small class="text-muted ms-1">(1=låg, 5=hög)</small></label>
              <div class="d-flex align-items-center gap-2">
                <input
                  type="range"
                  class="form-range priority-range"
                  min="1"
                  max="5"
                  step="1"
                  [(ngModel)]="form.priority"
                />
                <span class="priority-badge" [ngClass]="priorityBadgeClass(form.priority)">
                  {{ priorityLabel(form.priority) }}
                </span>
              </div>
            </div>

            <div class="mb-3 d-flex gap-4">
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="pinnedCheck"
                  [(ngModel)]="form.pinned"
                />
                <label class="form-check-label" for="pinnedCheck">
                  <i class="fas fa-thumbtack me-1 text-warning"></i>Pinnad
                  <small class="text-muted ms-1">(visas med gul kant)</small>
                </label>
              </div>
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="publishedCheck"
                  [(ngModel)]="form.published"
                />
                <label class="form-check-label" for="publishedCheck">
                  <i class="fas fa-eye me-1 text-success"></i>Publicerad
                  <small class="text-muted ms-1">(syns på startsidan)</small>
                </label>
              </div>
            </div>

            <div class="alert alert-danger py-2" *ngIf="formError">{{ formError }}</div>
            <div class="alert alert-success py-2" *ngIf="formSuccess">{{ formSuccess }}</div>

            <div class="d-flex gap-2">
              <button
                class="btn btn-primary"
                (click)="saveNews()"
                [disabled]="saving"
              >
                <span *ngIf="saving"><i class="fas fa-spinner fa-spin me-2"></i>Sparar...</span>
                <span *ngIf="!saving"><i class="fas fa-save me-2"></i>{{ editingId ? 'Uppdatera' : 'Skapa' }}</span>
              </button>
              <button class="btn btn-outline-secondary" (click)="cancelForm()">
                Avbryt
              </button>
            </div>
          </div>
        </div>

        <!-- Laddning / Fel -->
        <div class="text-center py-5" *ngIf="loading">
          <div class="spinner-border text-info" role="status"></div>
          <p class="mt-2 text-muted">Hämtar nyheter...</p>
        </div>
        <div class="alert alert-danger" *ngIf="loadError && !loading">{{ loadError }}</div>

        <!-- Tabell -->
        <div class="card table-card" *ngIf="!loading">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-dark table-hover mb-0">
                <thead>
                  <tr>
                    <th class="text-muted small" style="width:50px">ID</th>
                    <th class="text-muted small">Rubrik</th>
                    <th class="text-muted small" style="width:130px">Typ</th>
                    <th class="text-muted small text-center" style="width:70px">Prio</th>
                    <th class="text-muted small text-center" style="width:80px">Pinnad</th>
                    <th class="text-muted small text-center" style="width:100px">Publicerad</th>
                    <th class="text-muted small" style="width:160px">Datum</th>
                    <th class="text-muted small text-end" style="width:140px">Åtgärder</th>
                  </tr>
                </thead>
                <tbody>
                  <tr *ngFor="let item of newsList" [class.row-pinned]="item.pinned" [class.row-urgent]="item.category === 'urgent'">
                    <td class="text-muted">{{ item.id }}</td>
                    <td>
                      <div class="news-title">{{ item.title }}</div>
                      <div class="news-body-preview text-muted small" *ngIf="item.body">
                        {{ item.body | slice:0:80 }}{{ item.body.length > 80 ? '...' : '' }}
                      </div>
                    </td>
                    <td>
                      <span class="badge" [ngClass]="categoryBadgeClass(item.category)">
                        {{ categoryLabel(item.category) }}
                      </span>
                    </td>
                    <td class="text-center">
                      <span class="priority-dot" [ngClass]="priorityBadgeClass(item.priority ?? 3)" title="Prioritet {{ item.priority ?? 3 }}">
                        {{ item.priority ?? 3 }}
                      </span>
                    </td>
                    <td class="text-center">
                      <i class="fas fa-thumbtack text-warning" *ngIf="item.pinned" title="Pinnad"></i>
                      <span class="text-muted" *ngIf="!item.pinned">—</span>
                    </td>
                    <td class="text-center">
                      <i class="fas fa-eye text-success" *ngIf="item.published" title="Publicerad"></i>
                      <i class="fas fa-eye-slash text-muted" *ngIf="!item.published" title="Opublicerad"></i>
                    </td>
                    <td class="text-muted small">{{ formatDate(item.created_at) }}</td>
                    <td class="text-end">
                      <button
                        class="btn btn-sm btn-outline-info me-1"
                        (click)="editNews(item)"
                        title="Redigera"
                      >
                        <i class="fas fa-edit"></i>
                      </button>
                      <button
                        class="btn btn-sm btn-outline-danger"
                        (click)="deleteNews(item)"
                        title="Ta bort"
                      >
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <tr *ngIf="newsList.length === 0 && !loading">
                    <td colspan="8" class="text-center text-muted py-4">
                      Inga nyheter hittades. Skapa en ny nyhet ovan.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  `,
  styles: [`
    .news-admin-page {
      background: #1a202c;
      min-height: 100vh;
      color: #e2e8f0;
    }
    .page-title {
      color: #e2e8f0;
      font-size: 1.5rem;
    }
    .form-card, .table-card {
      background: #2d3748;
      border: 1px solid #4a5568;
      border-radius: 8px;
    }
    .card-title {
      color: #e2e8f0;
    }
    .form-label {
      color: #a0aec0;
      font-size: 0.875rem;
      margin-bottom: 4px;
    }
    .form-control-dark,
    .form-select.form-control-dark {
      background: #1a202c;
      border: 1px solid #4a5568;
      color: #e2e8f0;
      border-radius: 6px;
    }
    .form-control-dark:focus,
    .form-select.form-control-dark:focus {
      background: #1a202c;
      border-color: #63b3ed;
      color: #e2e8f0;
      box-shadow: 0 0 0 2px rgba(99,179,237,0.2);
    }
    .form-control-dark::placeholder {
      color: #718096;
    }
    .form-check-label {
      color: #a0aec0;
      font-size: 0.875rem;
    }
    .form-check-input {
      background-color: #1a202c;
      border-color: #4a5568;
    }
    .form-check-input:checked {
      background-color: #4299e1;
      border-color: #4299e1;
    }
    .table-dark {
      background: transparent;
      --bs-table-bg: transparent;
      --bs-table-hover-bg: rgba(99,179,237,0.06);
    }
    .table-dark th {
      border-bottom: 1px solid #4a5568;
      font-weight: 500;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      font-size: 0.75rem;
      padding: 12px 16px;
    }
    .table-dark td {
      border-bottom: 1px solid #2d3748;
      padding: 10px 16px;
      vertical-align: middle;
    }
    .row-pinned td:first-child {
      border-left: 3px solid #ecc94b;
    }
    .row-urgent td:first-child {
      border-left: 3px solid #e53e3e;
    }
    .news-title {
      font-weight: 500;
      color: #e2e8f0;
    }
    .news-body-preview {
      margin-top: 2px;
    }
    /* Priority range */
    .priority-range {
      flex: 1;
      accent-color: #4299e1;
    }
    .priority-badge {
      min-width: 72px;
      text-align: center;
      padding: 0.2rem 0.6rem;
      border-radius: 6px;
      font-size: 0.78rem;
      font-weight: 600;
    }
    .priority-dot {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .prio-low      { background: rgba(113,128,150,0.2); color: #a0aec0; }
    .prio-normal   { background: rgba(56,178,172,0.2);  color: #38b2ac; }
    .prio-medium   { background: rgba(66,153,225,0.2);  color: #4299e1; }
    .prio-high     { background: rgba(237,137,54,0.2);  color: #ed8936; }
    .prio-critical { background: rgba(229,62,62,0.2);   color: #e53e3e; }
    /* Custom badge colors */
    .bg-teal   { background-color: #0d9488 !important; }
    .bg-purple { background-color: #7c3aed !important; }
  `]
})
export class NewsAdminPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  newsList: NewsItem[] = [];
  loading = false;
  loadError = '';

  showForm = false;
  editingId: number | null = null;
  saving = false;
  formError = '';
  formSuccess = '';

  form = {
    title: '',
    content: '',
    category: 'info',
    pinned: false,
    published: true,
    priority: 3,
  };

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadNews();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadNews() {
    this.loading = true;
    this.loadError = '';
    this.http.get<{ success: boolean; news: NewsItem[] }>(
      `${this.apiBase}?action=news&run=admin-list`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loading = false;
      if (res && res.success) {
        this.newsList = res.news;
      } else {
        this.loadError = 'Kunde inte hämta nyheter. Försök igen.';
      }
    });
  }

  toggleForm() {
    this.showForm = true;
    this.editingId = null;
    this.resetForm();
  }

  cancelForm() {
    this.showForm = false;
    this.editingId = null;
    this.resetForm();
  }

  resetForm() {
    this.form = { title: '', content: '', category: 'info', pinned: false, published: true, priority: 3 };
    this.formError = '';
    this.formSuccess = '';
  }

  editNews(item: NewsItem) {
    this.showForm = true;
    this.editingId = item.id;
    this.form = {
      title: item.title,
      content: item.body,
      category: item.category,
      pinned: item.pinned,
      published: item.published,
      priority: item.priority ?? 3,
    };
    this.formError = '';
    this.formSuccess = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  saveNews() {
    if (!this.form.title.trim()) {
      this.formError = 'Rubrik krävs.';
      return;
    }

    this.saving = true;
    this.formError = '';
    this.formSuccess = '';

    const run = this.editingId ? 'update' : 'create';
    const payload: any = {
      title: this.form.title.trim(),
      content: this.form.content.trim(),
      category: this.form.category,
      pinned: this.form.pinned,
      published: this.form.published,
      priority: this.form.priority,
    };
    if (this.editingId) {
      payload.id = this.editingId;
    }

    this.http.post<{ success: boolean; id?: number; error?: string }>(
      `${this.apiBase}?action=news&run=${run}`,
      payload,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.saving = false;
      if (res && res.success) {
        this.formSuccess = this.editingId ? 'Nyhet uppdaterad.' : 'Nyhet skapad.';
        setTimeout(() => {
          this.cancelForm();
          this.loadNews();
        }, 800);
      } else {
        this.formError = res?.error ?? 'Kunde inte spara. Försök igen.';
      }
    });
  }

  deleteNews(item: NewsItem) {
    if (!confirm(`Ta bort nyheten "${item.title}"? Detta kan inte ångras.`)) return;

    this.http.post<{ success: boolean; error?: string }>(
      `${this.apiBase}?action=news&run=delete`,
      { id: item.id },
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res && res.success) {
        this.newsList = this.newsList.filter(n => n.id !== item.id);
      } else {
        alert('Kunde inte ta bort nyheten. Försök igen.');
      }
    });
  }

  categoryLabel(cat: string): string {
    const map: Record<string, string> = {
      produktion:    'Produktion',
      bonus:         'Bonus',
      system:        'System',
      info:          'Info',
      viktig:        'Viktig',
      rekord:        'Rekord',
      hog_oee:       'Hög OEE',
      certifiering:  'Certifiering',
      urgent:        'Brådskande',
    };
    return map[cat] ?? cat;
  }

  categoryBadgeClass(cat: string): string {
    const map: Record<string, string> = {
      produktion:    'bg-primary',
      bonus:         'bg-warning text-dark',
      system:        'bg-secondary',
      info:          'bg-info text-dark',
      viktig:        'bg-danger',
      rekord:        'bg-success',
      hog_oee:       'bg-teal text-dark',
      certifiering:  'bg-purple',
      urgent:        'bg-danger',
    };
    return map[cat] ?? 'bg-secondary';
  }

  priorityLabel(p: number): string {
    const labels: Record<number, string> = { 1: 'Låg', 2: 'Normal', 3: 'Medel', 4: 'Hög', 5: 'Kritisk' };
    return labels[p] ?? 'Medel';
  }

  priorityBadgeClass(p: number): string {
    if (p >= 5) return 'prio-critical';
    if (p >= 4) return 'prio-high';
    if (p >= 3) return 'prio-medium';
    if (p >= 2) return 'prio-normal';
    return 'prio-low';
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '—';
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString('sv-SE', { year: 'numeric', month: '2-digit', day: '2-digit' })
        + ' ' + d.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
    } catch {
      return dateStr;
    }
  }
}
