import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { Calendar, User, Clock, AlertCircle, MapPin, DoorOpen, ArrowUpDown } from 'lucide-react'
import { calendarChangesApi, adminKeys, roomsApi, usersApi } from '@/api/admin'
import type { CalendarChangeFilters } from '@/types/admin'
import { CalendarChangeDetailModal } from '@/components/admin/CalendarChangeDetailModal'
import { cn } from '@/lib/utils'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Button } from '@/components/ui/button'

export default function EventChangesPage() {
  const { t, i18n } = useTranslation('admin')
  const locale = i18n.language === 'hu' ? hu : enUS

  const [selectedChangeId, setSelectedChangeId] = useState<number | null>(null)
  const [detailModalOpen, setDetailModalOpen] = useState(false)

  const [filters, setFilters] = useState<CalendarChangeFilters>({
    actorUserId: undefined,
    roomId: undefined,
    site: undefined,
    action: undefined,
    changedFrom: undefined,
    changedTo: undefined,
    sort: 'changed_at',
    order: 'desc',
    page: 1,
    perPage: 50,
  })

  // Fetch calendar changes
  const { data, isLoading, error } = useQuery({
    queryKey: adminKeys.calendarChangesList(filters),
    queryFn: () => calendarChangesApi.list(filters),
  })

  // Fetch rooms for filter dropdown
  const { data: rooms } = useQuery({
    queryKey: adminKeys.roomsList(),
    queryFn: () => roomsApi.list(),
  })

  // Fetch staff for filter dropdown
  const { data: staffList } = useQuery({
    queryKey: adminKeys.usersList({ role: 'staff' }),
    queryFn: () => usersApi.list({ role: 'staff' }),
  })

  const getActionBadge = (action: string) => {
    const badges = {
      EVENT_CREATED: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      EVENT_UPDATED: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      EVENT_DELETED: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }
    return badges[action as keyof typeof badges] || 'bg-gray-100 text-gray-800'
  }

  const handleRowClick = (changeId: number) => {
    setSelectedChangeId(changeId)
    setDetailModalOpen(true)
  }

  const toggleSortOrder = () => {
    setFilters({
      ...filters,
      order: filters.order === 'desc' ? 'asc' : 'desc',
    })
  }

  const resetFilters = () => {
    setFilters({
      actorUserId: undefined,
      roomId: undefined,
      site: undefined,
      action: undefined,
      changedFrom: undefined,
      changedTo: undefined,
      sort: 'changed_at',
      order: 'desc',
      page: 1,
      perPage: 50,
    })
  }

  return (
    <div className="container mx-auto p-6">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
          {t('calendarChanges.title')}
        </h1>
        <p className="text-gray-600 dark:text-gray-400">
          {t('calendarChanges.description')}
        </p>
      </div>

      {/* Filters */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
          {/* Site Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('calendarChanges.filters.site')}
            </label>
            <Select
              value={filters.site || 'all'}
              onValueChange={(value) =>
                setFilters({ ...filters, site: value === 'all' ? undefined : value })
              }
            >
              <SelectTrigger data-testid="calendar-changes-site-filter">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('calendarChanges.filters.allSites')}</SelectItem>
                <SelectItem value="SASAD">SASAD</SelectItem>
                <SelectItem value="TB">TB</SelectItem>
                <SelectItem value="ÚJBUDA">ÚJBUDA</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Room Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('calendarChanges.filters.room')}
            </label>
            <Select
              value={filters.roomId?.toString() || 'all'}
              onValueChange={(value) =>
                setFilters({ ...filters, roomId: value === 'all' ? undefined : parseInt(value) })
              }
            >
              <SelectTrigger data-testid="calendar-changes-room-filter">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('calendarChanges.filters.allRooms')}</SelectItem>
                {rooms?.map((room) => (
                  <SelectItem key={room.id} value={room.id.toString()}>
                    {room.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Actor/Staff Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('calendarChanges.filters.actor')}
            </label>
            <Select
              value={filters.actorUserId?.toString() || 'all'}
              onValueChange={(value) =>
                setFilters({ ...filters, actorUserId: value === 'all' ? undefined : parseInt(value) })
              }
            >
              <SelectTrigger data-testid="calendar-changes-actor-filter">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('calendarChanges.filters.allActors')}</SelectItem>
                {staffList?.data?.map((staff) => (
                  <SelectItem key={staff.id} value={staff.id.toString()}>
                    {staff.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Action Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('eventChanges.filters.action')}
            </label>
            <Select
              value={filters.action || 'all'}
              onValueChange={(value) =>
                setFilters({ ...filters, action: value === 'all' ? undefined : value })
              }
            >
              <SelectTrigger data-testid="calendar-changes-action-filter">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('eventChanges.filters.allActions')}</SelectItem>
                <SelectItem value="EVENT_CREATED">
                  {t('calendarChanges.actions.created')}
                </SelectItem>
                <SelectItem value="EVENT_UPDATED">
                  {t('calendarChanges.actions.updated')}
                </SelectItem>
                <SelectItem value="EVENT_DELETED">
                  {t('calendarChanges.actions.deleted')}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Date From */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('eventChanges.filters.dateFrom')}
            </label>
            <input
              type="date"
              className="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              value={filters.changedFrom || ''}
              onChange={(e) =>
                setFilters({ ...filters, changedFrom: e.target.value || undefined })
              }
              data-testid="calendar-changes-date-from"
            />
          </div>

          {/* Date To */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('eventChanges.filters.dateTo')}
            </label>
            <input
              type="date"
              className="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              value={filters.changedTo || ''}
              onChange={(e) =>
                setFilters({ ...filters, changedTo: e.target.value || undefined })
              }
              data-testid="calendar-changes-date-to"
            />
          </div>
        </div>

        {/* Filter Actions */}
        <div className="flex gap-2 mt-4">
          <Button
            variant="outline"
            onClick={resetFilters}
            data-testid="calendar-changes-reset-filters"
          >
            {t('eventChanges.filters.reset')}
          </Button>
          <Button
            variant="outline"
            onClick={toggleSortOrder}
            className="flex items-center gap-2"
            data-testid="calendar-changes-sort-toggle"
          >
            <ArrowUpDown className="w-4 h-4" />
            {filters.order === 'desc'
              ? t('calendarChanges.filters.sortDesc')
              : t('calendarChanges.filters.sortAsc')}
          </Button>
        </div>
      </div>

      {/* Results */}
      {isLoading && (
        <div className="text-center py-12">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p className="mt-2 text-gray-600 dark:text-gray-400">{t('common:loading')}</p>
        </div>
      )}

      {error && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 flex items-start">
          <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 mr-3 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <h3 className="text-sm font-medium text-red-800 dark:text-red-400">
              {t('common:error')}
            </h3>
            <p className="text-sm text-red-700 dark:text-red-300 mt-1">
              {(error as Error).message}
            </p>
          </div>
        </div>
      )}

      {data && (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {t('calendarChanges.table.timestamp')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {t('calendarChanges.table.action')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {t('calendarChanges.table.staff')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {t('calendarChanges.table.site')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {t('calendarChanges.table.room')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {t('calendarChanges.table.eventTime')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {t('calendarChanges.table.summary')}
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {data.data.length === 0 ? (
                  <tr>
                    <td
                      colSpan={7}
                      className="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                    >
                      {t('eventChanges.noResults')}
                    </td>
                  </tr>
                ) : (
                  data.data.map((change) => (
                    <tr
                      key={change.id}
                      className="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors"
                      onClick={() => handleRowClick(change.id)}
                      data-testid={`calendar-change-row-${change.id}`}
                    >
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center text-sm text-gray-900 dark:text-white">
                          <Clock className="w-4 h-4 mr-2 text-gray-400" />
                          {format(new Date(change.changed_at), 'PPp', { locale })}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span
                          className={cn(
                            'px-2 py-1 text-xs font-semibold rounded-full',
                            getActionBadge(change.action)
                          )}
                        >
                          {t(
                            `calendarChanges.actions.${change.action
                              .toLowerCase()
                              .replace('event_', '')}`,
                            change.action
                          )}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <User className="w-4 h-4 mr-2 text-gray-400" />
                          <div className="text-sm">
                            <div className="font-medium text-gray-900 dark:text-white">
                              {change.actor.name}
                            </div>
                            <div className="text-gray-500 dark:text-gray-400">
                              {change.actor.role}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {change.site ? (
                          <div className="flex items-center text-sm text-gray-900 dark:text-white">
                            <MapPin className="w-4 h-4 mr-2 text-gray-400" />
                            {change.site}
                          </div>
                        ) : (
                          <span className="text-sm text-gray-400">-</span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {change.room ? (
                          <div className="flex items-center text-sm text-gray-900 dark:text-white">
                            <DoorOpen className="w-4 h-4 mr-2 text-gray-400" />
                            {change.room.name}
                          </div>
                        ) : (
                          <span className="text-sm text-gray-400">-</span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {change.event_time ? (
                          <div className="flex items-center text-sm text-gray-900 dark:text-white">
                            <Calendar className="w-4 h-4 mr-2 text-gray-400" />
                            <div>
                              <div>
                                {format(new Date(change.event_time.starts_at), 'HH:mm', {
                                  locale,
                                })}{' '}
                                -{' '}
                                {format(new Date(change.event_time.ends_at), 'HH:mm', {
                                  locale,
                                })}
                              </div>
                              <div className="text-xs text-gray-500 dark:text-gray-400">
                                {format(new Date(change.event_time.starts_at), 'PP', { locale })}
                              </div>
                            </div>
                          </div>
                        ) : (
                          <span className="text-sm text-gray-400">-</span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-gray-900 dark:text-white max-w-md truncate">
                          {change.summary}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {data.meta.total > data.meta.per_page && (
            <div className="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
              <div className="flex items-center justify-between">
                <div className="text-sm text-gray-700 dark:text-gray-300">
                  {t('common:pagination.showing', {
                    from: data.meta.from,
                    to: data.meta.to,
                    total: data.meta.total,
                  })}
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    disabled={data.meta.current_page === 1}
                    onClick={() => setFilters({ ...filters, page: (filters.page || 1) - 1 })}
                    data-testid="calendar-changes-prev-page"
                  >
                    {t('common:pagination.previous')}
                  </Button>
                  <Button
                    variant="outline"
                    disabled={data.meta.current_page === data.meta.last_page}
                    onClick={() => setFilters({ ...filters, page: (filters.page || 1) + 1 })}
                    data-testid="calendar-changes-next-page"
                  >
                    {t('common:pagination.next')}
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Detail Modal */}
      <CalendarChangeDetailModal
        changeId={selectedChangeId}
        open={detailModalOpen}
        onOpenChange={setDetailModalOpen}
      />
    </div>
  )
}
