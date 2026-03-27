import { Component, OnInit, OnDestroy, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { ToastService } from '../../services/toast.service';
import { ComponentCanDeactivate } from '../../guards/pending-changes.guard';
import {
  SkiftoverlamningProtokollService,
  SkiftdataResponse,
  ProtokollItem,
  SparaPayload,
} from '../skiftoverlamning.service';

@Component({
  standalone: true,
  selector: 'app-skiftoverlamning-protokoll',
  imports: [CommonModule, FormsModule],
  templateUrl: './skiftoverlamning.component.html',
  styleUrl: './skiftoverlamning.component.css',
})
export class SkiftoverlamningProtokollPage implements OnInit, OnDestroy, ComponentCanDeactivate {

  // Tillstand
  isLoading = false;
  isSubmitting = false;
  showConfirm = false;
  errorSkiftdata = false;

  @HostListener('document:keydown.escape')
  onEscapeKey(): void {
    if (this.showConfirm) { this.cancelSubmit(); }
  }

  // Skiftsammanfattning (auto-populerad)
  skiftdata: SkiftdataResponse | null = null;

  // Checklista
  checklista = {
    rengoring: false,
    verktyg: false,
    kemikalier: false,
    avvikelser: false,
    sakerhet: false,
    material: false,
  };

  // Kommentarer
  kommentarHande = '';
  kommentarAtgarda = '';
  kommentarOvrigt = '';

  // Historik
  historikItems: ProtokollItem[] = [];
  expandedItemId: number | null = null;
  selectedDetail: ProtokollItem | null = null;

  private destroy$ = new Subject<void>();

  constructor(
    private svc: SkiftoverlamningProtokollService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.loadSkiftdata();
    this.loadHistorik();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  canDeactivate(): boolean {
    // Varna om formuläret har ifylld data (checklista eller kommentarer)
    const hasChecklist = this.checklista.rengoring || this.checklista.verktyg ||
      this.checklista.kemikalier || this.checklista.avvikelser ||
      this.checklista.sakerhet || this.checklista.material;
    const hasComments = !!(this.kommentarHande.trim() || this.kommentarAtgarda.trim() || this.kommentarOvrigt.trim());
    return !(hasChecklist || hasComments);
  }

  // =========================================================================
  // Data-laddning
  // =========================================================================

  loadSkiftdata(): void {
    this.isLoading = true;
    this.errorSkiftdata = false;
    this.svc.getSkiftdata().pipe(
      catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isLoading = false;
      if (res?.success) {
        this.skiftdata = res;
      } else {
        this.errorSkiftdata = true;
      }
    });
  }

  loadHistorik(): void {
    this.svc.getHistorik(10).pipe(
      catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.historikItems = res.items;
      }
    });
  }

  // =========================================================================
  // Formuler-hantering
  // =========================================================================

  onSubmitClick(): void {
    this.showConfirm = true;
  }

  cancelSubmit(): void {
    this.showConfirm = false;
  }

  confirmSubmit(): void {
    this.showConfirm = false;
    this.submitForm();
  }

  private submitForm(): void {
    if (this.isSubmitting || !this.skiftdata) return;
    this.isSubmitting = true;

    const payload: SparaPayload = {
      skift_datum: this.skiftdata.skift_datum,
      skift_typ: this.skiftdata.skift_typ,
      produktion_antal: this.skiftdata.produktion_antal,
      oee_procent: this.skiftdata.oee_procent,
      stopp_antal: this.skiftdata.stopp_antal,
      stopp_minuter: this.skiftdata.stopp_minuter,
      kassation_procent: this.skiftdata.kassation_procent,
      checklista_rengoring: this.checklista.rengoring,
      checklista_verktyg: this.checklista.verktyg,
      checklista_kemikalier: this.checklista.kemikalier,
      checklista_avvikelser: this.checklista.avvikelser,
      checklista_sakerhet: this.checklista.sakerhet,
      checklista_material: this.checklista.material,
      kommentar_hande: this.kommentarHande,
      kommentar_atgarda: this.kommentarAtgarda,
      kommentar_ovrigt: this.kommentarOvrigt,
    };

    this.svc.spara(payload).pipe(takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.isSubmitting = false;
        if (res?.success) {
          this.toast.success('Skiftöverlämningsprotokoll sparat!');
          this.resetForm();
          this.loadHistorik();
        } else {
          this.toast.error(res?.error ?? 'Kunde inte spara protokollet');
        }
      },
      error: () => { this.isSubmitting = false; this.toast.error('Kunde inte spara protokollet'); }
    });
  }

  private resetForm(): void {
    this.checklista = {
      rengoring: false,
      verktyg: false,
      kemikalier: false,
      avvikelser: false,
      sakerhet: false,
      material: false,
    };
    this.kommentarHande = '';
    this.kommentarAtgarda = '';
    this.kommentarOvrigt = '';
    this.loadSkiftdata();
  }

  // =========================================================================
  // Historik
  // =========================================================================

  toggleHistorikItem(id: number): void {
    if (this.expandedItemId === id) {
      this.expandedItemId = null;
      this.selectedDetail = null;
      return;
    }
    this.expandedItemId = id;
    this.svc.getDetalj(id).pipe(takeUntil(this.destroy$)).subscribe({
      next: res => {
        if (res?.success && res.item) {
          this.selectedDetail = res.item;
        }
      },
      error: () => { this.toast.error('Kunde inte hämta detalj'); }
    });
  }

  // =========================================================================
  // Hjalpare
  // =========================================================================

  get checklistaCount(): number {
    let c = 0;
    if (this.checklista.rengoring)   c++;
    if (this.checklista.verktyg)     c++;
    if (this.checklista.kemikalier)  c++;
    if (this.checklista.avvikelser)  c++;
    if (this.checklista.sakerhet)    c++;
    if (this.checklista.material)    c++;
    return c;
  }

  checklistaCountFor(item: ProtokollItem): number {
    let c = 0;
    if (item.checklista_rengoring)   c++;
    if (item.checklista_verktyg)     c++;
    if (item.checklista_kemikalier)  c++;
    if (item.checklista_avvikelser)  c++;
    if (item.checklista_sakerhet)    c++;
    if (item.checklista_material)    c++;
    return c;
  }

  skiftLabel(typ: string): string {
    switch (typ) {
      case 'dag':   return 'Dag (06-14)';
      case 'kvall': return 'Kväll (14-22)';
      case 'natt':  return 'Natt (22-06)';
      default:      return typ;
    }
  }

  skiftBadgeClass(typ: string): string {
    switch (typ) {
      case 'dag':   return 'bg-warning text-dark';
      case 'kvall': return 'bg-info text-dark';
      case 'natt':  return 'bg-secondary';
      default:      return 'bg-secondary';
    }
  }

  formatDate(d: string | null): string {
    if (!d) return '--';
    return new Date(d + 'T00:00:00').toLocaleDateString('sv-SE');
  }

  formatDateTime(dt: string | null): string {
    if (!dt) return '--';
    const d = new Date(dt);
    return d.toLocaleString('sv-SE', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit'
    });
  }

  truncate(text: string | null, len = 60): string {
    if (!text) return '--';
    return text.length > len ? text.substring(0, len) + '...' : text;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
