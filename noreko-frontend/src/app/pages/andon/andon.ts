import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

interface AndonStatus {
  datum: string;
  skift: string;
  ibc_idag: number;
  mal_idag: number;
  mal_pct: number;
  oee_pct: number;
  ibc_per_h: number;
  runtime_min: number;
  rasttime_min: number;
  senaste_ibc_tid: string;
  minuter_sedan_senaste_ibc: number;
  linje_status: string;
}

@Component({
  standalone: true,
  selector: 'app-andon',
  templateUrl: './andon.html',
  styleUrls: ['./andon.css'],
  imports: [CommonModule]
})
export class AndonPage implements OnInit, OnDestroy {
  Math = Math;

  private readonly apiUrl = '/noreko-backend/api.php';

  status: AndonStatus | null = null;
  laddas = true;
  fel: string | null = null;
  klockslag = '';
  nedrakning = 10;
  isFetching = false;

  private destroy$ = new Subject<void>();
  private pollInterval: any = null;
  private clockInterval: any = null;
  private countdownInterval: any = null;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.uppdateraKlocka();
    this.hamtaStatus();

    this.clockInterval = setInterval(() => {
      this.uppdateraKlocka();
    }, 1000);

    this.pollInterval = setInterval(() => {
      this.hamtaStatus();
      this.nedrakning = 10;
    }, 10000);

    this.countdownInterval = setInterval(() => {
      if (this.nedrakning > 0) {
        this.nedrakning--;
      }
    }, 1000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) clearInterval(this.pollInterval);
    if (this.clockInterval) clearInterval(this.clockInterval);
    if (this.countdownInterval) clearInterval(this.countdownInterval);
  }

  private uppdateraKlocka(): void {
    const nu = new Date();
    this.klockslag = nu.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  hamtaStatus(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.http.get<AndonStatus>(`${this.apiUrl}?action=andon&run=status`)
      .pipe(
        timeout(5000),
        catchError(() => {
          this.fel = 'Kunde inte hamta data fran servern.';
          this.isFetching = false;
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetching = false;
        if (data) {
          this.status = data;
          this.laddas = false;
          this.fel = null;
        }
      });
  }

  get statusFarg(): string {
    if (!this.status) return '#888';
    if (this.status.linje_status === 'stopp') return '#f44336';
    if (this.status.linje_status === 'väntar') return '#ff9800';
    if (this.status.oee_pct < 60) return '#f44336';
    if (this.status.oee_pct < 75) return '#ff9800';
    return '#00e676';
  }

  get statusText(): string {
    if (!this.status) return '';
    if (this.status.linje_status === 'stopp') return 'STOPP';
    if (this.status.linje_status === 'väntar') return 'VANTAR';
    return 'KOR';
  }

  get statusEtikett(): string {
    if (!this.status) return '';
    const min = this.status.minuter_sedan_senaste_ibc;
    if (this.status.linje_status === 'stopp') {
      return `Senaste IBC: ${min} min sedan`;
    }
    if (this.status.linje_status === 'väntar') {
      return `Vantar — ${min} min sedan senaste IBC`;
    }
    return `Linjen kor! Senaste IBC for ${min} min sedan`;
  }

  get malBreddpct(): number {
    if (!this.status) return 0;
    return Math.min(100, Math.round(this.status.mal_pct));
  }

  get oeeEtikett(): string {
    if (!this.status) return '';
    if (this.status.oee_pct >= 85) return 'Utmarkt!';
    if (this.status.oee_pct >= 75) return 'Bra!';
    if (this.status.oee_pct >= 60) return 'OK';
    return 'Under mal';
  }
}
