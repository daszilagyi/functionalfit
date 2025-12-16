// Event form validation schemas
import { z } from 'zod'

export const createEventSchema = z.object({
  type: z.enum(['INDIVIDUAL', 'GROUP_CLASS', 'BLOCK'], {
    errorMap: () => ({ message: 'calendar.form.validation.typeRequired' }),
  }),
  staff_id: z.string().optional(),
  client_id: z.string().optional(),
  room_id: z.string().min(1, { message: 'calendar.form.validation.roomRequired' }),
  starts_at: z.string().min(1, { message: 'calendar.form.validation.dateRequired' }),
  duration_minutes: z
    .number()
    .min(15, { message: 'calendar.form.validation.durationMin' })
    .max(480, { message: 'calendar.form.validation.durationMax' }),
  notes: z.string().max(1000, { message: 'errors.maxLength' }).optional(),
}).refine((data) => {
  // For INDIVIDUAL events, client_id is required
  if (data.type === 'INDIVIDUAL') {
    return data.client_id && data.client_id.length > 0;
  }
  return true;
}, {
  message: 'calendar.form.validation.clientRequired',
  path: ['client_id'],
})

export type CreateEventFormData = z.infer<typeof createEventSchema>

export const updateEventSchema = z.object({
  room_id: z.string().min(1, { message: 'calendar.form.validation.roomRequired' }).optional(),
  starts_at: z.string().min(1, { message: 'calendar.form.validation.dateRequired' }).optional(),
  duration_minutes: z
    .number()
    .min(15, { message: 'calendar.form.validation.durationMin' })
    .max(480, { message: 'calendar.form.validation.durationMax' })
    .optional(),
  notes: z.string().max(1000, { message: 'errors.maxLength' }).optional(),
  status: z.enum(['scheduled', 'cancelled', 'completed']).optional(),
})

export type UpdateEventFormData = z.infer<typeof updateEventSchema>

// Custom validation: check if the event move is same-day only
export const isSameDayMove = (originalDate: string, newDate: string): boolean => {
  const original = new Date(originalDate)
  const updated = new Date(newDate)
  return (
    original.getFullYear() === updated.getFullYear() &&
    original.getMonth() === updated.getMonth() &&
    original.getDate() === updated.getDate()
  )
}

export const checkInSchema = z.object({
  attended: z.boolean({ required_error: 'calendar.form.validation.attendedRequired' }),
  notes: z.string().max(500, { message: 'errors.maxLength' }).optional(),
})

export type CheckInFormData = z.infer<typeof checkInSchema>
