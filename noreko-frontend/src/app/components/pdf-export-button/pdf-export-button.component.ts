import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PdfExportService } from '../../services/pdf-export.service';

@Component({
  standalone: true,
  selector: 'app-pdf-export-button',
  imports: [CommonModule],
  templateUrl: './pdf-export-button.component.html',
  styleUrls: ['./pdf-export-button.component.css'],
})
export class PdfExportButtonComponent {
  @Input() targetElementId = '';
  @Input() filename = 'rapport';
  @Input() title = '';

  isLoading = false;

  constructor(private pdfService: PdfExportService) {}

  async exportPdf(): Promise<void> {
    if (this.isLoading || !this.targetElementId) return;
    this.isLoading = true;
    try {
      await this.pdfService.exportToPdf(
        this.targetElementId,
        this.filename,
        this.title || undefined
      );
    } finally {
      this.isLoading = false;
    }
  }
}
