import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-login',
  imports: [FormsModule, CommonModule, RouterModule],
  template: `
    <div class="login-page">
      <div class="login-card">
        <h2><i class="fas fa-sign-in-alt me-2"></i>Logga in</h2>
        <form (ngSubmit)="login()" class="login-form">
          <div class="mb-3">
            <label class="form-label">Användarnamn</label>
            <input type="text" class="form-control" placeholder="Ange användarnamn"
                   [(ngModel)]="username" name="username" required [disabled]="loading" />
          </div>
          <div class="mb-3">
            <label class="form-label">Lösenord</label>
            <input type="password" class="form-control" placeholder="Ange lösenord"
                   [(ngModel)]="password" name="password" required [disabled]="loading" />
          </div>
          <button type="submit" class="btn btn-primary w-100" [disabled]="loading || !username || !password">
            <span *ngIf="loading"><i class="fas fa-spinner fa-spin me-1"></i>Loggar in...</span>
            <span *ngIf="!loading">Logga in</span>
          </button>
          <div *ngIf="error" class="alert alert-danger mt-3 mb-0 py-2">{{ error }}</div>
          <div class="text-center mt-3">
            <small style="color: #718096;">Inget konto? <a routerLink="/register" style="color: #4299e1; text-decoration: none;">Registrera dig</a></small>
          </div>
        </form>
      </div>
    </div>
  `,
  styles: [`
    .login-page {
      min-height: 80vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 1rem;
    }
    .login-card {
      background: #23272b;
      border-radius: 1rem;
      padding: 2rem;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    h2 { color: #e2e8f0; margin-bottom: 1.5rem; text-align: center; }
    .form-label { color: #a0aec0; font-size: 0.85rem; }
    .form-control {
      background: #1a202c;
      border-color: #4a5568;
      color: #e2e8f0;
    }
    .form-control:focus {
      background: #2d3748;
      border-color: #4299e1;
      color: #e2e8f0;
      box-shadow: 0 0 0 2px rgba(66, 153, 225, 0.3);
    }
    .form-control::placeholder { color: #718096; }
  `]
})
export class LoginPage {
  username = '';
  password = '';
  error = '';
  loading = false;

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private router: Router
  ) {}

  login() {
    this.error = '';
    this.loading = true;

    this.http.post<any>('/noreko-backend/api.php?action=login', {
      username: this.username,
      password: this.password
    }, { withCredentials: true }).subscribe({
      next: (res) => {
        if (res.success) {
          this.auth.fetchStatus();
          this.router.navigate(['/']);
        } else {
          this.error = res.message || 'Fel användarnamn eller lösenord.';
          this.loading = false;
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Inloggningen misslyckades. Försök igen.';
        this.loading = false;
      }
    });
  }
}
