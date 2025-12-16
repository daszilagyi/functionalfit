// Authentication and registration validation schemas
import { z } from 'zod'

export const quickRegisterSchema = z.object({
  name: z.string().min(1, { message: 'errors.nameRequired' }),
  email: z.string().email({ message: 'errors.invalidEmail' }),
  password: z.string().min(8, { message: 'errors.passwordMinLength' }),
  phone: z.string().optional(),
})

export type QuickRegisterFormData = z.infer<typeof quickRegisterSchema>

export const loginSchema = z.object({
  email: z.string().email({ message: 'errors.invalidEmail' }),
  password: z.string().min(1, { message: 'errors.passwordRequired' }),
})

export type LoginFormData = z.infer<typeof loginSchema>
