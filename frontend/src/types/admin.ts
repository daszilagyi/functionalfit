// Admin-specific types for user management, rooms, class templates, and reports

export interface UserWithProfile {
  id: number
  role: 'client' | 'staff' | 'admin'
  name: string
  email: string
  phone?: string
  status: 'active' | 'inactive' | 'suspended'
  created_at: string
  updated_at: string
  // Role-specific profiles
  client?: ClientProfile
  staff_profile?: StaffProfile
}

export interface ClientProfile {
  id: number
  user_id: number
  date_of_birth?: string
  emergency_contact_name?: string
  emergency_contact_phone?: string
  notes?: string
  unpaid_balance: number
  created_at: string
  updated_at: string
}

export interface StaffProfile {
  id: number
  user_id: number
  specialization?: string
  bio?: string
  default_hourly_rate?: number
  is_available_for_booking: boolean
  daily_schedule_notification: boolean
  created_at: string
  updated_at: string
}

export interface CreateUserRequest {
  role: 'client' | 'staff' | 'admin'
  name: string
  email: string
  phone?: string
  password: string
  status?: 'active' | 'inactive' | 'suspended'
  // Client-specific fields
  date_of_birth?: string
  emergency_contact_name?: string
  emergency_contact_phone?: string
  notes?: string
  // Staff-specific fields
  specialization?: string
  bio?: string
  default_hourly_rate?: number
  is_available_for_booking?: boolean
}

export interface UpdateUserRequest {
  name?: string
  email?: string
  phone?: string
  status?: 'active' | 'inactive' | 'suspended'
  // Client-specific fields
  date_of_birth?: string
  emergency_contact_name?: string
  emergency_contact_phone?: string
  notes?: string
  // Staff-specific fields
  specialization?: string
  bio?: string
  default_hourly_rate?: number
  is_available_for_booking?: boolean
  daily_schedule_notification?: boolean
}

// Site Management Types
export interface Site {
  id: number
  name: string
  slug: string
  address?: string
  city?: string
  postal_code?: string
  phone?: string
  email?: string
  description?: string
  opening_hours?: OpeningHours
  is_active: boolean
  rooms_count?: number
  rooms?: Room[]
  created_at: string
  updated_at: string
  deleted_at?: string
}

export interface OpeningHours {
  monday?: DayHours
  tuesday?: DayHours
  wednesday?: DayHours
  thursday?: DayHours
  friday?: DayHours
  saturday?: DayHours
  sunday?: DayHours
}

export interface DayHours {
  open: string  // HH:MM format
  close: string // HH:MM format
}

export interface CreateSiteRequest {
  name: string
  slug?: string
  address?: string
  city?: string
  postal_code?: string
  phone?: string
  email?: string
  description?: string
  opening_hours?: OpeningHours
  is_active?: boolean
}

export interface UpdateSiteRequest {
  name?: string
  slug?: string
  address?: string
  city?: string
  postal_code?: string
  phone?: string
  email?: string
  description?: string
  opening_hours?: OpeningHours
  is_active?: boolean
}

export interface Room {
  id: number
  site_id: number
  site?: Site
  name: string
  google_calendar_id?: string
  color?: string
  capacity?: number
  created_at: string
  updated_at: string
  deleted_at?: string
}

export interface CreateRoomRequest {
  site_id: number
  name: string
  google_calendar_id?: string
  color?: string
  capacity?: number
}

export interface UpdateRoomRequest {
  site_id?: number
  name?: string
  capacity?: number
  google_calendar_id?: string
  color?: string
}

export interface ClassTemplate {
  id: number
  title: string
  description?: string
  duration_minutes: number
  default_capacity: number
  credits_required?: number
  base_price_huf?: number
  color?: string
  is_active: boolean
  is_public_visible: boolean
  created_at: string
  updated_at: string
}

export interface CreateClassTemplateRequest {
  name: string
  description?: string
  duration_minutes: number
  default_capacity: number
  credits_required?: number
  color?: string
  is_active?: boolean
  is_public_visible?: boolean
}

export interface UpdateClassTemplateRequest {
  name?: string
  description?: string
  duration_minutes?: number
  default_capacity?: number
  credits_required?: number
  color?: string
  is_active?: boolean
  is_public_visible?: boolean
}

