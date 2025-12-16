// Public API types for unauthenticated class browsing
// Matches backend response structure from /api/v1/public/classes

export type Site = 'SASAD' | 'TB' | 'ÚJBUDA'

export interface PublicSite {
  id: number
  name: string
}

export interface PublicRoom {
  id: number
  name: string
  capacity?: number
  site?: PublicSite
}

export interface PublicClassTemplate {
  id: number
  name: string
  description: string
  duration_minutes: number
  capacity: number
  credits_required: number
  color_hex: string
  status: string
}

export interface PublicStaffUser {
  id: number
  name: string
  email: string
}

export interface PublicStaff {
  id: number
  user_id: number
  user: PublicStaffUser
  default_site?: Site
}

export interface PublicClassOccurrence {
  id: number
  template_id: number
  starts_at: string // ISO 8601
  ends_at: string
  status: 'scheduled' | 'completed' | 'cancelled'
  capacity: number
  booked_count: number
  available_spots: number
  is_full: boolean
  class_template: PublicClassTemplate
  room: PublicRoom
  trainer: PublicStaff
  trainer_id: number
  room_id: number
}

// API Request types

export interface PublicClassFilters {
  from?: string // ISO date
  to?: string // ISO date
  room?: number
  trainer?: number
  site?: Site
}

export interface QuickRegisterRequest {
  name: string
  email: string
  password: string
  phone?: string
}

export interface QuickRegisterResponse {
  user: {
    id: number
    name: string
    email: string
  }
  token: string
}
