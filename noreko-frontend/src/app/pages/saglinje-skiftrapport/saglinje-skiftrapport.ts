import { Component } from '@angular/core';
import { SharedSkiftrapportComponent, LineSkiftrapportConfig } from '../shared-skiftrapport/shared-skiftrapport';

@Component({
  standalone: true,
  selector: 'app-saglinje-skiftrapport',
  imports: [SharedSkiftrapportComponent],
  template: '<app-shared-skiftrapport [config]="config"></app-shared-skiftrapport>'
})
export class SaglinjeSkiftrapportPage {
  config: LineSkiftrapportConfig = {
    line: 'saglinje',
    lineName: 'Såglinje',
    liveUrl: '/saglinje/live',
    themeColor: 'warning',
    accentHex: '#d69e2e',
    emptyText: 'Såglinje är ej i drift ännu. Rapporter visas när produktion startar.'
  };
  trackByIndex(index: number): number { return index; }
}
