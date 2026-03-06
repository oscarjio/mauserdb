import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [],
  templateUrl: './header.html',
  styleUrl: './header.css'
})
export class Header {
  @Input() logoUrl: string = 'https://www.noreko.com/wp-content/uploads/2023/07/Mauser_Packaging_Solutions_Grayscale.png';
  selectedMenu: string = 'Älvängen';
  loggedIn = false;
  user: any = null;
  showMenu = false;
}
