import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { reportsApi, adminKeys } from '@/api/admin'
import { adminReportsApi } from '@/api/reports'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useToast } from '@/hooks/use-toast'
import { FileDown, TrendingUp, DollarSign, Users, Activity, Calendar } from 'lucide-react'
import { format, subDays } from 'date-fns'

export default function EnhancedReportsPage() {
  const { t } = useTranslation(['admin', 'common'])
  const { toast } = useToast()

  // Date range state - default to last 30 days
  const [dateFrom, setDateFrom] = useState(format(subDays(new Date(), 30), 'yyyy-MM-dd'))
  const [dateTo, setDateTo] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [isExporting, setIsExporting] = useState(false)

  // Fetch reports
  const { data: attendanceData, isLoading: isLoadingAttendance } = useQuery({
    queryKey: adminKeys.attendanceReport(dateFrom, dateTo),
    queryFn: () => reportsApi.attendance(dateFrom, dateTo),
  })

  const { data: payoutData, isLoading: isLoadingPayout } = useQuery({
    queryKey: adminKeys.payoutReport(dateFrom, dateTo),
    queryFn: () => reportsApi.payouts(dateFrom, dateTo),
  })

  const { data: revenueData, isLoading: isLoadingRevenue } = useQuery({
    queryKey: adminKeys.revenueReport(dateFrom, dateTo),
    queryFn: () => reportsApi.revenue(dateFrom, dateTo),
  })

  const { data: utilizationData, isLoading: isLoadingUtilization } = useQuery({
    queryKey: adminKeys.utilizationReport(dateFrom, dateTo),
    queryFn: () => reportsApi.utilization(dateFrom, dateTo),
  })

  const { data: clientActivityData, isLoading: isLoadingClientActivity } = useQuery({
    queryKey: adminKeys.clientActivityReport(dateFrom, dateTo),
    queryFn: () => reportsApi.clientActivity(dateFrom, dateTo),
  })

  const handleExport = async (
    reportType: 'attendance' | 'payouts' | 'revenue' | 'utilization' | 'clients'
  ) => {
    try {
      setIsExporting(true)
      toast({
        title: t('reports.downloading'),
        description: t('reports.exportToExcel'),
      })

      const blob = await adminReportsApi.exportReport(reportType, dateFrom, dateTo, 'xlsx')

      // Create download link
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `${reportType}_${dateFrom}_${dateTo}.xlsx`
      document.body.appendChild(link)
      link.click()

      // Cleanup
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)

      toast({
        title: t('reports.downloadSuccess'),
        description: `${reportType}_${dateFrom}_${dateTo}.xlsx`,
      })
    } catch (error) {
      console.error('Export error:', error)
      toast({
        variant: 'destructive',
        title: t('reports.downloadError'),
        description: t('common:errorOccurred'),
      })
    } finally {
      setIsExporting(false)
    }
  }

  return (
    <div className="space-y-6" data-testid="admin-reports-page">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('reports.title')}</h1>
        <p className="text-gray-500 mt-2">{t('reports.subtitle')}</p>
      </div>

      {/* Date Range Filter */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-end gap-4 flex-wrap">
            <div className="space-y-2">
              <Label htmlFor="date_from">{t('reports.dateFrom')}</Label>
              <Input
                id="date_from"
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                data-testid="reports-date-from"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="date_to">{t('reports.dateTo')}</Label>
              <Input
                id="date_to"
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                data-testid="reports-date-to"
              />
            </div>
            <Button
              variant="outline"
              onClick={() => {
                setDateFrom(format(subDays(new Date(), 30), 'yyyy-MM-dd'))
                setDateTo(format(new Date(), 'yyyy-MM-dd'))
              }}
              data-testid="reports-last-30-days-btn"
            >
              {t('reports.last30Days')}
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setDateFrom(format(subDays(new Date(), 7), 'yyyy-MM-dd'))
                setDateTo(format(new Date(), 'yyyy-MM-dd'))
              }}
              data-testid="reports-last-7-days-btn"
            >
              {t('reports.last7Days')}
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Reports Tabs */}
      <Tabs defaultValue="attendance" className="space-y-4">
        <TabsList className="grid w-full grid-cols-5">
          <TabsTrigger value="attendance" data-testid="tab-attendance">
            <Calendar className="h-4 w-4 mr-2" />
            {t('reports.attendance')}
          </TabsTrigger>
          <TabsTrigger value="payouts" data-testid="tab-payouts">
            <DollarSign className="h-4 w-4 mr-2" />
            {t('reports.payouts')}
          </TabsTrigger>
          <TabsTrigger value="revenue" data-testid="tab-revenue">
            <TrendingUp className="h-4 w-4 mr-2" />
            {t('reports.revenue')}
          </TabsTrigger>
          <TabsTrigger value="utilization" data-testid="tab-utilization">
            <Activity className="h-4 w-4 mr-2" />
            {t('reports.utilization')}
          </TabsTrigger>
          <TabsTrigger value="clients" data-testid="tab-clients">
            <Users className="h-4 w-4 mr-2" />
            {t('reports.clientActivity')}
          </TabsTrigger>
        </TabsList>

        {/* Attendance Report */}
        <TabsContent value="attendance" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle>{t('reports.attendanceReport')}</CardTitle>
                <CardDescription>
                  {dateFrom} - {dateTo}
                </CardDescription>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleExport('attendance')}
                disabled={isExporting || isLoadingAttendance}
                data-testid="export-attendance-btn"
              >
                <FileDown className="h-4 w-4 mr-2" />
                {isExporting ? t('reports.downloading') : t('reports.exportToExcel')}
              </Button>
            </CardHeader>
            <CardContent>
              {isLoadingAttendance ? (
                <div className="space-y-4">
                  <Skeleton className="h-20 w-full" />
                  <Skeleton className="h-40 w-full" />
                </div>
              ) : attendanceData ? (
                <div className="space-y-6">
                  {/* Summary Cards */}
                  <div className="grid gap-4 md:grid-cols-4">
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.totalSessions')}</p>
                      <p className="text-2xl font-bold">{attendanceData.summary.total_sessions}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.attended')}</p>
                      <p className="text-2xl font-bold text-green-600">{attendanceData.summary.total_attended}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.noShows')}</p>
                      <p className="text-2xl font-bold text-red-600">{attendanceData.summary.total_no_shows}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.attendanceRate')}</p>
                      <p className="text-2xl font-bold">{attendanceData.summary.attendance_rate.toFixed(1)}%</p>
                    </div>
                  </div>

                  {/* Breakdown by Type */}
                  <div>
                    <h3 className="text-lg font-semibold mb-4">{t('reports.byType')}</h3>
                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="p-4 border rounded-lg">
                        <p className="text-sm font-medium mb-2">{t('reports.individualSessions')}</p>
                        <div className="space-y-1 text-sm">
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">{t('reports.total')}:</span>
                            <span className="font-medium">{attendanceData.by_type.individual.total}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">{t('reports.attended')}:</span>
                            <span className="font-medium text-green-600">{attendanceData.by_type.individual.attended}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">{t('reports.noShows')}:</span>
                            <span className="font-medium text-red-600">{attendanceData.by_type.individual.no_shows}</span>
                          </div>
                        </div>
                      </div>
                      <div className="p-4 border rounded-lg">
                        <p className="text-sm font-medium mb-2">{t('reports.groupClasses')}</p>
                        <div className="space-y-1 text-sm">
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">{t('reports.total')}:</span>
                            <span className="font-medium">{attendanceData.by_type.group_classes.total}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">{t('reports.attended')}:</span>
                            <span className="font-medium text-green-600">{attendanceData.by_type.group_classes.attended}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">{t('reports.noShows')}:</span>
                            <span className="font-medium text-red-600">{attendanceData.by_type.group_classes.no_shows}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ) : (
                <p className="text-center text-gray-500 py-8">{t('reports.noDataForPeriod')}</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Payout Report */}
        <TabsContent value="payouts" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle>{t('reports.payoutsReport')}</CardTitle>
                <CardDescription>
                  {dateFrom} - {dateTo}
                </CardDescription>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleExport('payouts')}
                disabled={isExporting || isLoadingPayout}
                data-testid="export-payouts-btn"
              >
                <FileDown className="h-4 w-4 mr-2" />
                {isExporting ? t('reports.downloading') : t('reports.exportToExcel')}
              </Button>
            </CardHeader>
            <CardContent>
              {isLoadingPayout ? (
                <div className="space-y-4">
                  <Skeleton className="h-20 w-full" />
                  <Skeleton className="h-64 w-full" />
                </div>
              ) : payoutData ? (
                <div className="space-y-6">
                  {/* Summary Cards */}
                  <div className="grid gap-4 md:grid-cols-4">
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.totalEntryFee')}</p>
                      <p className="text-2xl font-bold">{(payoutData.summary.total_entry_fee || 0).toLocaleString()} {payoutData.summary.currency}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.totalTrainerFee')}</p>
                      <p className="text-2xl font-bold">{(payoutData.summary.total_trainer_fee || 0).toLocaleString()} {payoutData.summary.currency}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.totalRevenue')}</p>
                      <p className="text-2xl font-bold">{(payoutData.summary.total_revenue || 0).toLocaleString()} {payoutData.summary.currency}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.staffCount')}</p>
                      <p className="text-2xl font-bold">{payoutData.summary.staff_count}</p>
                    </div>
                  </div>

                  {/* Staff Payouts Table */}
                  <div>
                    <h3 className="text-lg font-semibold mb-4">{t('reports.staffBreakdown')}</h3>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{t('reports.staffName')}</TableHead>
                          <TableHead className="text-right">{t('reports.entryFee')}</TableHead>
                          <TableHead className="text-right">{t('reports.trainerFee')}</TableHead>
                          <TableHead className="text-right">{t('reports.individualCount')}</TableHead>
                          <TableHead className="text-right">{t('reports.groupCount')}</TableHead>
                          <TableHead className="text-right">{t('reports.totalRevenue')}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {payoutData.staff_payouts.map((staff) => (
                          <TableRow key={staff.staff_id}>
                            <TableCell className="font-medium">{staff.name}</TableCell>
                            <TableCell className="text-right">{(staff.entry_fee || 0).toLocaleString()} {payoutData.summary.currency}</TableCell>
                            <TableCell className="text-right">{(staff.trainer_fee || 0).toLocaleString()} {payoutData.summary.currency}</TableCell>
                            <TableCell className="text-right">{staff.individual_count || 0}</TableCell>
                            <TableCell className="text-right">{staff.group_count || 0}</TableCell>
                            <TableCell className="text-right font-bold">{(staff.total_revenue || 0).toLocaleString()} {payoutData.summary.currency}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              ) : (
                <p className="text-center text-gray-500 py-8">{t('reports.noDataForPeriod')}</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Revenue Report */}
        <TabsContent value="revenue" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle>{t('reports.revenueReport')}</CardTitle>
                <CardDescription>
                  {dateFrom} - {dateTo}
                </CardDescription>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleExport('revenue')}
                disabled={isExporting || isLoadingRevenue}
                data-testid="export-revenue-btn"
              >
                <FileDown className="h-4 w-4 mr-2" />
                {isExporting ? t('reports.downloading') : t('reports.exportToExcel')}
              </Button>
            </CardHeader>
            <CardContent>
              {isLoadingRevenue ? (
                <div className="space-y-4">
                  <Skeleton className="h-20 w-full" />
                  <Skeleton className="h-40 w-full" />
                </div>
              ) : revenueData ? (
                <div className="space-y-6">
                  {/* Summary Cards */}
                  <div className="grid gap-4 md:grid-cols-3">
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.totalRevenue')}</p>
                      <p className="text-2xl font-bold">{revenueData.summary.total_revenue.toLocaleString()} {revenueData.summary.currency}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.passesSold')}</p>
                      <p className="text-2xl font-bold">{revenueData.summary.total_passes_sold}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.averagePassPrice')}</p>
                      <p className="text-2xl font-bold">{revenueData.summary.average_pass_price.toLocaleString()} {revenueData.summary.currency}</p>
                    </div>
                  </div>

                  {/* Breakdown by Status */}
                  <div>
                    <h3 className="text-lg font-semibold mb-4">{t('reports.byType')}</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                      <div className="p-4 border rounded-lg">
                        <p className="text-sm font-medium text-green-600">{t('users.status.active')}</p>
                        <p className="text-2xl font-bold">{revenueData.by_status.active}</p>
                      </div>
                      <div className="p-4 border rounded-lg">
                        <p className="text-sm font-medium text-gray-600">{t('passes.status.expired')}</p>
                        <p className="text-2xl font-bold">{revenueData.by_status.expired}</p>
                      </div>
                      <div className="p-4 border rounded-lg">
                        <p className="text-sm font-medium text-blue-600">{t('passes.status.fullyUsed')}</p>
                        <p className="text-2xl font-bold">{revenueData.by_status.fully_used}</p>
                      </div>
                    </div>
                  </div>
                </div>
              ) : (
                <p className="text-center text-gray-500 py-8">{t('reports.noDataForPeriod')}</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Utilization Report */}
        <TabsContent value="utilization" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle>{t('reports.utilizationReport')}</CardTitle>
                <CardDescription>
                  {dateFrom} - {dateTo}
                </CardDescription>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleExport('utilization')}
                disabled={isExporting || isLoadingUtilization}
                data-testid="export-utilization-btn"
              >
                <FileDown className="h-4 w-4 mr-2" />
                {isExporting ? t('reports.downloading') : t('reports.exportToExcel')}
              </Button>
            </CardHeader>
            <CardContent>
              {isLoadingUtilization ? (
                <div className="space-y-4">
                  <Skeleton className="h-20 w-full" />
                  <Skeleton className="h-64 w-full" />
                </div>
              ) : utilizationData ? (
                <div className="space-y-6">
                  {/* Summary Cards */}
                  <div className="grid gap-4 md:grid-cols-3">
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.individualSessions')}</p>
                      <p className="text-2xl font-bold">{utilizationData.summary.total_individual_sessions}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.groupClasses')}</p>
                      <p className="text-2xl font-bold">{utilizationData.summary.total_group_classes}</p>
                    </div>
                    <div className="p-4 border rounded-lg">
                      <p className="text-sm font-medium text-muted-foreground">{t('reports.totalSessions')}</p>
                      <p className="text-2xl font-bold">{utilizationData.summary.total_sessions}</p>
                    </div>
                  </div>

                  {/* Staff Utilization Table */}
                  <div>
                    <h3 className="text-lg font-semibold mb-4">{t('reports.byTrainer')}</h3>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{t('reports.staffName')}</TableHead>
                          <TableHead className="text-right">{t('reports.individualSessions')}</TableHead>
                          <TableHead className="text-right">{t('reports.groupClasses')}</TableHead>
                          <TableHead className="text-right">{t('reports.total')}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {utilizationData.staff_utilization.map((staff) => (
                          <TableRow key={staff.staff_id}>
                            <TableCell className="font-medium">{staff.name}</TableCell>
                            <TableCell className="text-right">{staff.individual_sessions}</TableCell>
                            <TableCell className="text-right">{staff.group_classes}</TableCell>
                            <TableCell className="text-right font-bold">{staff.total_sessions}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              ) : (
                <p className="text-center text-gray-500 py-8">{t('reports.noDataForPeriod')}</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Client Activity Report */}
        <TabsContent value="clients" className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle>{t('reports.clientActivityReport')}</CardTitle>
                <CardDescription>
                  {dateFrom} - {dateTo}
                </CardDescription>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleExport('clients')}
                disabled={isExporting || isLoadingClientActivity}
                data-testid="export-clients-btn"
              >
                <FileDown className="h-4 w-4 mr-2" />
                {isExporting ? t('reports.downloading') : t('reports.exportToExcel')}
              </Button>
            </CardHeader>
            <CardContent>
              {isLoadingClientActivity ? (
                <div className="space-y-4">
                  <Skeleton className="h-20 w-full" />
                  <Skeleton className="h-64 w-full" />
                </div>
              ) : clientActivityData ? (
                <div className="space-y-6">
                  {/* Summary */}
                  <div className="p-4 border rounded-lg">
                    <p className="text-sm font-medium text-muted-foreground">{t('dashboard.totalUsers')}</p>
                    <p className="text-2xl font-bold">{clientActivityData.summary.total_active_clients}</p>
                  </div>

                  {/* Client Activity Table */}
                  <div>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{t('reports.clientName')}</TableHead>
                          <TableHead>{t('reports.email')}</TableHead>
                          <TableHead className="text-right">{t('reports.totalSessions')}</TableHead>
                          <TableHead className="text-right">{t('reports.attended')}</TableHead>
                          <TableHead className="text-right">{t('reports.noShows')}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {clientActivityData.clients.map((client) => (
                          <TableRow key={client.client_id}>
                            <TableCell className="font-medium">{client.name}</TableCell>
                            <TableCell>{client.email}</TableCell>
                            <TableCell className="text-right">{client.total_sessions}</TableCell>
                            <TableCell className="text-right text-green-600">{client.attended}</TableCell>
                            <TableCell className="text-right text-red-600">{client.no_shows}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              ) : (
                <p className="text-center text-gray-500 py-8">{t('reports.noDataForPeriod')}</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
