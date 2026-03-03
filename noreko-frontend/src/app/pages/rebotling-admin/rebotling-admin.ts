import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-admin',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './rebotling-admin.html',
  styleUrl: './rebotling-admin.css'
})
export class RebotlingAdminPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  // ---- Produkthantering ----
  products: any[] = [];
  newProduct: any = { name: '', cycle_time_minutes: null };
  loading = false;
  showSuccessMessage = false;
  successMessage = '';
  private successTimerId: any = null;
  showAddProductForm = false;

  // ---- Admin-inställningar (befintliga) ----
  settings = {
    rebotlingTarget: 1000,
    hourlyTarget: 50,
    shiftHours: 8.0,
    systemSettings: { autoStart: false, maintenanceMode: false, alertThreshold: 80 }
  };
  settingsLoading = false;
  settingsSaving  = false;
  settingsError   = '';

  // ---- Veckodagsmål ----
  weekdayGoals: { weekday: number; daily_goal: number; label: string }[] = [];
  weekdayLoading = false;
  weekdaySaving  = false;
  weekdayError   = '';
  weekdaySaved   = false;

  // ---- Skifttider ----
  shiftTimes: { shift_name: string; start_time: string; end_time: string; enabled: boolean }[] = [];
  shiftTimesLoading = false;
  shiftTimesSaving  = false;
  shiftTimesError   = '';
  shiftTimesSaved   = false;

  // ---- Systemstatus ----
  systemStatus: any = null;
  systemStatusLoading = false;
  systemStatusError   = '';
  private systemStatusInterval: any = null;

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user    = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnDestroy() {
    clearTimeout(this.successTimerId);
    clearInterval(this.systemStatusInterval);
    this.destroy$.next();
    this.destroy$.complete();
  }

  ngOnInit() {
    this.loadProducts();
    this.loadSettings();
    this.loadWeekdayGoals();
    this.loadShiftTimes();
    this.loadSystemStatus();
    // Uppdatera systemstatus var 30:e sekund
    this.systemStatusInterval = setInterval(() => {
      if (!this.destroy$.closed) this.loadSystemStatus();
    }, 30000);
  }

  // ========== Inställningar ==========
  loadSettings() {
    this.settingsLoading = true;
    this.settingsError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=admin-settings', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.settings.rebotlingTarget = res.data.rebotlingTarget;
            this.settings.hourlyTarget    = res.data.hourlyTarget;
            this.settings.shiftHours      = res.data.shiftHours;
            if (res.data.systemSettings) {
              this.settings.systemSettings = { ...res.data.systemSettings };
            }
          }
          this.settingsLoading = false;
        },
        error: () => {
          this.settingsError   = 'Kunde inte ladda inställningar';
          this.settingsLoading = false;
        }
      });
  }

  saveSettings() {
    this.settingsSaving = true;
    this.settingsError  = '';
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=admin-settings', this.settings, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.showSuccess('Inställningar sparade!');
          } else {
            this.settingsError = res.error || 'Kunde inte spara inställningar';
          }
          this.settingsSaving = false;
        },
        error: () => {
          this.settingsError  = 'Serverfel vid sparning';
          this.settingsSaving = false;
        }
      });
  }

  // ========== Veckodagsmål ==========
  loadWeekdayGoals() {
    this.weekdayLoading = true;
    this.weekdayError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=weekday-goals', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.weekdayGoals = res.data;
          }
          this.weekdayLoading = false;
        },
        error: () => {
          this.weekdayError   = 'Kunde inte ladda veckodagsmål';
          this.weekdayLoading = false;
        }
      });
  }

  saveWeekdayGoals() {
    this.weekdaySaving = true;
    this.weekdayError  = '';
    this.weekdaySaved  = false;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=weekday-goals',
      { goals: this.weekdayGoals }, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.weekdaySaved = true;
            this.showSuccess('Veckodagsmål sparade!');
            setTimeout(() => { if (!this.destroy$.closed) this.weekdaySaved = false; }, 3000);
          } else {
            this.weekdayError = res.error || 'Kunde inte spara';
          }
          this.weekdaySaving = false;
        },
        error: () => {
          this.weekdayError  = 'Serverfel vid sparning';
          this.weekdaySaving = false;
        }
      });
  }

  // ========== Skifttider ==========
  loadShiftTimes() {
    this.shiftTimesLoading = true;
    this.shiftTimesError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=shift-times', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.shiftTimes = res.data.map((s: any) => ({
              ...s,
              enabled: s.enabled == 1 || s.enabled === true
            }));
          }
          this.shiftTimesLoading = false;
        },
        error: () => {
          this.shiftTimesError   = 'Kunde inte ladda skifttider';
          this.shiftTimesLoading = false;
        }
      });
  }

  saveShiftTimes() {
    this.shiftTimesSaving = true;
    this.shiftTimesError  = '';
    this.shiftTimesSaved  = false;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=shift-times',
      { shifts: this.shiftTimes }, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.shiftTimesSaved = true;
            this.showSuccess('Skifttider sparade!');
            setTimeout(() => { if (!this.destroy$.closed) this.shiftTimesSaved = false; }, 3000);
          } else {
            this.shiftTimesError = res.error || 'Kunde inte spara';
          }
          this.shiftTimesSaving = false;
        },
        error: () => {
          this.shiftTimesError  = 'Serverfel vid sparning';
          this.shiftTimesSaving = false;
        }
      });
  }

  getShiftLabel(name: string): string {
    const map: { [k: string]: string } = {
      'förmiddag': 'Förmiddag',
      'eftermiddag': 'Eftermiddag',
      'natt': 'Natt'
    };
    return map[name] || name;
  }

  // ========== Systemstatus ==========
  loadSystemStatus() {
    if (this.systemStatusLoading) return;
    this.systemStatusLoading = true;
    this.systemStatusError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=system-status', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.systemStatus = res.data;
          } else {
            this.systemStatusError = res.error || 'Kunde inte ladda systemstatus';
          }
          this.systemStatusLoading = false;
        },
        error: () => {
          this.systemStatusError   = 'Kunde inte nå servern';
          this.systemStatusLoading = false;
        }
      });
  }

  getPlcAge(): string {
    if (!this.systemStatus?.last_plc_ping) return 'Ingen data';
    const last = new Date(this.systemStatus.last_plc_ping);
    const now  = new Date();
    const diffSec = Math.floor((now.getTime() - last.getTime()) / 1000);
    if (diffSec < 60)       return `${diffSec} sek sedan`;
    if (diffSec < 3600)     return `${Math.floor(diffSec / 60)} min sedan`;
    if (diffSec < 86400)    return `${Math.floor(diffSec / 3600)} tim sedan`;
    return `${Math.floor(diffSec / 86400)} dag sedan`;
  }

  getPlcStatus(): 'ok' | 'warn' | 'err' {
    if (!this.systemStatus?.last_plc_ping) return 'err';
    const last    = new Date(this.systemStatus.last_plc_ping);
    const diffSec = (new Date().getTime() - last.getTime()) / 1000;
    if (diffSec < 300)  return 'ok';
    if (diffSec < 1800) return 'warn';
    return 'err';
  }

  // ========== Produkthantering ==========
  private loadProducts() {
    this.loading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotlingproduct', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.products = response.data.map((product: any) => ({
              ...product,
              editing: false,
              originalName: product.name,
              originalCycleTime: product.cycle_time_minutes
            }));
          } else {
            console.error('Kunde inte ladda produkter:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid laddning av produkter:', error);
          this.loading = false;
        }
      });
  }

  addProduct() {
    if (!this.newProduct.name || !this.newProduct.cycle_time_minutes) return;
    this.loading = true;
    this.http.post<any>('/noreko-backend/api.php?action=rebotlingproduct', this.newProduct, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.loadProducts();
            this.newProduct = { name: '', cycle_time_minutes: null };
            this.showAddProductForm = false;
            this.showSuccess('Produkt tillagd!');
          } else {
            console.error('Kunde inte lägga till produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid tillägg av produkt:', error);
          this.loading = false;
        }
      });
  }

  editProduct(product: any) {
    this.products.forEach(p => {
      if (p.id !== product.id) {
        p.editing  = false;
        p.name     = p.originalName;
        p.cycle_time_minutes = p.originalCycleTime;
      }
    });
    product.editing           = true;
    product.originalName      = product.name;
    product.originalCycleTime = product.cycle_time_minutes;
  }

  saveProduct(product: any) {
    if (!product.name || !product.cycle_time_minutes) return;
    this.loading = true;
    const updateData = { id: product.id, name: product.name, cycle_time_minutes: product.cycle_time_minutes };
    this.http.put<any>('/noreko-backend/api.php?action=rebotlingproduct', updateData, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            product.editing           = false;
            product.originalName      = product.name;
            product.originalCycleTime = product.cycle_time_minutes;
            this.showSuccess('Produkt uppdaterad!');
          } else {
            console.error('Kunde inte uppdatera produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid uppdatering av produkt:', error);
          this.loading = false;
        }
      });
  }

  cancelEdit(product: any) {
    product.editing              = false;
    product.name                 = product.originalName;
    product.cycle_time_minutes   = product.originalCycleTime;
  }

  deleteProduct(product: any) {
    if (!confirm(`Är du säker på att du vill ta bort produkten "${product.name}"?`)) return;
    this.loading = true;
    this.http.post<any>('/noreko-backend/api.php?action=rebotlingproduct&run=delete', { id: product.id }, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.loadProducts();
            this.showSuccess('Produkt borttagen!');
          } else {
            console.error('Kunde inte ta bort produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid borttagning av produkt:', error);
          this.loading = false;
        }
      });
  }

  trackByProductId(index: number, product: any): number {
    return product.id;
  }

  private showSuccess(message: string) {
    this.successMessage     = message;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
}
