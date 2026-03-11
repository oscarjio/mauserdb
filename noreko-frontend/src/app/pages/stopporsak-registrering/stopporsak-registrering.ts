import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import {
  StopporsakRegistreringService,
  StopporsakKategori,
  StopporsakRegistrering
} from '../../services/stopporsak-registrering.service';

@Component({
  standalone: true,
  selector: 'app-stopporsak-registrering',
  imports: [CommonModule, FormsModule],
  templateUrl: './stopporsak-registrering.html',
  styleUrl: './stopporsak-registrering.css'
})
export class StopporsakRegistreringPage implements OnInit, OnDestroy {
  loggedIn = false;
  user: any = null;

  kategorier: StopporsakKategori[] = [];
  aktivaStopp: StopporsakRegistrering[] = [];
  senasteStopp: StopporsakRegistrering[] = [];

  // Registreringsflöde
  valdKategori: StopporsakKategori | null = null;
  kommentar = '';
  submitting = false;
  successMessage = '';
  errorMessage = '';

  // UI state
  visaKommentarFalt = false;
  loadingKategorier = false;
  loadingAktiva = false;
  loadingHistorik = false;
  endingStopId: number | null = null;

  // Live-timer för aktiva stopp
  timerStr: { [id: number]: string } = {};
  private timerInterval: any;
  private successTimerId: any;
  private destroy$ = new Subject<void>();

  Math = Math;

  constructor(
    private auth: AuthService,
    private service: StopporsakRegistreringService
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(v => this.loggedIn = v);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(v => this.user = v);

    this.loadKategorier();
    this.loadAktivaStopp();
    this.loadHistorik();

    // Uppdatera aktiva stopp var 30:e sekund
    const refreshInterval = setInterval(() => {
      this.loadAktivaStopp();
    }, 30000);

    this.destroy$.subscribe(() => clearInterval(refreshInterval));

    // Live-timer varje sekund
    this.timerInterval = setInterval(() => {
      this.uppdateraTimers();
    }, 1000);
  }

  ngOnDestroy() {
    clearTimeout(this.successTimerId);
    clearInterval(this.timerInterval);
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadKategorier() {
    this.loadingKategorier = true;
    this.service.getCategories()
      .pipe(timeout(10000), catchError(() => of({ success: false, data: [] })), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingKategorier = false;
          if (res.success) this.kategorier = res.data;
        },
        error: () => { this.loadingKategorier = false; }
      });
  }

  loadAktivaStopp() {
    this.loadingAktiva = true;
    this.service.getActiveStops()
      .pipe(timeout(10000), catchError(() => of({ success: false, data: [] })), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingAktiva = false;
          if (res.success) {
            this.aktivaStopp = res.data;
            this.uppdateraTimers();
          }
        },
        error: () => { this.loadingAktiva = false; }
      });
  }

  loadHistorik() {
    this.loadingHistorik = true;
    this.service.getRecent(20)
      .pipe(timeout(10000), catchError(() => of({ success: false, data: [] })), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingHistorik = false;
          if (res.success) this.senasteStopp = res.data;
        },
        error: () => { this.loadingHistorik = false; }
      });
  }

  valjKategori(kat: StopporsakKategori) {
    this.valdKategori = kat;
    this.kommentar = '';
    this.errorMessage = '';
    this.visaKommentarFalt = true;
  }

  avbrytRegistrering() {
    this.valdKategori = null;
    this.kommentar = '';
    this.visaKommentarFalt = false;
    this.errorMessage = '';
  }

  bekraftaRegistrering() {
    if (!this.valdKategori || this.submitting) return;
    this.submitting = true;
    this.errorMessage = '';

    this.service.registerStop(this.valdKategori.id, this.kommentar)
      .pipe(timeout(10000), catchError(err => of({ success: false, message: err?.error?.message || 'Anslutningsfel' })), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.submitting = false;
          if (res.success) {
            this.visaBekraftelse(`Stopp registrerat: ${this.valdKategori?.ikon} ${this.valdKategori?.namn}`);
            this.valdKategori = null;
            this.kommentar = '';
            this.visaKommentarFalt = false;
            this.loadAktivaStopp();
            this.loadHistorik();
          } else {
            this.errorMessage = res.message || 'Kunde inte registrera stoppet';
          }
        },
        error: () => {
          this.submitting = false;
          this.errorMessage = 'Anslutningsfel — försök igen';
        }
      });
  }

  avslutaStopp(stopp: StopporsakRegistrering) {
    if (this.endingStopId !== null) return;
    this.endingStopId = stopp.id;

    this.service.endStop(stopp.id)
      .pipe(timeout(10000), catchError(err => of({ success: false, message: err?.error?.message || 'Anslutningsfel' })), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.endingStopId = null;
          if (res.success) {
            this.visaBekraftelse('Stopp avslutat');
            this.loadAktivaStopp();
            this.loadHistorik();
          } else {
            this.errorMessage = res.message || 'Kunde inte avsluta stoppet';
          }
        },
        error: () => {
          this.endingStopId = null;
          this.errorMessage = 'Anslutningsfel — försök igen';
        }
      });
  }

  uppdateraTimers() {
    const nu = new Date().getTime();
    for (const stopp of this.aktivaStopp) {
      const start = new Date(stopp.start_time).getTime();
      const diffSek = Math.floor((nu - start) / 1000);
      this.timerStr[stopp.id] = this.formatSekunder(diffSek);
    }
  }

  formatSekunder(sek: number): string {
    if (sek < 0) sek = 0;
    const h = Math.floor(sek / 3600);
    const m = Math.floor((sek % 3600) / 60);
    const s = sek % 60;
    if (h > 0) {
      return `${h}:${this.pad(m)}:${this.pad(s)}`;
    }
    return `${this.pad(m)}:${this.pad(s)}`;
  }

  formatMinuter(min: number | null | undefined): string {
    if (min === null || min === undefined) return 'Pågår';
    const minVal = parseFloat(String(min));
    if (isNaN(minVal)) return 'Pågår';
    const h = Math.floor(minVal / 60);
    const m = Math.round(minVal % 60);
    if (h > 0) return `${h} h ${m} min`;
    return `${m} min`;
  }

  formatTidpunkt(dt: string): string {
    if (!dt) return '';
    const d = new Date(dt);
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  private pad(n: number): string {
    return n.toString().padStart(2, '0');
  }

  private visaBekraftelse(msg: string) {
    this.successMessage = msg;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.successMessage = '';
    }, 4000);
  }
}
