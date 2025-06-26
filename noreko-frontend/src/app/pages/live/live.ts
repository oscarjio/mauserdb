import { Component } from '@angular/core';
import { NgIf } from '@angular/common';

@Component({
  standalone: true,
  selector: 'app-live',
  imports: [NgIf],
  template: `<div class="live-page"><h2>Live</h2><ng-container *ngIf="showBack"><button class='btn btn-outline-info go-back' (click)="goBack()">&larr; Gå tillbaka</button></ng-container></div>`,
  styles: [`.live-page { min-height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; } .go-back { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); }`]
})
export class LivePage {
  showBack = true;
  goBack() { window.location.href = '/'; }
}
