import { useAuth } from '@/hooks/useAuth'
import ClientActivityPage from './client/ClientActivityPage'
import StaffActivityPage from './staff/StaffActivityPage'

/**
 * Router component that displays the appropriate activity page based on user role.
 * - Staff and Admin users see StaffActivityPage (their events/sessions)
 * - Client users see ClientActivityPage (their bookings and passes)
 */
export default function ActivityPageRouter() {
  const { user } = useAuth()

  // Staff and Admin see the staff activity page
  if (user?.role === 'staff' || user?.role === 'admin') {
    return <StaffActivityPage />
  }

  // Clients see the client activity page
  return <ClientActivityPage />
}
