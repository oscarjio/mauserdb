import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface RebotlingLiveStatsResponse {
  success: boolean;
  data: {
    rebotlingToday: number;
    rebotlingTarget: number;
    rebotlingThisHour: number;
    hourlyTarget: number;
    ibcToday: number;
    productionPercentage: number;
    nextLopnummer: number | null;
    utetemperatur: number | null;
  };
}

export interface LineStatusResponse {
  success: boolean;
  data: {
    running: boolean;
    on_rast?: boolean;
    lastUpdate: string | null;
  };
}

export interface RastEvent {
  datum: string;
  rast_status: number; // 1 = rast börjar, 0 = rast slutar
}

export interface RastStatusResponse {
  success: boolean;
  data: {
    on_rast: boolean;
    rast_minutes_today: number;
    rast_count_today: number;
    last_event: string | null;
    events: RastEvent[];
  };
}

export interface ProductionCycle {
  datum: string;
  ibc_count: number;
  produktion_procent: number;
  skiftraknare: number;
  cycle_time: number;  // Faktisk cykeltid i minuter
  target_cycle_time?: number;  // Mål cykeltid från produkt
}

export interface OnOffEvent {
  datum: string;
  running: boolean;
  runtime_today: number;
}

export interface DayStats {
  date: string;
  total_cycles: number;
  avg_production_percent: number;
  total_runtime_minutes: number;
  shifts_count: number;
}

export interface StatisticsResponse {
  success: boolean;
  data: {
    cycles: ProductionCycle[];
    onoff_events: OnOffEvent[];
    summary: {
      total_cycles: number;
      avg_production_percent: number;
      avg_cycle_time: number;
      target_cycle_time: number;
      total_runtime_hours: number;
      days_with_production: number;
    };
  };
}


export interface WeekdayStatsEntry {
  veckodag_nr: number;
  namn: string;
  antal_dagar: number;
  snitt_ibc: number;
  snitt_oee: number | null;
  max_ibc: number;
  min_ibc: number;
}

export interface WeekdayStatsResponse {
  success: boolean;
  veckodagar?: WeekdayStatsEntry[];
  dagar?: number;
  error?: string;
}

@Injectable({ providedIn: 'root' })
export class RebotlingService {
  constructor(private http: HttpClient) {}

  getLiveStats(): Observable<RebotlingLiveStatsResponse> {
    return this.http.get<RebotlingLiveStatsResponse>(
      '/noreko-backend/api.php?action=rebotling',
      { withCredentials: true }
    );
  }

  getRunningStatus(): Observable<LineStatusResponse> {
    return this.http.get<LineStatusResponse>(
      '/noreko-backend/api.php?action=rebotling&run=status',
      { withCredentials: true }
    );
  }

  getRastStatus(): Observable<RastStatusResponse> {
    return this.http.get<RastStatusResponse>(
      '/noreko-backend/api.php?action=rebotling&run=rast',
      { withCredentials: true }
    );
  }

