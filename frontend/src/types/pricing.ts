// Pricing and Settlement types for admin management

export interface ClassPricingDefault {
  id: number
  name: string | null
  class_template_id: number
  entry_fee_brutto: number // HUF
  trainer_fee_brutto: number // HUF
  currency: string // "HUF"
  valid_from: string // ISO date
  valid_until: string | null
  is_active: boolean
  created_at: string
  updated_at: string
  // Relations (snake_case from Laravel API)
  class_template?: {
    id: number
    title: string
    color?: string
  }
  creator?: {
    id: number
    name: string
  }
}

export interface ClientClassPricing {
  id: number
  client_id: number
  class_template_id: number
  entry_fee_brutto: number // HUF
  trainer_fee_brutto: number // HUF
  currency: string // "HUF"
  valid_from: string // ISO date
  valid_until: string | null
  is_active: boolean
  created_at: string
  updated_at: string
  // Relations
  client?: {
    id: number
    name: string
  }
  classTemplate?: {
    id: number
    title: string
  }
}

export interface CreateClassPricingDefaultRequest {
  name?: string | null
  class_template_id: number
  entry_fee_brutto: number
  trainer_fee_brutto: number
  currency?: string // defaults to "HUF"
  valid_from: string // ISO date
  valid_until?: string | null
  is_active?: boolean
}

export interface CreateClientClassPricingRequest {
  client_id: number
  class_template_id: number
  entry_fee_brutto: number
  trainer_fee_brutto: number
  currency?: string // defaults to "HUF"
  valid_from: string // ISO date
  valid_until?: string | null
  is_active?: boolean
}

export interface Settlement {
  id: number
  trainer_id: number
  trainer_name: string
  period_start: string // ISO date
  period_end: string // ISO date
  total_trainer_fee: number // HUF
  total_entry_fee: number // HUF
  status: 'draft' | 'finalized' | 'paid'
  notes: string | null
  items_count: number
  created_at: string
  updated_at: string
  // Relations
  items?: SettlementItem[]
  trainer?: {
    id: number
    name: string
  }
}

export interface SettlementItem {
  id: number
  settlement_id: number
  class_occurrence_id: number
  class_name: string
  class_date: string // ISO datetime
  client_name: string
  entry_fee_brutto: number
  trainer_fee_brutto: number
  currency: string
  status: string
  created_at: string
  updated_at: string
}

export interface SettlementPreview {
  trainer_id: number
  trainer_name: string
  period_start: string
  period_end: string
  total_trainer_fee: number
  total_entry_fee: number
  items_count: number
  items: SettlementPreviewItem[]
}

export interface SettlementPreviewItem {
  class_occurrence_id: number
  class_name: string
  class_date: string
  client_name: string
  entry_fee_brutto: number
  trainer_fee_brutto: number
  currency: string
}

export interface GenerateSettlementRequest {
  trainer_id: number
  period_start: string // ISO date
  period_end: string // ISO date
  notes?: string
}

export interface UpdateSettlementStatusRequest {
  status: 'draft' | 'finalized' | 'paid'
  notes?: string
}
