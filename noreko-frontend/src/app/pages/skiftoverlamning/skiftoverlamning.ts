import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import {
  SkiftoverlamningService,
  SkiftoverlamningItem,
  ShiftKpis,
  SenastOverlamning,
  PagaendeProblem,
  CreatePayload,
  ChecklistaItem,
  AktuelltSkiftResponse,
  SkiftSammanfattningResponse,
  OppnaProblemItem,
} from '../../services/skiftoverlamning.service';

type ViewMode = 'dashboard' | 'form' | 'detail';

@Component({
  standalone: true,
  selector: 'app-skiftoverlamning',
  imports: [CommonModule, FormsModule],
  templateUrl: './skiftoverlamning.html',
  styleUrl: './skiftoverlamning.css',
})
export class SkiftoverlamningPage implements OnInit, OnDestroy {
  // Vy
  viewMode: ViewMode = 'dashboard';

  // Tillstand
  isLoading = false;
  isLoadingDetail = false;
  isLoadingKpis = false;
  isSubmitting = false;
  loadError = '';

  // Auth
  currentUser: any = null;
  loggedIn = false;

  // Aktuellt skift
  aktuelltSkift: AktuelltSkiftResponse | null = null;
  tidKvarFormatted = '';

  // Forra skiftet
  skiftSammanfattning: SkiftSammanfattningResponse | null = null;

  // Oppna problem
  oppnaProblem: OppnaProblemItem[] = [];

  // KPI-sammanfattning
  senaste: SenastOverlamning | null = null;
  antalVecka = 0;
  snittProduktion = 0;
  pagaendeProblems: PagaendeProblem[] = [];
  pagaendeAntal = 0;

  // Historik
  historikItems: SkiftoverlamningItem[] = [];
  historikExpanded = false;

  // Detaljvy
  selectedItem: SkiftoverlamningItem | null = null;

  // Formular
  autoKpis: ShiftKpis | null = null;
  form: CreatePayload = this.emptyForm();
  checklista: ChecklistaItem[] = [];

  // Timer
  private refreshInterval: any;
  private destroy$ = new Subject<void>();

  constructor(
    private svc: SkiftoverlamningService,
    private auth: AuthService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(u => {
      this.currentUser = u;
    });
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(li => {
      this.loggedIn = li;
    });

    this.loadDashboard();

    // Uppdatera aktuellt skift var 60:e sekund
    this.refreshInterval = setInterval(() => {
      this.loadAktuelltSkift();
      this.updateTidKvar();
    }, 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }
  }

  // =========================================================================
  // Data loading
  // =========================================================================

  loadDashboard(): void {
    this.isLoading = true;
    this.loadError = '';
    this.loadAktuelltSkift();
    this.loadSkiftSammanfattning();
    this.loadOppnaProblem();
    this.loadHistorik();
    this.loadSummary();
  }

