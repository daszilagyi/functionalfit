import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { settlementsApi, settlementKeys } from '@/api/settlements'
import { usersApi, adminKeys } from '@/api/admin'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { useToast } from '@/hooks/use-toast'
import { FileText, DollarSign, Filter, ChevronRight, Check } from 'lucide-react'
import { format } from 'date-fns'
import type { GenerateSettlementRequest } from '@/types/pricing'

/**
 * Formats a number as Hungarian Forint currency
 */
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('hu-HU', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount) + ' Ft'
}

export default function SettlementsPage() {
  const { t } = useTranslation('admin')
  const { toast } = useToast()
  const queryClient = useQueryClient()

  // Preview form state
  const [previewTrainerId, setPreviewTrainerId] = useState<number | undefined>()
  const [previewFrom, setPreviewFrom] = useState(format(new Date(), 'yyyy-MM-01')) // First day of month
  const [previewTo, setPreviewTo] = useState(format(new Date(), 'yyyy-MM-dd')) // Today

  // List filters
  const [trainerFilter, setTrainerFilter] = useState<number | undefined>()
  const [statusFilter, setStatusFilter] = useState<string | undefined>()

  // Detail view
  const [selectedSettlementId, setSelectedSettlementId] = useState<number | null>(null)
  const [detailDialogOpen, setDetailDialogOpen] = useState(false)

  // Fetch staff users for dropdown
  const { data: staffUsers } = useQuery({
    queryKey: adminKeys.usersList({ role: 'staff' }),
    queryFn: () => usersApi.list({ role: 'staff' }),
  })

  const staffList = staffUsers?.data || []

  // Preview settlement query (only when trainer and dates are set)
  const shouldFetchPreview = !!previewTrainerId && !!previewFrom && !!previewTo
  const { data: previewData, isLoading: isPreviewLoading } = useQuery({
    queryKey: settlementKeys.preview(previewTrainerId || 0, previewFrom, previewTo),
    queryFn: () => settlementsApi.preview(previewTrainerId!, previewFrom, previewTo),
    enabled: shouldFetchPreview,
  })

  // List settlements query
  const { data: settlements, isLoading: isListLoading } = useQuery({
    queryKey: settlementKeys.list({ trainer_id: trainerFilter, status: statusFilter }),
    queryFn: () => settlementsApi.list({ trainer_id: trainerFilter, status: statusFilter }),
  })

  // Get single settlement for detail view
  const { data: settlementDetail } = useQuery({
    queryKey: settlementKeys.detail(selectedSettlementId || 0),
    queryFn: () => settlementsApi.get(selectedSettlementId!),
    enabled: !!selectedSettlementId,
  })

  // Generate settlement mutation
  const generateMutation = useMutation({
    mutationFn: settlementsApi.generate,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: settlementKeys.lists() })
      toast({
        title: t('settlements.generateSuccess'),
        description: t('settlements.generateSuccessDescription'),
      })
      // Reset preview
      setPreviewTrainerId(undefined)
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('settlements.generateError'),
        description: t('settlements.generateErrorDescription'),
      })
    },
  })

  // Update status mutation
  const updateStatusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: 'draft' | 'finalized' | 'paid' }) =>
      settlementsApi.updateStatus(id, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: settlementKeys.lists() })
      queryClient.invalidateQueries({ queryKey: settlementKeys.detail(selectedSettlementId || 0) })
      toast({
        title: t('settlements.statusUpdateSuccess'),
        description: t('settlements.statusUpdateSuccessDescription'),
      })
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('settlements.statusUpdateError'),
      })
    },
  })

  const handleGenerateSettlement = () => {
    if (!previewTrainerId || !previewFrom || !previewTo) {
      toast({
        variant: 'destructive',
        title: t('settlements.validationError'),
        description: t('settlements.selectTrainerAndDates'),
      })
      return
    }

    const settlementData: GenerateSettlementRequest = {
      trainer_id: previewTrainerId,
      period_start: previewFrom,
      period_end: previewTo,
    }

    generateMutation.mutate(settlementData)
  }

  const handleViewDetail = (settlementId: number) => {
    setSelectedSettlementId(settlementId)
    setDetailDialogOpen(true)
  }

  const handleStatusChange = (settlementId: number, newStatus: 'draft' | 'finalized' | 'paid') => {
    updateStatusMutation.mutate({ id: settlementId, status: newStatus })
  }

  const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'outline' => {
    switch (status) {
      case 'paid':
        return 'default'
      case 'finalized':
        return 'secondary'
      case 'draft':
        return 'outline'
      default:
        return 'outline'
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('settlements.title')}</h1>
        <p className="text-gray-500 mt-2">{t('settlements.subtitle')}</p>
      </div>

      {/* Preview Section */}
      <Card>
        <CardHeader>
          <CardTitle>{t('settlements.preview')}</CardTitle>
          <CardDescription>{t('settlements.previewDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {/* Trainer Select */}
            <div className="space-y-2">
              <Label>{t('settlements.selectTrainer')}</Label>
              <Select
                value={previewTrainerId?.toString() || ''}
                onValueChange={(value) => setPreviewTrainerId(parseInt(value))}
              >
                <SelectTrigger data-testid="preview-trainer-select">
                  <SelectValue placeholder={t('settlements.selectTrainer')} />
                </SelectTrigger>
                <SelectContent>
                  {staffList.map((staff) => (
                    <SelectItem key={staff.id} value={staff.id.toString()}>
                      {staff.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Date From */}
            <div className="space-y-2">
              <Label>{t('settlements.from')}</Label>
              <Input
                type="date"
                value={previewFrom}
                onChange={(e) => setPreviewFrom(e.target.value)}
                data-testid="preview-from-input"
              />
            </div>

            {/* Date To */}
            <div className="space-y-2">
              <Label>{t('settlements.to')}</Label>
              <Input
                type="date"
                value={previewTo}
                onChange={(e) => setPreviewTo(e.target.value)}
                data-testid="preview-to-input"
              />
            </div>
          </div>

          {/* Preview Results */}
          {isPreviewLoading && (
            <div className="space-y-2">
              <Skeleton className="h-20 w-full" />
            </div>
          )}

          {previewData && (
            <div className="mt-4 space-y-4">
              <Separator />
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-sm font-medium text-gray-500">
                      {t('settlements.totalTrainerFee')}
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <p className="text-2xl font-bold">{formatCurrency(previewData.total_trainer_fee)}</p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-sm font-medium text-gray-500">
                      {t('settlements.totalEntryFee')}
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <p className="text-2xl font-bold">{formatCurrency(previewData.total_entry_fee)}</p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-sm font-medium text-gray-500">
                      {t('settlements.itemsCount')}
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <p className="text-2xl font-bold">{previewData.items_count}</p>
                  </CardContent>
                </Card>
              </div>

              <Button
                onClick={handleGenerateSettlement}
                disabled={generateMutation.isPending}
                className="w-full"
                data-testid="generate-settlement-btn"
              >
                <FileText className="h-4 w-4 mr-2" />
                {generateMutation.isPending ? t('common:loading') : t('settlements.generateSettlement')}
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex gap-4 items-end">
            <div className="flex-1">
              <Label>{t('settlements.filterByTrainer')}</Label>
              <Select
                value={trainerFilter?.toString() || 'all'}
                onValueChange={(value) => setTrainerFilter(value === 'all' ? undefined : parseInt(value))}
              >
                <SelectTrigger data-testid="filter-trainer-select">
                  <SelectValue placeholder={t('settlements.allTrainers')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t('settlements.allTrainers')}</SelectItem>
                  {staffList.map((staff) => (
                    <SelectItem key={staff.id} value={staff.id.toString()}>
                      {staff.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex gap-2">
              <Button
                variant={statusFilter === undefined ? 'default' : 'outline'}
                onClick={() => setStatusFilter(undefined)}
                data-testid="filter-all-status-btn"
              >
                <Filter className="h-4 w-4 mr-2" />
                {t('settlements.allStatuses')}
              </Button>
              <Button
                variant={statusFilter === 'draft' ? 'default' : 'outline'}
                onClick={() => setStatusFilter('draft')}
              >
                {t('settlements.statusDraft')}
              </Button>
              <Button
                variant={statusFilter === 'finalized' ? 'default' : 'outline'}
                onClick={() => setStatusFilter('finalized')}
              >
                {t('settlements.statusFinalized')}
              </Button>
              <Button
                variant={statusFilter === 'paid' ? 'default' : 'outline'}
                onClick={() => setStatusFilter('paid')}
              >
                {t('settlements.statusPaid')}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Settlements List */}
      <Card>
        <CardHeader>
          <CardTitle>{t('settlements.settlementsList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isListLoading ? (
            <div className="space-y-4">
              {[...Array(5)].map((_, i) => (
                <Skeleton key={i} className="h-20 w-full" />
              ))}
            </div>
          ) : settlements && settlements.length > 0 ? (
            <div className="space-y-2">
              {settlements.map((settlement) => (
                <div
                  key={settlement.id}
                  className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors cursor-pointer"
                  onClick={() => handleViewDetail(settlement.id)}
                  data-testid={`settlement-row-${settlement.id}`}
                >
                  <div className="flex-1 grid grid-cols-5 gap-4 items-center">
                    <div>
                      <p className="font-medium">{settlement.trainer_name}</p>
                      <p className="text-sm text-gray-500">ID: {settlement.id}</p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">{t('settlements.period')}</p>
                      <p className="font-medium">
                        {format(new Date(settlement.period_start), 'yyyy-MM-dd')} -{' '}
                        {format(new Date(settlement.period_end), 'yyyy-MM-dd')}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">{t('settlements.totalTrainerFee')}</p>
                      <p className="font-medium">{formatCurrency(settlement.total_trainer_fee)}</p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">{t('settlements.itemsCount')}</p>
                      <p className="font-medium">{settlement.items_count}</p>
                    </div>
                    <div className="flex items-center justify-between">
                      <Badge variant={getStatusBadgeVariant(settlement.status)}>
                        {t(`settlements.status${settlement.status.charAt(0).toUpperCase() + settlement.status.slice(1)}`)}
                      </Badge>
                      <ChevronRight className="h-5 w-5 text-gray-400" />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12 text-gray-500" data-testid="no-settlements-found">
              <p>{t('settlements.noSettlementsFound')}</p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Settlement Detail Dialog */}
      <Dialog open={detailDialogOpen} onOpenChange={setDetailDialogOpen}>
        <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('settlements.settlementDetail')}</DialogTitle>
          </DialogHeader>
          {settlementDetail && (
            <div className="space-y-6 py-4">
              {/* Summary */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-gray-500">{t('settlements.trainer')}</p>
                  <p className="font-medium">{settlementDetail.trainer_name}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">{t('settlements.status')}</p>
                  <Badge variant={getStatusBadgeVariant(settlementDetail.status)}>
                    {t(`settlements.status${settlementDetail.status.charAt(0).toUpperCase() + settlementDetail.status.slice(1)}`)}
                  </Badge>
                </div>
                <div>
                  <p className="text-sm text-gray-500">{t('settlements.period')}</p>
                  <p className="font-medium">
                    {format(new Date(settlementDetail.period_start), 'yyyy-MM-dd')} -{' '}
                    {format(new Date(settlementDetail.period_end), 'yyyy-MM-dd')}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">{t('settlements.totalTrainerFee')}</p>
                  <p className="font-medium text-lg">{formatCurrency(settlementDetail.total_trainer_fee)}</p>
                </div>
              </div>

              <Separator />

              {/* Status Update Buttons */}
              <div className="flex gap-2">
                {settlementDetail.status === 'draft' && (
                  <Button
                    onClick={() => handleStatusChange(settlementDetail.id, 'finalized')}
                    disabled={updateStatusMutation.isPending}
                    data-testid="finalize-btn"
                  >
                    <Check className="h-4 w-4 mr-2" />
                    {t('settlements.markAsFinalized')}
                  </Button>
                )}
                {settlementDetail.status === 'finalized' && (
                  <Button
                    onClick={() => handleStatusChange(settlementDetail.id, 'paid')}
                    disabled={updateStatusMutation.isPending}
                    data-testid="mark-paid-btn"
                  >
                    <DollarSign className="h-4 w-4 mr-2" />
                    {t('settlements.markAsPaid')}
                  </Button>
                )}
              </div>

              <Separator />

              {/* Items Table */}
              <div>
                <h3 className="font-semibold mb-4">{t('settlements.items')}</h3>
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="border-b">
                      <tr className="text-left text-sm text-gray-500">
                        <th className="pb-3 font-medium">{t('settlements.className')}</th>
                        <th className="pb-3 font-medium">{t('settlements.date')}</th>
                        <th className="pb-3 font-medium">{t('settlements.client')}</th>
                        <th className="pb-3 font-medium">{t('settlements.entryFee')}</th>
                        <th className="pb-3 font-medium">{t('settlements.trainerFee')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {settlementDetail.items?.map((item) => (
                        <tr key={item.id} className="border-b last:border-0">
                          <td className="py-3">{item.class_name}</td>
                          <td className="py-3">{format(new Date(item.class_date), 'yyyy-MM-dd HH:mm')}</td>
                          <td className="py-3">{item.client_name}</td>
                          <td className="py-3">{formatCurrency(item.entry_fee_brutto)}</td>
                          <td className="py-3">{formatCurrency(item.trainer_fee_brutto)}</td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="border-t font-semibold">
                      <tr>
                        <td colSpan={3} className="py-3 text-right">
                          {t('settlements.total')}:
                        </td>
                        <td className="py-3">{formatCurrency(settlementDetail.total_entry_fee)}</td>
                        <td className="py-3">{formatCurrency(settlementDetail.total_trainer_fee)}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
