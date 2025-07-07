import { Component, Input } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { interval } from 'rxjs';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [FormsModule, HttpClientModule, CommonModule],
  templateUrl: './header.html',
  styleUrl: './header.css'
})
export class Header {
  @Input() logoUrl: string = 'https://www.noreko.com/wp-content/uploads/2023/07/Mauser_Packaging_Solutions_Grayscale.png';
  selectedMenu: string = 'Älvängen';
  loggedIn = false;
  user: any = null;
  showMenu = false;
  http = new HttpClient(null as any);


}
