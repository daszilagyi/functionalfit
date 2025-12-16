import { useState, useMemo, useCallback, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams, Link } from 'react-router-dom'
import { format, startOfWeek, endOfWeek, startOfDay, endOfDay, addWeeks, subWeeks, addDays, subDays, isSameDay, parseISO } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { ChevronLeft, ChevronRight, List, CalendarDays, Calendar, LogOut, Shield, LogIn, User } from 'lucide-react'
import { usePublicClasses } from '@/api/public'
import { useAuth } from '@/hooks/useAuth'
import { PublicClassOccurrence, PublicClassFilters, Site } from '@/types/public'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { PublicClassListView } from '@/components/public/PublicClassListView'
import { PublicClassCard } from '@/components/public/PublicClassCard'

type ViewMode = 'week' | 'day' | 'list'

const SITES: Site[] = ['SASAD', 'TB', 'ÚJBUDA']

export default function PublicClassesPage() {
  const { t, i18n } = useTranslation('public')
  const [searchParams, setSearchParams] = useSearchParams()
  const { isAuthenticated, user } = useAuth()
  const locale = i18n.language === 'hu' ? hu : enUS

  // View state from URL params
  const viewParam = searchParams.get('view') as ViewMode | null
  const [view, setView] = useState<ViewMode>(viewParam || 'week')
  const [currentDate, setCurrentDate] = useState(new Date())
  const [selectedSite, setSelectedSite] = useState<Site | 'all'>('all')

  // Logout handler
  const handleLogout = () => {
    localStorage.removeItem('auth_token')
    window.location.href = '/public/classes'
  }

  // Calculate date range based on view
  const dateRange = useMemo(() => {
    if (view === 'week') {
      return {
        from: format(startOfWeek(currentDate, { weekStartsOn: 1 }), 'yyyy-MM-dd'),
        to: format(endOfWeek(currentDate, { weekStartsOn: 1 }), 'yyyy-MM-dd'),
      }
    } else if (view === 'day') {
      return {
        from: format(startOfDay(currentDate), 'yyyy-MM-dd'),
        to: format(endOfDay(currentDate), 'yyyy-MM-dd'),
      }
    } else {
      // List view: 14 days from today
      return {
        from: format(new Date(), 'yyyy-MM-dd'),
        to: format(addDays(new Date(), 14), 'yyyy-MM-dd'),
      }
    }
  }, [view, currentDate])

  // Build filters
  const filters: PublicClassFilters = useMemo(() => ({
    from: dateRange.from,
    to: dateRange.to,
    ...(selectedSite !== 'all' && { site: selectedSite }),
  }), [dateRange, selectedSite])

  // Fetch classes
  const { data: classes, isLoading, error } = usePublicClasses(filters)

  // Update URL when view changes
  useEffect(() => {
    const newParams = new URLSearchParams(searchParams)
    newParams.set('view', view)
    setSearchParams(newParams, { replace: true })
  }, [view, searchParams, setSearchParams])

  // Navigation handlers
  const goToPrevious = useCallback(() => {
    if (view === 'week') {
      setCurrentDate(prev => subWeeks(prev, 1))
    } else if (view === 'day') {
      setCurrentDate(prev => subDays(prev, 1))
    }
  }, [view])

  const goToNext = useCallback(() => {
    if (view === 'week') {
      setCurrentDate(prev => addWeeks(prev, 1))
    } else if (view === 'day') {
      setCurrentDate(prev => addDays(prev, 1))
    }
  }, [view])

  const goToToday = useCallback(() => {
    setCurrentDate(new Date())
  }, [])

  // Get title based on view
  const getTitle = () => {
    if (view === 'week') {
      const start = startOfWeek(currentDate, { weekStartsOn: 1 })
      const end = endOfWeek(currentDate, { weekStartsOn: 1 })
      return `${format(start, 'MMM d', { locale })} - ${format(end, 'MMM d, yyyy', { locale })}`
    } else if (view === 'day') {
      return format(currentDate, 'EEEE, MMMM d, yyyy', { locale })
    }
    return t('publicClasses.upcomingClasses')
  }

  // Group classes by day for week view
  const classesByDay = useMemo(() => {
    if (!classes || view !== 'week') return null

    const start = startOfWeek(currentDate, { weekStartsOn: 1 })
    const days: { date: Date; classes: PublicClassOccurrence[] }[] = []

    for (let i = 0; i < 7; i++) {
      const day = addDays(start, i)
      const dayClasses = classes.filter(c =>
        isSameDay(parseISO(c.starts_at), day)
      ).sort((a, b) => a.starts_at.localeCompare(b.starts_at))

      days.push({ date: day, classes: dayClasses })
    }

    return days
  }, [classes, currentDate, view])

  // Filter for day view
  const dayClasses = useMemo(() => {
    if (!classes || view !== 'day') return null
    return classes.filter(c =>
      isSameDay(parseISO(c.starts_at), currentDate)
    ).sort((a, b) => a.starts_at.localeCompare(b.starts_at))
  }, [classes, currentDate, view])

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="sticky top-0 z-50 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 border-b">
        {/* Top bar with auth */}
        <div className="border-b bg-muted/50">
          <div className="container mx-auto px-4 py-2 flex items-center justify-between">
            <Link to="/public/classes" className="text-lg font-bold text-primary">
              FunctionalFit
            </Link>
            <div className="flex items-center gap-2">
              {isAuthenticated ? (
                <>
                  {/* User info */}
                  <div className="hidden sm:flex items-center gap-2 text-sm text-muted-foreground">
                    <User className="h-4 w-4" />
                    <span>{user?.name}</span>
                  </div>
                  {/* Admin link */}
                  {(user?.role === 'admin' || user?.role === 'staff') && (
                    <Button variant="ghost" size="sm" asChild>
                      <Link to="/dashboard">
                        <Shield className="h-4 w-4 mr-1" />
                        <span className="hidden sm:inline">{t('common:navigation.dashboard', 'Irányítópult')}</span>
                      </Link>
                    </Button>
                  )}
                  {/* Logout button */}
                  <Button variant="outline" size="sm" onClick={handleLogout}>
                    <LogOut className="h-4 w-4 mr-1" />
                    <span className="hidden sm:inline">{t('common:logout', 'Kijelentkezés')}</span>
                  </Button>
                </>
              ) : (
                <Button variant="outline" size="sm" asChild>
                  <Link to="/login">
                    <LogIn className="h-4 w-4 mr-1" />
                    <span>{t('common:login', 'Bejelentkezés')}</span>
                  </Link>
                </Button>
              )}
            </div>
          </div>
        </div>

        <div className="container mx-auto px-4 py-4">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h1 className="text-2xl font-bold">{t('publicClasses.title')}</h1>

            {/* View Toggle & Filters */}
            <div className="flex flex-wrap items-center gap-2">
              {/* Site Filter */}
              <Select
                value={selectedSite}
                onValueChange={(value) => setSelectedSite(value as Site | 'all')}
              >
                <SelectTrigger className="w-[140px]" data-testid="site-filter">
                  <SelectValue placeholder={t('publicClasses.allSites')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t('publicClasses.allSites')}</SelectItem>
                  {SITES.map((site) => (
                    <SelectItem key={site} value={site}>{site}</SelectItem>
                  ))}
                </SelectContent>
              </Select>

              {/* View Toggle */}
              <div className="flex border rounded-lg overflow-hidden">
                <Button
                  variant={view === 'week' ? 'default' : 'ghost'}
                  size="sm"
                  onClick={() => setView('week')}
                  className="rounded-none"
                  data-testid="view-week-btn"
                >
                  <Calendar className="h-4 w-4 mr-1" />
                  <span className="hidden sm:inline">{t('publicClasses.views.week')}</span>
                </Button>
                <Button
                  variant={view === 'day' ? 'default' : 'ghost'}
                  size="sm"
                  onClick={() => setView('day')}
                  className="rounded-none border-x"
                  data-testid="view-day-btn"
                >
                  <CalendarDays className="h-4 w-4 mr-1" />
                  <span className="hidden sm:inline">{t('publicClasses.views.day')}</span>
                </Button>
                <Button
                  variant={view === 'list' ? 'default' : 'ghost'}
                  size="sm"
                  onClick={() => setView('list')}
                  className="rounded-none"
                  data-testid="view-list-btn"
                >
                  <List className="h-4 w-4 mr-1" />
                  <span className="hidden sm:inline">{t('publicClasses.views.list')}</span>
                </Button>
              </div>
            </div>
          </div>

          {/* Navigation (for week/day views) */}
          {view !== 'list' && (
            <div className="flex items-center justify-between mt-4">
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="icon"
                  onClick={goToPrevious}
                  data-testid="nav-prev-btn"
                >
                  <ChevronLeft className="h-4 w-4" />
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  onClick={goToNext}
                  data-testid="nav-next-btn"
                >
                  <ChevronRight className="h-4 w-4" />
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={goToToday}
                  data-testid="nav-today-btn"
                >
                  {t('publicClasses.today')}
                </Button>
              </div>
              <h2 className="text-lg font-semibold">{getTitle()}</h2>
            </div>
          )}
        </div>
      </header>

      {/* Content */}
      <main className="container mx-auto px-4 py-6">
        {/* Loading State */}
        {isLoading && (
          <div className="space-y-4">
            {view === 'week' ? (
              <div className="grid grid-cols-1 lg:grid-cols-7 gap-4">
                {Array.from({ length: 7 }).map((_, i) => (
                  <div key={i} className="space-y-2">
                    <Skeleton className="h-6 w-full" />
                    <Skeleton className="h-32 w-full" />
                    <Skeleton className="h-32 w-full" />
                  </div>
                ))}
              </div>
            ) : (
              <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {Array.from({ length: 6 }).map((_, i) => (
                  <Skeleton key={i} className="h-48 w-full" />
                ))}
              </div>
            )}
          </div>
        )}

        {/* Error State */}
        {error && (
          <Card className="border-destructive">
            <CardContent className="pt-6">
              <p className="text-destructive">{t('errors.loadFailed')}</p>
            </CardContent>
          </Card>
        )}

        {/* Week View */}
        {!isLoading && !error && view === 'week' && classesByDay && (
          <div className="grid grid-cols-1 lg:grid-cols-7 gap-4" data-testid="week-view">
            {classesByDay.map(({ date, classes: dayClasses }) => {
              const isToday = isSameDay(date, new Date())
              return (
                <div
                  key={date.toISOString()}
                  className={`space-y-2 ${isToday ? 'ring-2 ring-primary rounded-lg p-2' : ''}`}
                >
                  <div className={`text-center p-2 rounded-lg ${isToday ? 'bg-primary text-primary-foreground' : 'bg-muted'}`}>
                    <div className="font-semibold">{format(date, 'EEE', { locale })}</div>
                    <div className="text-sm">{format(date, 'd', { locale })}</div>
                  </div>
                  {dayClasses.length === 0 ? (
                    <div className="text-center text-sm text-muted-foreground py-4">
                      {t('publicClasses.noClassesThisDay')}
                    </div>
                  ) : (
                    <div className="space-y-2">
                      {dayClasses.map((c) => (
                        <PublicClassCard key={c.id} classOccurrence={c} />
                      ))}
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {/* Day View */}
        {!isLoading && !error && view === 'day' && dayClasses && (
          <div data-testid="day-view">
            {dayClasses.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                {t('publicClasses.noClassesThisDay')}
              </div>
            ) : (
              <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {dayClasses.map((c) => (
                  <PublicClassCard key={c.id} classOccurrence={c} />
                ))}
              </div>
            )}
          </div>
        )}

        {/* List View */}
        {!isLoading && !error && view === 'list' && classes && (
          <PublicClassListView classes={classes} />
        )}
      </main>

      {/* Footer */}
      <footer className="border-t mt-8">
        <div className="container mx-auto px-4 py-4 text-center text-sm text-muted-foreground">
          <p>{t('publicClasses.footer.info')}</p>
          <p className="mt-1">
            {t('publicClasses.footer.contact')}
          </p>
        </div>
      </footer>
    </div>
  )
}
