import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Dumbbell, Edit, Trash2, DollarSign } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/hooks/use-toast'
import { classTemplatesApi, adminKeys } from '@/api/admin'
import type { ClassTemplate } from '@/types/admin'
import { createClassTemplateSchema, updateClassTemplateSchema, type CreateClassTemplateFormData, type UpdateClassTemplateFormData } from '@/lib/validations/admin'
import { AssignPricingDialog } from '@/components/pricing/AssignPricingDialog'

export default function ClassTemplatesPage() {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [pricingDialogOpen, setPricingDialogOpen] = useState(false)
  const [selectedTemplate, setSelectedTemplate] = useState<ClassTemplate | null>(null)

  // Fetch templates
  const { data: templates, isLoading } = useQuery({
    queryKey: adminKeys.classTemplatesList(),
    queryFn: () => classTemplatesApi.list(),
  })

  // Create form
  const createForm = useForm<CreateClassTemplateFormData>({
    resolver: zodResolver(createClassTemplateSchema),
    defaultValues: {
      name: '',
      description: '',
      duration_minutes: 60,
      default_capacity: 10,
      credits_required: 1,
      base_price_huf: 1000,
      color: '#3b82f6',
      is_active: true,
      is_public_visible: true,
    },
  })

  // Edit form
  const editForm = useForm<UpdateClassTemplateFormData>({
    resolver: zodResolver(updateClassTemplateSchema),
  })

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: CreateClassTemplateFormData) => classTemplatesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.classTemplatesList() })
      toast({
        title: t('classTemplates.createSuccess'),
      })
      setCreateDialogOpen(false)
      createForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('classTemplates.createError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateClassTemplateFormData }) =>
      classTemplatesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.classTemplatesList() })
      toast({
        title: t('classTemplates.updateSuccess'),
      })
      setEditDialogOpen(false)
      setSelectedTemplate(null)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('classTemplates.updateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => classTemplatesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.classTemplatesList() })
      toast({
        title: t('classTemplates.deleteSuccess'),
      })
      setDeleteDialogOpen(false)
      setSelectedTemplate(null)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('classTemplates.deleteError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  const handleEdit = (template: ClassTemplate) => {
    setSelectedTemplate(template)
    editForm.reset({
      name: template.title,
      description: template.description || undefined,
      duration_minutes: template.duration_minutes,
      default_capacity: template.default_capacity,
      credits_required: template.credits_required || undefined,
      base_price_huf: template.base_price_huf || undefined,
      color: template.color || undefined,
      is_active: template.is_active,
      is_public_visible: template.is_public_visible,
    })
    setEditDialogOpen(true)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('classTemplates.title')}</h1>
          <p className="text-gray-500 mt-2">{t('classTemplates.subtitle')}</p>
        </div>
        <Button onClick={() => setCreateDialogOpen(true)} data-testid="create-template-btn">
          <Dumbbell className="h-4 w-4 mr-2" />
          {t('classTemplates.createTemplate')}
        </Button>
      </div>

      {/* Templates Table */}
      <Card>
        <CardHeader>
          <CardTitle>{t('classTemplates.templatesList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : templates && templates.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('classTemplates.name')}</TableHead>
                  <TableHead>{t('classTemplates.duration')}</TableHead>
                  <TableHead>{t('classTemplates.capacity')}</TableHead>
                  <TableHead>{t('classTemplates.credits')}</TableHead>
                  <TableHead>{t('classTemplates.basePrice')}</TableHead>
                  <TableHead>{t('classTemplates.status')}</TableHead>
                  <TableHead>{t('classTemplates.isPublicVisible')}</TableHead>
                  <TableHead className="text-right">{t('common:actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {templates.map((template) => (
                  <TableRow key={template.id} data-testid={`template-row-${template.id}`}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        {template.color && (
                          <div
                            className="w-4 h-4 rounded border"
                            style={{ backgroundColor: template.color }}
                          />
                        )}
                        <span className="font-medium">{template.title}</span>
                      </div>
                    </TableCell>
                    <TableCell>{template.duration_minutes} min</TableCell>
                    <TableCell>{template.default_capacity}</TableCell>
                    <TableCell>{template.credits_required || 0}</TableCell>
                    <TableCell>{template.base_price_huf || 0} Ft</TableCell>
                    <TableCell>
                      <Badge variant={template.is_active ? 'default' : 'secondary'}>
                        {template.is_active ? t('classTemplates.active') : t('classTemplates.inactive')}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant={template.is_public_visible ? 'default' : 'outline'}>
                        {template.is_public_visible ? t('common:yes') : t('common:no')}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => {
                            setSelectedTemplate(template)
                            setPricingDialogOpen(true)
                          }}
                          data-testid={`assign-pricing-btn-${template.id}`}
                        >
                          <DollarSign className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleEdit(template)}
                          data-testid={`edit-template-btn-${template.id}`}
                        >
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => {
                            setSelectedTemplate(template)
                            setDeleteDialogOpen(true)
                          }}
                          data-testid={`delete-template-btn-${template.id}`}
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
            <p className="text-sm text-gray-500 text-center py-8">{t('classTemplates.noTemplatesFound')}</p>
          )}
        </CardContent>
      </Card>

      {/* Create Dialog */}
      <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto" data-testid="create-template-dialog">
          <DialogHeader>
            <DialogTitle>{t('classTemplates.createTemplate')}</DialogTitle>
            <DialogDescription>{t('classTemplates.createTemplateDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={createForm.handleSubmit((data) => createMutation.mutate(data))} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="create-name">{t('classTemplates.name')}</Label>
              <Input id="create-name" {...createForm.register('name')} data-testid="create-template-name-input" />
              {createForm.formState.errors.name && (
                <p className="text-sm text-destructive">{t(createForm.formState.errors.name.message!)}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="create-description">{t('classTemplates.description')}</Label>
              <Textarea id="create-description" {...createForm.register('description')} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="create-duration">{t('classTemplates.duration')}</Label>
                <Input
                  id="create-duration"
                  type="number"
                  {...createForm.register('duration_minutes', { valueAsNumber: true })}
                />
                {createForm.formState.errors.duration_minutes && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.duration_minutes.message!)}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-capacity">{t('classTemplates.capacity')}</Label>
                <Input
                  id="create-capacity"
                  type="number"
                  {...createForm.register('default_capacity', { valueAsNumber: true })}
                />
                {createForm.formState.errors.default_capacity && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.default_capacity.message!)}</p>
                )}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="create-credits">{t('classTemplates.credits')}</Label>
                <Input
                  id="create-credits"
                  type="number"
                  {...createForm.register('credits_required', { valueAsNumber: true })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-base-price">{t('classTemplates.basePrice')}</Label>
                <Input
                  id="create-base-price"
                  type="number"
                  {...createForm.register('base_price_huf', { valueAsNumber: true })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="create-color">{t('classTemplates.color')}</Label>
              <Input id="create-color" type="color" {...createForm.register('color')} />
            </div>
            <div className="flex items-center space-x-2">
              <Switch
                id="create-is-active"
                checked={createForm.watch('is_active')}
                onCheckedChange={(checked) => createForm.setValue('is_active', checked)}
              />
              <Label htmlFor="create-is-active">{t('classTemplates.isActive')}</Label>
            </div>
            <div className="flex items-center space-x-2">
              <Switch
                id="create-is-public-visible"
                checked={createForm.watch('is_public_visible')}
                onCheckedChange={(checked) => createForm.setValue('is_public_visible', checked)}
              />
              <Label htmlFor="create-is-public-visible">{t('classTemplates.isPublicVisible')}</Label>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setCreateDialogOpen(false)}>
                {t('common:cancel')}
              </Button>
              <Button type="submit" disabled={createMutation.isPending} data-testid="submit-create-template-btn">
                {createMutation.isPending ? t('common:loading') : t('common:create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit Dialog */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('classTemplates.editTemplate')}</DialogTitle>
          </DialogHeader>
          <form
            onSubmit={editForm.handleSubmit((data) =>
              selectedTemplate && updateMutation.mutate({ id: selectedTemplate.id, data })
            )}
            className="space-y-4"
          >
            <div className="space-y-2">
              <Label htmlFor="edit-name">{t('classTemplates.name')}</Label>
              <Input id="edit-name" {...editForm.register('name')} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-description">{t('classTemplates.description')}</Label>
              <Textarea id="edit-description" {...editForm.register('description')} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="edit-duration">{t('classTemplates.duration')}</Label>
                <Input
                  id="edit-duration"
                  type="number"
                  {...editForm.register('duration_minutes', { valueAsNumber: true })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-capacity">{t('classTemplates.capacity')}</Label>
                <Input
                  id="edit-capacity"
                  type="number"
                  {...editForm.register('default_capacity', { valueAsNumber: true })}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="edit-credits">{t('classTemplates.credits')}</Label>
                <Input
                  id="edit-credits"
                  type="number"
                  {...editForm.register('credits_required', { valueAsNumber: true })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-base-price">{t('classTemplates.basePrice')}</Label>
                <Input
                  id="edit-base-price"
                  type="number"
                  {...editForm.register('base_price_huf', { valueAsNumber: true })}
                />
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <Switch
                id="edit-is-active"
                checked={editForm.watch('is_active')}
                onCheckedChange={(checked) => editForm.setValue('is_active', checked)}
              />
              <Label htmlFor="edit-is-active">{t('classTemplates.isActive')}</Label>
            </div>
            <div className="flex items-center space-x-2">
              <Switch
                id="edit-is-public-visible"
                checked={editForm.watch('is_public_visible')}
                onCheckedChange={(checked) => editForm.setValue('is_public_visible', checked)}
              />
              <Label htmlFor="edit-is-public-visible">{t('classTemplates.isPublicVisible')}</Label>
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

      {/* Delete Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('classTemplates.deleteTemplate')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('classTemplates.deleteTemplateConfirmation', { name: selectedTemplate?.title })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => selectedTemplate && deleteMutation.mutate(selectedTemplate.id)}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleteMutation.isPending ? t('common:loading') : t('common:delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Assign Pricing Dialog */}
      {selectedTemplate && (
        <AssignPricingDialog
          open={pricingDialogOpen}
          onOpenChange={setPricingDialogOpen}
          classTemplateId={selectedTemplate.id}
          classTemplateName={selectedTemplate.title}
          onSuccess={() => queryClient.invalidateQueries({ queryKey: adminKeys.classTemplatesList() })}
        />
      )}
    </div>
  )
}
