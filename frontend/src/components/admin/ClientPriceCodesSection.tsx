import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  clientPriceCodesApi,
  clientPriceCodeKeys,
  serviceTypesApi,
  serviceTypeKeys,
} from '@/api/serviceTypes'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useToast } from '@/hooks/use-toast'
import { Plus, Pencil, Trash2 } from 'lucide-react'
import type { ClientPriceCode, ClientPriceCodeFormData, ServiceType } from '@/types/serviceType'

// Validation schema
const priceCodeSchema = z.object({
  service_type_id: z.number().min(1, 'Service type is required'),
  price_code: z.string().optional(),
  entry_fee_brutto: z.number().min(0, 'Entry fee must be positive'),
  trainer_fee_brutto: z.number().min(0, 'Trainer fee must be positive'),
  currency: z.string().default('HUF'),
  valid_from: z.string().min(1, 'Valid from date is required'),
  valid_until: z.string().optional().nullable(),
  is_active: z.boolean().default(true),
})

type PriceCodeFormValues = z.infer<typeof priceCodeSchema>

interface ClientPriceCodesSectionProps {
  clientId: number
}

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

export function ClientPriceCodesSection({ clientId }: ClientPriceCodesSectionProps) {
  const { t } = useTranslation(['admin', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingPriceCode, setEditingPriceCode] = useState<ClientPriceCode | null>(null)
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)

  // Fetch client price codes
  const { data: priceCodes, isLoading: isLoadingPriceCodes } = useQuery({
    queryKey: clientPriceCodeKeys.listByClient(clientId),
    queryFn: () => clientPriceCodesApi.listByClient(clientId),
    enabled: !!clientId,
  })

  // Fetch service types for the dropdown
  const { data: serviceTypes } = useQuery({
    queryKey: serviceTypeKeys.lists(),
    queryFn: serviceTypesApi.list,
  })

  const form = useForm<PriceCodeFormValues>({
    resolver: zodResolver(priceCodeSchema),
    defaultValues: {
      service_type_id: 0,
      price_code: '',
      entry_fee_brutto: 0,
      trainer_fee_brutto: 0,
      currency: 'HUF',
      valid_from: new Date().toISOString().split('T')[0],
      valid_until: null,
      is_active: true,
    },
  })

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: ClientPriceCodeFormData) => clientPriceCodesApi.create(clientId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(clientId) })
      toast({ title: t('admin:clientPriceCodes.createSuccess') })
      handleCloseModal()
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: t('admin:clientPriceCodes.createError'),
      })
    },
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<ClientPriceCodeFormData> }) =>
      clientPriceCodesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(clientId) })
      toast({ title: t('admin:clientPriceCodes.updateSuccess') })
      handleCloseModal()
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: t('admin:clientPriceCodes.updateError'),
      })
    },
  })

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => clientPriceCodesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(clientId) })
      toast({ title: t('admin:clientPriceCodes.deleteSuccess') })
      setDeleteConfirmId(null)
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: t('admin:clientPriceCodes.deleteError'),
      })
    },
  })

  // Toggle active mutation
  const toggleActiveMutation = useMutation({
    mutationFn: (id: number) => clientPriceCodesApi.toggleActive(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(clientId) })
    },
  })

  const handleOpenCreateModal = () => {
    setEditingPriceCode(null)
    form.reset({
      service_type_id: 0,
      price_code: '',
      entry_fee_brutto: 0,
      trainer_fee_brutto: 0,
      currency: 'HUF',
      valid_from: new Date().toISOString().split('T')[0],
      valid_until: null,
      is_active: true,
    })
    setIsModalOpen(true)
  }

  const handleOpenEditModal = (priceCode: ClientPriceCode) => {
    setEditingPriceCode(priceCode)
    form.reset({
      service_type_id: priceCode.service_type_id,
      price_code: priceCode.price_code || '',
      entry_fee_brutto: priceCode.entry_fee_brutto,
      trainer_fee_brutto: priceCode.trainer_fee_brutto,
      currency: priceCode.currency,
      valid_from: priceCode.valid_from.split('T')[0],
      valid_until: priceCode.valid_until?.split('T')[0] || null,
      is_active: priceCode.is_active,
    })
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setEditingPriceCode(null)
    form.reset()
  }

  const onSubmit = form.handleSubmit((data) => {
    const formData: ClientPriceCodeFormData = {
      service_type_id: data.service_type_id,
      price_code: data.price_code || undefined,
      entry_fee_brutto: data.entry_fee_brutto,
      trainer_fee_brutto: data.trainer_fee_brutto,
      currency: data.currency,
      valid_from: data.valid_from,
      valid_until: data.valid_until || null,
      is_active: data.is_active,
    }

    if (editingPriceCode) {
      updateMutation.mutate({ id: editingPriceCode.id, data: formData })
    } else {
      createMutation.mutate(formData)
    }
  })

  const handleServiceTypeChange = (serviceTypeId: string) => {
    const id = parseInt(serviceTypeId, 10)
    form.setValue('service_type_id', id)

    // Auto-fill default fees from service type
    const serviceType = serviceTypes?.find((st: ServiceType) => st.id === id)
    if (serviceType && !editingPriceCode) {
      form.setValue('entry_fee_brutto', serviceType.default_entry_fee_brutto)
      form.setValue('trainer_fee_brutto', serviceType.default_trainer_fee_brutto)
    }
  }

  const isPending = createMutation.isPending || updateMutation.isPending

  // Get service types not yet assigned to this client
  const availableServiceTypes = serviceTypes?.filter(
    (st: ServiceType) =>
      st.is_active &&
      (!priceCodes?.some((pc: ClientPriceCode) => pc.service_type_id === st.id) || editingPriceCode?.service_type_id === st.id)
  )

  if (isLoadingPriceCodes) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('admin:clientPriceCodes.title')}</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-32 w-full" />
        </CardContent>
      </Card>
    )
  }

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2 pb-4">
          <CardTitle className="text-base sm:text-lg">{t('admin:clientPriceCodes.title')}</CardTitle>
          <Button size="sm" onClick={handleOpenCreateModal} className="shrink-0">
            <Plus className="h-4 w-4 sm:mr-2" />
            <span className="hidden sm:inline">{t('admin:clientPriceCodes.add')}</span>
          </Button>
        </CardHeader>
        <CardContent className="px-2 sm:px-6">
          {priceCodes && priceCodes.length > 0 ? (
            <>
              {/* Mobile Card View */}
              <div className="sm:hidden space-y-3">
                {priceCodes.map((pc: ClientPriceCode) => (
                  <div key={pc.id} className="border rounded-lg p-3 space-y-2">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0 flex-1">
                        <p className="font-medium text-sm truncate">
                          {pc.service_type?.name || pc.service_type_id}
                        </p>
                        {pc.price_code && (
                          <p className="text-xs text-muted-foreground">{pc.price_code}</p>
                        )}
                      </div>
                      <Badge
                        variant={pc.is_active ? 'default' : 'secondary'}
                        className="cursor-pointer text-xs shrink-0"
                        onClick={() => toggleActiveMutation.mutate(pc.id)}
                      >
                        {pc.is_active ? t('common:active') : t('common:inactive')}
                      </Badge>
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-xs">
                      <div>
                        <span className="text-muted-foreground">{t('admin:clientPriceCodes.entryFee')}:</span>
                        <p className="font-medium">{formatCurrency(pc.entry_fee_brutto)}</p>
                      </div>
                      <div>
                        <span className="text-muted-foreground">{t('admin:clientPriceCodes.trainerFee')}:</span>
                        <p className="font-medium">{formatCurrency(pc.trainer_fee_brutto)}</p>
                      </div>
                    </div>
                    <div className="text-xs text-muted-foreground">
                      {new Date(pc.valid_from).toLocaleDateString('hu-HU')}
                      {pc.valid_until && ` - ${new Date(pc.valid_until).toLocaleDateString('hu-HU')}`}
                    </div>
                    <div className="flex gap-2 pt-1">
                      <Button
                        variant="outline"
                        size="sm"
                        className="flex-1"
                        onClick={() => handleOpenEditModal(pc)}
                      >
                        <Pencil className="h-3 w-3 mr-1" />
                        {t('common:edit')}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        className="text-destructive"
                        onClick={() => setDeleteConfirmId(pc.id)}
                      >
                        <Trash2 className="h-3 w-3" />
                      </Button>
                    </div>
                  </div>
                ))}
              </div>

              {/* Desktop Table View */}
              <div className="hidden sm:block overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('admin:clientPriceCodes.serviceType')}</TableHead>
                      <TableHead>{t('admin:clientPriceCodes.priceCode')}</TableHead>
                      <TableHead className="text-right">{t('admin:clientPriceCodes.entryFee')}</TableHead>
                      <TableHead className="text-right">{t('admin:clientPriceCodes.trainerFee')}</TableHead>
                      <TableHead>{t('admin:clientPriceCodes.validFrom')}</TableHead>
                      <TableHead>{t('admin:clientPriceCodes.status')}</TableHead>
                      <TableHead className="text-right">{t('common:actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {priceCodes.map((pc: ClientPriceCode) => (
                      <TableRow key={pc.id}>
                        <TableCell className="font-medium">
                          {pc.service_type?.name || pc.service_type_id}
                        </TableCell>
                        <TableCell>{pc.price_code || '-'}</TableCell>
                        <TableCell className="text-right">{formatCurrency(pc.entry_fee_brutto)}</TableCell>
                        <TableCell className="text-right">{formatCurrency(pc.trainer_fee_brutto)}</TableCell>
                        <TableCell>
                          {new Date(pc.valid_from).toLocaleDateString('hu-HU')}
                          {pc.valid_until && (
                            <span className="text-muted-foreground">
                              {' - '}
                              {new Date(pc.valid_until).toLocaleDateString('hu-HU')}
                            </span>
                          )}
                        </TableCell>
                        <TableCell>
                          <Badge
                            variant={pc.is_active ? 'default' : 'secondary'}
                            className="cursor-pointer"
                            onClick={() => toggleActiveMutation.mutate(pc.id)}
                          >
                            {pc.is_active ? t('common:active') : t('common:inactive')}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-2">
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => handleOpenEditModal(pc)}
                            >
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => setDeleteConfirmId(pc.id)}
                            >
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </>
          ) : (
            <p className="text-center text-muted-foreground py-8 text-sm">
              {t('admin:clientPriceCodes.noPriceCodes')}
            </p>
          )}
        </CardContent>
      </Card>

      {/* Create/Edit Modal */}
      <Dialog open={isModalOpen} onOpenChange={handleCloseModal}>
        <DialogContent className="w-[95vw] max-h-[90vh] overflow-y-auto sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="text-lg">
              {editingPriceCode
                ? t('admin:clientPriceCodes.editTitle')
                : t('admin:clientPriceCodes.createTitle')}
            </DialogTitle>
          </DialogHeader>

          <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid gap-3">
              {/* Service Type */}
              <div className="grid gap-2">
                <Label>{t('admin:clientPriceCodes.serviceType')}</Label>
                <Select
                  value={form.watch('service_type_id') > 0 ? form.watch('service_type_id').toString() : ''}
                  onValueChange={handleServiceTypeChange}
                  disabled={isPending}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('admin:clientPriceCodes.selectServiceType')} />
                  </SelectTrigger>
                  <SelectContent>
                    {availableServiceTypes?.map((st: ServiceType) => (
                      <SelectItem key={st.id} value={st.id.toString()}>
                        {st.name} ({st.code})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {form.formState.errors.service_type_id && (
                  <span className="text-sm text-destructive">
                    {form.formState.errors.service_type_id.message}
                  </span>
                )}
              </div>

              {/* Price Code */}
              <div className="grid gap-2">
                <Label htmlFor="price_code">{t('admin:clientPriceCodes.priceCode')}</Label>
                <Input
                  id="price_code"
                  {...form.register('price_code')}
                  placeholder={t('admin:clientPriceCodes.priceCodePlaceholder')}
                  disabled={isPending}
                />
              </div>

              {/* Entry Fee */}
              <div className="grid gap-2">
                <Label htmlFor="entry_fee_brutto">{t('admin:clientPriceCodes.entryFee')}</Label>
                <Input
                  id="entry_fee_brutto"
                  type="number"
                  {...form.register('entry_fee_brutto', { valueAsNumber: true })}
                  disabled={isPending}
                />
                {form.formState.errors.entry_fee_brutto && (
                  <span className="text-sm text-destructive">
                    {form.formState.errors.entry_fee_brutto.message}
                  </span>
                )}
              </div>

              {/* Trainer Fee */}
              <div className="grid gap-2">
                <Label htmlFor="trainer_fee_brutto">{t('admin:clientPriceCodes.trainerFee')}</Label>
                <Input
                  id="trainer_fee_brutto"
                  type="number"
                  {...form.register('trainer_fee_brutto', { valueAsNumber: true })}
                  disabled={isPending}
                />
                {form.formState.errors.trainer_fee_brutto && (
                  <span className="text-sm text-destructive">
                    {form.formState.errors.trainer_fee_brutto.message}
                  </span>
                )}
              </div>

              {/* Valid From */}
              <div className="grid gap-2">
                <Label htmlFor="valid_from">{t('admin:clientPriceCodes.validFrom')}</Label>
                <Input
                  id="valid_from"
                  type="date"
                  {...form.register('valid_from')}
                  disabled={isPending}
                />
                {form.formState.errors.valid_from && (
                  <span className="text-sm text-destructive">
                    {form.formState.errors.valid_from.message}
                  </span>
                )}
              </div>

              {/* Valid Until */}
              <div className="grid gap-2">
                <Label htmlFor="valid_until">{t('admin:clientPriceCodes.validUntil')}</Label>
                <Input
                  id="valid_until"
                  type="date"
                  {...form.register('valid_until')}
                  disabled={isPending}
                />
              </div>
            </div>

            <DialogFooter className="flex-col sm:flex-row gap-2">
              <Button type="button" variant="outline" onClick={handleCloseModal} disabled={isPending} className="w-full sm:w-auto">
                {t('common:cancel')}
              </Button>
              <Button type="submit" disabled={isPending} className="w-full sm:w-auto">
                {isPending ? t('common:saving') : t('common:save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={!!deleteConfirmId} onOpenChange={() => setDeleteConfirmId(null)}>
        <DialogContent className="w-[95vw] sm:max-w-[400px]">
          <DialogHeader>
            <DialogTitle>{t('admin:clientPriceCodes.deleteConfirmTitle')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">{t('admin:clientPriceCodes.deleteConfirmMessage')}</p>
          <DialogFooter className="flex-col sm:flex-row gap-2">
            <Button variant="outline" onClick={() => setDeleteConfirmId(null)} className="w-full sm:w-auto">
              {t('common:cancel')}
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteConfirmId && deleteMutation.mutate(deleteConfirmId)}
              disabled={deleteMutation.isPending}
              className="w-full sm:w-auto"
            >
              {deleteMutation.isPending ? t('common:deleting') : t('common:delete')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
