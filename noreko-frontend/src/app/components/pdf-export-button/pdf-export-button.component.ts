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

  exportError = false;

  async exportPdf(): Promise<void> {
    if (this.isLoading || !this.targetElementId) return;
    this.isLoading = true;
    this.exportError = false;
    try {
      await this.pdfService.exportToPdf(
        this.targetElementId,
        this.filename,
        this.title || undefined
      );
    } catch (err) {
      console.error('PDF-export misslyckades:', err);
      this.exportError = true;
    } finally {
      this.isLoading = false;
    }
  }
}
