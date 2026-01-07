import { RouteObject, Navigate } from 'react-router-dom'
import MainLayout from './components/layout/MainLayout'
import ProtectedRoute from './components/auth/ProtectedRoute'
import LoginPage from './pages/auth/LoginPage'
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage'
import ResetPasswordPage from './pages/auth/ResetPasswordPage'
import DashboardPage from './pages/DashboardPage'
import CalendarPage from './pages/calendar/CalendarPage'
import ClassesPage from './pages/classes/ClassesPage'
import ClientActivityPage from './pages/client/ClientActivityPage'
import StaffDashboardPage from './pages/staff/StaffDashboardPage'
import StaffClientsPage from './pages/staff/StaffClientsPage'
import SettingsPage from './pages/SettingsPage'
import AdminDashboardPage from './pages/admin/AdminDashboardPage'
import UsersPage from './pages/admin/UsersPage'
import SitesPage from './pages/admin/SitesPage'
import RoomsPage from './pages/admin/RoomsPage'
import ClassTemplatesPage from './pages/admin/ClassTemplatesPage'
import EmailTemplatesPage from './pages/admin/EmailTemplatesPage'
import EmailShortcodesPage from './pages/admin/EmailShortcodesPage'
import ReportsPage from './pages/admin/ReportsPage'
import GoogleCalendarSyncPage from './pages/admin/GoogleCalendarSyncPage'
import PricingPage from './pages/admin/PricingPage'
import ServiceTypesPage from './pages/admin/ServiceTypesPage'
import SettlementsPage from './pages/admin/SettlementsPage'
import EventChangesPage from './pages/admin/EventChangesPage'
import ClientImportPage from './pages/admin/ClientImportPage'
import NotFoundPage from './pages/NotFoundPage'
import PublicClassesPage from './pages/public/PublicClassesPage'

export const routes: RouteObject[] = [
  // Public routes (no authentication required)
  {
    path: '/public/classes',
    element: <PublicClassesPage />,
  },
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/forgot-password',
    element: <ForgotPasswordPage />,
  },
  {
    path: '/reset-password',
    element: <ResetPasswordPage />,
  },
  {
    path: '/',
    element: (
      <ProtectedRoute>
        <MainLayout />
      </ProtectedRoute>
    ),
    children: [
      {
        index: true,
        element: <Navigate to="/dashboard" replace />,
      },
      {
        path: 'dashboard',
        element: <DashboardPage />,
      },
      {
        path: 'calendar',
        element: <CalendarPage />,
      },
      {
        path: 'classes',
        element: <ClassesPage />,
      },
      {
        path: 'activity',
        element: <ClientActivityPage />,
      },
      {
        path: 'staff',
        element: <StaffDashboardPage />,
      },
      {
        path: 'clients',
        element: <StaffClientsPage />,
      },
      {
        path: 'settings',
        element: <SettingsPage />,
      },
      {
        path: 'admin',
        children: [
          {
            index: true,
            element: <Navigate to="/admin/dashboard" replace />,
          },
          {
            path: 'dashboard',
            element: <AdminDashboardPage />,
          },
          {
            path: 'users',
            element: <UsersPage />,
          },
          {
            path: 'sites',
            element: <SitesPage />,
          },
          {
            path: 'rooms',
            element: <RoomsPage />,
          },
          {
            path: 'class-templates',
            element: <ClassTemplatesPage />,
          },
          {
            path: 'email-templates',
            element: <EmailTemplatesPage />,
          },
          {
            path: 'email-shortcodes',
            element: <EmailShortcodesPage />,
          },
          {
            path: 'reports',
            element: <ReportsPage />,
          },
          {
            path: 'google-calendar-sync',
            element: <GoogleCalendarSyncPage />,
          },
          {
            path: 'pricing',
            element: <PricingPage />,
          },
          {
            path: 'service-types',
            element: <ServiceTypesPage />,
          },
          {
            path: 'settlements',
            element: <SettlementsPage />,
          },
          {
            path: 'event-changes',
            element: <EventChangesPage />,
          },
          {
            path: 'client-import',
            element: <ClientImportPage />,
          },
        ],
      },
    ],
  },
  {
    path: '*',
    element: <NotFoundPage />,
  },
]
