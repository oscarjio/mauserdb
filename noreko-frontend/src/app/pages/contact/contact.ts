import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  standalone: true,
  selector: 'app-contact',
  imports: [CommonModule],
  templateUrl: './contact.html',
  styleUrl: './contact.css'
})
export class ContactPage {
  contacts = [
    { name: 'IT-support', desc: 'Tekniska frågor om Mauserdb', icon: 'fa-headset', email: 'it@noreko.com' },
    { name: 'Produktion', desc: 'Frågor om produktionsdata och rapporter', icon: 'fa-industry', email: 'produktion@noreko.com' },
    { name: 'Administration', desc: 'Användarkonton och behörigheter', icon: 'fa-user-shield', email: 'admin@noreko.com' },
  ];
}
