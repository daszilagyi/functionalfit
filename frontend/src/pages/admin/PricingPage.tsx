import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { classPricingDefaultsApi, pricingKeys } from '@/api/pricing'
import { classTemplatesApi, adminKeys } from '@/api/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useToast } from '@/hooks/use-toast'
import { Plus, Filter, Edit, Power, Trash2 } from 'lucide-react'
import { format } from 'date-fns'
import type { CreateClassPricingDefaultRequest, ClassPricingDefault } from '@/types/pricing'

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

export default function PricingPage() {
  const { t } = useTranslation('admin')
  const { toast } = useToast()
  const queryClient = useQueryClient()

  const [classTemplateFilter, setClassTemplateFilter] = useState<number | undefined>()
  const [activeFilter, setActiveFilter] = useState<boolean | undefined>(true)
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [selectedPricing, setSelectedPricing] = useState<ClassPricingDefault | null>(null)

  // Form state for creating new pricing
  const [formData, setFormData] = useState<CreateClassPricingDefaultRequest>({
    name: '',
    class_template_id: 0,
    entry_fee_brutto: 0,
    trainer_fee_brutto: 0,
    currency: 'HUF',
    valid_from: format(new Date(), 'yyyy-MM-dd'),
    valid_until: null,
    is_active: true,
  })

  // Fetch class pricing defaults
  const { data: pricingList, isLoading } = useQuery({
    queryKey: pricingKeys.classDefaultsList({ class_template_id: classTemplateFilter, is_active: activeFilter }),
    queryFn: () =>
      classPricingDefaultsApi.list({ class_template_id: classTemplateFilter, is_active: activeFilter }),
  })

  // Fetch class templates for dropdown
  const { data: classTemplates } = useQuery({
    queryKey: adminKeys.classTemplatesList({ is_active: true }),
    queryFn: () => classTemplatesApi.list({ is_active: true }),
  })

  // Create pricing mutation
  const createMutation = useMutation({
    mutationFn: classPricingDefaultsApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: pricingKeys.classDefaults() })
      toast({
        title: t('pricing.createSuccess'),
        description: t('pricing.createSuccessDescription'),
      })
      setCreateDialogOpen(false)
      // Reset form
      setFormData({
        name: '',
        class_template_id: 0,
        entry_fee_brutto: 0,
        trainer_fee_brutto: 0,
        currency: 'HUF',
        valid_from: format(new Date(), 'yyyy-MM-dd'),
        valid_until: null,
        is_active: true,
      })
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('pricing.createError'),
        description: t('pricing.createErrorDescription'),
      })
    },
  })

  // Update pricing mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CreateClassPricingDefaultRequest> }) =>
      classPricingDefaultsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: pricingKeys.classDefaults() })
      toast({
        title: t('pricing.updateSuccess'),
        description: t('pricing.updateSuccessDescription'),
      })
      setEditDialogOpen(false)
      setSelectedPricing(null)
      // Reset form
      setFormData({
        name: '',
        class_template_id: 0,
        entry_fee_brutto: 0,
        trainer_fee_brutto: 0,
        currency: 'HUF',
        valid_from: format(new Date(), 'yyyy-MM-dd'),
        valid_until: null,
        is_active: true,
      })
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('pricing.updateError'),
        description: t('pricing.updateErrorDescription'),
      })
    },
  })

  // Toggle active mutation
  const toggleActiveMutation = useMutation({
    mutationFn: classPricingDefaultsApi.toggleActive,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: pricingKeys.classDefaults() })
      toast({
        title: t('pricing.toggleSuccess'),
        description: t('pricing.toggleSuccessDescription'),
      })
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('pricing.toggleError'),
        description: t('pricing.toggleErrorDescription'),
      })
    },
  })

  // Delete pricing mutation
  const deleteMutation = useMutation({
    mutationFn: classPricingDefaultsApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: pricingKeys.classDefaults() })
      toast({
        title: t('pricing.deleteSuccess'),
        description: t('pricing.deleteSuccessDescription'),
      })
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('pricing.deleteError'),
        description: t('pricing.deleteErrorDescription'),
      })
    },
  })

  const handleCreatePricing = () => {
    if (formData.class_template_id === 0) {
      toast({
        variant: 'destructive',
        title: t('pricing.validationError'),
        description: t('pricing.selectClassTemplate'),
      })
      return
    }
    if (formData.entry_fee_brutto <= 0 || formData.trainer_fee_brutto <= 0) {
      toast({
        variant: 'destructive',
        title: t('pricing.validationError'),
        description: t('pricing.enterValidAmounts'),
      })
      return
    }
    createMutation.mutate(formData)
  }

  const handleEditPricing = (pricing: ClassPricingDefault) => {
    setSelectedPricing(pricing)
    setFormData({
      name: pricing.name || '',
      class_template_id: pricing.class_template_id,
      entry_fee_brutto: pricing.entry_fee_brutto,
      trainer_fee_brutto: pricing.trainer_fee_brutto,
      currency: pricing.currency,
      valid_from: format(new Date(pricing.valid_from), 'yyyy-MM-dd'),
      valid_until: pricing.valid_until ? format(new Date(pricing.valid_until), 'yyyy-MM-dd') : null,
      is_active: pricing.is_active,
    })
    setEditDialogOpen(true)
  }

  const handleUpdatePricing = () => {
    if (!selectedPricing) return
    if (formData.class_template_id === 0) {
      toast({
        variant: 'destructive',
        title: t('pricing.validationError'),
        description: t('pricing.selectClassTemplate'),
      })
      return
    }
    if (formData.entry_fee_brutto <= 0 || formData.trainer_fee_brutto <= 0) {
      toast({
        variant: 'destructive',
        title: t('pricing.validationError'),
        description: t('pricing.enterValidAmounts'),
      })
      return
    }
    updateMutation.mutate({ id: selectedPricing.id, data: formData })
  }

  const handleToggleActive = (pricing: ClassPricingDefault) => {
    toggleActiveMutation.mutate(pricing.id)
  }

  const handleDeletePricing = (pricing: ClassPricingDefault) => {
    if (confirm(t('pricing.confirmDelete'))) {
      deleteMutation.mutate(pricing.id)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('pricing.title')}</h1>
          <p className="text-gray-500 mt-2">{t('pricing.subtitle')}</p>
        </div>
        <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
          <DialogTrigger asChild>
            <Button data-testid="create-pricing-btn">
              <Plus className="h-4 w-4 mr-2" />
              {t('pricing.createPricing')}
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>{t('pricing.createPricing')}</DialogTitle>
            </DialogHeader>
            <div className="space-y-4 py-4">
              {/* Name */}
              <div className="space-y-2">
                <Label htmlFor="name">{t('pricing.pricingName')} ({t('pricing.optional')})</Label>
                <Input
                  id="name"
                  type="text"
                  placeholder={t('pricing.pricingNamePlaceholder')}
                  value={formData.name || ''}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value || null })}
                  data-testid="name-input"
                />
              </div>

              {/* Class Template Select */}
              <div className="space-y-2">
                <Label htmlFor="class_template_id">{t('pricing.classTemplate')}</Label>
                <Select
                  value={formData.class_template_id.toString()}
                  onValueChange={(value) =>
                    setFormData({ ...formData, class_template_id: parseInt(value) })
                  }
                >
                  <SelectTrigger data-testid="class-template-select">
                    <SelectValue placeholder={t('pricing.selectClassTemplate')} />
                  </SelectTrigger>
                  <SelectContent>
                    {classTemplates?.map((template) => (
                      <SelectItem key={template.id} value={template.id.toString()}>
                        {template.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Entry Fee */}
              <div className="space-y-2">
                <Label htmlFor="entry_fee_brutto">{t('pricing.entryFee')}</Label>
                <Input
                  id="entry_fee_brutto"
                  type="number"
                  min="0"
                  step="100"
                  value={formData.entry_fee_brutto}
                  onChange={(e) =>
                    setFormData({ ...formData, entry_fee_brutto: parseInt(e.target.value) || 0 })
                  }
                  data-testid="entry-fee-input"
                />
              </div>

              {/* Trainer Fee */}
              <div className="space-y-2">
                <Label htmlFor="trainer_fee_brutto">{t('pricing.trainerFee')}</Label>
                <Input
                  id="trainer_fee_brutto"
                  type="number"
                  min="0"
                  step="100"
                  value={formData.trainer_fee_brutto}
                  onChange={(e) =>
                    setFormData({ ...formData, trainer_fee_brutto: parseInt(e.target.value) || 0 })
                  }
                  data-testid="trainer-fee-input"
                />
              </div>

              {/* Valid From */}
              <div className="space-y-2">
                <Label htmlFor="valid_from">{t('pricing.validFrom')}</Label>
                <Input
                  id="valid_from"
                  type="date"
                  value={formData.valid_from}
                  onChange={(e) => setFormData({ ...formData, valid_from: e.target.value })}
                  data-testid="valid-from-input"
                />
              </div>

              {/* Valid Until (optional) */}
              <div className="space-y-2">
                <Label htmlFor="valid_until">{t('pricing.validUntil')} ({t('pricing.optional')})</Label>
                <Input
                  id="valid_until"
                  type="date"
                  value={formData.valid_until || ''}
                  onChange={(e) => setFormData({ ...formData, valid_until: e.target.value || null })}
                  data-testid="valid-until-input"
                />
              </div>

              {/* Submit Button */}
              <Button
                onClick={handleCreatePricing}
                disabled={createMutation.isPending}
                className="w-full"
                data-testid="submit-pricing-btn"
              >
                {createMutation.isPending ? t('common:loading') : t('pricing.create')}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex gap-4 items-end">
            <div className="flex-1">
              <Label>{t('pricing.filterByClass')}</Label>
              <Select
                value={classTemplateFilter?.toString() || 'all'}
                onValueChange={(value) => setClassTemplateFilter(value === 'all' ? undefined : parseInt(value))}
              >
                <SelectTrigger data-testid="filter-class-select">
                  <SelectValue placeholder={t('pricing.allClasses')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t('pricing.allClasses')}</SelectItem>
                  {classTemplates?.map((template) => (
                    <SelectItem key={template.id} value={template.id.toString()}>
                      {template.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex gap-2">
              <Button
                variant={activeFilter === undefined ? 'default' : 'outline'}
                onClick={() => setActiveFilter(undefined)}
                data-testid="filter-all-btn"
              >
                <Filter className="h-4 w-4 mr-2" />
                {t('pricing.allStatus')}
              </Button>
              <Button
                variant={activeFilter === true ? 'default' : 'outline'}
                onClick={() => setActiveFilter(true)}
                data-testid="filter-active-btn"
              >
                {t('pricing.activeOnly')}
              </Button>
              <Button
                variant={activeFilter === false ? 'default' : 'outline'}
                onClick={() => setActiveFilter(false)}
                data-testid="filter-inactive-btn"
              >
                {t('pricing.inactiveOnly')}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Pricing List */}
      <Card>
        <CardHeader>
          <CardTitle>{t('pricing.pricingList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[...Array(5)].map((_, i) => (
                <Skeleton key={i} className="h-20 w-full" />
              ))}
            </div>
          ) : pricingList && pricingList.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full" data-testid="pricing-table">
                <thead className="border-b">
                  <tr className="text-left text-sm text-gray-500">
                    <th className="pb-3 font-medium">{t('pricing.pricingName')}</th>
                    <th className="pb-3 font-medium">{t('pricing.classTemplate')}</th>
                    <th className="pb-3 font-medium">{t('pricing.entryFee')}</th>
                    <th className="pb-3 font-medium">{t('pricing.trainerFee')}</th>
                    <th className="pb-3 font-medium">{t('pricing.validFrom')}</th>
                    <th className="pb-3 font-medium">{t('pricing.validUntil')}</th>
                    <th className="pb-3 font-medium">{t('pricing.status')}</th>
                    <th className="pb-3 font-medium text-right">{t('common:actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {pricingList.map((pricing) => (
                    <tr key={pricing.id} className="border-b last:border-0" data-testid={`pricing-row-${pricing.id}`}>
                      <td className="py-4">
                        <span className="font-medium">{pricing.name || '-'}</span>
                      </td>
                      <td className="py-4">
                        <div className="flex items-center gap-2">
                          {pricing.class_template?.color && (
                            <div
                              className="w-3 h-3 rounded-full"
                              style={{ backgroundColor: pricing.class_template.color }}
                            />
                          )}
                          <span>{pricing.class_template?.title || '-'}</span>
                        </div>
                      </td>
                      <td className="py-4">{formatCurrency(pricing.entry_fee_brutto)}</td>
                      <td className="py-4">{formatCurrency(pricing.trainer_fee_brutto)}</td>
                      <td className="py-4">{format(new Date(pricing.valid_from), 'yyyy-MM-dd')}</td>
                      <td className="py-4">
                        {pricing.valid_until ? format(new Date(pricing.valid_until), 'yyyy-MM-dd') : '-'}
                      </td>
                      <td className="py-4">
                        <Badge variant={pricing.is_active ? 'default' : 'secondary'}>
                          {pricing.is_active ? t('pricing.active') : t('pricing.inactive')}
                        </Badge>
                      </td>
                      <td className="py-4">
                        <div className="flex items-center justify-end gap-2">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleEditPricing(pricing)}
                            data-testid={`edit-pricing-${pricing.id}`}
                          >
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleToggleActive(pricing)}
                            data-testid={`toggle-pricing-${pricing.id}`}
                          >
                            <Power className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleDeletePricing(pricing)}
                            data-testid={`delete-pricing-${pricing.id}`}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center py-12 text-gray-500" data-testid="no-pricing-found">
              <p>{t('pricing.noPricingFound')}</p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Edit Pricing Dialog */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t('pricing.editPricing')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
            {/* Name */}
            <div className="space-y-2">
              <Label htmlFor="edit_name">{t('pricing.pricingName')} ({t('pricing.optional')})</Label>
              <Input
                id="edit_name"
                type="text"
                placeholder={t('pricing.pricingNamePlaceholder')}
                value={formData.name || ''}
                onChange={(e) => setFormData({ ...formData, name: e.target.value || null })}
                data-testid="edit-name-input"
              />
            </div>

            {/* Class Template Select */}
            <div className="space-y-2">
              <Label htmlFor="edit_class_template_id">{t('pricing.classTemplate')}</Label>
              <Select
                value={formData.class_template_id.toString()}
                onValueChange={(value) =>
                  setFormData({ ...formData, class_template_id: parseInt(value) })
                }
              >
                <SelectTrigger data-testid="edit-class-template-select">
                  <SelectValue placeholder={t('pricing.selectClassTemplate')} />
                </SelectTrigger>
                <SelectContent>
                  {classTemplates?.map((template) => (
                    <SelectItem key={template.id} value={template.id.toString()}>
                      {template.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Entry Fee */}
            <div className="space-y-2">
              <Label htmlFor="edit_entry_fee_brutto">{t('pricing.entryFee')}</Label>
              <Input
                id="edit_entry_fee_brutto"
                type="number"
                min="0"
                step="100"
                value={formData.entry_fee_brutto}
                onChange={(e) =>
                  setFormData({ ...formData, entry_fee_brutto: parseInt(e.target.value) || 0 })
                }
                data-testid="edit-entry-fee-input"
              />
            </div>

            {/* Trainer Fee */}
            <div className="space-y-2">
              <Label htmlFor="edit_trainer_fee_brutto">{t('pricing.trainerFee')}</Label>
              <Input
                id="edit_trainer_fee_brutto"
                type="number"
                min="0"
                step="100"
                value={formData.trainer_fee_brutto}
                onChange={(e) =>
                  setFormData({ ...formData, trainer_fee_brutto: parseInt(e.target.value) || 0 })
                }
                data-testid="edit-trainer-fee-input"
              />
            </div>

            {/* Valid From */}
            <div className="space-y-2">
              <Label htmlFor="edit_valid_from">{t('pricing.validFrom')}</Label>
              <Input
                id="edit_valid_from"
                type="date"
                value={formData.valid_from}
                onChange={(e) => setFormData({ ...formData, valid_from: e.target.value })}
                data-testid="edit-valid-from-input"
              />
            </div>

            {/* Valid Until (optional) */}
            <div className="space-y-2">
              <Label htmlFor="edit_valid_until">{t('pricing.validUntil')} ({t('pricing.optional')})</Label>
              <Input
                id="edit_valid_until"
                type="date"
                value={formData.valid_until || ''}
                onChange={(e) => setFormData({ ...formData, valid_until: e.target.value || null })}
                data-testid="edit-valid-until-input"
              />
            </div>

            {/* Submit Button */}
            <Button
              onClick={handleUpdatePricing}
              disabled={updateMutation.isPending}
              className="w-full"
              data-testid="update-pricing-btn"
            >
              {updateMutation.isPending ? t('common:loading') : t('pricing.update')}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
