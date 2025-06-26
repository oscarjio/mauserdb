import { Component } from '@angular/core';

@Component({
  standalone: true,
  selector: 'app-skiftrapport',
  template: `<div class="skiftrapport-page"><h2>Skiftrapport</h2></div>`,
  styles: [`.skiftrapport-page { min-height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }`]
})
export class SkiftrapportPage {}
