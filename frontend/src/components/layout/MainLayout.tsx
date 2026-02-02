import { Outlet, Link, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Calendar, LayoutDashboard, Users, UserPlus, LogOut, Menu, Activity, ClipboardList, Shield, ChevronDown, ChevronRight, DoorOpen, FileText, Dumbbell, Mail, Code, History, RefreshCw, Settings, MapPin, DollarSign, Receipt, Tag, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/hooks/useAuth'
import { useState } from 'react'
import { cn } from '@/lib/utils'

export default function MainLayout() {
  const { t } = useTranslation()
  const location = useLocation()
  const { user } = useAuth()
  const [sidebarOpen, setSidebarOpen] = useState(window.innerWidth >= 1024)
  const [adminMenuOpen, setAdminMenuOpen] = useState(location.pathname.startsWith('/admin'))

  const navigation = [
    { name: t('navigation.dashboard'), href: '/dashboard', icon: LayoutDashboard },
    // Calendar is only for staff and admin - clients book classes from the Classes page
    ...(user?.role === 'staff' || user?.role === 'admin' ? [{ name: t('navigation.calendar'), href: '/calendar', icon: Calendar }] : []),
    { name: t('navigation.classes'), href: '/classes', icon: Users },
    // Activity page - staff see their sessions, clients see their bookings/passes
    { name: user?.role === 'staff' || user?.role === 'admin' ? t('staff:activity.title') : t('client:myActivity'), href: '/activity', icon: Activity },
    ...(user?.role === 'staff' || user?.role === 'admin' ? [{ name: t('staff:title'), href: '/staff', icon: ClipboardList }] : []),
    // Clients management for staff and admin
    ...(user?.role === 'staff' || user?.role === 'admin' ? [{ name: 'Vendégek', href: '/clients', icon: UserPlus }] : []),
    { name: t('navigation.settings'), href: '/settings', icon: Settings },
  ]

  const adminNavigation = user?.role === 'admin' ? [
    { name: t('admin:dashboard.title'), href: '/admin/dashboard', icon: LayoutDashboard },
    { name: t('admin:users.title'), href: '/admin/users', icon: Users },
    { name: t('admin:clientImport.title', 'Vendég import'), href: '/admin/client-import', icon: Upload },
    { name: t('admin:sites.title'), href: '/admin/sites', icon: MapPin },
    { name: t('admin:rooms.title'), href: '/admin/rooms', icon: DoorOpen },
    { name: t('admin:classTemplates.title'), href: '/admin/class-templates', icon: Dumbbell },
    { name: t('admin:emailTemplates.title'), href: '/admin/email-templates', icon: Mail },
    { name: 'Shortcode-ok', href: '/admin/email-shortcodes', icon: Code },
    { name: t('admin:googleCalendarSync.title'), href: '/admin/google-calendar-sync', icon: RefreshCw },
    { name: t('admin:pricing.title'), href: '/admin/pricing', icon: DollarSign },
    { name: t('admin:serviceTypes.title'), href: '/admin/service-types', icon: Tag },
    { name: t('admin:settlements.title'), href: '/admin/settlements', icon: Receipt },
    { name: t('admin:reports.title'), href: '/admin/reports', icon: FileText },
    { name: t('admin:eventChanges.title'), href: '/admin/event-changes', icon: History },
    { name: t('admin:settings.title'), href: '/admin/settings', icon: Settings },
  ] : []

  const handleLogout = () => {
    localStorage.removeItem('auth_token')
    window.location.href = '/login'
  }

  // Close sidebar when clicking a link on mobile
  const handleNavClick = () => {
    if (window.innerWidth < 1024) {
      setSidebarOpen(false)
    }
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Mobile overlay - click to close sidebar */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/50 lg:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Sidebar */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transition-transform duration-300 flex flex-col',
          !sidebarOpen && '-translate-x-full'
        )}
      >
        <div className="flex h-16 items-center justify-between px-6 border-b shrink-0">
          <h1 className="text-xl font-bold text-primary">FunctionalFit</h1>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden"
          >
            <Menu className="h-5 w-5" />
          </Button>
        </div>

        <nav className="flex-1 space-y-1 px-3 py-4 overflow-y-auto">
          {navigation.map((item) => {
            const isActive = location.pathname === item.href
            const Icon = item.icon
            return (
              <Link
                key={item.name}
                to={item.href}
                onClick={handleNavClick}
                className={cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-primary text-primary-foreground'
                    : 'text-gray-700 hover:bg-gray-100'
                )}
              >
                <Icon className="h-5 w-5" />
                {item.name}
              </Link>
            )
          })}

          {/* Admin Section */}
          {user?.role === 'admin' && (
            <div className="space-y-1">
              <button
                onClick={() => setAdminMenuOpen(!adminMenuOpen)}
                className={cn(
                  'w-full flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  location.pathname.startsWith('/admin')
                    ? 'bg-primary/10 text-primary'
                    : 'text-gray-700 hover:bg-gray-100'
                )}
              >
                <div className="flex items-center gap-3">
                  <Shield className="h-5 w-5" />
                  {t('admin:title')}
                </div>
                {adminMenuOpen ? (
                  <ChevronDown className="h-4 w-4" />
                ) : (
                  <ChevronRight className="h-4 w-4" />
                )}
              </button>

              {adminMenuOpen && (
                <div className="ml-4 space-y-1 border-l-2 border-gray-200 pl-2">
                  {adminNavigation.map((item) => {
                    const isActive = location.pathname === item.href
                    const Icon = item.icon
                    return (
                      <Link
                        key={item.name}
                        to={item.href}
                        onClick={handleNavClick}
                        className={cn(
                          'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                          isActive
                            ? 'bg-primary text-primary-foreground'
                            : 'text-gray-600 hover:bg-gray-100'
                        )}
                      >
                        <Icon className="h-4 w-4" />
                        {item.name}
                      </Link>
                    )
                  })}
                </div>
              )}
            </div>
          )}
        </nav>

        <div className="border-t p-4 shrink-0">
          <div className="flex items-center gap-3 mb-3">
            <div className="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
              {user?.name.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-gray-900 truncate">{user?.name}</p>
              <p className="text-xs text-gray-500 truncate">{t(`roles.${user?.role}`)}</p>
            </div>
          </div>
          <Button
            variant="outline"
            className="w-full justify-start"
            onClick={handleLogout}
          >
            <LogOut className="mr-2 h-4 w-4" />
            {t('logout')}
          </Button>
        </div>
      </aside>

      {/* Main content */}
      <div className={cn('transition-all duration-300', sidebarOpen ? 'lg:pl-64' : '')}>
        <header className="sticky top-0 z-40 bg-white border-b border-gray-200">
          <div className="flex h-16 items-center gap-4 px-6">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setSidebarOpen(!sidebarOpen)}
            >
              <Menu className="h-5 w-5" />
            </Button>
            <div className="flex-1" />
          </div>
        </header>

        <main className="p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
