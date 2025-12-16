export enum UserRole {
  CLIENT = 'client',
  STAFF = 'staff',
  ADMIN = 'admin',
}

export interface User {
  id: string
  email: string
  name: string
  role: UserRole
  phone?: string
  avatarUrl?: string
  createdAt: string
  updatedAt: string
  // Profile relationships (populated based on role)
  client?: { id: number }
  staffProfile?: { id: number }
}

export interface Client extends User {
  role: UserRole.CLIENT
  passCredits: number
  membershipStatus: 'active' | 'inactive' | 'expired'
  membershipExpiresAt?: string
}

export interface Staff extends User {
  role: UserRole.STAFF
  hourlyRate?: number
  skills: string[]
  color?: string // For calendar color coding
}

export interface Admin extends User {
  role: UserRole.ADMIN
}
