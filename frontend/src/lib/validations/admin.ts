// Zod validation schemas for admin forms
import { z } from 'zod'

// User Management Schemas
export const createUserSchema = z.object({
  role: z.enum(['client', 'staff', 'admin'], {
    required_error: 'errors.roleRequired',
  }),
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255),
  email: z.string().email({ message: 'errors.invalidEmail' }),
  phone: z.string().optional(),
  password: z.string().min(8, { message: 'errors.passwordMinLength' }),
  status: z.enum(['active', 'inactive']).optional().default('active'),
  // Client-specific fields
  date_of_birth: z.string().optional(),
  emergency_contact_name: z.string().max(255).optional(),
  emergency_contact_phone: z.string().max(20).optional(),
  notes: z.string().max(1000).optional(),
  // Staff-specific fields
  specialization: z.string().max(500).optional(),
  bio: z.string().max(1000).optional(),
  default_hourly_rate: z.number().min(0).optional(),
  is_available_for_booking: z.boolean().optional().default(true),
})

export const updateUserSchema = z.object({
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255).optional(),
  email: z.string().email({ message: 'errors.invalidEmail' }).optional(),
  phone: z.string().optional(),
  status: z.enum(['active', 'inactive']).optional(),
  // Client-specific fields
  date_of_birth: z.string().optional(),
  emergency_contact_name: z.string().max(255).optional(),
  emergency_contact_phone: z.string().max(20).optional(),
  notes: z.string().max(1000).optional(),
  // Staff-specific fields
  specialization: z.string().max(500).optional(),
  bio: z.string().max(1000).optional(),
  default_hourly_rate: z.number().min(0).optional(),
  is_available_for_booking: z.boolean().optional(),
})

export type CreateUserFormData = z.infer<typeof createUserSchema>
export type UpdateUserFormData = z.infer<typeof updateUserSchema>

// Site Management Schemas
const dayHoursSchema = z.object({
  open: z.string().regex(/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/, { message: 'errors.invalidTimeFormat' }),
  close: z.string().regex(/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/, { message: 'errors.invalidTimeFormat' }),
})

export const createSiteSchema = z.object({
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255),
  slug: z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, { message: 'errors.invalidSlug' }).optional(),
  address: z.string().max(255).optional(),
  city: z.string().max(100).optional(),
  postal_code: z.string().max(20).optional(),
  phone: z.string().max(50).optional(),
  email: z.string().email({ message: 'errors.invalidEmail' }).optional(),
  description: z.string().max(1000).optional(),
  opening_hours: z.object({
    monday: dayHoursSchema.optional(),
    tuesday: dayHoursSchema.optional(),
    wednesday: dayHoursSchema.optional(),
    thursday: dayHoursSchema.optional(),
    friday: dayHoursSchema.optional(),
    saturday: dayHoursSchema.optional(),
    sunday: dayHoursSchema.optional(),
  }).optional(),
  is_active: z.boolean().optional().default(true),
})

export const updateSiteSchema = z.object({
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255).optional(),
  slug: z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, { message: 'errors.invalidSlug' }).optional(),
  address: z.string().max(255).optional(),
  city: z.string().max(100).optional(),
  postal_code: z.string().max(20).optional(),
  phone: z.string().max(50).optional(),
  email: z.string().email({ message: 'errors.invalidEmail' }).optional(),
  description: z.string().max(1000).optional(),
  opening_hours: z.object({
    monday: dayHoursSchema.optional(),
    tuesday: dayHoursSchema.optional(),
    wednesday: dayHoursSchema.optional(),
    thursday: dayHoursSchema.optional(),
    friday: dayHoursSchema.optional(),
    saturday: dayHoursSchema.optional(),
    sunday: dayHoursSchema.optional(),
  }).optional(),
  is_active: z.boolean().optional(),
})

export type CreateSiteFormData = z.infer<typeof createSiteSchema>
export type UpdateSiteFormData = z.infer<typeof updateSiteSchema>

// Room Management Schemas
export const createRoomSchema = z.object({
  site_id: z.number().min(1, { message: 'errors.siteRequired' }),
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255),
  capacity: z.number().min(1, { message: 'errors.capacityMin' }).optional(),
  google_calendar_id: z.string().max(255).optional(),
  color: z.string().max(7).regex(/^#[0-9A-Fa-f]{6}$/, { message: 'errors.invalidColor' }).optional(),
})

