/** Shared formatting and badge helpers for maintenance-log child components */

export function formatDuration(minutes: number | null | undefined): string {
  if (minutes === null || minutes === undefined) return 'Pågående';
  if (+minutes === 0) return 'Pågående';
  const m = +minutes;
  if (m < 60) return `${m} min`;
  const h = Math.floor(m / 60);
  const rem = m % 60;
  return rem > 0 ? `${h}h ${rem}min` : `${h}h`;
}

export function formatCost(cost: number | null | undefined): string {
  if (cost === null || cost === undefined || +cost === 0) return '';
  return new Intl.NumberFormat('sv-SE', { style: 'currency', currency: 'SEK', maximumFractionDigits: 0 }).format(+cost);
}

export function formatDateTime(dt: string | null): string {
  if (!dt) return '';
  return dt.replace('T', ' ').slice(0, 16);
}

export function getLineBadgeClass(line: string): string {
  const map: Record<string, string> = {
    rebotling: 'badge-line-rebotling',
    tvattlinje: 'badge-line-tvattlinje',
    saglinje: 'badge-line-saglinje',
    klassificeringslinje: 'badge-line-klassificeringslinje',
    allmant: 'badge-line-allmant'
  };
  return map[line] ?? 'badge-line-allmant';
}

export function getLineLabel(line: string): string {
  const map: Record<string, string> = {
    rebotling: 'Rebotling',
    tvattlinje: 'Tvättlinje',
    saglinje: 'Såglinje',
    klassificeringslinje: 'Klassificeringslinje',
    allmant: 'Allmänt'
  };
  return map[line] ?? line;
}

export function getTypeBadgeClass(type: string): string {
  const map: Record<string, string> = {
    akut: 'badge-type-akut',
    planerat: 'badge-type-planerat',
    inspektion: 'badge-type-inspektion',
    kalibrering: 'badge-type-kalibrering',
    rengoring: 'badge-type-rengoring',
    ovrigt: 'badge-type-ovrigt'
  };
  return map[type] ?? 'badge-type-ovrigt';
}

export function getTypeLabel(type: string): string {
  const map: Record<string, string> = {
    planerat: 'Planerat',
    akut: 'Akut',
    inspektion: 'Inspektion',
    kalibrering: 'Kalibrering',
    rengoring: 'Rengöring',
    ovrigt: 'Övrigt'
  };
  return map[type] ?? type;
}

export function getStatusBadgeClass(status: string): string {
  const map: Record<string, string> = {
    planerat: 'badge-status-planerat',
    pagaende: 'badge-status-pagaende',
    klart: 'badge-status-klart',
    avbokat: 'badge-status-avbokat'
  };
  return map[status] ?? 'badge-status-klart';
}

export function getStatusLabel(status: string): string {
  const map: Record<string, string> = {
    planerat: 'Planerat',
    pagaende: 'Pågående',
    klart: 'Klart',
    avbokat: 'Avbokat'
  };
  return map[status] ?? status;
}

export function getKategoriBadgeClass(kategori: string): string {
  const map: Record<string, string> = {
    maskin: 'badge-kategori-maskin',
    transport: 'badge-kategori-transport',
    verktyg: 'badge-kategori-verktyg',
    infrastruktur: 'badge-kategori-infrastruktur',
    'övrigt': 'badge-kategori-ovrigt'
  };
  return map[kategori] ?? 'badge-kategori-ovrigt';
}

export function getKategoriLabel(kategori: string): string {
  const map: Record<string, string> = {
    maskin: 'Maskin',
    transport: 'Transport',
    verktyg: 'Verktyg',
    infrastruktur: 'Infrastruktur',
    'övrigt': 'Övrigt'
  };
  return map[kategori] ?? kategori;
}

