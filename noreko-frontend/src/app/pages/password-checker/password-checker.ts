import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-password-checker',
  standalone: true,
  imports: [FormsModule, CommonModule],
  templateUrl: './password-checker.html',
  styleUrl: './password-checker.css'
})
export class PasswordCheckerComponent {
  password = '';
  isLongEnough = false;
  hasLetter = false;
  hasNumber = false;
  isAcceptable = false;

  checkPasswordStrength() {
    const pwd = this.password;
    this.isLongEnough = pwd.length >= 8;
    this.hasLetter = /[A-Za-z]/.test(pwd);
    this.hasNumber = /[0-9]/.test(pwd);
    this.isAcceptable = this.isLongEnough && this.hasLetter && this.hasNumber;
  }
}

// Denna komponent är borttagen och används inte längre.
// All lösenordslogik finns nu direkt i register-komponenten.
