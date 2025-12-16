// Reports module types - Admin, Staff, and Client reporting

// ============================================
// ADMIN REPORTS
// ============================================

export interface AttendanceReportData {
  period: { from: string; to: string }
  summary: {
    total_sessions: number
    total_attended: number
    total_no_shows: number
    not_checked_in: number
    attendance_rate: number
    no_show_rate: number
  }
  by_type: {
    individual: {
      total: number
      attended: number
      no_shows: number
    }
    group_classes: {
      total: number
      attended: number
      no_shows: number
    }
  }
}

export interface PayoutReportData {
  period: { from: string; to: string }
  summary: {
    total_payout: number
    total_hours: number
    staff_count: number
    currency: string
  }
  staff_payouts: StaffPayoutData[]
}

export interface StaffPayoutData {
  staff_id: number
  name: string
  hourly_rate: number
  individual_hours: number
  group_hours: number
  total_hours: number
  total_earnings: number
}

export interface RevenueReportData {
  period: { from: string; to: string }
  summary: {
    total_revenue: number
    total_passes_sold: number
    average_pass_price: number
    currency: string
  }
  by_status: {
    active: number
    expired: number
    fully_used: number
  }
}

export interface UtilizationReportData {
  period: { from: string; to: string }
  summary: {
    total_individual_sessions: number
    total_group_classes: number
    total_sessions: number
  }
  staff_utilization: StaffUtilizationData[]
}

export interface StaffUtilizationData {
  staff_id: number
  name: string
  individual_sessions: number
  group_classes: number
  total_sessions: number
}

export interface ClientActivityReportData {
  period: { from: string; to: string }
  summary: {
    total_active_clients: number
  }
  clients: ClientActivityData[]
}

export interface ClientActivityData {
  client_id: number
  name: string
  email: string
  total_sessions: number
  attended: number
  no_shows: number
}

// ============================================
// STAFF REPORTS (already in types/staff.ts, but adding for completeness)
// ============================================

export interface StaffDashboardStats {
  today_sessions: number
  today_completed: number
  today_remaining: number
  week_total_hours: number
  next_session?: StaffNextSession | null
}

export interface StaffNextSession {
  id: number
  client_name?: string
  starts_at: string
  ends_at: string
  room_name: string
  type: 'INDIVIDUAL' | 'GROUP_CLASS' | 'BLOCK'
}

export interface PayoutExportData {
  period: { from: string; to: string }
  staff: {
    id: number
    name: string
  }
  summary: {
    total_hours: number
    individual_hours: number
    group_hours: number
    hourly_rate: number
    total_earnings: number
    currency: string
  }
  sessions: PayoutSessionData[]
}

export interface PayoutSessionData {
  date: string
  time: string
  type: 'INDIVIDUAL' | 'GROUP_CLASS'
  client_name?: string
  class_name?: string
  duration_minutes: number
  hours: number
  rate: number
  amount: number
}

export interface AttendanceExportData {
  period: { from: string; to: string }
  staff: {
    id: number
    name: string
  }
  summary: {
    total_sessions: number
    attended: number
    no_shows: number
    not_checked_in: number
    attendance_rate: number
  }
  sessions: AttendanceSessionData[]
}

export interface AttendanceSessionData {
  date: string
  time: string
  type: 'INDIVIDUAL' | 'GROUP_CLASS'
  client_name?: string
  class_name?: string
  room_name: string
  attendance_status: 'attended' | 'no_show' | null
  notes?: string
}

// ============================================
// CLIENT REPORTS
// ============================================

export interface ClientSummaryStats {
  total_sessions: number
  attended_sessions: number
  attendance_rate: number
  total_credits_used: number
}

export interface ClientActivityPeriod {
  period: 'last_30_days' | 'last_3_months' | 'custom'
  date_from?: string
  date_to?: string
}

export interface ClientSessionHistory {
  id: number
  date: string
  time: string
  type: 'INDIVIDUAL' | 'GROUP_CLASS'
  service_name: string
  trainer_name: string
  room_name: string
  credits_used: number
  attendance_status: 'attended' | 'no_show' | null
  notes?: string
}

export interface ClientPassSummary {
  id: number
  name: string
  purchased_at: string
  expires_at?: string
  total_credits: number
  remaining_credits: number
  price: number
  currency: string
  status: 'active' | 'expired' | 'fully_used'
}

// ============================================
// EXPORT FORMATS
// ============================================

export type ExportFormat = 'xlsx' | 'csv'

export interface ExportRequest {
  date_from: string
  date_to: string
  format: ExportFormat
}

// ============================================
// FILTER OPTIONS
// ============================================

export interface ReportFilters {
  date_from: string
  date_to: string
  trainer_id?: number
  site_id?: number
  room_id?: number
  service_type?: 'INDIVIDUAL' | 'GROUP_CLASS'
}

export interface TrainerOption {
  id: number
  name: string
}

export interface SiteOption {
  id: number
  name: string
  slug: string
}

export interface RoomOption {
  id: number
  site_id: number
  name: string
}
