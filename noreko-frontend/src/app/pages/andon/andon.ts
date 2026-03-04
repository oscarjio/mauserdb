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

interface Stoppage {
  id: number;
  reason_name: string;
  category: string;
  duration_minutes: number;
  created_at: string;
  notes: string;
}

interface HandoverNote {
  id: number;
  note: string;
  priority: 'normal' | 'important' | 'urgent';
  op_name: string | null;
  created_at: string;
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

  // Befintlig state
  status: AndonStatus | null = null;
  laddas = true;
  fel: string | null = null;
  klockslag = '';
  nedrakning = 10;
  isFetching = false;
  isFetchingStoppages = false;

  // Feature 1: Skifttimer
  skifttimerText = '';
  skifttimerKvar = '';          // "HH:MM:SS"
  skiftProgressPct = 0;         // 0–100
  skiftStatus: 'kör' | 'slut' | 'ej_startat' = 'kör';

  // Feature 2: Senaste stopporsaker
  stoppages: Stoppage[] = [];
  stoppagesLaddas = true;

  // Feature 3: Produktionsprognos
  prognosBanner: {
    text: string;
    ibcPrognos: number;
    niva: 'rekord' | 'ok' | 'warn' | 'critical' | 'ingen';
  } = { text: '', ibcPrognos: 0, niva: 'ingen' };

  // Feature 4: Skiftöverlämningsnoter
  handoverNotes: HandoverNote[] = [];
  unreadNoteCount = 0;
  isFetchingNotes = false;
  private notesInterval: any = null;

  private destroy$ = new Subject<void>();
  private pollInterval: any = null;
  private stoppagePollInterval: any = null;
  private clockInterval: any = null;
  private countdownInterval: any = null;
  private skiftTimerInterval: any = null;

  // Skifttider (06:00–22:00)
  private readonly SKIFT_START_H = 6;
  private readonly SKIFT_SLUT_H = 22;
  private readonly SKIFT_TOTAL_S = (22 - 6) * 3600; // 57600 s

  // Exponerade skifttider (används i template för nedräkningsbaren)
  readonly shiftStartTime = '06:00';
  readonly shiftEndTime = '22:00';

  // IBC-måltakt (IBC per timme för att nå dagsmålet på 16h skift)
  get targetRate(): number {
    if (!this.status || this.status.mal_idag <= 0) return 0;
    return this.status.mal_idag / 16; // 16h skift
  }

  get ibcPerHour(): number {
    return this.status ? this.status.ibc_per_h : 0;
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.uppdateraKlocka();
    this.uppdateraSkiftTimer();
    this.hamtaStatus();
    this.hamtaStoppages();
    this.hamtaHandoverNotes();

    // Klocka + skifttimer uppdateras varje sekund
    this.clockInterval = setInterval(() => {
      this.uppdateraKlocka();
    }, 1000);

    this.skiftTimerInterval = setInterval(() => {
      this.uppdateraSkiftTimer();
    }, 1000);

    // Nedräkning till nästa datapoll
    this.countdownInterval = setInterval(() => {
      if (this.nedrakning > 0) {
        this.nedrakning--;
      }
    }, 1000);

    // Statusdata var 10s
    this.pollInterval = setInterval(() => {
      this.hamtaStatus();
      this.nedrakning = 10;
    }, 10000);

    // Stopporsaker var 30s
    this.stoppagePollInterval = setInterval(() => {
      this.hamtaStoppages();
    }, 30000);

    // Skiftöverlämningsnoter var 30s
    this.notesInterval = setInterval(() => {
      this.hamtaHandoverNotes();
    }, 30000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) clearInterval(this.pollInterval);
    if (this.stoppagePollInterval) clearInterval(this.stoppagePollInterval);
    if (this.clockInterval) clearInterval(this.clockInterval);
    if (this.countdownInterval) clearInterval(this.countdownInterval);
    if (this.skiftTimerInterval) clearInterval(this.skiftTimerInterval);
    if (this.notesInterval) clearInterval(this.notesInterval);
  }

  // ─────────────────────────────────────────────
  // Befintliga metoder
  // ─────────────────────────────────────────────