/** Shared CSS styles used by multiple child components */
export const SHARED_STYLES = `
  .text-orange { color: #ed8936 !important; }
  .cost-item { color: #ecc94b; }
  .meta-downtime { color: #fc8181; }

  /* KPI cards */
  .kpi-card {
    background: #2d3748;
    border-radius: 10px;
    padding: 1.2rem 1rem;
    text-align: center;
    border: 1px solid #3d4f6b;
    position: relative;
    transition: box-shadow 0.2s;
  }
  .kpi-card.kpi-alert {
    border-color: #ed8936;
    box-shadow: 0 0 0 2px rgba(237,137,54,0.25);
  }
  .kpi-icon {
    font-size: 1.4rem;
    margin-bottom: 0.4rem;
  }
  .kpi-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: #e2e8f0;
    line-height: 1.2;
  }
  .kpi-value-sm { font-size: 1.1rem; }
  .kpi-label {
    font-size: 0.75rem;
    color: #a0aec0;
    margin-top: 0.2rem;
  }

  /* Empty state */
  .empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #718096;
  }

  /* Statistics table */
  .stats-table-wrap {
    background: #2d3748;
    border-radius: 10px;
    border: 1px solid #3d4f6b;
    overflow: hidden;
  }
  .table-stats {
    margin-bottom: 0;
    background: transparent;
    color: #e2e8f0;
    font-size: 0.875rem;
  }
  .table-stats thead tr {
    background: #1a202c;
    border-bottom: 1px solid #4a5568;
  }
  .table-stats thead th {
    color: #a0aec0;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1rem;
    border: none;
    white-space: nowrap;
  }
  .table-stats tbody tr {
    border-bottom: 1px solid #3d4f6b;
    transition: background 0.15s;
  }
  .table-stats tbody tr:last-child { border-bottom: none; }
  .table-stats tbody tr:hover { background: rgba(99,179,237,0.05); }
  .table-stats tbody td {
    padding: 0.7rem 1rem;
    border: none;
    vertical-align: middle;
  }
  .sortable { cursor: pointer; user-select: none; }
  .sortable:hover { color: #e2e8f0; }

  /* Filter bar */
  .filter-bar {
    background: #2d3748;
    border-radius: 10px;
    padding: 1rem;
    border: 1px solid #3d4f6b;
  }
  .filter-label {
    font-size: 0.75rem;
    color: #a0aec0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.3rem;
    display: block;
  }
  .filter-select {
    background: #1a202c;
    border: 1px solid #4a5568;
    color: #e2e8f0;
    font-size: 0.875rem;
  }
  .filter-select:focus {
    background: #1a202c;
    border-color: #63b3ed;
    color: #e2e8f0;
    box-shadow: 0 0 0 2px rgba(99,179,237,0.25);
  }

  /* Badges - line */
  .line-badge { font-size: 0.7rem; letter-spacing: 0.03em; }
  .badge-line-rebotling { background: #2b6cb0; color: #bee3f8; }
  .badge-line-tvattlinje { background: #276749; color: #c6f6d5; }
  .badge-line-saglinje { background: #744210; color: #fefcbf; }
  .badge-line-klassificeringslinje { background: #553c9a; color: #e9d8fd; }
  .badge-line-allmant { background: #4a5568; color: #e2e8f0; }

  /* Badges - type */
  .type-badge { font-size: 0.7rem; }
  .badge-type-akut { background: #c53030; color: #fed7d7; }
  .badge-type-planerat { background: #2b6cb0; color: #bee3f8; }
  .badge-type-inspektion { background: #7b341e; color: #fbd38d; }
  .badge-type-kalibrering { background: #086f83; color: #c4f1f9; }
  .badge-type-rengoring { background: #276749; color: #c6f6d5; }
  .badge-type-ovrigt { background: #4a5568; color: #e2e8f0; }

  /* Badges - status */
  .status-badge { font-size: 0.7rem; }
  .badge-status-planerat { background: #2b6cb0; color: #bee3f8; }
  .badge-status-pagaende { background: #c05621; color: #fed7aa; }
  .badge-status-klart { background: #276749; color: #c6f6d5; }
  .badge-status-avbokat { background: #4a5568; color: #a0aec0; }

  /* Badges - equipment & resolved */
  .equipment-badge { background: #2c4a6e; color: #90cdf4; font-size: 0.7rem; }
  .resolved-badge { background: #22543d; color: #9ae6b4; font-size: 0.7rem; }

  /* Badges - kategori */
  .kategori-badge { font-size: 0.7rem; }
  .badge-kategori-maskin { background: #2b4c8c; color: #90cdf4; }
  .badge-kategori-transport { background: #276749; color: #c6f6d5; }
  .badge-kategori-verktyg { background: #744210; color: #fbd38d; }
  .badge-kategori-infrastruktur { background: #553c9a; color: #e9d8fd; }
  .badge-kategori-ovrigt { background: #4a5568; color: #e2e8f0; }

  /* Modal styles */
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    padding: 1rem;
  }
  .modal-panel {
    background: #2d3748;
    border-radius: 14px;
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid #4a5568;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
  }
  .modal-header-custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #4a5568;
    color: #e2e8f0;
  }
  .modal-body-custom { padding: 1.5rem; }
  .btn-close-custom {
    background: none;
    border: none;
    color: #a0aec0;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    transition: color 0.2s, background 0.2s;
  }
  .btn-close-custom:hover {
    color: #e2e8f0;
    background: rgba(255,255,255,0.1);
  }

  /* Dark form controls */
  .form-label-dark {
    color: #a0aec0;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.3rem;
  }
  .form-control-dark,
  .form-select-dark {
    background: #1a202c;
    border: 1px solid #4a5568;
    color: #e2e8f0;
    font-size: 0.9rem;
  }
  .form-control-dark:focus,
  .form-select-dark:focus {
    background: #1a202c;
    border-color: #63b3ed;
    color: #e2e8f0;
    box-shadow: 0 0 0 2px rgba(99,179,237,0.25);
  }
  .form-control-dark::placeholder { color: #718096; }
  .form-control-dark option,
  .form-select-dark option { background: #2d3748; }
  .form-text { font-size: 0.75rem; }

  /* Dark checkbox */
  .form-check-dark .form-check-input {
    background-color: #1a202c;
    border-color: #4a5568;
  }
  .form-check-dark .form-check-input:checked {
    background-color: #38a169;
    border-color: #38a169;
  }
  .form-check-dark .form-check-label { margin-bottom: 0; }

  /* Button actions */
  .btn-action {
    width: 30px;
    height: 30px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 0.8rem;
  }
  .btn-edit {
    background: rgba(99,179,237,0.15);
    border: 1px solid rgba(99,179,237,0.4);
    color: #63b3ed;
  }
  .btn-edit:hover {
    background: rgba(99,179,237,0.3);
    color: #63b3ed;
  }
  .btn-delete {
    background: rgba(229,62,62,0.15);
    border: 1px solid rgba(229,62,62,0.4);
    color: #fc8181;
  }
  .btn-delete:hover {
    background: rgba(229,62,62,0.3);
    color: #fc8181;
  }
  .btn-service-reset {
    background: rgba(72,187,120,0.15);
    border: 1px solid rgba(72,187,120,0.4);
    color: #48bb78;
  }
  .btn-service-reset:hover {
    background: rgba(72,187,120,0.3);
    color: #48bb78;
  }
`;
