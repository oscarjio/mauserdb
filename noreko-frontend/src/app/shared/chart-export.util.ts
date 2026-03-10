/**
 * chart-export.util.ts
 * Exportera Chart.js-grafer som PNG-bilder med titel och datumperiod.
 */

export interface ChartExportOptions {
  /** Grafens namn (visas i bilden och i filnamnet) */
  chartName: string;
  /** Startdatum (YYYY-MM-DD) */
  startDate?: string;
  /** Slutdatum (YYYY-MM-DD) */
  endDate?: string;
  /** Bakgrundsfarg, default #1a202c */
  bgColor?: string;
  /** Textfarg for titel, default #e2e8f0 */
  titleColor?: string;
  /** Textfarg for datumperiod, default #a0aec0 */
  subtitleColor?: string;
}

/**
 * Exportera ett canvas-element (Chart.js) som PNG-bild.
 * Skapar en temporar canvas med mork bakgrund, titel och datumperiod som header.
 *
 * @param canvas - HTMLCanvasElement fran Chart.js
 * @param options - Exportinstallningar
 */
export function exportChartAsPng(canvas: HTMLCanvasElement, options: ChartExportOptions): void {
  if (!canvas) return;

  const bgColor = options.bgColor || '#1a202c';
  const titleColor = options.titleColor || '#e2e8f0';
  const subtitleColor = options.subtitleColor || '#a0aec0';

  const headerHeight = 60;
  const padding = 16;
  const chartWidth = canvas.width;
  const chartHeight = canvas.height;
  const totalWidth = chartWidth + padding * 2;
  const totalHeight = chartHeight + headerHeight + padding * 2;

  // Skapa temporar canvas
  const exportCanvas = document.createElement('canvas');
  exportCanvas.width = totalWidth;
  exportCanvas.height = totalHeight;
  const ctx = exportCanvas.getContext('2d');
  if (!ctx) return;

  // Bakgrund
  ctx.fillStyle = bgColor;
  ctx.fillRect(0, 0, totalWidth, totalHeight);

  // Titel
  ctx.fillStyle = titleColor;
  ctx.font = 'bold 18px sans-serif';
  ctx.textBaseline = 'top';
  ctx.fillText(options.chartName, padding, padding);

  // Datumperiod
  if (options.startDate || options.endDate) {
    ctx.fillStyle = subtitleColor;
    ctx.font = '13px sans-serif';
    const periodText = options.startDate && options.endDate
      ? `Period: ${options.startDate} - ${options.endDate}`
      : options.startDate
        ? `Fran: ${options.startDate}`
        : `Till: ${options.endDate}`;
    ctx.fillText(periodText, padding, padding + 26);
  }

  // Rita ursprunglig graf
  ctx.drawImage(canvas, padding, headerHeight + padding);

  // Skapa nedladdningslanken
  const dataUrl = exportCanvas.toDataURL('image/png');
  const link = document.createElement('a');
  const safeName = options.chartName.replace(/[^a-zA-Z0-9åäöÅÄÖ_\- ]/g, '').replace(/\s+/g, '_');
  const parts = [safeName];
  if (options.startDate) parts.push(options.startDate);
  if (options.endDate) parts.push(options.endDate);
  link.download = parts.join('_') + '.png';
  link.href = dataUrl;
  link.click();
}
