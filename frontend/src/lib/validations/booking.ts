// Booking form validation schemas
import { z } from 'zod'

export const bookClassSchema = z.object({
  client_id: z.string().uuid({ message: 'errors.invalidClient' }).optional(),
  notes: z.string().max(500, { message: 'errors.maxLength' }).optional(),
})

export type BookClassFormData = z.infer<typeof bookClassSchema>

export const cancelBookingSchema = z.object({
  reason: z.string().max(500, { message: 'errors.maxLength' }).optional(),
})

export type CancelBookingFormData = z.infer<typeof cancelBookingSchema>
