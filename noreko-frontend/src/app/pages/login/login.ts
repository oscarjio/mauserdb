import { Component, OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router, RouterModule, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { environment } from '../../../environments/environment';

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
            <label class="form-label" for="login-username">Användarnamn</label>
            <input type="text" class="form-control" id="login-username" placeholder="Ange användarnamn"
                   [(ngModel)]="username" name="username" required minlength="3" maxlength="50" [disabled]="loading" autocomplete="username" />
          </div>
          <div class="mb-3">
            <label class="form-label" for="login-password">Lösenord</label>
            <input type="password" class="form-control" id="login-password" placeholder="Ange lösenord"
                   [(ngModel)]="password" name="password" required minlength="8" maxlength="128" [disabled]="loading" autocomplete="current-password" />
          </div>
          <button type="submit" class="btn btn-primary w-100" [disabled]="loading || !username || !password">
            <span *ngIf="loading"><i class="fas fa-spinner fa-spin me-1"></i>Loggar in...</span>
            <span *ngIf="!loading">Logga in</span>
          </button>
          <div *ngIf="error" class="alert alert-danger mt-3 mb-0 py-2">{{ error }}</div>
          <div class="text-center mt-3">
            <small style="color: #8fa3b8;">Inget konto? <a routerLink="/register" style="color: #4299e1; text-decoration: none;">Registrera dig</a></small>
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
      background: #2d3748;
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
    .form-control::placeholder { color: #8fa3b8; }
  `]
})
export class LoginPage implements OnDestroy {
  username = '';
  password = '';
  error = '';
  loading = false;
  private destroy$ = new Subject<void>();

  private returnUrl: string = '/';

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {
    const raw = this.route.snapshot.queryParams['returnUrl'] || '/';
    // Validate returnUrl to prevent open redirect — only allow relative paths
    this.returnUrl = (typeof raw === 'string' && raw.startsWith('/') && !raw.startsWith('//')) ? raw : '/';
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  login() {
    this.error = '';
    this.loading = true;

    this.http.post<any>(`${environment.apiUrl}?action=login`, {
      username: this.username.trim(),
      password: this.password
    }, { withCredentials: true }).pipe(
      timeout(8000),
      catchError(err => {
        console.error('Inloggning misslyckades:', err);
        this.error = err?.error?.error || 'Inloggningen misslyckades. Försök igen.';
        this.loading = false;
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (!res) return;
        if (res.success) {
          // Sätt auth-state SYNKRONT från login-svaret innan navigate().
          // Utan detta hinner authGuard se loggedIn$=false (från startup-fetchStatus
          // som redan körts och returnerat false) och redirectar tillbaka till /login.
          this.auth.loggedIn$.next(true);
          this.auth.user$.next(res.user);
          this.auth.initialized$.next(true);
          sessionStorage.setItem('auth_user', JSON.stringify(res.user));
          if (res.csrfToken) {
            sessionStorage.setItem('csrf_token', res.csrfToken);
          }
          this.auth.onLoginSuccess();
          this.router.navigateByUrl(this.returnUrl);
          this.auth.fetchStatus().pipe(
            timeout(8000),
            catchError(err => {
              console.error('Verifiering av inloggningsstatus misslyckades:', err);
              return of(null);
            }),
            takeUntil(this.destroy$)
          ).subscribe(); // bakgrundsverifiering
        } else {
          this.error = res.error || 'Fel användarnamn eller lösenord.';
          this.loading = false;
        }
      }
    });
  }
}
