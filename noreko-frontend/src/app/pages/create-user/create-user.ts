import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { UsersService } from '../../services/users.service';

@Component({
  standalone: true,
  selector: 'app-create-user',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './create-user.html',
  styleUrl: './create-user.css'
})
export class CreateUserPage implements OnInit {
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
    this.auth.user$.subscribe(user => {
      if (!user || user.role !== 'admin') {
        this.router.navigate(['/']);
      }
    });
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

    this.usersService.createUser(this.user).subscribe({
      next: (res) => {
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
      },
      error: (error) => {
        this.isLoading = false;
        if (error.error && error.error.message) {
          this.errorMessage = error.error.message;
        } else {
          this.errorMessage = 'Ett fel uppstod vid skapande av användare. Försök igen senare.';
        }
      }
    });
  }
}