// Report types
export interface AttendanceReport {
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

export interface PayoutReport {
  period: { from: string; to: string }
  summary: {
    total_entry_fee: number
    total_trainer_fee: number
    total_revenue: number
    staff_count: number
    currency: string
    // Legacy fields (for backwards compatibility)
    total_payout?: number
    total_hours?: number
  }
  staff_payouts: StaffPayout[]
}

export interface IndividualSessionDetail {
  id: number
  date: string
  time: string
  client_name: string
  service_type: string
  room: string
  entry_fee: number
  trainer_fee: number
  total_fee: number
  attendance_status: 'attended' | 'no_show' | null
}

export interface GroupSessionDetail {
  id: number
  date: string
  time: string
  class_name: string
  room: string
  participants: number
  entry_fee: number
  trainer_fee: number
}

export interface StaffPayout {
  staff_id: number
  name: string
  entry_fee: number
  trainer_fee: number
  individual_count: number
  group_count: number
  total_revenue: number
  individual_sessions: IndividualSessionDetail[]
  group_sessions: GroupSessionDetail[]
  // Legacy fields (for backwards compatibility)
  hourly_rate?: number
  individual_hours?: number
  group_hours?: number
  total_hours?: number
  total_earnings?: number
}

export interface RevenueReport {
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

export interface UtilizationReport {
  period: { from: string; to: string }
  summary: {
    total_individual_sessions: number
    total_group_classes: number
    total_sessions: number
  }
  staff_utilization: StaffUtilization[]
}

export interface StaffUtilization {
  staff_id: number
  name: string
  individual_sessions: number
  group_classes: number
  total_sessions: number
}

export interface ClientActivityReport {
  period: { from: string; to: string }
  summary: {
    total_active_clients: number
  }
  clients: ClientActivity[]
}

export interface ClientSession {
  id: number
  type: 'individual' | 'group'
  date: string
  time: string
  trainer_name: string
  service_type: string
  room: string
  entry_fee: number
  trainer_fee: number
  total_fee: number
  attendance_status: 'attended' | 'no_show' | null
}

export interface ClientActivity {
  client_id: number
  name: string
  email: string
  total_sessions: number
  attended: number
  no_shows: number
  sessions?: ClientSession[]
}

// Email Template types
export interface EmailTemplate {
  id: number
  slug: string
  subject: string
  html_body: string
  fallback_body: string
  is_active: boolean
  version: number
  updated_by: number
  updated_at: string
  created_at: string
  updated_by_user?: {
    id: number
    name: string
    email: string
  }
}

export interface EmailTemplateVersion {
  id: number
  email_template_id: number
  version: number
  subject: string
  html_body: string
  fallback_body: string
  created_by: number
  created_at: string
  created_by_user?: {
    id: number
    name: string
    email: string
  }
}

export interface EmailTemplateVariable {
  name: string
  description: string
  example: string
}

export interface CreateEmailTemplateRequest {
  slug: string
  subject: string
  html_body: string
  fallback_body: string
  is_active?: boolean
}

export interface UpdateEmailTemplateRequest {
  subject?: string
  html_body?: string
  fallback_body?: string
  is_active?: boolean
}

export interface PreviewEmailTemplateRequest {
  variables?: Record<string, unknown>
}

export interface SendTestEmailRequest {
  email: string
  variables?: Record<string, unknown>
}

// Event Changes types (legacy - to be deprecated)
export interface EventChange {
  id: number
  event_id: number
  action: 'created' | 'updated' | 'moved' | 'cancelled' | 'deleted'
  by_user_id: number
  meta: Record<string, unknown>
  created_at: string
  event?: {
    id: number
    type: 'one-on-one' | 'group-class'
    starts_at: string
    ends_at: string
    status: 'scheduled' | 'cancelled' | 'completed'
    staff?: {
      id: number
      user: {
        id: number
        name: string
        email: string
      }
    }
    client?: {
      id: number
      user: {
        id: number
        name: string
        email: string
      }
    }
    room?: {
      id: number
      name: string
      site: string
    }
  }
  user?: {
    id: number
    name: string
    email: string
    staff?: {
      id: number
    }
    client?: {
      id: number
    }
  }
}

// New Calendar Change Log types (unified events + class_occurrences)
export interface CalendarChangeLog {
  id: number
  changed_at: string
  action: 'EVENT_CREATED' | 'EVENT_UPDATED' | 'EVENT_DELETED'
  actor: {
    id: number
    name: string
    role: string
  }
  site: string | null
  room: {
    id: number
    name: string
  } | null
  event_time: {
    starts_at: string
    ends_at: string
  } | null
  summary: string
}

export interface CalendarChangeLogDetail extends CalendarChangeLog {
  entity: {
    type: 'event' | 'class_occurrence'
    id: number
  }
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  changed_fields: string[] | null
  ip_address: string | null
  user_agent: string | null
}

export interface CalendarChangeFilters {
  actorUserId?: number
  roomId?: number
  site?: string
  action?: string
  changedFrom?: string
  changedTo?: string
  sort?: string
  order?: 'asc' | 'desc'
  page?: number
  perPage?: number
}
