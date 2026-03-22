import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { ProduktionspulsService, PulsItem } from '../../../services/produktionspuls.service';

@Component({
  selector: 'app-produktionspuls-widget',
  standalone: true,
  imports: [CommonModule, RouterModule],
  template: `
    <div class="puls-widget" *ngIf="isLoading">
      <div class="puls-widget-header">
        <span class="puls-widget-title">
          <i class="fas fa-heartbeat text-danger me-1"></i>Produktionspuls
        </span>
      </div>
      <div class="text-center py-2" style="color:#a0aec0;font-size:0.8rem;">
        <i class="fas fa-circle-notch fa-spin me-1"></i>Laddar pulsdata...
      </div>
    </div>
    <div class="puls-widget" *ngIf="!isLoading && items.length > 0">
      <div class="puls-widget-header">
        <span class="puls-widget-title">
          <i class="fas fa-heartbeat text-danger me-1"></i>Produktionspuls
        </span>
        <a routerLink="/rebotling/produktionspuls" class="puls-widget-link">
          Visa alla <i class="fas fa-arrow-right ms-1"></i>
        </a>
      </div>
      <div class="puls-widget-ticker"
           (mouseenter)="paused = true"
           (mouseleave)="paused = false">
        <div class="puls-widget-track" [class.ticker-paused]="paused">
          <ng-container *ngFor="let item of items; trackBy: trackByIndex">
            <div class="pw-item" [ngClass]="{
              'pw-ok': !item.kasserad && !item.over_target,
              'pw-kasserad': item.kasserad,
              'pw-over': !item.kasserad && item.over_target
            }">
              <span class="pw-op">{{ item.operator }}</span>
              <span class="pw-cykel" *ngIf="item.cykeltid !== null">{{ item.cykeltid }}m</span>
              <span class="pw-cykel pw-na" *ngIf="item.cykeltid === null">--</span>
              <span class="pw-badge" *ngIf="item.kasserad">K</span>
            </div>
          </ng-container>
          <ng-container *ngFor="let item of items; trackBy: trackByIndex">
            <div class="pw-item" [ngClass]="{
              'pw-ok': !item.kasserad && !item.over_target,
              'pw-kasserad': item.kasserad,
              'pw-over': !item.kasserad && item.over_target
            }">
              <span class="pw-op">{{ item.operator }}</span>
              <span class="pw-cykel" *ngIf="item.cykeltid !== null">{{ item.cykeltid }}m</span>
              <span class="pw-cykel pw-na" *ngIf="item.cykeltid === null">--</span>
              <span class="pw-badge" *ngIf="item.kasserad">K</span>
            </div>
          </ng-container>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .puls-widget {
      background: #2d3748;
      border: 1px solid #4a5568;
      border-radius: 0.75rem;
      overflow: hidden;
      margin-bottom: 1rem;
    }

    .puls-widget-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.5rem 0.75rem;
      background: #1a202c;
      border-bottom: 1px solid #4a5568;
    }

    .puls-widget-title {
      font-size: 0.8rem;
      font-weight: 600;
      color: #e2e8f0;
    }

    .puls-widget-link {
      font-size: 0.72rem;
      color: #63b3ed;
      text-decoration: none;
    }

    .puls-widget-link:hover {
      color: #90cdf4;
      text-decoration: underline;
    }

    .puls-widget-ticker {
      overflow: hidden;
      padding: 0.4rem 0;
      position: relative;
    }

    .puls-widget-track {
      display: flex;
      gap: 0.5rem;
      animation: pwScroll 45s linear infinite;
      width: max-content;
      padding: 0 0.5rem;
    }

    .ticker-paused {
      animation-play-state: paused;
    }

    @keyframes pwScroll {
      0% { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    .pw-item {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.25rem 0.5rem;
      border-radius: 0.35rem;
      font-size: 0.75rem;
      background: #1a202c;
      border: 1px solid #4a5568;
      white-space: nowrap;
    }

    .pw-ok { border-left: 2px solid #48bb78; }
    .pw-kasserad { border-left: 2px solid #fc8181; background: #2d1b1b; }
    .pw-over { border-left: 2px solid #ecc94b; }

    .pw-op {
      font-weight: 600;
      color: #e2e8f0;
      max-width: 80px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .pw-cykel {
      color: #63b3ed;
      font-weight: 700;
    }

    .pw-na { color: #718096; }

    .pw-badge {
      background: #fc8181;
      color: #1a202c;
      font-size: 0.6rem;
      font-weight: 700;
      padding: 0.1em 0.35em;
      border-radius: 0.2rem;
    }
  `]
})
export class ProduktionspulsWidget implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;

  items: PulsItem[] = [];
  isLoading = true;
  paused = false;

  constructor(private pulsService: ProduktionspulsService) {}

  ngOnInit(): void {
    this.fetchData();
    this.pollInterval = setInterval(() => this.fetchData(), 15000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
    }
  }

  fetchData(): void {
    this.pulsService.getLatest(30).pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isLoading = false;
      if (res?.success && Array.isArray(res.data)) {
        this.items = res.data;
      }
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
