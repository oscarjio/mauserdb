import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import {
  FavoriterService,
  Favorit,
  AVAILABLE_PAGES,
  AvailablePage,
} from '../../services/favoriter.service';

@Component({
  standalone: true,
  selector: 'app-favoriter',
  templateUrl: './favoriter.html',
  styleUrl: './favoriter.css',
  imports: [CommonModule, FormsModule, RouterModule],
})
export class FavoriterPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  favoriter: Favorit[] = [];
  loading = true;
  error = '';
  successMsg = '';

  // Lägg-till-modal
  showAddDialog = false;
  searchQuery = '';
  availablePages: AvailablePage[] = AVAILABLE_PAGES;

  get filteredPages(): AvailablePage[] {
    const existingRoutes = new Set(this.favoriter.map(f => f.route));
    let pages = this.availablePages.filter(p => !existingRoutes.has(p.route));
    if (this.searchQuery.trim()) {
      const q = this.searchQuery.toLowerCase();
      pages = pages.filter(p => p.label.toLowerCase().includes(q) || p.route.toLowerCase().includes(q));
    }
    return pages;
  }

  constructor(private favService: FavoriterService) {}

  ngOnInit(): void {
    this.loadFavoriter();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadFavoriter(): void {
    this.loading = true;
    this.error = '';
    this.favService.list()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (res.success) {
          this.favoriter = res.data;
        } else {
          this.error = 'Kunde inte ladda favoriter';
        }
      });
  }

  addFavorit(page: AvailablePage): void {
    this.favService.add(page.route, page.label, page.icon, page.color)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        if (res.success && res.data) {
          this.favoriter.push(res.data as Favorit);
          this.successMsg = `"${page.label}" tillagd`;
          setTimeout(() => this.successMsg = '', 2500);
        } else {
          this.error = res.error || 'Kunde inte lägga till';
          setTimeout(() => this.error = '', 3000);
        }
      });
  }

  removeFavorit(fav: Favorit): void {
    this.favService.remove(fav.id)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        if (res.success) {
          this.favoriter = this.favoriter.filter(f => f.id !== fav.id);
          this.successMsg = `"${fav.label}" borttagen`;
          setTimeout(() => this.successMsg = '', 2500);
        } else {
          this.error = res.error || 'Kunde inte ta bort';
          setTimeout(() => this.error = '', 3000);
        }
      });
  }

  moveUp(index: number): void {
    if (index <= 0) return;
    [this.favoriter[index - 1], this.favoriter[index]] = [this.favoriter[index], this.favoriter[index - 1]];
    this.saveOrder();
  }

  moveDown(index: number): void {
    if (index >= this.favoriter.length - 1) return;
    [this.favoriter[index], this.favoriter[index + 1]] = [this.favoriter[index + 1], this.favoriter[index]];
    this.saveOrder();
  }

  private saveOrder(): void {
    const ids = this.favoriter.map(f => f.id);
    this.favService.reorder(ids)
      .pipe(takeUntil(this.destroy$))
      .subscribe();
  }

  openAddDialog(): void {
    this.showAddDialog = true;
    this.searchQuery = '';
  }

  closeAddDialog(): void {
    this.showAddDialog = false;
  }
  trackByIndex(index: number): number { return index; }
}
