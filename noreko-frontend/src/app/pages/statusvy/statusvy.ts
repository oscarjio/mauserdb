import { Component, OnInit, OnDestroy, ElementRef, ViewChild, NgZone, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject, of, forkJoin } from 'rxjs';
import { catchError, timeout, takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';
import {
  StatusDag, EffektivitetTyp, Period, PeriodSumma, SerieTidpunkt,
  perioder, summera, veckoserie, manadsserie, braDagar, isoWeek, manadNamn,
} from '../../utils/statusvy';

Chart.register(...registerables);

type Linje = 'rebotling' | 'tvattlinje';
type Vy = 'veckor' | 'manader' | 'bradagar';

interface StatusvySvar {
  linje: Linje; idag: string; fran: string; dataFran: string | null;
  effektivitetTyp: EffektivitetTyp; dagar: StatusDag[];
}

interface PeriodRad { period: Period; summa: PeriodSumma; jamfor: PeriodSumma | null; }

interface LinjeVy {
  linje: Linje;
  namn: string;
  typ: EffektivitetTyp;
  dataFran: string | null;
  idag: PeriodSumma;
  rader: PeriodRad[];
  veckor: SerieTidpunkt[];
  manader: SerieTidpunkt[];
  bra: ReturnType<typeof braDagar>;
}

const LINJER: { linje: Linje; namn: string }[] = [
  { linje: 'rebotling', namn: 'Rebotling' },
  { linje: 'tvattlinje', namn: 'Tvättlinje' },
];
const UPPDATERA_MS = 60_000;
const ROTERA_MS = 20_000;
const INAKTUELL_MS = 5 * 60_000;

/**
 * Statusvy — TV-/storskärmsvy för produktionens medarbetare (Rebotling + Tvättlinje).
 * Visar bara lagnivå och aggregat: inga operatörer, inga individjämförelser.
 * Kräver inloggning (authGuard + backendens sessionskontroll).
 */
@Component({
  selector: 'app-statusvy',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './statusvy.html',
  styleUrl: './statusvy.css',
})
export class StatusvyPage implements OnInit, OnDestroy {
  @ViewChild('rot') rotRef?: ElementRef<HTMLElement>;

  readonly linjer = LINJER;
  readonly vyer: { key: Vy; label: string }[] = [
    { key: 'veckor', label: 'Veckor' },
    { key: 'manader', label: 'Månader' },
    { key: 'bradagar', label: 'Bra dagar' },
  ];

  vy: Record<Linje, LinjeVy | null> = { rebotling: null, tvattlinje: null };
  status: Record<Linje, boolean | null> = { rebotling: null, tvattlinje: null };
  idag: string | null = null;
  aktivVy: Vy = 'veckor';
  pausad = false;
  senastOk: number | null = null;
  senasteFel = false;
  laddar = true;
  nu = new Date();
  helskarm = false;

  private destroy$ = new Subject<void>();
  private timers: ReturnType<typeof setInterval>[] = [];
  private charts: Chart[] = [];

  constructor(private http: HttpClient, private zone: NgZone, private host: ElementRef<HTMLElement>) {}

  ngOnInit(): void {
    this.hamta();
    this.timers.push(setInterval(() => this.hamta(), UPPDATERA_MS));
    this.timers.push(setInterval(() => { this.nu = new Date(); }, 1000));
    this.timers.push(setInterval(() => { if (!this.pausad) this.byttVy(this.nastaVy(), false); }, ROTERA_MS));
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.timers.forEach(t => clearInterval(t));
    this.forstorDiagram();
  }

  // ---------- data ----------
  private hamta(): void {
    const api = environment.apiUrl;
    const opts = { withCredentials: true };
    forkJoin({
      rebotling: this.http.get<{ success: boolean; data?: StatusvySvar }>(`${api}?action=rebotling&run=statusvy-dagar`, opts)
        .pipe(timeout(30000), catchError(() => of(null))),
      tvattlinje: this.http.get<{ success: boolean; data?: StatusvySvar }>(`${api}?action=tvattlinje&run=statusvy-dagar`, opts)
        .pipe(timeout(30000), catchError(() => of(null))),
      rebStatus: this.http.get<any>(`${api}?action=rebotling&run=status`, opts).pipe(timeout(8000), catchError(() => of(null))),
      tvStatus: this.http.get<any>(`${api}?action=tvattlinje`, opts).pipe(timeout(8000), catchError(() => of(null))),
    }).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.laddar = false;
      let alltOk = true;
      for (const { linje, namn } of LINJER) {
        const svar = res[linje];
        if (svar?.success && svar.data) {
          this.vy[linje] = this.byggVy(linje, namn, svar.data);
          this.idag = svar.data.idag;
        } else {
          alltOk = false; // behåll senast kända vy
        }
      }
      const r = res.rebStatus?.data?.running, t = res.tvStatus?.data?.running;
      this.status.rebotling = r == null ? null : (r == 1 || r === true);
      this.status.tvattlinje = t == null ? null : (t == 1 || t === true);
      this.senasteFel = !alltOk;
      if (alltOk) this.senastOk = Date.now();
      this.ritaDiagram();
    });
  }

  private byggVy(linje: Linje, namn: string, d: StatusvySvar): LinjeVy {
    const typ = d.effektivitetTyp;
    const p = perioder(d.idag);
    return {
      linje, namn, typ, dataFran: d.dataFran,
      idag: summera(d.dagar, d.idag, d.idag, typ),
      rader: p.filter(x => x.key !== 'idag').map(period => ({
        period,
        summa: summera(d.dagar, period.fran, period.till, typ),
        jamfor: period.jamfor ? summera(d.dagar, period.jamfor.fran, period.jamfor.till, typ) : null,
      })),
      veckor: veckoserie(d.dagar, d.idag, typ),
      manader: manadsserie(d.dagar, d.idag, typ),
      bra: braDagar(d.dagar, d.idag),
    };
  }

  // ---------- rotation / kontroller ----------
  private nastaVy(): Vy {
    const i = this.vyer.findIndex(v => v.key === this.aktivVy);
    return this.vyer[(i + 1) % this.vyer.length].key;
  }

  byttVy(v: Vy, manuell = true): void {
    if (manuell) this.pausad = true;
    if (v === this.aktivVy) return;
    this.aktivVy = v;
    this.ritaDiagram();
  }

  vaxlaPaus(): void { this.pausad = !this.pausad; }

  vaxlaHelskarm(): void {
    const el = this.host.nativeElement;
    if (!document.fullscreenElement) el.requestFullscreen?.().catch(() => {});
    else document.exitFullscreen?.().catch(() => {});
  }

  @HostListener('document:fullscreenchange')
  onFullscreen(): void { this.helskarm = !!document.fullscreenElement; }

  // ---------- anslutning ----------
  get anslutning(): 'ok' | 'inaktuell' | 'fel' | 'laddar' {
    if (this.laddar) return 'laddar';
    if (this.senastOk === null) return 'fel';
    if (Date.now() - this.senastOk > INAKTUELL_MS) return 'inaktuell';
    return this.senasteFel ? 'inaktuell' : 'ok';
  }

  get uppdateradText(): string {
    if (this.senastOk === null) return this.laddar ? 'Hämtar data…' : 'Ingen data hämtad';
    return 'Uppdaterad ' + new Date(this.senastOk).toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
  }

  get datumRubrik(): string {
    if (!this.idag) return '';
    const d = new Date(`${this.idag}T12:00:00`);
    const s = d.toLocaleDateString('sv-SE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return `${s.charAt(0).toUpperCase()}${s.slice(1)} · vecka ${isoWeek(this.idag)}`;
  }

  // ---------- formatering (saknad data = "–", aldrig 0) ----------
  fmtAntal(v: number | null | undefined): string {
    return v === null || v === undefined ? '–' : Math.round(v).toLocaleString('sv-SE');
  }
  fmtTakt(v: number | null | undefined): string {
    return v === null || v === undefined ? '–' : v.toFixed(1).replace('.', ',');
  }
  fmtTid(v: number | null | undefined): string {
    if (v === null || v === undefined) return '–';
    const h = Math.floor(v / 60), m = Math.round(v % 60);
    if (h >= 100) return `${h.toLocaleString('sv-SE')} h`;
    return h > 0 ? `${h} h ${m} min` : `${m} min`;
  }
  fmtEff(v: number | null | undefined, typ: EffektivitetTyp): string {
    if (v === null || v === undefined) return '–';
    const pct = Math.round(v * 100);
    if (typ === 'kvot') return `${pct} %`;
    if (pct === 0) return '±0 %';
    return pct > 0 ? `+${pct} %` : `−${Math.abs(pct)} %`;
  }
  effKlass(v: number | null | undefined, typ: EffektivitetTyp): string {
    if (v === null || v === undefined) return '';
    if (typ === 'kvot') return v >= 0.9 ? 'bra' : v >= 0.7 ? 'neutral' : 'lag';
    return v >= 0 ? 'bra' : v >= -0.1 ? 'neutral' : 'lag';
  }
  fmtDag(s: string): string {
    const d = new Date(`${s}T12:00:00`);
    return d.toLocaleDateString('sv-SE', { weekday: 'short', day: 'numeric', month: 'short' });
  }
  fmtDatum(s: string | null): string {
    if (!s) return '–';
    return new Date(`${s}T12:00:00`).toLocaleDateString('sv-SE', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  /** Förändring i IBC mot motsvarande förfluten tid; null om jämförelsen saknar data. */
  jamforText(r: PeriodRad): string | null {
    if (!r.jamfor || r.summa.ibc === null || r.jamfor.ibc === null || r.jamfor.ibc === 0) return null;
    const diff = r.summa.ibc - r.jamfor.ibc;
    const pct = Math.round((diff / r.jamfor.ibc) * 100);
    const tecken = diff > 0 ? '+' : diff < 0 ? '−' : '±';
    return `${tecken}${Math.abs(diff).toLocaleString('sv-SE')} IBC (${tecken}${Math.abs(pct)} %) mot ${r.period.jamfor!.label}`;
  }
  rullandeText(): string {
    const p = this.idag ? perioder(this.idag).find(x => x.key === 'rullande12') : null;
    return p ? `${this.fmtDatum(p.fran)} – ${this.fmtDatum(p.till)}` : '';
  }
  manad(s: string): string { return manadNamn(s); }
  trackLinje = (_: number, l: { linje: Linje }) => l.linje;

  // ---------- diagram ----------
  private forstorDiagram(): void {
    this.charts.forEach(c => c.destroy());
    this.charts = [];
  }

  private ritaDiagram(): void {
    // Rita efter att Angular renderat aktuell vy (canvas finns först då).
    setTimeout(() => this.zone.runOutsideAngular(() => {
      this.forstorDiagram();
      if (this.aktivVy === 'bradagar') return;
      for (const { linje } of LINJER) {
        const v = this.vy[linje];
        const canvas = this.host.nativeElement.querySelector<HTMLCanvasElement>(`canvas[data-linje="${linje}"]`);
        if (!v || !canvas) continue;
        const serie = this.aktivVy === 'veckor' ? v.veckor : v.manader;
        this.charts.push(new Chart(canvas, {
          data: {
            labels: serie.map(s => s.label + (s.pagar ? ' (pågår)' : '')),
            datasets: [
              {
                type: 'bar', label: 'IBC', yAxisID: 'y',
                data: serie.map(s => s.summa.ibc),
                backgroundColor: serie.map(s => s.pagar ? 'rgba(99, 179, 237, 0.35)' : 'rgba(99, 179, 237, 0.85)'),
                borderRadius: 6, order: 2,
              },
              {
                type: 'line', label: 'Antal per drifttimme', yAxisID: 'y1',
                data: serie.map(s => s.summa.taktPerTimme === null ? null : Math.round(s.summa.taktPerTimme * 10) / 10),
                borderColor: '#f6c453', backgroundColor: '#f6c453', borderWidth: 3, pointRadius: 4,
                spanGaps: false, tension: 0.25, order: 1,
              },
            ],
          },
          options: {
            responsive: true, maintainAspectRatio: false, animation: false,
            plugins: {
              legend: { labels: { color: '#e2e8f0', font: { size: 14 } } },
              tooltip: { callbacks: { label: c => c.raw === null ? `${c.dataset.label}: saknas` : `${c.dataset.label}: ${String(c.raw).replace('.', ',')}` } },
            },
            scales: {
              x: { ticks: { color: '#cbd5e0', font: { size: 13 } }, grid: { display: false } },
              y: { beginAtZero: true, position: 'left', title: { display: true, text: 'IBC', color: '#90cdf4' },
                   ticks: { color: '#90cdf4', font: { size: 13 } }, grid: { color: 'rgba(255,255,255,0.08)' } },
              y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'IBC per drifttimme', color: '#f6c453' },
                    ticks: { color: '#f6c453', font: { size: 13 } }, grid: { display: false } },
            },
          },
        }));
      }
    }), 0);
  }
}
