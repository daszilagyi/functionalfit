import { Navigate, type RouteObject } from 'react-router-dom'
import LoginPage from '@/pages/auth/LoginPage'
import DashboardPage from '@/pages/DashboardPage'
import CalendarPage from '@/pages/calendar/CalendarPage'
import ClassesPage from '@/pages/classes/ClassesPage'
import NotFoundPage from '@/pages/NotFoundPage'
import MainLayout from '@/components/layout/MainLayout'
import ProtectedRoute from '@/components/auth/ProtectedRoute'
import AdminDashboardPage from '@/pages/admin/AdminDashboardPage'
import UsersPage from '@/pages/admin/UsersPage'
import RoomsPage from '@/pages/admin/RoomsPage'
import ClassTemplatesPage from '@/pages/admin/ClassTemplatesPage'
import ReportsPage from '@/pages/admin/ReportsPage'
import EmailTemplatesPage from '@/pages/admin/EmailTemplatesPage'
import EventChangesPage from '@/pages/admin/EventChangesPage'
import ServiceTypesPage from '@/pages/admin/ServiceTypesPage'

export const routes: RouteObject[] = [
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/',
    element: <ProtectedRoute><MainLayout /></ProtectedRoute>,
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
            path: 'rooms',
            element: <RoomsPage />,
          },
          {
            path: 'class-templates',
            element: <ClassTemplatesPage />,
          },
          {
            path: 'reports',
            element: <ReportsPage />,
          },
          {
            path: 'email-templates',
            element: <EmailTemplatesPage />,
          },
          {
            path: 'event-changes',
            element: <EventChangesPage />,
          },
          {
            path: 'service-types',
            element: <ServiceTypesPage />,
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
