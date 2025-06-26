import { Component } from '@angular/core';

@Component({
  standalone: true,
  selector: 'app-contact',
  template: `<div class="contact-page"><h2>Contact</h2></div>`,
  styles: [`.contact-page { min-height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }`]
})
export class ContactPage {}
