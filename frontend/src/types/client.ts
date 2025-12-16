// Client related types matching Laravel backend models

export type PassStatus = 'active' | 'expired' | 'depleted'

// Simplified client type returned by search API
export interface ClientSearchResult {
  id: string
  is_technical_guest?: boolean
  user: {
    id: string | null
    name: string
    email: string | null
    phone: string | null
  }
}

export interface Client {
  id: string
  user_id: string
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  medical_notes: string | null
  is_active: boolean
  is_technical_guest?: boolean
  created_at: string
  updated_at: string
  user: {
    id: string | null
    name: string
    email: string | null
    phone: string | null
  }
}

export interface Pass {
  id: string
  client_id: string
  pass_type: string
  credits_total: number | null // null for unlimited
  credits_remaining: number | null
  purchased_at: string
  expires_at: string | null
  status: PassStatus
  external_order_id: string | null // WooCommerce/Stripe order ID
  created_at: string
  updated_at: string
  // Computed fields
  is_unlimited?: boolean
  is_expired?: boolean
  is_depleted?: boolean
}

export interface ActivityHistoryItem {
  id: string
  type: 'class' | 'event'
  title: string
  date: string
  start_time: string
  end_time: string
  attended: boolean | null
  checked_in_at: string | null
  trainer: string | null
  room: string | null
  credits_used: number
  notes: string | null
}

export interface ActivityHistoryFilters {
  date_from?: string
  date_to?: string
  attended?: boolean | null
  type?: 'class' | 'event'
}

export interface ActivitySummary {
  total_sessions: number
  attended_sessions: number
  no_shows: number
  upcoming_sessions: number
  total_credits_used: number
  attendance_rate: number // Percentage
}

export interface ClientActivityResponse {
  activities: ActivityHistoryItem[]
  summary: ActivitySummary
  pagination: {
    current_page: number
    total_pages: number
    per_page: number
    total: number
  }
}

export interface ClientPassesResponse {
  active_passes: Pass[]
  expired_passes: Pass[]
  total_credits_remaining: number
}

export interface UpcomingBooking {
  id: string
  occurrence_id?: string // For class bookings - used for cancellation
  type: 'class' | 'event'
  title: string
  starts_at: string
  ends_at: string
  trainer: string | null
  room: string | null
  can_cancel: boolean // Based on 24h rule
  cancellation_deadline: string | null
}
