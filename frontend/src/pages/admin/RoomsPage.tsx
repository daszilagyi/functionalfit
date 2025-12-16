import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { PlusCircle, Edit, Trash2 } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/hooks/use-toast'
import { roomsApi, sitesApi, adminKeys } from '@/api/admin'
import type { Room } from '@/types/admin'
import { createRoomSchema, updateRoomSchema, type CreateRoomFormData, type UpdateRoomFormData } from '@/lib/validations/admin'

export default function RoomsPage() {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [selectedRoom, setSelectedRoom] = useState<Room | null>(null)

  // Fetch rooms
  const { data: rooms, isLoading } = useQuery({
    queryKey: adminKeys.roomsList(),
    queryFn: () => roomsApi.list(),
  })

  // Fetch sites for dropdown
  const { data: sites } = useQuery({
    queryKey: adminKeys.sitesList(),
    queryFn: () => sitesApi.list(),
  })

  // Create form
  const createForm = useForm<CreateRoomFormData>({
    resolver: zodResolver(createRoomSchema),
    defaultValues: {
      site_id: sites && sites.length > 0 ? sites[0].id : 1,
      name: '',
      capacity: undefined,
      google_calendar_id: '',
      color: '',
    },
  })

  // Edit form
  const editForm = useForm<UpdateRoomFormData>({
    resolver: zodResolver(updateRoomSchema),
  })

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: CreateRoomFormData) => roomsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.roomsList() })
      toast({
        title: t('rooms.createSuccess'),
        description: t('rooms.createSuccessDescription'),
      })
      setCreateDialogOpen(false)
      createForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('rooms.createError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateRoomFormData }) =>
      roomsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.roomsList() })
      toast({
        title: t('rooms.updateSuccess'),
        description: t('rooms.updateSuccessDescription'),
      })
      setEditDialogOpen(false)
      setSelectedRoom(null)
      editForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('rooms.updateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => roomsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.roomsList() })
      toast({
        title: t('rooms.deleteSuccess'),
        description: t('rooms.deleteSuccessDescription'),
      })
      setDeleteDialogOpen(false)
      setSelectedRoom(null)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('rooms.deleteError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  const handleCreate = (data: CreateRoomFormData) => {
    createMutation.mutate(data)
  }

  const handleEdit = (room: Room) => {
    setSelectedRoom(room)
    editForm.reset({
      site_id: room.site_id,
      name: room.name,
      capacity: room.capacity,
      google_calendar_id: room.google_calendar_id,
      color: room.color,
    })
    setEditDialogOpen(true)
  }

  const handleUpdate = (data: UpdateRoomFormData) => {
    if (selectedRoom) {
      updateMutation.mutate({ id: selectedRoom.id, data })
    }
  }

  const handleDelete = (room: Room) => {
    setSelectedRoom(room)
    setDeleteDialogOpen(true)
  }

  const confirmDelete = () => {
    if (selectedRoom) {
      deleteMutation.mutate(selectedRoom.id)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('rooms.title')}</h1>
          <p className="text-gray-500 mt-2">{t('rooms.subtitle')}</p>
        </div>
        <Button onClick={() => setCreateDialogOpen(true)} data-testid="create-room-btn">
          <PlusCircle className="h-4 w-4 mr-2" />
          {t('rooms.createRoom')}
        </Button>
      </div>

      {/* Rooms Table */}
      <Card>
        <CardHeader>
          <CardTitle>{t('rooms.roomsList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : rooms && rooms.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('rooms.name')}</TableHead>
                  <TableHead>{t('rooms.site')}</TableHead>
                  <TableHead>{t('rooms.capacity')}</TableHead>
                  <TableHead>{t('rooms.color')}</TableHead>
                  <TableHead className="text-right">{t('common:actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rooms.map((room) => (
                  <TableRow key={room.id} data-testid={`room-row-${room.id}`}>
                    <TableCell className="font-medium">{room.name}</TableCell>
                    <TableCell>
                      <Badge variant="outline">{room.site?.name || '-'}</Badge>
                    </TableCell>
                    <TableCell>{room.capacity || '-'}</TableCell>
                    <TableCell>
                      {room.color ? (
                        <div className="flex items-center gap-2">
                          <div
                            className="w-6 h-6 rounded border"
                            style={{ backgroundColor: room.color }}
                          />
                          <span className="text-sm text-gray-500">{room.color}</span>
                        </div>
                      ) : (
                        '-'
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleEdit(room)}
                          data-testid={`edit-room-btn-${room.id}`}
                        >
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => handleDelete(room)}
                          data-testid={`delete-room-btn-${room.id}`}
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
            <p className="text-sm text-gray-500 text-center py-8">{t('rooms.noRoomsFound')}</p>
          )}
        </CardContent>
      </Card>

      {/* Create Dialog */}
      <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
        <DialogContent data-testid="create-room-dialog">
          <DialogHeader>
            <DialogTitle>{t('rooms.createRoom')}</DialogTitle>
            <DialogDescription>{t('rooms.createRoomDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={createForm.handleSubmit(handleCreate)} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="create-site">{t('rooms.site')}</Label>
              <Select
                value={createForm.watch('site_id')?.toString()}
                onValueChange={(value) => createForm.setValue('site_id', parseInt(value))}
              >
                <SelectTrigger id="create-site" data-testid="create-site-select">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {sites?.map((site) => (
                    <SelectItem key={site.id} value={site.id.toString()}>
                      {site.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {createForm.formState.errors.site_id && (
                <p className="text-sm text-destructive">{t(createForm.formState.errors.site_id.message!)}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="create-name">{t('rooms.name')}</Label>
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
              <Label htmlFor="create-capacity">{t('rooms.capacity')}</Label>
              <Input
                id="create-capacity"
                type="number"
                data-testid="create-capacity-input"
                {...createForm.register('capacity', { valueAsNumber: true })}
              />
              {createForm.formState.errors.capacity && (
                <p className="text-sm text-destructive">{t(createForm.formState.errors.capacity.message!)}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="create-color">{t('rooms.color')}</Label>
              <Input
                id="create-color"
                type="color"
                data-testid="create-color-input"
                {...createForm.register('color')}
              />
              {createForm.formState.errors.color && (
                <p className="text-sm text-destructive">{t(createForm.formState.errors.color.message!)}</p>
              )}
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
                data-testid="submit-create-room-btn"
              >
                {createMutation.isPending ? t('common:loading') : t('common:create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit Dialog */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent data-testid="edit-room-dialog">
          <DialogHeader>
            <DialogTitle>{t('rooms.editRoom')}</DialogTitle>
            <DialogDescription>{t('rooms.editRoomDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={editForm.handleSubmit(handleUpdate)} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="edit-site">{t('rooms.site')}</Label>
              <Select
                value={editForm.watch('site_id')?.toString()}
                onValueChange={(value) => editForm.setValue('site_id', parseInt(value))}
              >
                <SelectTrigger id="edit-site">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {sites?.map((site) => (
                    <SelectItem key={site.id} value={site.id.toString()}>
                      {site.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-name">{t('rooms.name')}</Label>
              <Input
                id="edit-name"
                {...editForm.register('name')}
              />
              {editForm.formState.errors.name && (
                <p className="text-sm text-destructive">{t(editForm.formState.errors.name.message!)}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-capacity">{t('rooms.capacity')}</Label>
              <Input
                id="edit-capacity"
                type="number"
                {...editForm.register('capacity', { valueAsNumber: true })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-color">{t('rooms.color')}</Label>
              <Input
                id="edit-color"
                type="color"
                {...editForm.register('color')}
              />
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditDialogOpen(false)}
              >
                {t('common:cancel')}
              </Button>
              <Button
                type="submit"
                disabled={updateMutation.isPending}
              >
                {updateMutation.isPending ? t('common:loading') : t('common:save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent data-testid="delete-room-dialog">
          <AlertDialogHeader>
            <AlertDialogTitle>{t('rooms.deleteRoom')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('rooms.deleteRoomConfirmation', { name: selectedRoom?.name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              data-testid="confirm-delete-room-btn"
            >
              {deleteMutation.isPending ? t('common:loading') : t('common:delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