  private uppdateraKlocka(): void {
    const nu = new Date();
    this.klockslag = nu.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  hamtaStatus(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.http.get<AndonStatus>(`${this.apiUrl}?action=andon&run=status`)
      .pipe(
        timeout(8000),
        catchError(() => {
          this.fel = 'Kunde inte hämta data från servern.';
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
          this.beraknaPrognos();
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
    if (this.status.linje_status === 'väntar') return 'VÄNTAR';
    return 'KÖR';
  }

  get statusEtikett(): string {
    if (!this.status) return '';
    const min = this.status.minuter_sedan_senaste_ibc;
    if (this.status.linje_status === 'stopp') {
      return `Senaste IBC: ${min} min sedan`;
    }
    if (this.status.linje_status === 'väntar') {
      return `Väntar — ${min} min sedan senaste IBC`;
    }
    return `Linjen kör! Senaste IBC för ${min} min sedan`;
  }

  get malBreddpct(): number {
    if (!this.status) return 0;
    return Math.min(100, Math.round(this.status.mal_pct));
  }

  get oeeEtikett(): string {
    if (!this.status) return '';
    if (this.status.oee_pct >= 85) return 'Utmärkt!';
    if (this.status.oee_pct >= 75) return 'Bra!';
    if (this.status.oee_pct >= 60) return 'OK';
    return 'Under mål';
  }

  // ─────────────────────────────────────────────
  // Feature 1: Skifttimer
  // ─────────────────────────────────────────────

  private uppdateraSkiftTimer(): void {
    const nu = new Date();
    const h = nu.getHours();
    const m = nu.getMinutes();
    const s = nu.getSeconds();

    // Sekunder från midnatt
    const sekundFranMidnatt = h * 3600 + m * 60 + s;
    const skiftStartSek = this.SKIFT_START_H * 3600;   // 21600
    const skiftSlutSek  = this.SKIFT_SLUT_H * 3600;    // 79200

    if (sekundFranMidnatt < skiftStartSek) {
      // Före skiftstart
      this.skiftStatus = 'ej_startat';
      const kvarTillStart = skiftStartSek - sekundFranMidnatt;
      this.skifttimerKvar = this.formatSekunder(kvarTillStart);
      this.skifttimerText = 'Startar 06:00';
      this.skiftProgressPct = 0;
    } else if (sekundFranMidnatt >= skiftSlutSek) {
      // Efter skiftslut
      this.skiftStatus = 'slut';
      this.skifttimerKvar = '00:00:00';
      this.skifttimerText = 'Skiftet är slut';
      this.skiftProgressPct = 100;
    } else {
      // Mitt i skiftet
      this.skiftStatus = 'kör';
      const gangen = sekundFranMidnatt - skiftStartSek;
      const kvar   = skiftSlutSek - sekundFranMidnatt;
      this.skifttimerKvar = this.formatSekunder(kvar);
      this.skifttimerText = 'kvar av skiftet';
      this.skiftProgressPct = Math.round((gangen / this.SKIFT_TOTAL_S) * 100);
    }
  }

  private formatSekunder(sek: number): string {
    const h = Math.floor(sek / 3600);
    const m = Math.floor((sek % 3600) / 60);
    const s = sek % 60;
    return [
      String(h).padStart(2, '0'),
      String(m).padStart(2, '0'),
      String(s).padStart(2, '0')
    ].join(':');
  }

  get skifttimerFarg(): string {
    if (this.skiftStatus === 'slut' || this.skiftStatus === 'ej_startat') return '#718096';
    if (this.skiftProgressPct >= 90) return '#f44336';
    if (this.skiftProgressPct >= 75) return '#ff9800';
    return '#00e676';
  }

  // ─────────────────────────────────────────────
  // Feature 2: Senaste stopporsaker
  // ─────────────────────────────────────────────

  hamtaStoppages(): void {
    if (this.isFetchingStoppages) return;
    this.isFetchingStoppages = true;

    this.http.get<{ success: boolean; stoppages: Stoppage[] }>(`${this.apiUrl}?action=andon&run=recent-stoppages`)
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetchingStoppages = false;
        this.stoppagesLaddas = false;
        if (data && data.success) {
          this.stoppages = data.stoppages;
        }
      });
  }

  stoppageFarg(category: string): string {
    switch ((category || '').toLowerCase()) {
      case 'maskin':    return '#f44336';
      case 'material':  return '#ff9800';
      case 'operatör':
      case 'operator':  return '#4299e1';
      default:          return '#718096';
    }
  }

  stoppageTidSedan(createdAt: string): string {
    const nu  = Date.now();
    const tid = new Date(createdAt).getTime();
    const diffMin = Math.max(0, Math.round((nu - tid) / 60000));

    if (diffMin < 1)  return 'Just nu';
    if (diffMin < 60) return `${diffMin} min sedan`;
    const h = Math.floor(diffMin / 60);
    const m = diffMin % 60;
    return m > 0 ? `${h}h ${m}min sedan` : `${h}h sedan`;
  }

  // ─────────────────────────────────────────────
  // Feature 3: Produktionsprognos
  // ─────────────────────────────────────────────

  private beraknaPrognos(): void {
    if (!this.status) {
      this.prognosBanner = { text: '', ibcPrognos: 0, niva: 'ingen' };
      return;
    }

    const nu = new Date();
    const h  = nu.getHours();
    const m  = nu.getMinutes();
    const s  = nu.getSeconds();

    const sekundFranMidnatt = h * 3600 + m * 60 + s;
    const skiftStartSek = this.SKIFT_START_H * 3600;
    const skiftSlutSek  = this.SKIFT_SLUT_H * 3600;

    // Utanför skift → ingen prognos
    if (sekundFranMidnatt < skiftStartSek || sekundFranMidnatt >= skiftSlutSek) {
      this.prognosBanner = { text: '', ibcPrognos: 0, niva: 'ingen' };
      return;
    }

    const tidGangenH = (sekundFranMidnatt - skiftStartSek) / 3600;
    const tidKvarH   = (skiftSlutSek - sekundFranMidnatt) / 3600;

    // Visa inte prognos förrän minst 1h gått
    if (tidGangenH < 1) {
      this.prognosBanner = { text: '', ibcPrognos: 0, niva: 'ingen' };
      return;
    }

    const ibcHittills = this.status.ibc_idag;
    const mal         = this.status.mal_idag;

    const taktPerH    = ibcHittills / tidGangenH;
    const prognosIbc  = taktPerH * tidKvarH;
    const slutPrognos = Math.round(ibcHittills + prognosIbc);
    const slutPct     = mal > 0 ? (slutPrognos / mal) * 100 : 0;

    let text: string;
    let niva: 'rekord' | 'ok' | 'warn' | 'critical';

    if (slutPct >= 110) {
      text = `Rekorddag i sikte! Prognos: ${slutPrognos} IBC`;
      niva = 'rekord';
    } else if (slutPct >= 100) {
      text = `På väg att nå målet! Prognos: ${slutPrognos} IBC`;
      niva = 'ok';
    } else if (slutPct >= 80) {
      text = `Justera takten. Prognos: ${slutPrognos} IBC av mål ${mal}`;
      niva = 'warn';
    } else {
      text = `Takten behöver ökas. Prognos: ${slutPrognos} IBC av mål ${mal}`;
      niva = 'critical';
    }

    this.prognosBanner = { text, ibcPrognos: slutPrognos, niva };
  }

  get prognosBannerFarg(): string {
    switch (this.prognosBanner.niva) {
      case 'rekord':   return '#22543d';   // mörkgrön
      case 'ok':       return '#1a365d';   // mörkblå
      case 'warn':     return '#7b341e';   // mörkrange
      case 'critical': return '#742a2a';   // mörkröd
      default:         return 'transparent';
    }
  }

  get prognosBannerBord(): string {
    switch (this.prognosBanner.niva) {
      case 'rekord':   return '#48bb78';
      case 'ok':       return '#4299e1';
      case 'warn':     return '#ed8936';
      case 'critical': return '#f44336';
      default:         return 'transparent';
    }
  }

  get prognosBannerIkon(): string {
    switch (this.prognosBanner.niva) {
      case 'rekord':   return '🚀';
      case 'ok':       return '✅';
      case 'warn':     return '⚠️';
      case 'critical': return '🔴';
      default:         return '';
    }
  }

  // ─────────────────────────────────────────────
  // Feature 4: Skiftöverlämningsnoter
  // ─────────────────────────────────────────────

  hamtaHandoverNotes(): void {
    if (this.isFetchingNotes) return;
    this.isFetchingNotes = true;

    this.http.get<{ success: boolean; notes: HandoverNote[]; unread_count: number }>(
      `${this.apiUrl}?action=andon&run=andon-notes`
    )
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetchingNotes = false;
        if (data && data.success) {
          this.handoverNotes = data.notes;
          this.unreadNoteCount = data.unread_count ?? data.notes.length;
        }
      });
  }

  /** Returnerar true om det finns minst en 'urgent'-not */
  get hasUrgentNotes(): boolean {
    return this.handoverNotes.some(n => n.priority === 'urgent');
  }

  notePrioritetFarg(priority: string): string {
    switch (priority) {
      case 'urgent':    return '#f44336';
      case 'important': return '#ff9800';
      default:          return '#4a5568';
    }
  }

  notePrioritetEtikett(priority: string): string {
    switch (priority) {
      case 'urgent':    return 'BRÅDSKANDE';
      case 'important': return 'VIKTIG';
      default:          return '';
    }
  }

  timeAgo(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just nu';
    if (mins < 60) return `${mins} min sedan`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} h sedan`;
    return `${Math.floor(hours / 24)} dagar sedan`;
  }
}
