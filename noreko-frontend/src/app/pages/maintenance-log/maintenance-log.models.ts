export interface MaintenanceEntry {
  id: number;
  line: string;
  maintenance_type: string;
  title: string;
  description: string | null;
  start_time: string;
  duration_minutes: number | null;
  performed_by: string | null;
  cost_sek: number | null;
  status: string;
  created_by: number | null;
  created_at: string;
  equipment: string | null;
  downtime_minutes: number;
  resolved: number;
}

export interface MaintenanceStats {
  total_events: number;
  total_minutes: number;
  total_cost: number;
  akut_count: number;
  pagaende_count: number;
}

export interface EquipmentItem {
  id: number;
  namn: string;
  kategori: string;
  linje: string;
}

export interface EquipmentStat {
  namn: string;
  kategori: string;
  antal_handelser: number;
  total_driftstopp_min: number;
  snitt_driftstopp_min: number;
  total_kostnad: number;
  senaste_handelse: string | null;
}

export interface EquipmentSummary {
  total_downtime_min: number;
  total_cost: number;
  worst_equipment: string | null;
}

export interface KpiRow {
  equipment: string;
  antal_fel: number;
  total_stillestand_h: number;
  avg_mttr_h: number;
  avg_mtbf_dagar: number | null;
}

export interface ServiceInterval {
  id: number;
  maskin_namn: string;
  intervall_ibc: number;
  senaste_service_datum: string | null;
  senaste_service_ibc: number;
  ibc_sedan_service: number;
  kvar: number;
  procent_kvar: number;
  status: 'ok' | 'varning' | 'kritisk';
  skapad: string;
  uppdaterad: string;
}
