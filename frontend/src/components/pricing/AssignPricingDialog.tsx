import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { classPricingDefaultsApi, pricingKeys } from '@/api/pricing'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { useToast } from '@/hooks/use-toast'
import { DollarSign } from 'lucide-react'
import { format } from 'date-fns'

interface AssignPricingDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  classTemplateId?: number
  classTemplateName?: string
  eventId?: number
  eventTitle?: string
  onSuccess?: () => void
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

export function AssignPricingDialog({
  open,
  onOpenChange,
  classTemplateId,
  classTemplateName,
  eventId,
  eventTitle,
  onSuccess,
}: AssignPricingDialogProps) {
  const { t } = useTranslation('admin')
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [selectedPricingId, setSelectedPricingId] = useState<number | null>(null)

  // Determine if we're using class template mode or event mode
  const isClassTemplateMode = classTemplateId !== undefined && classTemplateId > 0
  const isEventMode = eventId !== undefined && eventId > 0
  const displayName = isClassTemplateMode ? classTemplateName : eventTitle

  // Fetch all active pricing options - show all available pricing regardless of class template
  const { data: pricingOptions, isLoading } = useQuery({
    queryKey: pricingKeys.classDefaultsList({ is_active: true }),
    queryFn: () => classPricingDefaultsApi.list({ is_active: true }),
    enabled: open,
  })

  // Assign pricing mutation for class template mode
  const assignClassTemplateMutation = useMutation({
    mutationFn: (pricingId: number) => classPricingDefaultsApi.assign(classTemplateId!, pricingId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: pricingKeys.classDefaults() })
      toast({
        title: t('pricing.assignSuccess'),
        description: t('pricing.assignSuccessDescription'),
      })
      onOpenChange(false)
      setSelectedPricingId(null)
      onSuccess?.()
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('pricing.assignError'),
        description: t('pricing.assignErrorDescription'),
      })
    },
  })

  // Assign pricing mutation for event mode
  const assignEventMutation = useMutation({
    mutationFn: (pricingId: number) => classPricingDefaultsApi.assignEvent(eventId!, pricingId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['events'] })
      toast({
        title: t('pricing.assignSuccess'),
        description: t('pricing.assignSuccessDescription'),
      })
      onOpenChange(false)
      setSelectedPricingId(null)
      onSuccess?.()
    },
    onError: () => {
      toast({
        variant: 'destructive',
        title: t('pricing.assignError'),
        description: t('pricing.assignErrorDescription'),
      })
    },
  })

  const isPending = assignClassTemplateMutation.isPending || assignEventMutation.isPending

  const handleAssign = () => {
    if (!selectedPricingId) {
      toast({
        variant: 'destructive',
        title: t('pricing.validationError'),
        description: t('pricing.selectPricingFirst'),
      })
      return
    }

    if (isClassTemplateMode) {
      assignClassTemplateMutation.mutate(selectedPricingId)
    } else if (isEventMode) {
      assignEventMutation.mutate(selectedPricingId)
    }
  }

  // Find the active pricing for the current class template (if in class template mode)
  const activePricing = isClassTemplateMode
    ? pricingOptions?.find((p) => p.is_active && p.class_template_id === classTemplateId)
    : null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>
            <div className="flex items-center gap-2">
              <DollarSign className="h-5 w-5" />
              {t('pricing.assignPricing')}
            </div>
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {/* Class Template / Event Info */}
          <div className="bg-gray-50 p-3 rounded-md">
            <p className="text-sm text-gray-600">{t('pricing.assigningTo')}</p>
            <p className="font-medium">{displayName || t('pricing.unknownItem')}</p>
          </div>

          {/* Current Active Pricing */}
          {activePricing && (
            <div className="bg-blue-50 p-3 rounded-md border border-blue-200">
              <p className="text-sm text-blue-600 mb-1">{t('pricing.currentActivePricing')}</p>
              <p className="font-medium text-sm">
                {activePricing.name || t('pricing.unnamedPricing')}
              </p>
              <div className="flex gap-4 mt-2 text-sm">
                <span>
                  {t('pricing.entryFee')}: {formatCurrency(activePricing.entry_fee_brutto)}
                </span>
                <span>
                  {t('pricing.trainerFee')}: {formatCurrency(activePricing.trainer_fee_brutto)}
                </span>
              </div>
            </div>
          )}

          {/* Select Pricing */}
          <div className="space-y-2">
            <Label htmlFor="pricing_select">{t('pricing.selectPricingToAssign')}</Label>
            {isLoading ? (
              <p className="text-sm text-gray-500">{t('common:loading')}</p>
            ) : pricingOptions && pricingOptions.length > 0 ? (
              <Select
                value={selectedPricingId?.toString() || ''}
                onValueChange={(value) => setSelectedPricingId(parseInt(value))}
              >
                <SelectTrigger id="pricing_select" data-testid="pricing-select">
                  <SelectValue placeholder={t('pricing.selectPricing')} />
                </SelectTrigger>
                <SelectContent>
                  {pricingOptions.map((pricing) => (
                    <SelectItem key={pricing.id} value={pricing.id.toString()}>
                      <div className="flex items-center justify-between gap-4 w-full">
                        <span>
                          {pricing.name || `${t('pricing.pricing')} #${pricing.id}`}
                          {!isClassTemplateMode && pricing.class_template && (
                            <span className="text-gray-500 ml-1">
                              ({pricing.class_template.title})
                            </span>
                          )}
                        </span>
                        {pricing.is_active && (
                          <Badge variant="default" className="ml-2">
                            {t('pricing.active')}
                          </Badge>
                        )}
                      </div>
                      <div className="text-xs text-gray-500 mt-1">
                        {formatCurrency(pricing.entry_fee_brutto)} / {formatCurrency(pricing.trainer_fee_brutto)}
                        {' • '}
                        {format(new Date(pricing.valid_from), 'yyyy-MM-dd')}
                      </div>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : (
              <p className="text-sm text-gray-500">{t('pricing.noPricingAvailable')}</p>
            )}
          </div>

          {/* Selected Pricing Details */}
          {selectedPricingId && pricingOptions && (
            <div className="bg-green-50 p-3 rounded-md border border-green-200">
              {(() => {
                const selected = pricingOptions.find((p) => p.id === selectedPricingId)
                if (!selected) return null
                return (
                  <>
                    <p className="text-sm text-green-600 mb-1">{t('pricing.selectedPricing')}</p>
                    <p className="font-medium text-sm">
                      {selected.name || t('pricing.unnamedPricing')}
                    </p>
                    <div className="grid grid-cols-2 gap-2 mt-2 text-sm">
                      <div>
                        <p className="text-gray-600">{t('pricing.entryFee')}</p>
                        <p className="font-medium">{formatCurrency(selected.entry_fee_brutto)}</p>
                      </div>
                      <div>
                        <p className="text-gray-600">{t('pricing.trainerFee')}</p>
                        <p className="font-medium">{formatCurrency(selected.trainer_fee_brutto)}</p>
                      </div>
                      <div>
                        <p className="text-gray-600">{t('pricing.validFrom')}</p>
                        <p className="font-medium">{format(new Date(selected.valid_from), 'yyyy-MM-dd')}</p>
                      </div>
                      {selected.valid_until && (
                        <div>
                          <p className="text-gray-600">{t('pricing.validUntil')}</p>
                          <p className="font-medium">{format(new Date(selected.valid_until), 'yyyy-MM-dd')}</p>
                        </div>
                      )}
                    </div>
                  </>
                )
              })()}
            </div>
          )}

          {/* Actions */}
          <div className="flex gap-2 justify-end pt-4">
            <Button
              variant="outline"
              onClick={() => {
                onOpenChange(false)
                setSelectedPricingId(null)
              }}
              disabled={isPending}
            >
              {t('common:cancel')}
            </Button>
            <Button
              onClick={handleAssign}
              disabled={!selectedPricingId || isPending || pricingOptions?.length === 0}
              data-testid="assign-pricing-btn"
            >
              {isPending ? t('common:loading') : t('pricing.assignPricing')}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
