import { Component } from '@angular/core';
import { SharedSkiftrapportComponent, LineSkiftrapportConfig } from '../shared-skiftrapport/shared-skiftrapport';

@Component({
  standalone: true,
  selector: 'app-klassificeringslinje-skiftrapport',
  imports: [SharedSkiftrapportComponent],
  template: '<app-shared-skiftrapport [config]="config"></app-shared-skiftrapport>'
})
export class KlassificeringslinjeSkiftrapportPage {
  config: LineSkiftrapportConfig = {
    line: 'klassificeringslinje',
    lineName: 'Klassificeringslinje',
    liveUrl: '/klassificeringslinje/live',
    themeColor: 'success',
    accentHex: '#38a169',
    emptyText: 'Klassificeringslinje är ej i drift ännu. Rapporter visas när produktion startar.'
  };
}
