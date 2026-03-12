import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import {
  SkiftoverlamningService,
  SkiftoverlamningItem,
  ShiftKpis,
  SenastOverlamning,
  PagaendeProblem,
  OperatorOption,
  CreatePayload,
} from '../../services/skiftoverlamning.service';

type ViewMode = 'list' | 'form' | 'detail';

@Component({
  standalone: true,
  selector: 'app-skiftoverlamning',
  imports: [CommonModule, FormsModule],
  templateUrl: './skiftoverlamning.html',
  styleUrl: './skiftoverlamning.css',
})
export class SkiftoverlamningPage implements OnInit, OnDestroy {
  // Vy
  viewMode: ViewMode = 'list';

  // Tillstand
  isLoading = false;
  isLoadingDetail = false;
  isLoadingKpis = false;
  isSubmitting = false;
  loadError = '';

  // Auth
  currentUser: any = null;
  loggedIn = false;

  // KPI-sammanfattning
  senaste: SenastOverlamning | null = null;
  antalVecka = 0;
  snittProduktion = 0;
  pagaendeProblems: PagaendeProblem[] = [];
  pagaendeAntal = 0;

  // Lista
  items: SkiftoverlamningItem[] = [];
  totalItems = 0;
  currentPage = 1;
  pageSize = 20;

  // Filter
  filterSkiftTyp = '';
  filterOperatorId = 0;
  filterFrom = '';
  filterTo = '';
  operators: OperatorOption[] = [];

  // Detaljvy
  selectedItem: SkiftoverlamningItem | null = null;

  // Formular
  autoKpis: ShiftKpis | null = null;
  form: CreatePayload = this.emptyForm();

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

    this.loadSummary();
    this.loadList();
    this.loadOperators();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // =========================================================================
  // Data
  // =========================================================================

  loadSummary(): void {
    this.svc.getSummary().pipe(takeUntil(this.destroy$)).subscribe(res => {
      if (res.success) {
        this.senaste = res.senaste_overlamning;
        this.antalVecka = res.antal_denna_vecka;
        this.snittProduktion = res.snitt_produktion_10;
        this.pagaendeAntal = res.pagaende_problem_antal;
        this.pagaendeProblems = res.pagaende_problem_lista;
      }
    });
  }

  loadList(): void {
    if (this.isLoading) return;
    this.isLoading = true;
    this.loadError = '';

    const offset = (this.currentPage - 1) * this.pageSize;
    this.svc.getList({
      skift_typ: this.filterSkiftTyp || undefined,
      operator_id: this.filterOperatorId || undefined,
      from: this.filterFrom || undefined,
      to: this.filterTo || undefined,
      limit: this.pageSize,
      offset,
    }).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.isLoading = false;
      if (!res.success) {
        this.loadError = res.error ?? 'Kunde inte hamta overlamningar';
        return;
      }
      this.items = res.items;
      this.totalItems = res.total;
    });
  }

  loadOperators(): void {
    this.svc.getOperators().pipe(takeUntil(this.destroy$)).subscribe(res => {
      if (res.success) {
        this.operators = res.operators;
      }
    });
  }

  loadDetail(id: number): void {
    this.isLoadingDetail = true;
    this.svc.getDetail(id).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.isLoadingDetail = false;
      if (res.success && res.item) {
        this.selectedItem = res.item;
        this.viewMode = 'detail';
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        this.toast.error(res.error ?? 'Kunde inte hamta detalj');
      }
    });
  }

  loadAutoKpis(): void {
    this.isLoadingKpis = true;
    this.svc.getShiftKpis().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.isLoadingKpis = false;
      if (res.success && res.kpis) {
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

  // =========================================================================
  // Actions
  // =========================================================================

  showForm(): void {
    this.form = this.emptyForm();
    this.autoKpis = null;
    this.viewMode = 'form';
    this.loadAutoKpis();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  backToList(): void {
    this.viewMode = 'list';
    this.selectedItem = null;
    this.loadList();
    this.loadSummary();
  }

  submitForm(): void {
    if (this.isSubmitting) return;
    this.isSubmitting = true;

    this.svc.create(this.form).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.isSubmitting = false;
      if (res.success) {
        this.toast.success('Skiftoverlamning sparad!');
        this.backToList();
      } else {
        this.toast.error(res.error ?? 'Kunde inte spara');
      }
    });
  }

  applyFilters(): void {
    this.currentPage = 1;
    this.loadList();
  }

  clearFilters(): void {
    this.filterSkiftTyp = '';
    this.filterOperatorId = 0;
    this.filterFrom = '';
    this.filterTo = '';
    this.currentPage = 1;
    this.loadList();
  }

  goPage(page: number): void {
    this.currentPage = page;
    this.loadList();
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
    };
  }

  private detectSkiftTyp(): string {
    const h = new Date().getHours();
    if (h >= 6 && h < 14) return 'dag';
    if (h >= 14 && h < 22) return 'kvall';
    return 'natt';
  }

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.totalItems / this.pageSize));
  }

  get pageNumbers(): number[] {
    const pages: number[] = [];
    const start = Math.max(1, this.currentPage - 2);
    const end = Math.min(this.totalPages, this.currentPage + 2);
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
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
}
