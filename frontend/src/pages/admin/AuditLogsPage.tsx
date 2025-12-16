import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { auditLogsApi, auditLogKeys, type AuditLogFilters } from '@/api/admin/auditLogs'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export default function AuditLogsPage() {
  const { t, i18n } = useTranslation(['calendar', 'common'])
  const { user } = useAuth()
  const locale = i18n.language === 'hu' ? hu : enUS

  const [filters, setFilters] = useState<AuditLogFilters>({
    per_page: 50,
    page: 1,
  })

  // Fetch audit logs
  const { data: logsData, isLoading } = useQuery({
    queryKey: auditLogKeys.list(filters),
    queryFn: () => auditLogsApi.list(filters),
    enabled: user?.role === 'admin',
  })

  const handleFilterChange = (key: keyof AuditLogFilters, value: any) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: 1 }))
  }

  const handlePageChange = (newPage: number) => {
    setFilters((prev) => ({ ...prev, page: newPage }))
  }

  const getActionBadgeVariant = (action: string) => {
    switch (action) {
      case 'created':
        return 'default'
      case 'updated':
        return 'secondary'
      case 'deleted':
      case 'cancelled':
        return 'destructive'
      case 'moved':
        return 'outline'
      default:
        return 'default'
    }
  }

  const formatMetaChanges = (meta: any): string => {
    if (!meta) return '-'

    const changes: string[] = []
    const oldData = meta.old || {}
    const newData = meta.new || {}

    Object.keys({ ...oldData, ...newData }).forEach((key) => {
      if (oldData[key] !== newData[key]) {
        if (key === 'starts_at' || key === 'ends_at') {
          const oldValue = oldData[key] ? format(new Date(oldData[key]), 'PPp', { locale }) : '-'
          const newValue = newData[key] ? format(new Date(newData[key]), 'PPp', { locale }) : '-'
          changes.push(`${key}: ${oldValue} → ${newValue}`)
        } else {
          changes.push(`${key}: ${oldData[key] || '-'} → ${newData[key] || '-'}`)
        }
      }
    })

    return changes.join(', ') || '-'
  }

  if (user?.role !== 'admin') {
    return (
      <div className="container py-6">
        <p>{t('common:error')}: {t('common:unauthorized')}</p>
      </div>
    )
  }

  if (isLoading) {
    return (
      <div className="container py-6 space-y-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-[600px] w-full" />
      </div>
    )
  }

  return (
    <div className="container py-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Audit Log</h1>
        <p className="text-muted-foreground">
          Esemény módosítások előzményei
        </p>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle>Szűrők</CardTitle>
          <CardDescription>Szűrd a log bejegyzéseket</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-2">
              <Label htmlFor="action-filter">Művelet típusa</Label>
              <Select
                value={filters.action || 'all'}
                onValueChange={(value) =>
                  handleFilterChange('action', value === 'all' ? undefined : value)
                }
              >
                <SelectTrigger id="action-filter">
                  <SelectValue placeholder="Összes" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Összes</SelectItem>
                  <SelectItem value="created">Létrehozva</SelectItem>
                  <SelectItem value="updated">Módosítva</SelectItem>
                  <SelectItem value="moved">Áthelyezve</SelectItem>
                  <SelectItem value="cancelled">Törölve</SelectItem>
                  <SelectItem value="deleted">Törölve</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="date-from">Dátum-tól</Label>
              <Input
                id="date-from"
                type="date"
                value={filters.date_from?.split('T')[0] || ''}
                onChange={(e) =>
                  handleFilterChange(
                    'date_from',
                    e.target.value ? new Date(e.target.value).toISOString() : undefined
                  )
                }
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="date-to">Dátum-ig</Label>
              <Input
                id="date-to"
                type="date"
                value={filters.date_to?.split('T')[0] || ''}
                onChange={(e) =>
                  handleFilterChange(
                    'date_to',
                    e.target.value ? new Date(e.target.value).toISOString() : undefined
                  )
                }
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Audit Log Table */}
      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Dátum</TableHead>
                <TableHead>Esemény ID</TableHead>
                <TableHead>Művelet</TableHead>
                <TableHead>Felhasználó</TableHead>
                <TableHead>Változások</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logsData && logsData.data.length > 0 ? (
                logsData.data.map((log) => (
                  <TableRow key={log.id}>
                    <TableCell className="whitespace-nowrap">
                      {format(new Date(log.created_at), 'PPp', { locale })}
                    </TableCell>
                    <TableCell>#{log.event_id}</TableCell>
                    <TableCell>
                      <Badge variant={getActionBadgeVariant(log.action)}>
                        {log.action}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div>
                        <div className="font-medium">{log.user?.name}</div>
                        <div className="text-sm text-muted-foreground">
                          {log.user?.email}
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="max-w-md">
                      <div className="text-sm truncate" title={formatMetaChanges(log.meta)}>
                        {formatMetaChanges(log.meta)}
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              ) : (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-muted-foreground">
                    Nincs adat
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {/* Pagination */}
      {logsData && logsData.last_page > 1 && (
        <div className="flex items-center justify-between">
          <div className="text-sm text-muted-foreground">
            Oldal {logsData.current_page} / {logsData.last_page} (Összesen: {logsData.total})
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={logsData.current_page === 1}
              onClick={() => handlePageChange(logsData.current_page - 1)}
            >
              Előző
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={logsData.current_page === logsData.last_page}
              onClick={() => handlePageChange(logsData.current_page + 1)}
            >
              Következő
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
