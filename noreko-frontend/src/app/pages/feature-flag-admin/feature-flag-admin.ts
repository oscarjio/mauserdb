import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { FeatureFlagService } from '../../services/feature-flag.service';
import { environment } from '../../../environments/environment';

interface FeatureFlag {
  id: number;
  feature_key: string;
  label: string;
  category: string;
  min_role: string;
  enabled: boolean;
}

interface CategoryGroup {
  category: string;
  flags: FeatureFlag[];
}

@Component({
  selector: 'app-feature-flag-admin',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './feature-flag-admin.html',
  styleUrl: './feature-flag-admin.css'
})
export class FeatureFlagAdminPage implements OnInit, OnDestroy {
  groups: CategoryGroup[] = [];
  loading = true;
  saving = false;
  message: string | null = null;
  error: string | null = null;
  private destroy$ = new Subject<void>();
  private originalData: string = '';

  readonly roles = ['public', 'user', 'admin', 'developer'];

  constructor(
    private http: HttpClient,
    private ff: FeatureFlagService
  ) {}

  ngOnInit(): void {
    this.loadFlags();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadFlags(): void {
    this.loading = true;
    this.error = null;
    this.http.get<{ success: boolean; data: FeatureFlag[] }>(
      `${environment.apiUrl}?action=feature-flags&run=list`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(() => {
        this.error = 'Kunde inte ladda funktionsflaggor.';
        this.loading = false;
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (!res) return;
      if (res.success && Array.isArray(res.data)) {
        const categoryMap = new Map<string, FeatureFlag[]>();
        for (const flag of res.data) {
          flag.enabled = !!flag.enabled;
          const cat = flag.category || 'Okategoriserad';
          if (!categoryMap.has(cat)) categoryMap.set(cat, []);
          categoryMap.get(cat)!.push(flag);
        }
        this.groups = Array.from(categoryMap.entries())
          .sort((a, b) => a[0].localeCompare(b[0], 'sv'))
          .map(([category, flags]) => ({
            category,
            flags: flags.sort((a, b) => a.feature_key.localeCompare(b.feature_key))
          }));
        this.originalData = JSON.stringify(res.data.map(f => ({ id: f.id, min_role: f.min_role, enabled: f.enabled })));
      } else {
        this.error = 'Oväntat svar från servern.';
      }
      this.loading = false;
    });
  }

  hasChanges(): boolean {
    const allFlags: { id: number; min_role: string; enabled: boolean }[] = [];
    for (const g of this.groups) {
      for (const f of g.flags) {
        allFlags.push({ id: f.id, min_role: f.min_role, enabled: f.enabled });
      }
    }
    return JSON.stringify(allFlags) !== this.originalData;
  }

  save(): void {
    this.saving = true;
    this.message = null;
    this.error = null;

    const flags: { id: number; min_role: string; enabled: boolean }[] = [];
    for (const g of this.groups) {
      for (const f of g.flags) {
        flags.push({ id: f.id, min_role: f.min_role, enabled: f.enabled });
      }
    }

    this.http.post<{ success: boolean; message?: string; error?: string }>(
      `${environment.apiUrl}?action=feature-flags&run=bulk-update`,
      { flags },
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(err => {
        this.error = err?.error?.error || 'Kunde inte spara ändringar.';
        this.saving = false;
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (!res) return;
      if (res.success) {
        this.message = res.message || 'Ändringar sparade.';
        this.originalData = JSON.stringify(flags);
        // Ladda om feature flags i tjänsten
        this.ff.loadFlags();
      } else {
        this.error = res.error || 'Kunde inte spara ändringar.';
      }
      this.saving = false;
    });
  }

  roleLabel(role: string): string {
    switch (role) {
      case 'developer': return 'Utvecklare';
      case 'admin': return 'Admin';
      case 'user': return 'Användare';
      case 'public': return 'Publik';
      default: return role;
    }
  }

  roleBadgeClass(role: string): string {
    switch (role) {
      case 'developer': return 'bg-danger';
      case 'admin': return 'bg-warning text-dark';
      case 'user': return 'bg-info';
      case 'public': return 'bg-success';
      default: return 'bg-secondary';
    }
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
