import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe } from '@angular/common';

@Component({
  standalone: true,
  selector: 'app-klassificeringslinje-live',
  imports: [DatePipe],
  templateUrl: './klassificeringslinje-live.html',
  styleUrl: './klassificeringslinje-live.css'
})
export class KlassificeringslinjeLivePage implements OnInit, OnDestroy {
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

