import { Injectable } from '@angular/core';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

@Injectable({ providedIn: 'root' })
export class PdfExportService {

  /**
   * Fångar ett HTML-element med html2canvas och skapar en PDF.
   * Väljer automatiskt landscape eller portrait baserat på aspektratio.
   */
  async exportToPdf(elementId: string, filename: string, title?: string): Promise<void> {
    const element = document.getElementById(elementId);
    if (!element) {
      console.error(`PdfExportService: element med id "${elementId}" hittades inte.`);
      return;
    }

    const canvas = await html2canvas(element, {
      backgroundColor: '#1a202c',
      scale: 1.5,
      useCORS: true,
      logging: false,
    });

    const imgWidth  = canvas.width;
    const imgHeight = canvas.height;
    const isLandscape = imgWidth > imgHeight;

    const orientation: 'l' | 'p' = isLandscape ? 'l' : 'p';
    const pdf = new jsPDF({ orientation, unit: 'mm', format: 'a4' });

    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();

    // Marginaler
    const marginTop    = 18;
    const marginBottom = 14;
    const marginSide   = 10;

    // Header
    const now = new Date();
    const datumTid = this.formatDatumTid(now);
    const headerTitle = title ? `MauserDB — ${title}` : 'MauserDB';

    pdf.setFillColor(26, 32, 44); // #1a202c
    pdf.rect(0, 0, pageW, 12, 'F');

    pdf.setFontSize(11);
    pdf.setTextColor(226, 232, 240); // #e2e8f0
    pdf.text(headerTitle, marginSide, 8);
    pdf.setFontSize(8);
    pdf.setTextColor(160, 174, 192); // #a0aec0
    pdf.text(datumTid, pageW - marginSide, 8, { align: 'right' });

    // Footer
    pdf.setFontSize(8);
    pdf.setTextColor(160, 174, 192);
    pdf.text(`Genererad ${datumTid}`, marginSide, pageH - 4);
    pdf.text('MauserDB Produktionssystem', pageW - marginSide, pageH - 4, { align: 'right' });

    // Bild
    const availableW = pageW - marginSide * 2;
    const availableH = pageH - marginTop - marginBottom;

    const scaleW = availableW / imgWidth;
    const scaleH = availableH / imgHeight;
    const scale  = Math.min(scaleW, scaleH);

    const finalW = imgWidth * scale;
    const finalH = imgHeight * scale;

    const xOffset = (pageW - finalW) / 2;

    const imgData = canvas.toDataURL('image/png');
    pdf.addImage(imgData, 'PNG', xOffset, marginTop, finalW, finalH);

    pdf.save(`${filename}.pdf`);
  }

  /**
   * Skapar en ren tabell-PDF direkt från data (utan screenshot).
   * Passar för tabelldata som ska exporteras snyggt.
   */
  exportTableToPdf(
    data: Record<string, unknown>[],
    columns: { key: string; header: string; width?: number }[],
    filename: string,
    title?: string
  ): void {
    const pdf = new jsPDF({ orientation: 'l', unit: 'mm', format: 'a4' });
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    const now   = new Date();
    const datumTid = this.formatDatumTid(now);
    const headerTitle = title ? `MauserDB — ${title}` : 'MauserDB';

    const marginSide   = 10;
    const marginTop    = 18;
    const marginBottom = 14;
    const rowH         = 8;
    const headerRowH   = 10;

    // Header-band
    pdf.setFillColor(26, 32, 44);
    pdf.rect(0, 0, pageW, 12, 'F');
    pdf.setFontSize(11);
    pdf.setTextColor(226, 232, 240);
    pdf.text(headerTitle, marginSide, 8);
    pdf.setFontSize(8);
    pdf.setTextColor(160, 174, 192);
    pdf.text(datumTid, pageW - marginSide, 8, { align: 'right' });

    // Kolumnbredder
    const totalCols = columns.length;
    const availableW = pageW - marginSide * 2;
    const colWidths = columns.map(c => c.width ?? availableW / totalCols);

    let y = marginTop;

    // Tabell-header
    pdf.setFillColor(45, 55, 72); // #2d3748
    pdf.rect(marginSide, y, availableW, headerRowH, 'F');
    pdf.setFontSize(9);
    pdf.setTextColor(226, 232, 240);
    pdf.setFont('helvetica', 'bold');

    let x = marginSide;
    for (let i = 0; i < columns.length; i++) {
      pdf.text(columns[i].header, x + 2, y + 7);
      x += colWidths[i];
    }
    y += headerRowH;

    // Tabell-rader
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(8);

    for (let rowIdx = 0; rowIdx < data.length; rowIdx++) {
      // Ny sida vid behov
      if (y + rowH > pageH - marginBottom) {
        pdf.addPage();
        y = marginTop;
        // Upprepa header
        pdf.setFillColor(45, 55, 72);
        pdf.rect(marginSide, y, availableW, headerRowH, 'F');
        pdf.setFontSize(9);
        pdf.setTextColor(226, 232, 240);
        pdf.setFont('helvetica', 'bold');
        let hx = marginSide;
        for (let i = 0; i < columns.length; i++) {
          pdf.text(columns[i].header, hx + 2, y + 7);
          hx += colWidths[i];
        }
        y += headerRowH;
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(8);
      }

      // Zebra-rand
      if (rowIdx % 2 === 0) {
        pdf.setFillColor(26, 32, 44);
      } else {
        pdf.setFillColor(30, 40, 55);
      }
      pdf.rect(marginSide, y, availableW, rowH, 'F');

      pdf.setTextColor(226, 232, 240);
      x = marginSide;
      for (let i = 0; i < columns.length; i++) {
        const val = data[rowIdx][columns[i].key];
        const text = val !== undefined && val !== null ? String(val) : '-';
        pdf.text(text, x + 2, y + 5.5);
        x += colWidths[i];
      }
      y += rowH;
    }

    // Footer
    pdf.setFontSize(8);
    pdf.setTextColor(160, 174, 192);
    pdf.text(`Genererad ${datumTid}`, marginSide, pageH - 4);
    pdf.text('MauserDB Produktionssystem', pageW - marginSide, pageH - 4, { align: 'right' });

    pdf.save(`${filename}.pdf`);
  }

  private formatDatumTid(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }
}
