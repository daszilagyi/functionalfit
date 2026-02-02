// Event related types matching Laravel backend models

export type EventType = 'INDIVIDUAL' | 'GROUP_CLASS' | 'BLOCK'

export type EventStatus = 'scheduled' | 'cancelled' | 'completed'

export interface Event {
  id: string
  type: EventType
  staff_id: string | null
  client_id: string | null
  room_id: string | null
  class_occurrence_id: string | null
  service_type_id: number | null
  starts_at: string // ISO 8601 with timezone
  ends_at: string // ISO 8601 with timezone
  duration_minutes: number
  status: EventStatus
  attended: boolean | null
  attendance_status: 'attended' | 'no_show' | null
  checked_in_at: string | null
  notes: string | null
  google_calendar_event_id: string | null
  // Pricing fields for main client
  entry_fee_brutto: number | null
  trainer_fee_brutto: number | null
  currency: string
  price_source: string | null
  created_at: string
  updated_at: string
  // Relationships
  staff?: {
    id: string
    user_id: string
    user: {
      id: string
      name: string
      email: string
    }
  }
  client?: {
    id: string
    user_id: string
    is_technical_guest?: boolean
    full_name?: string
    user?: {
      id: string | null
      name: string
      email: string | null
      phone: string | null
    }
  }
  // Laravel returns snake_case but we accept both for compatibility
  additional_clients?: Array<{
    id: string
    user_id: string
    is_technical_guest?: boolean
    full_name?: string
    user?: {
      id: string | null
      name: string
      email: string | null
      phone: string | null
    }
    pivot?: {
      event_id: number
      client_id: number
      quantity: number
      guest_index?: number
      attendance_status?: string | null
      checked_in_at?: string | null
      // Pricing fields per guest
      entry_fee_brutto?: number | null
      trainer_fee_brutto?: number | null
      currency?: string
      price_source?: string | null
    }
  }>
  // Alias for camelCase usage
  additionalClients?: Array<{
    id: string
    user_id: string
    is_technical_guest?: boolean
    full_name?: string
    user?: {
      id: string | null
      name: string
      email: string | null
      phone: string | null
    }
    pivot?: {
      event_id: number
      client_id: number
      quantity: number
      guest_index?: number
      attendance_status?: string | null
      checked_in_at?: string | null
      // Pricing fields per guest
      entry_fee_brutto?: number | null
      trainer_fee_brutto?: number | null
      currency?: string
      price_source?: string | null
    }
  }>
  room?: {
    id: string
    name: string
    location: string
    facility: string
  }
  pricing?: {
    id: number
    name: string | null
    entry_fee_brutto: number
    trainer_fee_brutto: number
    is_active: boolean
  }
  pricing_id?: number | null
}

export interface EventChange {
  id: string
  event_id: string
  changed_by_user_id: string
  change_type: 'created' | 'updated' | 'cancelled' | 'restored'
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  reason: string | null
  created_at: string
}

// API Request/Response types

export interface CreateEventRequest {
  type: EventType
  staff_id?: string // Required for admin, optional for staff (backend fills it automatically)
  client_id?: string // Required for INDIVIDUAL events (main guest)
  additional_client_ids?: string[] // Additional guests for INDIVIDUAL events
  room_id: string
  starts_at: string // ISO 8601 with timezone
  duration_minutes?: number
  ends_at?: string // Alternative to duration_minutes
  notes?: string
  // Recurring event fields
  is_recurring?: boolean
  repeat_from?: string // YYYY-MM-DD
  repeat_until?: string // YYYY-MM-DD
  skip_dates?: string[] // YYYY-MM-DD dates to skip
}

// Recurring event preview types
export interface RecurringPreviewDate {
  date: string // YYYY-MM-DD
  starts_at: string
  ends_at: string
  status: 'ok' | 'conflict'
  conflict_with?: string
}

export interface RecurringPreviewResponse {
  dates: RecurringPreviewDate[]
  total: number
  ok_count: number
  conflict_count: number
}

export interface RecurringEventResponse {
  count: number
  events: Event[]
  skipped_dates: string[]
}

export interface UpdateEventRequest {
  room_id?: string
  starts_at?: string // Same-day only for staff, validated server-side
  duration_minutes?: number
  notes?: string
  status?: EventStatus
}

export interface EventListFilters {
  date_from?: string
  date_to?: string
  staff_id?: string
  client_id?: string
  room_id?: string
  type?: EventType
  status?: EventStatus
}

export interface CheckInRequest {
  attendance_status: 'attended' | 'no_show'
  notes?: string
  client_id?: number
  guest_index?: number
}

export interface CheckInResponse {
  message: string
  event: Event
  pass_credit_deducted: boolean
  checked_in_client_id?: number
  checked_in_guest_index?: number
}
