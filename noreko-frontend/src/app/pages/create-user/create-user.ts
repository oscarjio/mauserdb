import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { UsersService } from '../../services/users.service';

@Component({
  standalone: true,
  selector: 'app-create-user',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './create-user.html',
  styleUrl: './create-user.css'
})
export class CreateUserPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  user = {
    username: '',
    password: '',
    email: '',
    phone: ''
  };

  isLoading = false;
  errorMessage = '';
  successMessage = '';

  get isPasswordValid(): boolean {
    const p = this.user.password;
    return p.length >= 8 && /[a-zA-Z]/.test(p) && /[0-9]/.test(p);
  }

  get isEmailValid(): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.user.email);
  }

  get canSubmit(): boolean {
    return !!this.user.username.trim() && this.isPasswordValid && this.isEmailValid && !this.isLoading;
  }

  constructor(
    private usersService: UsersService,
    private auth: AuthService,
    private router: Router
  ) {}

  ngOnInit() {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      if (user !== undefined && (!user || user.role !== 'admin')) {
        this.router.navigate(['/']);
      }
    });
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onSubmit() {
    // Rensa tidigare meddelanden
    this.errorMessage = '';
    this.successMessage = '';

    if (!this.user.username.trim()) {
      this.errorMessage = 'Användarnamn är obligatoriskt.';
      return;
    }
    if (!this.isPasswordValid) {
      this.errorMessage = 'Lösenordet måste vara minst 8 tecken med bokstav och siffra.';
      return;
    }
    if (!this.isEmailValid) {
      this.errorMessage = 'Ange en giltig e-postadress.';
      return;
    }

    this.isLoading = true;

    this.usersService.createUser(this.user).pipe(
      takeUntil(this.destroy$),
      timeout(8000),
      catchError(err => {
        console.error('Create user request failed:', err);
        this.isLoading = false;
        this.errorMessage = err?.error?.message || 'Ett fel uppstod vid skapande av användare. Försök igen senare.';
        return of(null);
      })
    ).subscribe({
      next: (res) => {
        if (!res) return;
        this.isLoading = false;
        if (res.success) {
          this.successMessage = res.message || 'Användare skapad!';
          // Rensa formuläret
          this.user = {
            username: '',
            password: '',
            email: '',
            phone: ''
          };
        } else {
          this.errorMessage = res.message || 'Kunde inte skapa användare.';
        }
      }
    });
  }
}
