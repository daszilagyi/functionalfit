import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { PlusCircle, Edit, Trash2, MapPin } from 'lucide-react'
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
import { sitesApi, adminKeys } from '@/api/admin'
import type { Site } from '@/types/admin'
import { createSiteSchema, updateSiteSchema, type CreateSiteFormData, type UpdateSiteFormData } from '@/lib/validations/admin'

export default function SitesPage() {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [selectedSite, setSelectedSite] = useState<Site | null>(null)

  // Fetch sites
  const { data: sites, isLoading } = useQuery({
    queryKey: adminKeys.sitesList(),
    queryFn: () => sitesApi.list(),
  })

  // Create form
  const createForm = useForm<CreateSiteFormData>({
    resolver: zodResolver(createSiteSchema),
    defaultValues: {
      name: '',
      slug: '',
      address: '',
      city: '',
      postal_code: '',
      phone: '',
      email: '',
      description: '',
      is_active: true,
    },
  })

  // Edit form
  const editForm = useForm<UpdateSiteFormData>({
    resolver: zodResolver(updateSiteSchema),
  })

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: CreateSiteFormData) => sitesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.sitesList() })
      toast({
        title: t('sites.createSuccess'),
        description: t('sites.createSuccessDescription'),
      })
      setCreateDialogOpen(false)
      createForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('sites.createError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateSiteFormData }) =>
      sitesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.sitesList() })
      toast({
        title: t('sites.updateSuccess'),
        description: t('sites.updateSuccessDescription'),
      })
      setEditDialogOpen(false)
      setSelectedSite(null)
      editForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('sites.updateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => sitesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.sitesList() })
      toast({
        title: t('sites.deleteSuccess'),
        description: t('sites.deleteSuccessDescription'),
      })
      setDeleteDialogOpen(false)
      setSelectedSite(null)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('sites.deleteError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Toggle active mutation
  const toggleActiveMutation = useMutation({
    mutationFn: (id: number) => sitesApi.toggleActive(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.sitesList() })
      toast({
        title: t('sites.statusUpdated'),
        description: t('sites.statusUpdatedDescription'),
      })
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('sites.statusUpdateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  const handleCreate = (data: CreateSiteFormData) => {
    createMutation.mutate(data)
  }

  const handleEdit = (site: Site) => {
    setSelectedSite(site)
    editForm.reset({
      name: site.name,
      slug: site.slug,
      address: site.address,
      city: site.city,
      postal_code: site.postal_code,
      phone: site.phone,
      email: site.email,
      description: site.description,
      is_active: site.is_active,
    })
    setEditDialogOpen(true)
  }

  const handleUpdate = (data: UpdateSiteFormData) => {
    if (selectedSite) {
      updateMutation.mutate({ id: selectedSite.id, data })
    }
  }

  const handleDelete = (site: Site) => {
    setSelectedSite(site)
    setDeleteDialogOpen(true)
  }

  const confirmDelete = () => {
    if (selectedSite) {
      deleteMutation.mutate(selectedSite.id)
    }
  }

  const handleToggleActive = (site: Site) => {
    toggleActiveMutation.mutate(site.id)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('sites.title')}</h1>
          <p className="text-gray-500 mt-2">{t('sites.subtitle')}</p>
        </div>
        <Button onClick={() => setCreateDialogOpen(true)} data-testid="create-site-btn">
          <PlusCircle className="h-4 w-4 mr-2" />
          {t('sites.createSite')}
        </Button>
      </div>

      {/* Sites Table */}
      <Card>
        <CardHeader>
          <CardTitle>{t('sites.sitesList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : sites && sites.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('sites.name')}</TableHead>
                  <TableHead>{t('sites.address')}</TableHead>
                  <TableHead>{t('sites.city')}</TableHead>
                  <TableHead>{t('sites.phone')}</TableHead>
                  <TableHead>{t('sites.roomsCount')}</TableHead>
                  <TableHead>{t('sites.status')}</TableHead>
                  <TableHead className="text-right">{t('common:actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {sites.map((site) => (
                  <TableRow key={site.id} data-testid={`site-row-${site.id}`}>
                    <TableCell className="font-medium">
                      <div className="flex items-center gap-2">
                        <MapPin className="h-4 w-4 text-gray-400" />
                        {site.name}
                      </div>
                    </TableCell>
                    <TableCell>{site.address || '-'}</TableCell>
                    <TableCell>{site.city || '-'}</TableCell>
                    <TableCell>{site.phone || '-'}</TableCell>
                    <TableCell>
                      <Badge variant="outline">{site.rooms_count || 0}</Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Switch
                          checked={site.is_active}
                          onCheckedChange={() => handleToggleActive(site)}
                          data-testid={`toggle-active-${site.id}`}
                        />
                        <Badge variant={site.is_active ? 'default' : 'secondary'}>
                          {site.is_active ? t('sites.active') : t('sites.inactive')}
                        </Badge>
                      </div>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleEdit(site)}
                          data-testid={`edit-site-btn-${site.id}`}
                        >
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => handleDelete(site)}
                          data-testid={`delete-site-btn-${site.id}`}
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
            <p className="text-sm text-gray-500 text-center py-8">{t('sites.noSitesFound')}</p>
          )}
        </CardContent>
      </Card>

      {/* Create Dialog */}
      <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
        <DialogContent className="max-w-2xl" data-testid="create-site-dialog">
          <DialogHeader>
            <DialogTitle>{t('sites.createSite')}</DialogTitle>
            <DialogDescription>{t('sites.createSiteDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={createForm.handleSubmit(handleCreate)} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="create-name">{t('sites.name')}</Label>
                <Input
                  id="create-name"
                  data-testid="create-name-input"
                  {...createForm.register('name')}
                />
                {createForm.formState.errors.name && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.name.message!)}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-slug">{t('sites.slug')}</Label>
                <Input
                  id="create-slug"
                  placeholder="auto-generated"
                  data-testid="create-slug-input"
                  {...createForm.register('slug')}
                />
                {createForm.formState.errors.slug && (
                  <p className="text-sm text-destructive">{t(createForm.formState.errors.slug.message!)}</p>
                )}
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="create-address">{t('sites.address')}</Label>
              <Input
                id="create-address"
                data-testid="create-address-input"
                {...createForm.register('address')}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="create-city">{t('sites.city')}</Label>
                <Input
                  id="create-city"
                  data-testid="create-city-input"
                  {...createForm.register('city')}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-postal-code">{t('sites.postalCode')}</Label>
                <Input
                  id="create-postal-code"
                  data-testid="create-postal-code-input"
                  {...createForm.register('postal_code')}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="create-phone">{t('sites.phone')}</Label>
                <Input
                  id="create-phone"
                  data-testid="create-phone-input"
                  {...createForm.register('phone')}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="create-email">{t('sites.email')}</Label>
                <Input
                  id="create-email"
                  type="email"
                  data-testid="create-email-input"
                  {...createForm.register('email')}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="create-description">{t('sites.description')}</Label>
              <Textarea
                id="create-description"
                rows={3}
                data-testid="create-description-input"
                {...createForm.register('description')}
              />
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
                data-testid="submit-create-site-btn"
              >
                {createMutation.isPending ? t('common:loading') : t('common:create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit Dialog - Similar structure to Create */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent className="max-w-2xl" data-testid="edit-site-dialog">
          <DialogHeader>
            <DialogTitle>{t('sites.editSite')}</DialogTitle>
            <DialogDescription>{t('sites.editSiteDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={editForm.handleSubmit(handleUpdate)} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="edit-name">{t('sites.name')}</Label>
                <Input id="edit-name" {...editForm.register('name')} />
                {editForm.formState.errors.name && (
                  <p className="text-sm text-destructive">{t(editForm.formState.errors.name.message!)}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-slug">{t('sites.slug')}</Label>
                <Input id="edit-slug" {...editForm.register('slug')} />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-address">{t('sites.address')}</Label>
              <Input id="edit-address" {...editForm.register('address')} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="edit-city">{t('sites.city')}</Label>
                <Input id="edit-city" {...editForm.register('city')} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-postal-code">{t('sites.postalCode')}</Label>
                <Input id="edit-postal-code" {...editForm.register('postal_code')} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="edit-phone">{t('sites.phone')}</Label>
                <Input id="edit-phone" {...editForm.register('phone')} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-email">{t('sites.email')}</Label>
                <Input id="edit-email" type="email" {...editForm.register('email')} />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-description">{t('sites.description')}</Label>
              <Textarea id="edit-description" rows={3} {...editForm.register('description')} />
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
        <AlertDialogContent data-testid="delete-site-dialog">
          <AlertDialogHeader>
            <AlertDialogTitle>{t('sites.deleteSite')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('sites.deleteSiteConfirmation', { name: selectedSite?.name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              data-testid="confirm-delete-site-btn"
            >
              {deleteMutation.isPending ? t('common:loading') : t('common:delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
