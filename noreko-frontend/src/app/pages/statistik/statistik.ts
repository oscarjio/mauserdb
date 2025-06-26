import { Component } from '@angular/core';

@Component({
  standalone: true,
  selector: 'app-statistik',
  template: `<div class="statistik-page"><h2>Statistik</h2></div>`,
  styles: [`.statistik-page { min-height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }`]
})
export class StatistikPage {}
