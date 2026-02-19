import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export interface Toast {
  id: number;
  message: string;
  type: 'error' | 'warning' | 'success' | 'info';
  duration: number;
}

@Injectable({ providedIn: 'root' })
export class ToastService {
  private nextId = 0;
  private toasts: Toast[] = [];
  toasts$ = new BehaviorSubject<Toast[]>([]);

  show(message: string, type: Toast['type'] = 'info', duration = 5000): void {
    const toast: Toast = { id: this.nextId++, message, type, duration };
    this.toasts.push(toast);
    this.toasts$.next([...this.toasts]);

    if (duration > 0) {
      setTimeout(() => this.dismiss(toast.id), duration);
    }
  }

  error(message: string, duration = 6000): void {
    this.show(message, 'error', duration);
  }

  warning(message: string, duration = 5000): void {
    this.show(message, 'warning', duration);
  }

  success(message: string, duration = 4000): void {
    this.show(message, 'success', duration);
  }

  dismiss(id: number): void {
    this.toasts = this.toasts.filter(t => t.id !== id);
    this.toasts$.next([...this.toasts]);
  }
}
