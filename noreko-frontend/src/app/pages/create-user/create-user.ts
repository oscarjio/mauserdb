import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { UsersService } from '../../services/users.service';

@Component({
  standalone: true,
  selector: 'app-create-user',
  imports: [CommonModule, FormsModule, HttpClientModule, RouterModule],
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
  http = inject(HttpClient);

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

    // Enkel validering
    if (!this.user.username || !this.user.password || !this.user.email) {
      this.errorMessage = 'Användarnamn, lösenord och e-post är obligatoriska fält.';
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

