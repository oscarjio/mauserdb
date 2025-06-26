import { Component } from '@angular/core';

@Component({
  standalone: true,
  selector: 'app-register',
  template: `<div class="register-page"><h2>Register</h2></div>`,
  styles: [`.register-page { min-height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }`]
})
export class RegisterPage {}
