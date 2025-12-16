// Staff related types matching Laravel backend models

export interface StaffEvent {
  id: string
  type: 'INDIVIDUAL' | 'GROUP_CLASS' | 'BLOCK'
  client_name: string | null
  client_id: string | null
  room_name: string
  room_id: string
  starts_at: string
  ends_at: string
  duration_minutes: number
  status: 'scheduled' | 'cancelled' | 'completed'
  attended: boolean | null
  notes: string | null
}

export interface PayoutReport {
  staff_id: string
  staff_name: string
  period_start: string
  period_end: string
  total_hours: number
  hourly_rate: number
  total_amount: number
  sessions: Array<{
    date: string
    client_name: string | null
    room_name: string
    duration_minutes: number
    amount: number
  }>
}

export interface AttendanceReport {
  staff_id: string
  staff_name: string
  period_start: string
  period_end: string
  total_sessions: number
  attended_sessions: number
  no_shows: number
  cancelled_sessions: number
  attendance_rate: number
}

export interface StaffDashboardStats {
  today_sessions: number
  today_completed: number
  today_remaining: number
  week_total_hours: number
  week_sessions: number
  upcoming_session: StaffEvent | null
}
