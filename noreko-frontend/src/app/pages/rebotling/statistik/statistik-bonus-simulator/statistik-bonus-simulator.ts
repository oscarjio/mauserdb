import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, debounceTime, timeout } from 'rxjs/operators';
import { of, Subject as RxSubject } from 'rxjs';
import {
  RebotlingService,
  BonusSimulatorParams,
  BonusSimulatorOperator,
  BonusSimulatorSavePayload,
  BonusSimulatorWeights,
} from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-bonus-simulator',
  templateUrl: './statistik-bonus-simulator.html',
  styleUrls: ['./statistik-bonus-simulator.css'],
  imports: [CommonModule, FormsModule],
})
export class StatistikBonusSimulatorComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private simulate$ = new RxSubject<void>();

  loading = false;
  saving = false;
  error: string | null = null;
  saveSuccess = false;
  saveError: string | null = null;

  days = 30;

  // Simulerade vikter (slider-värden 0–100, skalas till 0.0–1.0)
  // FoodGrade (produkt 1)
  eff1 = 30;
  prod1 = 30;
  qual1 = 40;

  // NonUN (produkt 4)
  eff4 = 35;
  prod4 = 45;
  qual4 = 20;

  // Tvättade (produkt 5)
  eff5 = 40;
  prod5 = 35;
  qual5 = 25;

  // Produktivitetsmål (IBC/h)
  target1 = 12;
  target4 = 20;
  target5 = 15;

  // Tier-multiplikatorer (×100 för slider, visas som 2.00)
  tier95 = 200;
  tier90 = 150;
  tier80 = 125;
  tier70 = 100;
  tier0  = 75;

  maxBonus = 200;

  // Resultat
  operatorer: BonusSimulatorOperator[] = [];
  periodFrom = '';
  periodTo = '';

  // Viktnings-summor per produkt (för validering)
  get sum1(): number { return this.eff1 + this.prod1 + this.qual1; }
  get sum4(): number { return this.eff4 + this.prod4 + this.qual4; }
  get sum5(): number { return this.eff5 + this.prod5 + this.qual5; }

  get vikterGiltiga(): boolean {
    return this.sum1 === 100 && this.sum4 === 100 && this.sum5 === 100;
  }

  // Statistik om hela datasetet
  get totalPositivaDiff(): number {
    return this.operatorer.filter(o => o.bonus_diff > 0).length;
  }
  get totalNegativaDiff(): number {
    return this.operatorer.filter(o => o.bonus_diff < 0).length;
  }
  get snitBonusDiff(): number {
    if (!this.operatorer.length) return 0;
    return Math.round(this.operatorer.reduce((s, o) => s + o.bonus_diff, 0) / this.operatorer.length * 10) / 10;
  }

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    // Debounce simulate-triggern så att varje slider-drag inte ger ett nytt API-anrop direkt
    this.simulate$.pipe(
      debounceTime(400),
      takeUntil(this.destroy$)
    ).subscribe(() => this.doLoad());

    this.simulate$.next();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.simulate$.complete();
  }

  onSliderChange(): void {
    this.simulate$.next();
  }

  onDaysChange(): void {
    this.simulate$.next();
  }

  private buildParams(): BonusSimulatorParams {
    return {
      eff_w_1: this.eff1 / 100,
      prod_w_1: this.prod1 / 100,
      qual_w_1: this.qual1 / 100,
      eff_w_4: this.eff4 / 100,
      prod_w_4: this.prod4 / 100,
      qual_w_4: this.qual4 / 100,
      eff_w_5: this.eff5 / 100,
      prod_w_5: this.prod5 / 100,
      qual_w_5: this.qual5 / 100,
      target_1: this.target1,
      target_4: this.target4,
      target_5: this.target5,
      max_bonus: this.maxBonus,
      tier_95: this.tier95 / 100,
      tier_90: this.tier90 / 100,
      tier_80: this.tier80 / 100,
      tier_70: this.tier70 / 100,
      tier_0: this.tier0 / 100,
    };
  }

  private doLoad(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = null;

    this.rebotlingService.getBonusSimulator(this.days, this.buildParams()).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(resp => {
      this.loading = false;
      if (!resp || !resp.success || !resp.data) {
        this.error = 'Kunde inte ladda bonusdata.';
        return;
      }
      this.operatorer = resp.data.operatorer;
      this.periodFrom = resp.data.period_from;
      this.periodTo   = resp.data.period_to;

      // Ladda standardvärden vid första körning (om defaults ej ändrade)
      const ap = resp.data.aktuella_parametrar;
      if (ap && !this.hasUserEdited) {
        const w1 = ap.vikter?.[1];
        const w4 = ap.vikter?.[4];
        const w5 = ap.vikter?.[5];
        if (w1) { this.eff1 = Math.round(w1.eff * 100); this.prod1 = Math.round(w1.prod * 100); this.qual1 = Math.round(w1.qual * 100); }
        if (w4) { this.eff4 = Math.round(w4.eff * 100); this.prod4 = Math.round(w4.prod * 100); this.qual4 = Math.round(w4.qual * 100); }
        if (w5) { this.eff5 = Math.round(w5.eff * 100); this.prod5 = Math.round(w5.prod * 100); this.qual5 = Math.round(w5.qual * 100); }
        if (ap.mal?.[1]) this.target1 = ap.mal[1];
        if (ap.mal?.[4]) this.target4 = ap.mal[4];
        if (ap.mal?.[5]) this.target5 = ap.mal[5];
        if (ap.max_bonus) this.maxBonus = ap.max_bonus;
        if (ap.tiers) {
          if (ap.tiers[95]) this.tier95 = Math.round(ap.tiers[95] * 100);
          if (ap.tiers[90]) this.tier90 = Math.round(ap.tiers[90] * 100);
          if (ap.tiers[80]) this.tier80 = Math.round(ap.tiers[80] * 100);
          if (ap.tiers[70]) this.tier70 = Math.round(ap.tiers[70] * 100);
          if (ap.tiers[0]  !== undefined) this.tier0 = Math.round(ap.tiers[0] * 100);
        }
        this.hasUserEdited = true;
      }
    });
  }

  private hasUserEdited = false;

  sparaParametrar(): void {
    if (!this.vikterGiltiga) return;
    this.saving = true;
    this.saveSuccess = false;
    this.saveError = null;

    const payload: BonusSimulatorSavePayload = {
      vikter: {
        1: { eff: this.eff1 / 100, prod: this.prod1 / 100, qual: this.qual1 / 100 },
        4: { eff: this.eff4 / 100, prod: this.prod4 / 100, qual: this.qual4 / 100 },
        5: { eff: this.eff5 / 100, prod: this.prod5 / 100, qual: this.qual5 / 100 },
      },
      mal: {
        1: this.target1,
        4: this.target4,
        5: this.target5,
      },
      max_bonus: this.maxBonus,
    };

    this.rebotlingService.saveBonusSimulatorParams(payload).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(resp => {
      this.saving = false;
      if (!resp || !resp.success) {
        this.saveError = resp?.error ?? 'Kunde inte spara parametrar.';
        return;
      }
      this.saveSuccess = true;
      setTimeout(() => { this.saveSuccess = false; }, 3000);
    });
  }

  tierName(value: number): string {
    if (value >= 95) return 'Outstanding';
    if (value >= 90) return 'Excellent';
    if (value >= 80) return 'God prestanda';
    if (value >= 70) return 'Basbonus';
    return 'Under förväntan';
  }

  tierClass(tierNamn: string): string {
    switch (tierNamn) {
      case 'Outstanding':    return 'tier-outstanding';
      case 'Excellent':      return 'tier-excellent';
      case 'God prestanda':  return 'tier-god';
      case 'Basbonus':       return 'tier-bas';
      default:               return 'tier-under';
    }
  }

  produktNamn(id: number): string {
    switch (id) {
      case 1: return 'FoodGrade';
      case 4: return 'NonUN';
      case 5: return 'Tvättade';
      default: return 'Okänd';
    }
  }

  diffClass(diff: number): string {
    if (diff > 0) return 'diff-pos';
    if (diff < 0) return 'diff-neg';
    return 'diff-neutral';
  }

  diffPrefix(diff: number): string {
    if (diff > 0) return '+';
    return '';
  }
  trackByIndex(index: number): number { return index; }
}
