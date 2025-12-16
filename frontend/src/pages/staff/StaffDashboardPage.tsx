import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { staffApi, staffKeys } from '@/api/staff'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Calendar, Clock, CheckCircle2, TrendingUp, Download, FileSpreadsheet } from 'lucide-react'
import { useToast } from '@/hooks/use-toast'
import { format, subDays } from 'date-fns'

export default function StaffDashboardPage() {
  const { t } = useTranslation('staff')
  const { toast } = useToast()

  // Dashboard stats
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: staffKeys.dashboard(),
    queryFn: () => staffApi.getDashboardStats(),
    refetchInterval: 60000, // Refresh every minute
  })

  // Export date range state
  const [exportDateFrom, setExportDateFrom] = useState(
    format(subDays(new Date(), 30), 'yyyy-MM-dd')
  )
  const [exportDateTo, setExportDateTo] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [isExporting, setIsExporting] = useState(false)

  const handleExport = async (type: 'payout' | 'attendance') => {
    if (!exportDateFrom || !exportDateTo) {
      toast({
        title: t('common:error'),
        description: t('export.selectDateRange'),
        variant: 'destructive',
      })
      return
    }

    setIsExporting(true)
    try {
      let blob: Blob
      let filename: string

      if (type === 'payout') {
        blob = await staffApi.downloadPayoutXlsx(exportDateFrom, exportDateTo)
        filename = `payout_${exportDateFrom}_${exportDateTo}.xlsx`
      } else {
        blob = await staffApi.downloadAttendanceXlsx(exportDateFrom, exportDateTo)
        filename = `attendance_${exportDateFrom}_${exportDateTo}.xlsx`
      }

      // Create download link and trigger download
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      window.URL.revokeObjectURL(url)

      toast({
        title: t('common:success'),
        description: t('export.downloadSuccess'),
      })
    } catch (error: any) {
      const message = error.response?.data?.message || t('export.downloadFailed')
      toast({
        title: t('common:error'),
        description: message,
        variant: 'destructive',
      })
    } finally {
      setIsExporting(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-gray-500 mt-2">{t('subtitle')}</p>
      </div>

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-4">
        {/* Today's Sessions */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('stats.todaySessions')}
            </CardTitle>
            <Calendar className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{stats?.today_sessions || 0}</div>
            )}
          </CardContent>
        </Card>

        {/* Today's Completed */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('stats.todayCompleted')}
            </CardTitle>
            <CheckCircle2 className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{stats?.today_completed || 0}</div>
            )}
          </CardContent>
        </Card>

        {/* Today's Remaining */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('stats.todayRemaining')}
            </CardTitle>
            <Clock className="h-4 w-4 text-orange-600" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{stats?.today_remaining || 0}</div>
            )}
          </CardContent>
        </Card>

        {/* Week Total Hours */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('stats.weekTotalHours')}
            </CardTitle>
            <TrendingUp className="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">
                {stats?.week_total_hours?.toFixed(1) || 0}h
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Upcoming Session */}
      {stats?.upcoming_session && (
        <Card>
          <CardHeader>
            <CardTitle>{t('upcomingSession.title')}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center justify-between p-4 border rounded-lg">
              <div className="flex-1">
                <h3 className="font-medium">
                  {stats.upcoming_session.client_name || t('upcomingSession.blockTime')}
                </h3>
                <div className="flex items-center gap-4 mt-1 text-sm text-gray-500">
                  <div className="flex items-center gap-1">
                    <Clock className="h-3 w-3" />
                    {format(new Date(stats.upcoming_session.starts_at), 'HH:mm')} -{' '}
                    {format(new Date(stats.upcoming_session.ends_at), 'HH:mm')}
                  </div>
                  <span>• {stats.upcoming_session.room_name}</span>
                  <span>• {stats.upcoming_session.duration_minutes} {t('upcomingSession.minutes')}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Export Section */}
      <Card>
        <CardHeader>
          <CardTitle>{t('export.title')}</CardTitle>
          <CardDescription>{t('export.description')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Date Range Selection */}
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="export-date-from">{t('export.dateFrom')}</Label>
              <Input
                id="export-date-from"
                type="date"
                value={exportDateFrom}
                onChange={(e) => setExportDateFrom(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="export-date-to">{t('export.dateTo')}</Label>
              <Input
                id="export-date-to"
                type="date"
                value={exportDateTo}
                onChange={(e) => setExportDateTo(e.target.value)}
              />
            </div>
          </div>

          {/* Export Buttons */}
          <div className="grid gap-4 md:grid-cols-2">
            {/* Payout Export */}
            <Card className="border-2">
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <FileSpreadsheet className="h-5 w-5 text-blue-600" />
                  {t('export.payoutReport')}
                </CardTitle>
                <CardDescription>{t('export.payoutDescription')}</CardDescription>
              </CardHeader>
              <CardContent>
                <Button
                  onClick={() => handleExport('payout')}
                  disabled={isExporting}
                  className="w-full"
                >
                  <Download className="h-4 w-4 mr-2" />
                  {isExporting ? t('common:loading') : t('export.downloadXlsx')}
                </Button>
              </CardContent>
            </Card>

            {/* Attendance Export */}
            <Card className="border-2">
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <FileSpreadsheet className="h-5 w-5 text-green-600" />
                  {t('export.attendanceReport')}
                </CardTitle>
                <CardDescription>{t('export.attendanceDescription')}</CardDescription>
              </CardHeader>
              <CardContent>
                <Button
                  onClick={() => handleExport('attendance')}
                  disabled={isExporting}
                  className="w-full"
                >
                  <Download className="h-4 w-4 mr-2" />
                  {isExporting ? t('common:loading') : t('export.downloadXlsx')}
                </Button>
              </CardContent>
            </Card>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
