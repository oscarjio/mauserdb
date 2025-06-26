import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgIf } from '@angular/common';

@Component({
  standalone: true,
  selector: 'app-login',
  imports: [FormsModule, NgIf],
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

  login() {
    if (this.username === 'admin' && this.password === 'admin') {
      localStorage.setItem('loggedIn', 'true');
      window.location.href = '/';
    } else {
      this.error = true;
    }
  }
}