  loadAktuelltSkift(): void {
    this.svc.getAktuelltSkift().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.aktuelltSkift = res;
        this.updateTidKvar();
      }
    });
  }

  loadSkiftSammanfattning(): void {
    this.svc.getSkiftSammanfattning().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.skiftSammanfattning = res;
      }
    });
  }

  loadOppnaProblem(): void {
    this.svc.getOppnaProblem().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.oppnaProblem = res.problem;
      }
    });
  }

  loadSummary(): void {
    this.svc.getSummary().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.isLoading = false;
      if (res?.success) {
        this.senaste = res.senaste_overlamning;
        this.antalVecka = res.antal_denna_vecka;
        this.snittProduktion = res.snitt_produktion_10;
        this.pagaendeAntal = res.pagaende_problem_antal;
        this.pagaendeProblems = res.pagaende_problem_lista;
      } else {
        this.loadError = 'Kunde inte ladda sammanfattningsdata.';
      }
    });
  }

  loadHistorik(): void {
    this.svc.getHistorik(10).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.historikItems = res.items;
      }
    });
  }

  loadDetail(id: number): void {
    this.isLoadingDetail = true;
    this.svc.getDetail(id).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.isLoadingDetail = false;
      if (res?.success && res.item) {
        this.selectedItem = res.item;
        this.viewMode = 'detail';
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        this.toast.error(res?.error ?? 'Kunde inte hämta detalj');
      }
    });
  }

  loadAutoKpis(): void {
    this.isLoadingKpis = true;
    this.svc.getShiftKpis().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.isLoadingKpis = false;
      if (res?.success && res.kpis) {
        this.autoKpis = res.kpis;
        this.form.ibc_totalt = res.kpis.ibc_totalt;
        this.form.ibc_per_h = res.kpis.ibc_per_h;
        this.form.stopptid_min = res.kpis.stopptid_min;
        this.form.kassationer = res.kpis.kassationer;
        this.form.skift_typ = res.kpis.skift_typ;
        this.form.datum = res.kpis.skift_datum;
      }
    });
  }

  loadChecklista(): void {
    this.svc.getChecklista().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.checklista = res.checklista.map(c => ({ ...c }));
        this.form.checklista = this.checklista;
      }
    });
  }

  // =========================================================================
  // Actions
  // =========================================================================

  showForm(): void {
    this.form = this.emptyForm();
    this.autoKpis = null;
    this.checklista = [];
    this.viewMode = 'form';
    this.loadAutoKpis();
    this.loadChecklista();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  backToDashboard(): void {
    this.viewMode = 'dashboard';
    this.selectedItem = null;
    this.loadDashboard();
  }

  submitForm(): void {
    if (this.isSubmitting) return;
    this.isSubmitting = true;

    this.form.checklista = this.checklista;

    this.svc.create(this.form).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.isSubmitting = false;
      if (res?.success) {
        this.toast.success('Skiftoverlamning sparad!');
        this.backToDashboard();
      } else {
        this.toast.error(res?.error ?? 'Kunde inte spara — kontrollera anslutningen');
      }
    });
  }

  toggleChecklista(idx: number): void {
    if (this.checklista[idx]) {
      this.checklista[idx].checked = !this.checklista[idx].checked;
    }
  }

  toggleHistorik(): void {
    this.historikExpanded = !this.historikExpanded;
  }

  updateTidKvar(): void {
    if (!this.aktuelltSkift) return;
    const kvar = this.aktuelltSkift.tid_kvar_min;
    if (kvar <= 0) {
      this.tidKvarFormatted = 'Skiftet ar slut';
      return;
    }
    const h = Math.floor(kvar / 60);
    const m = Math.round(kvar % 60);
    this.tidKvarFormatted = h > 0 ? `${h}h ${m}min kvar` : `${m} min kvar`;
  }

  // =========================================================================
  // Helpers
  // =========================================================================

  private emptyForm(): CreatePayload {
    return {
      skift_typ: this.detectSkiftTyp(),
      datum: new Date().toISOString().substring(0, 10),
      ibc_totalt: 0,
      ibc_per_h: 0,
      stopptid_min: 0,
      kassationer: 0,
      problem_text: '',
      pagaende_arbete: '',
      instruktioner: '',
      kommentar: '',
      har_pagaende_problem: false,
      allvarlighetsgrad: 'medel',
      checklista: [],
      mal_nasta_skift: '',
    };
  }

  private detectSkiftTyp(): string {
    const h = new Date().getHours();
    if (h >= 6 && h < 14) return 'dag';
    if (h >= 14 && h < 22) return 'kvall';
    return 'natt';
  }

  get checklistaProgress(): number {
    if (this.checklista.length === 0) return 0;
    const checked = this.checklista.filter(c => c.checked).length;
    return Math.round((checked / this.checklista.length) * 100);
  }

  get allChecked(): boolean {
    return this.checklista.length > 0 && this.checklista.every(c => c.checked);
  }

  skiftBadgeClass(typ: string): string {
    switch (typ) {
      case 'dag': return 'bg-warning text-dark';
      case 'kvall': return 'bg-info text-dark';
      case 'natt': return 'bg-secondary';
      default: return 'bg-secondary';
    }
  }

  skiftLabel(typ: string): string {
    switch (typ) {
      case 'dag': return 'Dag';
      case 'kvall': return 'Kvall';
      case 'natt': return 'Natt';
      default: return typ;
    }
  }

  severityClass(grad: string): string {
    switch (grad) {
      case 'kritisk': return 'severity-kritisk';
      case 'hog':     return 'severity-hog';
      case 'medel':   return 'severity-medel';
      case 'lag':     return 'severity-lag';
      default:        return 'severity-medel';
    }
  }

  severityLabel(grad: string): string {
    switch (grad) {
      case 'kritisk': return 'Kritisk';
      case 'hog':     return 'Hog';
      case 'medel':   return 'Medel';
      case 'lag':     return 'Lag';
      default:        return grad;
    }
  }

  severityBadgeClass(grad: string): string {
    switch (grad) {
      case 'kritisk': return 'bg-danger';
      case 'hog':     return 'bg-warning text-dark';
      case 'medel':   return 'bg-info text-dark';
      case 'lag':     return 'bg-secondary';
      default:        return 'bg-secondary';
    }
  }

  kpiComparison(value: number, target: number): string {
    if (value >= target) return 'text-success';
    if (value >= target * 0.9) return 'text-warning';
    return 'text-danger';
  }

  kpiComparisonReverse(value: number, target: number): string {
    if (value <= target) return 'text-success';
    if (value <= target * 1.1) return 'text-warning';
    return 'text-danger';
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

  formatTime(dt: string | null): string {
    if (!dt) return '--';
    const d = new Date(dt);
    return d.toLocaleString('sv-SE', { hour: '2-digit', minute: '2-digit' });
  }

  formatMin(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h > 0) return `${h}h ${m}min`;
    return `${m} min`;
  }

  truncate(text: string | null, len = 80): string {
    if (!text) return '--';
    return text.length > len ? text.substring(0, len) + '...' : text;
  }

  getChecklistaCount(checklista: ChecklistaItem[]): string {
    if (!checklista || checklista.length === 0) return '--';
    const checked = checklista.filter(c => c.checked).length;
    return `${checked}/${checklista.length}`;
  }
  trackByIndex(index: number): number { return index; }
}
