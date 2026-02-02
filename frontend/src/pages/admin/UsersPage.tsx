import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { usersApi, adminKeys } from '@/api/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { UserPlus, Search, AlertCircle, Trash2, ArrowUpDown } from 'lucide-react'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { UserEditModal } from '@/components/admin/UserEditModal'
import { UserCreateModal } from '@/components/admin/UserCreateModal'
import { useToast } from '@/hooks/use-toast'
import type { UserWithProfile } from '@/types/admin'
import type { UserListParams } from '@/api/admin'

type SortField = 'name' | 'email' | 'created_at'
type SortDir = 'asc' | 'desc'

/**
 * Formats a number as Hungarian Forint currency
 * @param amount - The amount to format
 * @returns Formatted string like "1 000 Ft"
 */
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('hu-HU', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount) + ' Ft'
}

export default function UserManagementPage() {
  const { t } = useTranslation(['admin', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState<string | undefined>()
  const [hasUnpaidBalance, setHasUnpaidBalance] = useState(false)
  const [sortBy, setSortBy] = useState<SortField>('name')
  const [sortDir, setSortDir] = useState<SortDir>('asc')
  const [page, setPage] = useState(1)
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [selectedUser, setSelectedUser] = useState<UserWithProfile | null>(null)
  const [deleteUser, setDeleteUser] = useState<UserWithProfile | null>(null)

  const handleEditUser = (user: UserWithProfile) => {
    setSelectedUser(user)
    setEditModalOpen(true)
  }

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (userId: number) => usersApi.delete(userId),
    onSuccess: () => {
      toast({
        title: t('common:success'),
        description: t('users.deleteSuccess'),
      })
      queryClient.invalidateQueries({ queryKey: adminKeys.users() })
      setDeleteUser(null)
    },
    onError: (error: any) => {
      toast({
        title: t('common:error'),
        description: error.response?.data?.message || t('users.deleteFailed'),
        variant: 'destructive',
      })
      setDeleteUser(null)
    },
  })

  // Build query params
  const queryParams: UserListParams = {
    search: search || undefined,
    role: roleFilter,
    has_unpaid_balance: hasUnpaidBalance || undefined,
    sort_by: sortBy,
    sort_dir: sortDir,
    page,
  }

  // Fetch users with filters
  const { data: usersData, isLoading } = useQuery({
    queryKey: adminKeys.usersList(queryParams as Record<string, unknown>),
    queryFn: () => usersApi.list(queryParams),
  })

  // Reset page when filters change
  const handleSearchChange = (value: string) => {
    setSearch(value)
    setPage(1)
  }

  const handleRoleFilterChange = (role: string | undefined) => {
    setRoleFilter(role)
    setPage(1)
  }

  const handleUnpaidBalanceChange = () => {
    setHasUnpaidBalance(!hasUnpaidBalance)
    setPage(1)
  }

  const users = usersData?.data || []
  const meta = usersData?.meta

  const getRoleBadgeVariant = (role: string): 'default' | 'secondary' | 'outline' => {
    switch (role) {
      case 'admin':
        return 'default'
      case 'staff':
        return 'secondary'
      case 'client':
        return 'outline'
      default:
        return 'outline'
    }
  }

  const getStatusBadgeVariant = (status: string): 'default' | 'destructive' => {
    return status === 'active' ? 'default' : 'destructive'
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight">{t('users.title')}</h1>
          <p className="text-gray-500 mt-1 sm:mt-2 text-sm sm:text-base">{t('users.subtitle')}</p>
        </div>
        <Button onClick={() => setCreateModalOpen(true)} className="w-full sm:w-auto">
          <UserPlus className="h-4 w-4 mr-2" />
          {t('users.createUser')}
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex flex-col gap-4">
            {/* Search */}
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                placeholder={t('users.searchPlaceholder')}
                value={search}
                onChange={(e) => handleSearchChange(e.target.value)}
                className="pl-10"
              />
            </div>
            {/* Role filters and Sort */}
            <div className="flex flex-col sm:flex-row gap-3">
              {/* Role filter buttons */}
              <div className="flex flex-wrap gap-2">
                <Button
                  variant={roleFilter === undefined ? 'default' : 'outline'}
                  onClick={() => handleRoleFilterChange(undefined)}
                  size="sm"
                >
                  {t('users.allRoles')}
                </Button>
                <Button
                  variant={roleFilter === 'client' ? 'default' : 'outline'}
                  onClick={() => handleRoleFilterChange('client')}
                  size="sm"
                >
                  {t('users.clients')}
                </Button>
                <Button
                  variant={roleFilter === 'staff' ? 'default' : 'outline'}
                  onClick={() => handleRoleFilterChange('staff')}
                  size="sm"
                >
                  {t('users.staff')}
                </Button>
                <Button
                  variant={roleFilter === 'admin' ? 'default' : 'outline'}
                  onClick={() => handleRoleFilterChange('admin')}
                  size="sm"
                >
                  {t('users.admins')}
                </Button>
              </div>
              {/* Separator */}
              <div className="hidden sm:block border-l mx-2" />
              {/* Unpaid balance filter */}
              <Button
                variant={hasUnpaidBalance ? 'destructive' : 'outline'}
                onClick={handleUnpaidBalanceChange}
                data-testid="filter-unpaid-balance"
                size="sm"
                className="w-full sm:w-auto"
              >
                <AlertCircle className="h-4 w-4 mr-2" />
                {t('users.hasUnpaidBalance')}
              </Button>
            </div>
            {/* Sort controls */}
            <div className="flex gap-2">
              <Select value={sortBy} onValueChange={(value: SortField) => setSortBy(value)}>
                <SelectTrigger className="w-[140px]">
                  <ArrowUpDown className="h-4 w-4 mr-2" />
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="name">Név</SelectItem>
                  <SelectItem value="email">Email</SelectItem>
                  <SelectItem value="created_at">Dátum</SelectItem>
                </SelectContent>
              </Select>
              <Button
                variant="outline"
                size="icon"
                onClick={() => setSortDir(sortDir === 'asc' ? 'desc' : 'asc')}
                title={sortDir === 'asc' ? 'Növekvő sorrend' : 'Csökkenő sorrend'}
              >
                {sortDir === 'asc' ? '↑' : '↓'}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Users List */}
      <Card>
        <CardHeader>
          <CardTitle>{t('users.usersList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[...Array(5)].map((_, i) => (
                <Skeleton key={i} className="h-20 w-full" />
              ))}
            </div>
          ) : users.length > 0 ? (
            <div className="space-y-2">
              {users.map((user) => {
                const unpaidBalance = user.client?.unpaid_balance ?? 0
                const userHasUnpaidBalance = unpaidBalance > 0

                return (
                  <div
                    key={user.id}
                    data-testid={`user-row-${user.id}`}
                    className="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors gap-3"
                  >
                    <div className="flex-1 min-w-0">
                      <div className="flex flex-wrap items-center gap-2 mb-1">
                        <h3 className="font-medium truncate">{user.name}</h3>
                        <Badge variant={getRoleBadgeVariant(user.role)}>
                          {t(`users.role.${user.role}`)}
                        </Badge>
                        <Badge variant={getStatusBadgeVariant(user.status)}>
                          {t(`users.status.${user.status}`)}
                        </Badge>
                      </div>
                      <div className="flex flex-col sm:flex-row sm:items-center sm:gap-4 text-sm text-gray-500 gap-1">
                        <span className="truncate">{user.email}</span>
                        {user.phone && <span className="hidden sm:inline">·</span>}
                        {user.phone && <span>{user.phone}</span>}
                      </div>
                    </div>
                    {/* Unpaid Balance - only show for clients */}
                    {user.role === 'client' && (
                      <div
                        className="flex items-center gap-2 sm:mr-4"
                        data-testid={`user-unpaid-balance-${user.id}`}
                      >
                        {userHasUnpaidBalance ? (
                          <Badge
                            variant="destructive"
                            className="flex items-center gap-1"
                          >
                            <AlertCircle className="h-3 w-3" />
                            {formatCurrency(unpaidBalance)}
                          </Badge>
                        ) : (
                          <span className="text-sm text-gray-400">
                            {formatCurrency(0)}
                          </span>
                        )}
                      </div>
                    )}
                    <div className="flex gap-2 w-full sm:w-auto">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleEditUser(user)}
                        className="flex-1 sm:flex-initial"
                      >
                        {t('common:edit')}
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => setDeleteUser(user)}
                        className="shrink-0"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="text-center text-gray-500 py-12">{t('users.noUsersFound')}</p>
          )}
        </CardContent>
      </Card>

      {/* Pagination */}
      {meta && meta.total > meta.per_page && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
          <p className="text-sm text-gray-500">
            {t('users.showing')} {meta.from}-{meta.to} {t('users.of')} {meta.total}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              disabled={page <= 1}
              onClick={() => setPage(page - 1)}
            >
              {t('common:previous')}
            </Button>
            <Button
              variant="outline"
              disabled={page >= meta.last_page}
              onClick={() => setPage(page + 1)}
            >
              {t('common:next')}
            </Button>
          </div>
        </div>
      )}

      {/* Edit User Modal */}
      <UserEditModal
        user={selectedUser}
        open={editModalOpen}
        onOpenChange={setEditModalOpen}
      />

      {/* Create User Modal */}
      <UserCreateModal
        open={createModalOpen}
        onOpenChange={setCreateModalOpen}
      />

      {/* Delete User Confirmation Dialog */}
      <AlertDialog open={!!deleteUser} onOpenChange={(open) => !open && setDeleteUser(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin:users.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('admin:users.deleteConfirmMessage', { name: deleteUser?.name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>
              {t('common:cancel')}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => deleteUser && deleteMutation.mutate(Number(deleteUser.id))}
              disabled={deleteMutation.isPending}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleteMutation.isPending ? t('common:loading') : t('common:delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
