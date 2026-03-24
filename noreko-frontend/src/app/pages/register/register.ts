import { Component, OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

@Component({
  standalone: true,
  selector: 'app-register',
  imports: [FormsModule, CommonModule, RouterModule],
  templateUrl: './register.html',
  styleUrl: './register.css'
})
export class RegisterPage implements OnDestroy {
  private destroy$ = new Subject<void>();
  private redirectTimerId: any = null;
  user = {
    username: '',
    password: '',
    password2: '',
    email: '',
    phone: '',
    code: ''
  };

  isLongEnough = false;
  hasLetter = false;
  hasNumber = false;
  isAcceptable = false;
  passwordsMatch = false;
  showFeedback = false;
  isEmailValid = true;
  isLoading = false;
  errorMessage = '';
  successMessage = '';

  constructor(private http: HttpClient, private router: Router) {}

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    clearTimeout(this.redirectTimerId);
  }

  checkPasswordStrength() {
    const pwd = this.user.password;
    const pwd2 = this.user.password2;
    this.isLongEnough = pwd.length >= 8;
    this.hasLetter = /[A-Za-z]/.test(pwd);
    this.hasNumber = /[0-9]/.test(pwd);
    this.isAcceptable = this.isLongEnough && this.hasLetter && this.hasNumber;
    this.passwordsMatch = pwd === pwd2 && pwd.length > 0;
    this.showFeedback = pwd.length > 0 || pwd2.length > 0;
  }

  checkEmail() {
    const email = this.user.email;
    this.isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  onSubmit() {
    this.errorMessage = '';
    this.successMessage = '';

    if (!this.user.username.trim() || this.user.username.trim().length < 3) {
      this.errorMessage = 'Användarnamn måste vara minst 3 tecken!';
      return;
    }
    if (!this.passwordsMatch) {
      this.errorMessage = 'Lösenorden matchar inte!';
      return;
    }
    if (!this.isEmailValid) {
      this.errorMessage = 'Ogiltig e-postadress!';
      return;
    }
    if (!this.isAcceptable) {
      this.errorMessage = 'Lösenordet uppfyller inte kraven!';
      return;
    }
    if (!this.user.phone.trim()) {
      this.errorMessage = 'Telefonnummer krävs!';
      return;
    }
    if (!this.user.code.trim()) {
      this.errorMessage = 'Kontrollkod krävs!';
      return;
    }

    this.isLoading = true;

    this.http.post<any>(`${environment.apiUrl}?action=register`, {
      username: this.user.username.trim(),
      password: this.user.password,
      password2: this.user.password2,
      email: this.user.email.trim(),
      phone: this.user.phone.trim(),
      code: this.user.code.trim()
    }, { withCredentials: true }).pipe(
      timeout(8000),
      catchError(err => {
        console.error('Registrering misslyckades:', err);
        this.isLoading = false;
        this.errorMessage = err?.error?.error || 'Ett fel uppstod vid registrering. Försök igen senare.';
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (!res) return;
        this.isLoading = false;
        if (res.success) {
          this.successMessage = res.message || 'Registrering lyckades! Omdirigerar till inloggning...';
          this.user = { username: '', password: '', password2: '', email: '', phone: '', code: '' };
          this.showFeedback = false;
          this.redirectTimerId = setTimeout(() => this.router.navigate(['/login']), 2000);
        } else {
          this.errorMessage = res.error || 'Registrering misslyckades. Försök igen.';
        }
      }
    });
  }
}