  getStatistics(startDate: string, endDate: string): Observable<StatisticsResponse> {
    return this.http.get<StatisticsResponse>(
      `/noreko-backend/api.php?action=rebotling&run=statistics&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    );
  }

  getDayStats(date: string): Observable<any> {
    return this.http.get(
      `/noreko-backend/api.php?action=rebotling&run=day-stats&date=${date}`,
      { withCredentials: true }
    );
  }

  getHeatmap(days: number = 30): Observable<any> {
    return this.http.get<any>(
      `/noreko-backend/api.php?action=rebotling&run=heatmap&days=${days}`,
      { withCredentials: true }
    );
  }

  getCycleTrend(days: number = 30, granularity: string = 'day'): Observable<CycleTrendResponse> {
    return this.http.get<CycleTrendResponse>(
      `/noreko-backend/api.php?action=rebotling&run=cycle-trend&days=${days}&granularity=${granularity}`,
      { withCredentials: true }
    );
  }

  getProductionReport(period: string = 'week'): Observable<any> {
    return this.http.get(
      `/noreko-backend/api.php?action=rebotling&run=report&period=${period}`,
      { withCredentials: true }
    );
  }

  downloadReportCSV(period: string = 'week'): void {
    window.open(`/noreko-backend/api.php?action=rebotling&run=report&period=${period}&format=csv`, '_blank');
  }

  getWeekComparison(granularity: string = 'day'): Observable<WeekComparisonResponse> {
    return this.http.get<WeekComparisonResponse>(
      `/noreko-backend/api.php?action=rebotling&run=week-comparison&granularity=${granularity}`,
      { withCredentials: true }
    );
  }

  getOEETrend(days: number = 30, granularity: string = 'day'): Observable<OEETrendResponse> {
    return this.http.get<OEETrendResponse>(
      `/noreko-backend/api.php?action=rebotling&run=oee-trend&days=${days}&granularity=${granularity}`,
      { withCredentials: true }
    );
  }

  getBestShifts(limit: number = 10): Observable<BestShiftsResponse> {
    return this.http.get<BestShiftsResponse>(
      `/noreko-backend/api.php?action=rebotling&run=best-shifts&limit=${limit}`,
      { withCredentials: true }
    );
  }

  getExecDashboard(): Observable<ExecDashboardResponse> {
    return this.http.get<ExecDashboardResponse>(
      `/noreko-backend/api.php?action=rebotling&run=exec-dashboard`,
      { withCredentials: true }
    );
  }

  getOEE(period: string = 'today'): Observable<OEEResponse> {
    return this.http.get<OEEResponse>(
      `/noreko-backend/api.php?action=rebotling&run=oee&period=${period}`,
      { withCredentials: true }
    );
  }

  getCycleHistogram(date: string): Observable<CycleHistogramResponse> {
    return this.http.get<CycleHistogramResponse>(
      `/noreko-backend/api.php?action=rebotling&run=cycle-histogram&date=${date}`,
      { withCredentials: true }
    );
  }

  getSPC(days: number = 7): Observable<SPCResponse> {
    return this.http.get<SPCResponse>(
      `/noreko-backend/api.php?action=rebotling&run=spc&days=${days}`,
      { withCredentials: true }
    );
  }

  getBenchmarking(): Observable<BenchmarkingResponse> {
    return this.http.get<BenchmarkingResponse>(
      '/noreko-backend/api.php?action=rebotling&run=benchmarking',
      { withCredentials: true }
    );
  }

  getAnnotations(startDate: string, endDate: string): Observable<AnnotationsResponse> {
    return this.http.get<AnnotationsResponse>(
      `/noreko-backend/api.php?action=rebotling&run=annotations&start=${startDate}&end=${endDate}`,
      { withCredentials: true }
    );
  }

  getQualityTrend(days: number = 30): Observable<QualityTrendResponse> {
    return this.http.get<QualityTrendResponse>(
      `/noreko-backend/api.php?action=rebotling&run=quality-trend&days=${days}`,
      { withCredentials: true }
    );
  }

  getOeeWaterfall(days: number = 30): Observable<OeeWaterfallResponse> {
    return this.http.get<OeeWaterfallResponse>(
      `/noreko-backend/api.php?action=rebotling&run=oee-waterfall&days=${days}`,
      { withCredentials: true }
    );
  }

  getWeekdayStats(dagar: number = 90): Observable<WeekdayStatsResponse> {
    return this.http.get<WeekdayStatsResponse>(
      `/noreko-backend/api.php?action=rebotling&run=weekday-stats&dagar=${dagar}`,
      { withCredentials: true }
    );
  }
}

export interface CycleTrendResponse {
  success: boolean;
  granularity?: string;
  data?: {
    daily: { dag: string; label?: string; skiftraknare?: number; cycles: number; avg_runtime: number; avg_ibc_per_hour: number; total_ibc_ok: number; total_ibc_ej_ok: number; total_bur_ej_ok: number }[];
    moving_average: { dag: string; moving_avg: number }[];
    trend: 'increasing' | 'decreasing' | 'stable';
    avg_runtime: number;
    total_cycles: number;
    alert: { type: string; message: string; change_pct: number; current_avg: number; previous_avg: number } | null;
  };
  error?: string;
}

export interface WeekComparisonDay {
  date: string;
  label?: string;
  skiftraknare?: number;
  ibc_ok: number;
  cykler: number;
}

export interface WeekComparisonResponse {
  success: boolean;
  data?: {
    this_week: WeekComparisonDay[];
    prev_week: WeekComparisonDay[];
    all_days: WeekComparisonDay[];
  };
  error?: string;
}

export interface OEETrendDay {
  date: string;
  label?: string;
  skiftraknare?: number;
  oee: number;
  availability: number;
  performance: number;
  quality: number;
  ibc_ok: number;
}

export interface OEETrendResponse {
  success: boolean;
  data?: OEETrendDay[];
  error?: string;
}

export interface BestShift {
  rank: number;
  skiftraknare: number;
  dag: string;
  cykler: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  kvalitet_pct: number;
  runtime_h: number;
  avg_kvalitet: number;
}

export interface BestShiftsResponse {
  success: boolean;
  data?: BestShift[];
  error?: string;
}

export interface OEEResponse {
  success: boolean;
  data?: {
    period: string;
    oee: number;
    availability: number;
    performance: number;
    quality: number;
    total_ibc: number;
    good_ibc: number;
    rejected_ibc: number;
    runtime_hours: number;
    rast_hours: number;
    operating_hours: number;
    planned_hours: number;
    cycles: number;
    world_class_benchmark: number;
  };
  error?: string;
}

export interface ExecDashboardOperator {
  id: number;
  name: string;
  position: string;
  ibc_h: number;
  kvalitet: number;
  bonus: number;
}

export interface ExecDashboardResponse {
  success: boolean;
  data?: {
    today: {
      ibc: number;
      target: number;
      pct: number;
      forecast: number;
      oee_today: number;
      oee_yesterday: number;
      rate_per_h: number;
      shift_start: string;
    };
    week: {
      this_week_ibc: number;
      prev_week_ibc: number;
      week_diff_pct: number;
      quality_pct: number;
      oee_pct: number;
      best_operator: { id: number; name: string; ibc_h: number } | null;
    };
    days7: { date: string; ibc: number; target: number }[];
    last_shift_operators: ExecDashboardOperator[];
  };
  error?: string;
}

export interface CycleHistogramBucket {
  label: string;
  count: number;
}

export interface CycleHistogramResponse {
  success: boolean;
  data?: {
    date: string;
    buckets: CycleHistogramBucket[];
    stats: {
      n: number;
      snitt: number;
      p50: number;
      p90: number;
      p95: number;
    };
  };
  error?: string;
}

export interface SPCPoint {
  label: string;
  ibc_per_hour: number;
}

export interface SPCResponse {
  success: boolean;
  data?: {
    points: SPCPoint[];
    mean: number;
    stddev: number;
    ucl: number;
    lcl: number;
    n: number;
    days: number;
  };
  error?: string;
}

export interface BenchmarkingWeek {
  week_label: string;
  ibc_total: number;
  ibc_per_day: number;
  avg_quality: number;
  avg_oee: number | null;
  days_active?: number;
}

export interface BenchmarkingTopWeek {
  week_label: string;
  yr: number;
  wk: number;
  ibc_total: number;
  avg_quality: number;
  avg_oee: number | null;
  days_active: number;
}

export interface BenchmarkingMonthly {
  month: string;
  ibc_total: number;
  avg_quality: number;
  avg_oee: number | null;
}

export interface BenchmarkingBestDay {
  date: string;
  ibc_total: number;
  quality: number;
}

export interface BenchmarkingResponse {
  success: boolean;
  current_week?: BenchmarkingWeek;
  best_week_ever?: BenchmarkingWeek;
  best_day_ever?: BenchmarkingBestDay;
  top_weeks?: BenchmarkingTopWeek[];
  monthly_totals?: BenchmarkingMonthly[];
  error?: string;
}

export interface ChartAnnotation {
  date: string;
  dateShort: string;
  type: 'stopp' | 'low_production' | 'audit';
  label: string;
}

export interface AnnotationsResponse {
  success: boolean;
  annotations?: { date: string; type: string; label: string }[];
  error?: string;
}

export interface QualityTrendDay {
  date: string;
  quality_pct: number | null;
  rolling_avg: number | null;
  ibc_ok: number;
  ibc_totalt: number;
}

export interface QualityTrendResponse {
  success: boolean;
  days?: QualityTrendDay[];
  kpi?: {
    avg: number | null;
    min: number | null;
    max: number | null;
    trend: 'up' | 'down' | 'stable';
  };
  error?: string;
}

export interface OeeWaterfallResponse {
  success: boolean;
  availability?: number;
  performance?: number;
  quality?: number;
  oee?: number;
  availability_loss?: number;
  performance_loss?: number;
  quality_loss?: number;
  runtime_h?: number;
  rast_h?: number;
  ibc_ok?: number;
  ibc_ej_ok?: number;
  error?: string;
}



