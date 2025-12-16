// Class and booking related types matching Laravel backend models

export type RecurrenceFrequency = 'DAILY' | 'WEEKLY' | 'MONTHLY'

export type RegistrationStatus = 'booked' | 'waitlist' | 'cancelled' | 'attended' | 'no_show'

export interface Room {
  id: string
  name: string
  capacity: number
  location: string
  facility: string
  created_at: string
  updated_at: string
}

export interface ClassTemplate {
  id: number
  title: string
  name?: string // Backend sends 'name' instead of 'title' in some responses
  description: string | null
  duration_minutes: number
  capacity: number
  credits_required: number
  color_hex: string | null
  recurrence_rule: string | null // RRULE format
  recurrence_frequency: RecurrenceFrequency | null
  recurrence_interval: number | null
  recurrence_count: number | null
  recurrence_until: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface StaffProfile {
  id: string
  user_id: string
  hourly_rate: number
  specializations: string | null
  bio: string | null
  is_active: boolean
  created_at: string
  updated_at: string
  user?: {
    id: string
    name: string
    email: string
  }
}

export interface ClassOccurrence {
  id: string
  class_template_id: string
  room_id: string
  trainer_id: string | null
  starts_at: string // ISO 8601 with timezone
  ends_at: string // ISO 8601 with timezone
  capacity_override: number | null
  capacity?: number // Direct capacity from backend
  status: 'scheduled' | 'cancelled' | 'completed'
  notes: string | null
  created_at: string
  updated_at: string
  // Relationships
  class_template?: ClassTemplate
  room?: Room
  trainer?: StaffProfile
  // Computed fields from backend
  registered_count?: number
  booked_count?: number
  available_spots?: number
  is_full?: boolean
  waitlist_count?: number
  has_capacity?: boolean
}

export interface ClassRegistration {
  id: string
  class_occurrence_id: string
  client_id: string
  status: RegistrationStatus
  attended: boolean | null
  checked_in_at: string | null
  cancelled_at: string | null
  notes: string | null
  created_at: string
  updated_at: string
  // Relationships
  class_occurrence?: ClassOccurrence
  client?: {
    id: string
    user_id: string
    user: {
      id: string
      name: string
      email: string
    }
  }
}

// API Request/Response types

export interface ClassListFilters {
  date_from?: string
  date_to?: string
  room_id?: string
  class_template_id?: string
  has_capacity?: boolean
  status?: 'scheduled' | 'cancelled' | 'completed'
}

export interface BookClassRequest {
  client_id?: string // Admin only, auto-filled for clients
  notes?: string
}

// BookClassResponse is the ClassRegistration model itself
// The status field indicates if the booking is confirmed or waitlisted
export type BookClassResponse = ClassRegistration

export interface CancelBookingRequest {
  reason?: string
}

export interface CancelBookingResponse {
  message: string
  credit_refunded: boolean
}
