import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { PlusCircle, Edit, Trash2, Tag } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/hooks/use-toast'
import { serviceTypesApi, serviceTypeKeys } from '@/api/serviceTypes'
import type { ServiceType, ServiceTypeFormData } from '@/types/serviceType'

// Validation schema
const serviceTypeSchema = z.object({
  code: z.string().min(1, 'validation.codeRequired').max(64).regex(/^[A-Z0-9_]+$/, 'validation.codeFormat'),
  name: z.string().min(1, 'validation.nameRequired').max(255),
  description: z.string().optional(),
  default_entry_fee_brutto: z.coerce.number().min(0, 'validation.minZero'),
  default_trainer_fee_brutto: z.coerce.number().min(0, 'validation.minZero'),
  is_active: z.boolean().optional(),
})

type FormData = z.infer<typeof serviceTypeSchema>

export default function ServiceTypesPage() {
  const { t } = useTranslation(['admin', 'common'])
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [selectedServiceType, setSelectedServiceType] = useState<ServiceType | null>(null)

  // Fetch service types
  const { data: serviceTypes, isLoading } = useQuery({
    queryKey: serviceTypeKeys.lists(),
    queryFn: () => serviceTypesApi.list(),
  })

  // Create form
  const createForm = useForm<FormData>({
    resolver: zodResolver(serviceTypeSchema),
    defaultValues: {
      code: '',
      name: '',
      description: '',
      default_entry_fee_brutto: 0,
      default_trainer_fee_brutto: 0,
      is_active: true,
    },
  })

  // Edit form
  const editForm = useForm<FormData>({
    resolver: zodResolver(serviceTypeSchema),
  })

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: ServiceTypeFormData) => serviceTypesApi.create(data),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: serviceTypeKeys.lists() })
      toast({
        title: t('serviceTypes.createSuccess'),
        description: t('serviceTypes.createSuccessDescription', { count: result.price_codes_created }),
      })
      setCreateDialogOpen(false)
      createForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('serviceTypes.createError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<ServiceTypeFormData> }) =>
      serviceTypesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: serviceTypeKeys.lists() })
      toast({
        title: t('serviceTypes.updateSuccess'),
        description: t('serviceTypes.updateSuccessDescription'),
      })
      setEditDialogOpen(false)
      setSelectedServiceType(null)
      editForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('serviceTypes.updateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => serviceTypesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: serviceTypeKeys.lists() })
      toast({
        title: t('serviceTypes.deleteSuccess'),
        description: t('serviceTypes.deleteSuccessDescription'),
      })
      setDeleteDialogOpen(false)
      setSelectedServiceType(null)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('serviceTypes.deleteError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Toggle active mutation
  const toggleActiveMutation = useMutation({
    mutationFn: (id: number) => serviceTypesApi.toggleActive(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: serviceTypeKeys.lists() })
      toast({
        title: t('serviceTypes.statusUpdated'),
        description: t('serviceTypes.statusUpdatedDescription'),
      })
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('serviceTypes.statusUpdateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  const handleCreate = (data: FormData) => {
    createMutation.mutate(data as ServiceTypeFormData)
  }

  const handleEdit = (serviceType: ServiceType) => {
    setSelectedServiceType(serviceType)
    editForm.reset({
      code: serviceType.code,
      name: serviceType.name,
      description: serviceType.description || '',
      default_entry_fee_brutto: serviceType.default_entry_fee_brutto,
      default_trainer_fee_brutto: serviceType.default_trainer_fee_brutto,
      is_active: serviceType.is_active,
    })
    setEditDialogOpen(true)
  }

  const handleUpdate = (data: FormData) => {
    if (selectedServiceType) {
      updateMutation.mutate({ id: selectedServiceType.id, data: data as Partial<ServiceTypeFormData> })
    }
  }

  const handleDelete = (serviceType: ServiceType) => {
    setSelectedServiceType(serviceType)
    setDeleteDialogOpen(true)
  }

  const confirmDelete = () => {
    if (selectedServiceType) {
      deleteMutation.mutate(selectedServiceType.id)
    }
  }

  const handleToggleActive = (serviceType: ServiceType) => {
    toggleActiveMutation.mutate(serviceType.id)
  }

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('hu-HU', { style: 'currency', currency: 'HUF', maximumFractionDigits: 0 }).format(amount)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('serviceTypes.title')}</h1>
          <p className="text-gray-500 mt-2">{t('serviceTypes.subtitle')}</p>
        </div>
        <Button onClick={() => setCreateDialogOpen(true)} data-testid="create-service-type-btn">
          <PlusCircle className="h-4 w-4 mr-2" />
          {t('serviceTypes.createServiceType')}
        </Button>
      </div>

      {/* Service Types Table */}
      <Card>
        <CardHeader>
          <CardTitle>{t('serviceTypes.serviceTypesList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : serviceTypes && serviceTypes.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('serviceTypes.code')}</TableHead>
                  <TableHead>{t('serviceTypes.name')}</TableHead>
                  <TableHead>{t('serviceTypes.defaultEntryFee')}</TableHead>
                  <TableHead>{t('serviceTypes.defaultTrainerFee')}</TableHead>
                  <TableHead>{t('serviceTypes.status')}</TableHead>
                  <TableHead className="text-right">{t('common:actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {serviceTypes.map((serviceType) => (
                  <TableRow key={serviceType.id} data-testid={`service-type-row-${serviceType.id}`}>
                    <TableCell className="font-mono">
                      <div className="flex items-center gap-2">
                        <Tag className="h-4 w-4 text-gray-400" />
                        {serviceType.code}
                      </div>
                    </TableCell>
                    <TableCell className="font-medium">{serviceType.name}</TableCell>
                    <TableCell>{formatCurrency(serviceType.default_entry_fee_brutto)}</TableCell>
                    <TableCell>{formatCurrency(serviceType.default_trainer_fee_brutto)}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Switch
                          checked={serviceType.is_active}
                          onCheckedChange={() => handleToggleActive(serviceType)}
                          data-testid={`toggle-active-${serviceType.id}`}
                        />
                        <Badge variant={serviceType.is_active ? 'default' : 'secondary'}>
                          {serviceType.is_active ? t('serviceTypes.active') : t('serviceTypes.inactive')}
                        </Badge>
                      </div>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleEdit(serviceType)}
                          data-testid={`edit-service-type-btn-${serviceType.id}`}
                        >
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => handleDelete(serviceType)}
                          data-testid={`delete-service-type-btn-${serviceType.id}`}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">{t('serviceTypes.noServiceTypesFound')}</p>
          )}
        </CardContent>
      </Card>

      {/* Create Dialog */}
      <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
        <DialogContent className="max-w-lg" data-testid="create-service-type-dialog">
          <DialogHeader>
            <DialogTitle>{t('serviceTypes.createServiceType')}</DialogTitle>
            <DialogDescription>{t('serviceTypes.createServiceTypeDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={createForm.handleSubmit(handleCreate)} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="create-code">{t('serviceTypes.code')}</Label>
                <Input
                  id="create-code"
                  placeholder="PT, MASSZAZS, GYOGYTORNA"
                  className="uppercase"
                  data-testid="create-code-input"
                  {...createForm.register('code', {
                    onChange: (e) => {
                      e.target.value = e.target.value.toUpperCase()
                    }
                  })}
                />
                {createForm.formState.errors.code && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.code.message!)}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-name">{t('serviceTypes.name')}</Label>
                <Input
                  id="create-name"
                  placeholder="Személyi edzés"
                  data-testid="create-name-input"
                  {...createForm.register('name')}
                />
                {createForm.formState.errors.name && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.name.message!)}</p>
                )}
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="create-description">{t('serviceTypes.description')}</Label>
              <Textarea
                id="create-description"
                rows={2}
                data-testid="create-description-input"
                {...createForm.register('description')}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="create-entry-fee">{t('serviceTypes.defaultEntryFee')}</Label>
                <Input
                  id="create-entry-fee"
                  type="number"
                  min="0"
                  data-testid="create-entry-fee-input"
                  {...createForm.register('default_entry_fee_brutto')}
                />
                {createForm.formState.errors.default_entry_fee_brutto && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.default_entry_fee_brutto.message!)}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-trainer-fee">{t('serviceTypes.defaultTrainerFee')}</Label>
                <Input
                  id="create-trainer-fee"
                  type="number"
                  min="0"
                  data-testid="create-trainer-fee-input"
                  {...createForm.register('default_trainer_fee_brutto')}
                />
                {createForm.formState.errors.default_trainer_fee_brutto && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.default_trainer_fee_brutto.message!)}</p>
                )}
              </div>
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setCreateDialogOpen(false)}
              >
                {t('common:cancel')}
              </Button>
              <Button
                type="submit"
                disabled={createMutation.isPending}
                data-testid="submit-create-service-type-btn"
              >
                {createMutation.isPending ? t('common:loading') : t('common:create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit Dialog */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent className="max-w-lg" data-testid="edit-service-type-dialog">
          <DialogHeader>
            <DialogTitle>{t('serviceTypes.editServiceType')}</DialogTitle>
            <DialogDescription>{t('serviceTypes.editServiceTypeDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={editForm.handleSubmit(handleUpdate)} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="edit-code">{t('serviceTypes.code')}</Label>
                <Input
                  id="edit-code"
                  className="uppercase"
                  {...editForm.register('code', {
                    onChange: (e) => {
                      e.target.value = e.target.value.toUpperCase()
                    }
                  })}
                />
                {editForm.formState.errors.code && (
                  <p className="text-sm text-destructive">{t(editForm.formState.errors.code.message!)}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-name">{t('serviceTypes.name')}</Label>
                <Input id="edit-name" {...editForm.register('name')} />
                {editForm.formState.errors.name && (
                  <p className="text-sm text-destructive">{t(editForm.formState.errors.name.message!)}</p>
                )}
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-description">{t('serviceTypes.description')}</Label>
              <Textarea id="edit-description" rows={2} {...editForm.register('description')} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="edit-entry-fee">{t('serviceTypes.defaultEntryFee')}</Label>
                <Input
                  id="edit-entry-fee"
                  type="number"
                  min="0"
                  {...editForm.register('default_entry_fee_brutto')}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-trainer-fee">{t('serviceTypes.defaultTrainerFee')}</Label>
                <Input
                  id="edit-trainer-fee"
                  type="number"
                  min="0"
                  {...editForm.register('default_trainer_fee_brutto')}
                />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEditDialogOpen(false)}>
                {t('common:cancel')}
              </Button>
              <Button type="submit" disabled={updateMutation.isPending}>
                {updateMutation.isPending ? t('common:loading') : t('common:save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent data-testid="delete-service-type-dialog">
          <AlertDialogHeader>
            <AlertDialogTitle>{t('serviceTypes.deleteServiceType')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('serviceTypes.deleteServiceTypeConfirmation', { name: selectedServiceType?.name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              data-testid="confirm-delete-service-type-btn"
            >
              {deleteMutation.isPending ? t('common:loading') : t('common:delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