export const updateRoomSchema = z.object({
  site_id: z.number().min(1, { message: 'errors.siteRequired' }).optional(),
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255).optional(),
  capacity: z.number().min(1, { message: 'errors.capacityMin' }).optional(),
  google_calendar_id: z.string().max(255).optional(),
  color: z.string().max(7).regex(/^#[0-9A-Fa-f]{6}$/, { message: 'errors.invalidColor' }).optional(),
})

export type CreateRoomFormData = z.infer<typeof createRoomSchema>
export type UpdateRoomFormData = z.infer<typeof updateRoomSchema>

// Class Template Management Schemas
export const createClassTemplateSchema = z.object({
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255),
  description: z.string().max(1000).optional(),
  duration_minutes: z.number().min(15, { message: 'errors.durationMin' }).max(480, { message: 'errors.durationMax' }),
  default_capacity: z.number().min(1, { message: 'errors.capacityMin' }),
  credits_required: z.number().min(0).optional(),
  base_price_huf: z.number().min(0).optional(),
  color: z.string().max(7).regex(/^#[0-9A-Fa-f]{6}$/, { message: 'errors.invalidColor' }).optional(),
  is_active: z.boolean().optional().default(true),
  is_public_visible: z.boolean().optional().default(true),
})

export const updateClassTemplateSchema = z.object({
  name: z.string().min(1, { message: 'errors.nameRequired' }).max(255).optional(),
  description: z.string().max(1000).optional(),
  duration_minutes: z.number().min(15, { message: 'errors.durationMin' }).max(480, { message: 'errors.durationMax' }).optional(),
  default_capacity: z.number().min(1, { message: 'errors.capacityMin' }).optional(),
  credits_required: z.number().min(0).optional(),
  base_price_huf: z.number().min(0).optional(),
  color: z.string().max(7).regex(/^#[0-9A-Fa-f]{6}$/, { message: 'errors.invalidColor' }).optional(),
  is_active: z.boolean().optional(),
  is_public_visible: z.boolean().optional(),
})

export type CreateClassTemplateFormData = z.infer<typeof createClassTemplateSchema>
export type UpdateClassTemplateFormData = z.infer<typeof updateClassTemplateSchema>

// Report Filters Schema
export const reportFiltersSchema = z.object({
  date_from: z.string().min(1, { message: 'errors.dateFromRequired' }),
  date_to: z.string().min(1, { message: 'errors.dateToRequired' }),
})

export type ReportFiltersFormData = z.infer<typeof reportFiltersSchema>

// Email Template Management Schemas
export const createEmailTemplateSchema = z.object({
  slug: z.string().min(1, { message: 'errors.slugRequired' }).max(255).regex(/^[a-z0-9_-]+$/, { message: 'errors.invalidSlug' }),
  subject: z.string().min(1, { message: 'errors.subjectRequired' }).max(500, { message: 'errors.maxLength' }),
  html_body: z.string().min(1, { message: 'errors.htmlBodyRequired' }),
  fallback_body: z.string().min(1, { message: 'errors.fallbackBodyRequired' }),
  is_active: z.boolean().optional().default(true),
})

export const updateEmailTemplateSchema = z.object({
  subject: z.string().min(1, { message: 'errors.subjectRequired' }).max(500, { message: 'errors.maxLength' }).optional(),
  html_body: z.string().min(1, { message: 'errors.htmlBodyRequired' }).optional(),
  fallback_body: z.string().min(1, { message: 'errors.fallbackBodyRequired' }).optional(),
  is_active: z.boolean().optional(),
})

export const sendTestEmailSchema = z.object({
  email: z.string().email({ message: 'errors.invalidEmail' }),
  variables: z.record(z.unknown()).optional(),
})

export type CreateEmailTemplateFormData = z.infer<typeof createEmailTemplateSchema>
export type UpdateEmailTemplateFormData = z.infer<typeof updateEmailTemplateSchema>
export type SendTestEmailFormData = z.infer<typeof sendTestEmailSchema>
