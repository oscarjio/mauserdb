import { Component, Input } from '@angular/core';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './header.html',
  styleUrl: './header.css'
})
export class Header {
  @Input() logoUrl: string = 'https://www.noreko.com/wp-content/uploads/2023/07/Mauser_Packaging_Solutions_Grayscale.png';
  selectedMenu: string = 'Älvängen';

  onMenuChange(event: Event) {
    // Här kan du spara valet i localStorage eller annan logik
    localStorage.setItem('selectedMenu', this.selectedMenu);
  }

  ngOnInit() {
    const saved = localStorage.getItem('selectedMenu');
    if (saved) this.selectedMenu = saved;
  }
}
