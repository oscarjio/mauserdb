import { Component, Input } from '@angular/core';
import { NgIf } from '@angular/common';

@Component({
  selector: 'app-header',
  imports: [NgIf],
  templateUrl: './header.html',
  styleUrl: './header.css'
})
export class Header {
  @Input() logoUrl: string = 'https://www.noreko.com/wp-content/uploads/2023/07/Mauser_Packaging_Solutions_Grayscale.png';
}
