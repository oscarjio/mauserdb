import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgIf } from '@angular/common';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { inject } from '@angular/core';

@Component({
  standalone: true,
  selector: 'app-login',
  imports: [FormsModule, NgIf, HttpClientModule],
  template: `
    <div class="login-page">
      <h2>Login</h2>
      <form (ngSubmit)="login()" #loginForm="ngForm" class="login-form">
        <div class="mb-3">
          <input type="text" class="form-control" placeholder="Username" [(ngModel)]="username" name="username" required />
        </div>
        <div class="mb-3">
          <input type="password" class="form-control" placeholder="Password" [(ngModel)]="password" name="password" required />
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
        <div *ngIf="error" class="text-danger mt-2">Fel användarnamn eller lösenord</div>
      </form>
    </div>
  `,
  styles: [
    `.login-page { min-height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }`,
    `.login-form { width: 100%; max-width: 320px; }`
  ]
})
export class LoginPage {
  username = '';
  password = '';
  error = false;
  http = inject(HttpClient);

  login() {
    this.error = false;
    this.http.post<any>('http://localhost/noreko-backend/api.php?action=login', {
      username: this.username,
      password: this.password
    }, { withCredentials: true }).subscribe({
      next: (res) => {
        if (res.success) {
          window.location.href = '/';
        } else {
          this.error = true;
        }
      },
      error: () => {
        this.error = true;
      }
    });
  }
}
