import { Component } from '@angular/core';

@Component({
  standalone: true,
  selector: 'app-about',
  template: `<div class="about-page"><h2>About</h2></div>`,
  styles: [`.about-page { min-height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }`]
})
export class AboutPage {}
