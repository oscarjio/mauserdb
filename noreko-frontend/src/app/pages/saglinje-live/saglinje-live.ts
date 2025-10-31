import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe } from '@angular/common';

@Component({
  standalone: true,
  selector: 'app-saglinje-live',
  imports: [DatePipe],
  templateUrl: './saglinje-live.html',
  styleUrl: './saglinje-live.css'
})
export class SaglinjeLivePage implements OnInit, OnDestroy {
  now = new Date();
  intervalId: any;

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
    }, 1000);
  }

  ngOnDestroy() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
  }
}

