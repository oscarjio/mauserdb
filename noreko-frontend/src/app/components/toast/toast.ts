import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subscription } from 'rxjs';
import { ToastService, Toast } from '../../services/toast.service';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="toast-container">
      <div *ngFor="let toast of toasts"
           class="toast-item"
           [class.toast-error]="toast.type === 'error'"
           [class.toast-warning]="toast.type === 'warning'"
           [class.toast-success]="toast.type === 'success'"
           [class.toast-info]="toast.type === 'info'"
           (click)="dismiss(toast.id)">
        <i class="fas" [ngClass]="{
          'fa-times-circle': toast.type === 'error',
          'fa-exclamation-triangle': toast.type === 'warning',
          'fa-check-circle': toast.type === 'success',
          'fa-info-circle': toast.type === 'info'
        }"></i>
        <span class="toast-message">{{ toast.message }}</span>
        <i class="fas fa-times toast-close"></i>
      </div>
    </div>
  `,
  styles: [`
    .toast-container {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      max-width: 420px;
    }
    .toast-item {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      color: #fff;
      font-size: 0.9rem;
      cursor: pointer;
      animation: slideIn 0.3s ease;
      backdrop-filter: blur(8px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .toast-error { background: rgba(231, 76, 60, 0.95); }
    .toast-warning { background: rgba(241, 196, 15, 0.95); color: #1a1a2e; }
    .toast-success { background: rgba(39, 174, 96, 0.95); }
    .toast-info { background: rgba(52, 152, 219, 0.95); }
    .toast-message { flex: 1; }
    .toast-close { opacity: 0.6; font-size: 0.8rem; }
    .toast-close:hover { opacity: 1; }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  `]
})
export class ToastComponent implements OnInit, OnDestroy {
  toasts: Toast[] = [];
  private sub!: Subscription;

  constructor(private toastService: ToastService) {}

  ngOnInit(): void {
    this.sub = this.toastService.toasts$.subscribe(t => this.toasts = t);
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  dismiss(id: number): void {
    this.toastService.dismiss(id);
  }
}
