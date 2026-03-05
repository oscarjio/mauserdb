import { Component } from '@angular/core';
import { SharedSkiftrapportComponent, LineSkiftrapportConfig } from '../shared-skiftrapport/shared-skiftrapport';

@Component({
  standalone: true,
  selector: 'app-tvattlinje-skiftrapport',
  imports: [SharedSkiftrapportComponent],
  template: '<app-shared-skiftrapport [config]="config"></app-shared-skiftrapport>'
})
export class TvattlinjeSkiftrapportPage {
  config: LineSkiftrapportConfig = {
    line: 'tvattlinje',
    lineName: 'Tvättlinje',
    liveUrl: '/tvattlinje/live',
    themeColor: 'primary',
    accentHex: '#3182ce',
    emptyText: 'Tvättlinjen är ej i drift ännu. Rapporter visas när produktion startar.'
  };
}
