import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';

@Component({
  standalone: true,
  selector: 'app-register',
  imports: [FormsModule, CommonModule],
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
    if (!this.passwordsMatch) {
      alert('Lösenorden matchar inte!');
      return;
    }
    if (!this.isEmailValid) {
      alert('Ogiltig e-postadress!');
      return;
    }
    // Här kan du lägga till logik för att skicka registreringen till backend
    alert('Registrering skickad!');
  }
}
