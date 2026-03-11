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

  getHeatmap(days: number = 30, fromDate?: string, toDate?: string): Observable<any> {
    let url: string;
    if (fromDate && toDate) {
      url = `/noreko-backend/api.php?action=rebotling&run=heatmap&from_date=${fromDate}&to_date=${toDate}`;
    } else {
      url = `/noreko-backend/api.php?action=rebotling&run=heatmap&days=${days}`;
    }
    return this.http.get<any>(url, { withCredentials: true });
  }

  getCycleTrend(days: number = 30, granularity: string = 'day', fromDate?: string, toDate?: string): Observable<CycleTrendResponse> {
    let url: string;
    if (fromDate && toDate) {
      url = `/noreko-backend/api.php?action=rebotling&run=cycle-trend&from_date=${fromDate}&to_date=${toDate}&granularity=${granularity}`;
    } else {
      url = `/noreko-backend/api.php?action=rebotling&run=cycle-trend&days=${days}&granularity=${granularity}`;
    }
    return this.http.get<CycleTrendResponse>(url, { withCredentials: true });
  }

  getWeekComparison(granularity: string = 'day'): Observable<WeekComparisonResponse> {
    return this.http.get<WeekComparisonResponse>(
      `/noreko-backend/api.php?action=rebotling&run=week-comparison&granularity=${granularity}`,
      { withCredentials: true }
    );
  }

  getOEETrend(days: number = 30, granularity: string = 'day', fromDate?: string, toDate?: string): Observable<OEETrendResponse> {
    let url: string;
    if (fromDate && toDate) {
      url = `/noreko-backend/api.php?action=rebotling&run=oee-trend&from_date=${fromDate}&to_date=${toDate}&granularity=${granularity}`;
    } else {
      url = `/noreko-backend/api.php?action=rebotling&run=oee-trend&days=${days}&granularity=${granularity}`;
    }
    return this.http.get<OEETrendResponse>(url, { withCredentials: true });
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

  getCycleByOperator(startDate: string, endDate: string): Observable<CycleByOperatorResponse> {
    return this.http.get<CycleByOperatorResponse>(
      `/noreko-backend/api.php?action=rebotling&run=cycle-by-operator&start_date=${startDate}&end_date=${endDate}`,
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

  getManualAnnotations(startDate: string, endDate: string, typ?: string): Observable<ManualAnnotationsResponse> {
    let url = `/noreko-backend/api.php?action=rebotling&run=annotations-list&start=${startDate}&end=${endDate}`;
    if (typ) url += `&typ=${typ}`;
    return this.http.get<ManualAnnotationsResponse>(url, { withCredentials: true });
  }

  createManualAnnotation(data: { datum: string; typ: string; titel: string; beskrivning: string }): Observable<any> {
    const body = new URLSearchParams();
    body.set('datum', data.datum);
    body.set('typ', data.typ);
    body.set('titel', data.titel);
    body.set('beskrivning', data.beskrivning);
    return this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=annotation-create',
      body.toString(),
      { withCredentials: true, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
    );
  }

  deleteManualAnnotation(id: number): Observable<any> {
    const body = new URLSearchParams();
    body.set('id', String(id));
    return this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=annotation-delete',
      body.toString(),
      { withCredentials: true, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
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

  getProductionEvents(start: string, end: string): Observable<ProductionEventsResponse> {
    return this.http.get<ProductionEventsResponse>(
      `/noreko-backend/api.php?action=rebotling&run=events&start=${start}&end=${end}`,
      { withCredentials: true }
    );
  }

  addProductionEvent(event: { event_date: string; title: string; event_type: string; description: string }): Observable<any> {
    const body = new URLSearchParams();
    body.set('event_date', event.event_date);
    body.set('title', event.title);
    body.set('event_type', event.event_type);
    body.set('description', event.description);
    return this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=add-event',
      body.toString(),
      { withCredentials: true, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
    );
  }

  deleteProductionEvent(id: number): Observable<any> {
    const body = new URLSearchParams();
    body.set('id', String(id));
    return this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=delete-event',
      body.toString(),
      { withCredentials: true, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
    );
  }

  getStoppageAnalysis(days: number = 30): Observable<StoppageAnalysisResponse> {
    return this.http.get<StoppageAnalysisResponse>(
      `/noreko-backend/api.php?action=rebotling&run=stoppage-analysis&days=${days}`,
      { withCredentials: true }
    );
  }

  getParetoStoppage(days: number = 30): Observable<any> {
    return this.http.get<any>(
      `/noreko-backend/api.php?action=rebotling&run=pareto-stoppage&days=${days}`,
      { withCredentials: true }
    );
  }

  getRealtimeOee(period: string = 'today'): Observable<RealtimeOeeResponse> {
    return this.http.get<RealtimeOeeResponse>(
      `/noreko-backend/api.php?action=rebotling&run=realtime-oee&period=${period}`,
      { withCredentials: true }
    );
  }

  getStopCauseDrilldown(cause: string, days: number = 30): Observable<any> {
    return this.http.get<any>(
      `/noreko-backend/api.php?action=rebotling&run=stop-cause-drilldown&cause=${encodeURIComponent(cause)}&days=${days}`,
      { withCredentials: true }
    );
  }

  getPersonalBests(): Observable<PersonalBestsResponse> {
    return this.http.get<PersonalBestsResponse>(
      '/noreko-backend/api.php?action=rebotling&run=personal-bests',
      { withCredentials: true }
    );
  }

  getMonthlyLeaders(months: number = 12): Observable<MonthlyLeadersResponse> {
    return this.http.get<MonthlyLeadersResponse>(
      `/noreko-backend/api.php?action=rebotling&run=monthly-leaders&months=${months}`,
      { withCredentials: true }
    );
  }


  getHourlyRhythm(days: number = 30): Observable<any> {
    return this.http.get<any>(
      `/noreko-backend/api.php?action=rebotling&run=hourly-rhythm&days=${days}`,
      { withCredentials: true }
    );
  }

  getHallOfFameDays(): Observable<HallOfFameDaysResponse> {
    return this.http.get<HallOfFameDaysResponse>(
      '/noreko-backend/api.php?action=rebotling&run=hall-of-fame',
      { withCredentials: true }
    );
  }

  getRejectionAnalysis(days: number = 30): Observable<RejectionAnalysisResponse> {
    return this.http.get<RejectionAnalysisResponse>(
      `/noreko-backend/api.php?action=rebotling&run=rejection-analysis&days=${days}`,
      { withCredentials: true }
    );
  }

  getMaintenanceStats(): Observable<MaintenanceStatsResponse> {
    return this.http.get<MaintenanceStatsResponse>(
      '/noreko-backend/api.php?action=maintenance&run=stats',
      { withCredentials: true }
    );
  }

  getFeedbackSummary(): Observable<FeedbackSummaryResponse> {
    return this.http.get<FeedbackSummaryResponse>(
      '/noreko-backend/api.php?action=feedback&run=summary',
      { withCredentials: true }
    );
  }

  getStaffingWarning(): Observable<StaffingWarningResponse> {
    return this.http.get<StaffingWarningResponse>(
      '/noreko-backend/api.php?action=rebotling&run=staffing-warning',
      { withCredentials: true }
    );
  }

  getMonthlyStopSummary(month: string): Observable<MonthlyStopSummaryResponse> {
    return this.http.get<MonthlyStopSummaryResponse>(
      `/noreko-backend/api.php?action=rebotling&run=monthly-stop-summary&month=${month}`,
      { withCredentials: true }
    );
  }

  getProductionRate(): Observable<ProductionRateResponse> {
    return this.http.get<ProductionRateResponse>(
      '/noreko-backend/api.php?action=rebotling&run=production-rate',
      { withCredentials: true }
    );
  }

  // ---- Alert Thresholds ----
  saveAlertThresholds(thresholds: any): Observable<any> {
    return this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=save-alert-thresholds',
      thresholds,
      { withCredentials: true }
    );
  }

  // ---- Notification Settings ----
  saveNotificationSettings(settings: any): Observable<any> {
    return this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=save-notification-settings',
      settings,
      { withCredentials: true }
    );
  }

  getWeeklySummary(week: string): Observable<WeeklySummaryResponse> {
    return this.http.get<WeeklySummaryResponse>(
      `/noreko-backend/api.php?action=rebotling&run=weekly-summary-email&week=${week}`,
      { withCredentials: true }
    );
  }

  sendWeeklySummary(week: string): Observable<SendWeeklySummaryResponse> {
    return this.http.post<SendWeeklySummaryResponse>(
      '/noreko-backend/api.php?action=rebotling&run=send-weekly-summary',
      JSON.stringify({ week }),
      { withCredentials: true, headers: { 'Content-Type': 'application/json' } }
    );
  }

  // ---- Månadsrapport ----
  getMonthlyReport(year: number, month: number): Observable<MonthlyReportResponse> {
    const m = `${year}-${String(month).padStart(2, '0')}`;
    return this.http.get<MonthlyReportResponse>(
      `/noreko-backend/api.php?action=rebotling&run=monthly-report&month=${m}`,
      { withCredentials: true }
    );
  }

  getMonthCompare(year: number, month: number): Observable<MonthCompareResponse> {
    const m = `${year}-${String(month).padStart(2, '0')}`;
    return this.http.get<MonthCompareResponse>(
      `/noreko-backend/api.php?action=rebotling&run=month-compare&month=${m}`,
      { withCredentials: true }
    );
  }

  getProductionGoalProgress(period: string = 'today'): Observable<ProductionGoalProgressResponse> {
    return this.http.get<ProductionGoalProgressResponse>(
      `/noreko-backend/api.php?action=rebotling&run=production-goal-progress&period=${period}`,
      { withCredentials: true }
    );
  }

  setProductionGoal(periodType: string, targetCount: number): Observable<any> {
    return this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=set-production-goal',
      JSON.stringify({ period_type: periodType, target_count: targetCount }),
      { withCredentials: true, headers: { 'Content-Type': 'application/json' } }
    );
  }

}

export interface PersonalBestOperator {
  op_number: number;
  namn: string;
  initialer: string;
  best_ibc_h: number;
  best_kvalitet: number;
  pct_of_record: number;
  total_skift: number;
  best_day_ibc: number;
  best_day_date: string | null;
  best_week_ibc: number;
  best_month_ibc: number;
}

export interface PersonalBestsResponse {
  success: boolean;
  data?: {
    operators: PersonalBestOperator[];
    team_record_ibc_h: number;
    team_best_day: number;
    team_best_week: number;
    team_best_month: number;
  };
  error?: string;
}

export interface HallOfFameDayEntry {
  rank: number;
  date: string;
  ibc_total: number;
  avg_quality: number;
  operators: string[];
}

export interface HallOfFameDaysResponse {
  success: boolean;
  data?: HallOfFameDayEntry[];
  error?: string;
}

export interface MonthlyLeaderEntry {
  manad: string;
  total_ibc: number;
  avg_oee: number;
  top_ibc_h: number;
}

export interface MonthlyLeadersResponse {
  success: boolean;
  data?: MonthlyLeaderEntry[];
  error?: string;
}

export interface StoppageDayEntry {
  dag: string;
  total_minuter: number;
  antal: number;
  kategorier: { [category: string]: number };
}

export interface StoppageCategoryEntry {
  category: string;
  antal: number;
  total_min: number;
}

export interface StoppageReasonEntry {
  name: string;
  category: string;
  antal: number;
  total_min: number;
  snitt_min: number;
}

export interface StoppageAnalysisResponse {
  success: boolean;
  empty?: boolean;
  reason?: string;
  by_day: StoppageDayEntry[];
  by_category: StoppageCategoryEntry[];
  top_reasons: StoppageReasonEntry[];
  total_events: number;
  total_minutes: number;
  days: number;
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

export interface ProductionEvent {
  id: number;
  event_date: string;
  title: string;
  description: string;
  event_type: 'underhall' | 'ny_operator' | 'mal_andring' | 'rekord' | 'ovrigt';
}

export interface ProductionEventsResponse {
  success: boolean;
  events?: ProductionEvent[];
  error?: string;
}




export interface CycleByOperatorEntry {
  op_id: number;
  namn: string;
  initialer: string;
  antal_skift: number;
  snitt_cykel_sek: number;
  bast_cykel_sek: number;
  samst_cykel_sek: number;
  median_min: number;
  min_min: number;
  max_min: number;
  p90_min: number;
  stddev_min: number;
  total_ibc: number;
  // Beräknat i frontend
  vs_team_snitt?: number;
}

export interface CycleByOperatorResponse {
  success: boolean;
  start_date?: string;
  end_date?: string;
  data?: CycleByOperatorEntry[];
  error?: string;
}

export interface RejectionTrendDay {
  datum: string;
  kvalitet_pct: number | null;
  glidande_snitt: number | null;
  ibc_ok: number;
  ibc_kasserade: number;
  ibc_totalt: number;
}

export interface RejectionParetoItem {
  id: number;
  namn: string;
  antal: number;
  pct: number;
  kumulativ_pct: number;
  prev_antal?: number;
  trend?: 'up' | 'down' | 'stable';
}

export interface RejectionAnalysisResponse {
  success: boolean;
  days?: number;
  kpi?: {
    kvalitet_idag: number | null;
    kvalitet_vecka: number | null;
    kasserade_idag: number;
    trend_vs_forra_veckan: 'up' | 'down' | 'stable';
    trend_diff: number | null;
  };
  trend?: RejectionTrendDay[];
  pareto?: RejectionParetoItem[];
  has_pareto_data?: boolean;
  total_kassation?: number;
  error?: string;
}

export interface MaintenanceStatsResponse {
  success: boolean;
  stats?: {
    total_events: number;
    total_minutes: number;
    total_cost: number;
    akut_count: number;
    pagaende_count: number;
  };
  error?: string;
}

export interface FeedbackSummaryDayEntry {
  datum: string;
  snitt: number;
  antal: number;
}

export interface FeedbackSummaryResponse {
  success: boolean;
  avg_stamning: number | null;
  total?: number;
  per_dag?: FeedbackSummaryDayEntry[];
  error?: string;
}

export interface StaffingWarningShift {
  skift_nr: number;
  antal_ops: number;
}

export interface StaffingWarningDay {
  datum: string;
  dag_namn: string;
  underbemanning: StaffingWarningShift[];
}

export interface StaffingWarningResponse {
  success: boolean;
  warnings?: StaffingWarningDay[];
  min_operators?: number;
  error?: string;
}

export interface MonthlyStopSummaryItem {
  orsak: string;
  total_min: number;
  antal: number;
  pct: number;
}

export interface MonthlyStopSummaryResponse {
  success: boolean;
  items?: MonthlyStopSummaryItem[];
  error?: string;
}

export interface ProductionRateResponse {
  success: boolean;
  data?: {
    avg_ibc_per_day_7d: number;
    avg_ibc_per_day_30d: number;
    avg_ibc_per_day_90d: number;
    dag_mal: number;
  };
  error?: string;
}

export interface WeeklySummaryOperator {
  id: number;
  name: string;
  ibc_total: number;
  ibc_h: number;
  kvalitet: number;
  bonus_tier: string;
  antal_skift: number;
}

export interface WeeklySummaryStop {
  orsak: string;
  category: string;
  antal: number;
  total_min: number;
}

export interface WeeklySummaryData {
  week: string;
  start_date: string;
  end_date: string;
  total_ibc: number;
  prev_ibc: number;
  ibc_diff_pct: number;
  avg_oee: number;
  prev_oee: number;
  oee_diff: number;
  oee_trend: 'up' | 'down' | 'stable';
  kvalitet: number;
  best_day: { date: string; ibc: number } | null;
  worst_day: { date: string; ibc: number } | null;
  drifttid: string;
  drifttid_min: number;
  stopptid: string;
  stopptid_min: number;
  antal_skift: number;
  operators: WeeklySummaryOperator[];
  top_stops: WeeklySummaryStop[];
}

export interface WeeklySummaryResponse {
  success: boolean;
  data?: WeeklySummaryData;
  error?: string;
}

export interface SendWeeklySummaryResponse {
  success: boolean;
  recipients?: string[];
  week?: string;
  error?: string;
}

export interface ManualAnnotation {
  id: number;
  datum: string;
  typ: 'driftstopp' | 'helgdag' | 'handelse' | 'ovrigt';
  titel: string;
  beskrivning: string | null;
  created_at: string;
}

export interface ManualAnnotationsResponse {
  success: boolean;
  annotations?: ManualAnnotation[];
  error?: string;
}

export interface RealtimeOeeData {
  period: string;
  period_label: string;
  oee_percent: number;
  availability_percent: number;
  performance_percent: number;
  quality_percent: number;
  ibc_count: number;
  ibc_approved: number;
  ibc_rejected: number;
  stoppage_minutes: number;
  runtime_hours: number;
  planned_hours: number;
}

export interface RealtimeOeeResponse {
  success: boolean;
  data?: RealtimeOeeData;
  error?: string;
}

// ---- Månadsrapport-interfaces ----

export interface MonthlyReportSummary {
  ibc_total: number;
  ibc_goal: number;
  goal_pct: number;
  avg_ibc_per_day: number;
  active_days: number;
  production_days: number;
  avg_quality: number;
  avg_oee: number;
  total_runtime_hours: number;
  total_stoppage_hours: number;
  total_stopp_min: number;
}

export interface MonthlyReportDayEntry {
  date: string;
  ibc: number;
  quality: number;
  oee: number;
  skift_count?: number;
}

export interface MonthlyReportWeekEntry {
  week: string;
  ibc: number;
  avg_quality: number;
  avg_oee: number;
}

export interface MonthlyReportOperatorEntry {
  name: string;
  number: number;
  shifts: number;
  ibc_ok: number;
  avg_ibc_per_hour: number | null;
  avg_quality: number | null;
}

export interface MonthlyReportTopOperator {
  namn: string;
  ibc_total: number;
}

export interface MonthlyReportOeeTrendEntry {
  date: string;
  oee: number;
}

export interface MonthlyReportBestWorstDay {
  date: string;
  ibc: number;
  quality: number;
}

export interface MonthlyReportBestWorstWeek {
  week: string;
  ibc: number;
  avg_oee: number;
}

export interface MonthlyReportResponse {
  success: boolean;
  month: string;
  month_label: string;
  summary: MonthlyReportSummary;
  best_day: MonthlyReportBestWorstDay | null;
  worst_day: MonthlyReportBestWorstDay | null;
  basta_vecka: MonthlyReportBestWorstWeek | null;
  samsta_vecka: MonthlyReportBestWorstWeek | null;
  oee_trend: MonthlyReportOeeTrendEntry[];
  top_operatorer: MonthlyReportTopOperator[];
  operator_ranking: MonthlyReportOperatorEntry[];
  daily_production: MonthlyReportDayEntry[];
  week_summary: MonthlyReportWeekEntry[];
  error?: string;
}

export interface MonthCompareMonthData {
  total_ibc: number;
  avg_ibc_per_day: number;
  avg_oee_pct: number;
  avg_quality_pct: number;
  working_days: number;
  month_goal: number;
}

export interface MonthCompareDiff {
  total_ibc_pct: number | null;
  avg_ibc_per_day_pct: number | null;
  avg_oee_pct_diff: number;
  avg_quality_pct_diff: number;
}

export interface MonthCompareOperatorOfMonth {
  op_id: number;
  namn: string;
  initialer: string;
  total_ibc: number;
  avg_ibc_per_h: number;
  avg_quality_pct: number;
}

export interface MonthCompareOperatorRank {
  op_id: number;
  namn: string;
  initialer: string;
  shifts: number;
  total_ibc: number;
  avg_ibc_per_h: number;
  avg_quality_pct: number;
  score: number;
}

export interface MonthCompareDay {
  datum: string;
  ibc: number;
  target_pct: number;
  operator_count?: number;
}

export interface MonthCompareResponse {
  success: boolean;
  month: string;
  prev_month: string;
  this_month: MonthCompareMonthData;
  prev_month_data: MonthCompareMonthData;
  diff: MonthCompareDiff;
  operator_of_month: MonthCompareOperatorOfMonth | null;
  operator_ranking: MonthCompareOperatorRank[];
  best_day: MonthCompareDay | null;
  worst_day: MonthCompareDay | null;
  error?: string;
}

export interface ProductionGoalProgressResponse {
  success: boolean;
  period?: string;
  target?: number;
  actual?: number;
  percentage?: number;
  remaining?: number;
  time_remaining_seconds?: number;
  streak?: number;
  period_label?: string;
  error?: string;
}
