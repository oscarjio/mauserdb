import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpClient, HttpClientModule } from '@angular/common/http';

@Component({
  standalone: true,
  selector: 'app-register',
  imports: [FormsModule, CommonModule, HttpClientModule],
  templateUrl: './register.html',
  styleUrl: './register.css'
})
export class RegisterPage {
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
  http = inject(HttpClient);

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
    // Enkel e-postvalidering
    const email = this.user.email;
    this.isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  onSubmit() {
    // Rensa tidigare meddelanden
    this.errorMessage = '';
    this.successMessage = '';
    
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
    
    this.isLoading = true;
    
    this.http.post<any>('/noreko-backend/api.php?action=register', {
      username: this.user.username,
      password: this.user.password,
      password2: this.user.password2,
      email: this.user.email,
      phone: this.user.phone,
      code: this.user.code
    }, { withCredentials: true }).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.successMessage = res.message || 'Registrering lyckades! Du kan nu logga in.';
          // Rensa formuläret
          this.user = {
            username: '',
            password: '',
            password2: '',
            email: '',
            phone: '',
            code: ''
          };
          this.showFeedback = false;
          // Omdirigera till login efter 2 sekunder
          setTimeout(() => {
            window.location.href = '/login';
          }, 2000);
        } else {
          this.errorMessage = res.message || 'Registrering misslyckades. Försök igen.';
        }
      },
      error: (error) => {
        this.isLoading = false;
        if (error.error && error.error.message) {
          this.errorMessage = error.error.message;
        } else {
          this.errorMessage = 'Ett fel uppstod vid registrering. Försök igen senare.';
        }
      }
    });
  }
}
