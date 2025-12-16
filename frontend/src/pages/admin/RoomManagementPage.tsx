import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { roomsApi, sitesApi, adminKeys } from '@/api/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
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
import { Plus, Pencil, Trash2, DoorOpen } from 'lucide-react'
import { useToast } from '@/hooks/use-toast'
import type { Room, CreateRoomRequest } from '@/types/admin'

export default function RoomManagementPage() {
  const { t } = useTranslation(['admin', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()

  const [siteFilter, setSiteFilter] = useState<number | undefined>()
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false)
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false)
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false)
  const [selectedRoom, setSelectedRoom] = useState<Room | null>(null)

  // Fetch sites
  const { data: sites } = useQuery({
    queryKey: adminKeys.sitesList(),
    queryFn: () => sitesApi.list(),
  })

  // Form state
  const [formData, setFormData] = useState<CreateRoomRequest>({
    site_id: sites && sites.length > 0 ? sites[0].id : 1,
    name: '',
    color: '#3788D8',
    capacity: undefined,
    google_calendar_id: '',
  })

  // Fetch rooms
  const { data: rooms, isLoading } = useQuery({
    queryKey: adminKeys.roomsList({ site_id: siteFilter }),
    queryFn: () => roomsApi.list({ site_id: siteFilter }),
  })

  // Create room mutation
  const createMutation = useMutation({
    mutationFn: roomsApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.rooms() })
      setIsCreateDialogOpen(false)
      resetForm()
      toast({
        title: t('common:success'),
        description: 'Room created successfully',
      })
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: 'Failed to create room',
        variant: 'destructive',
      })
    },
  })

  // Update room mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: CreateRoomRequest }) => roomsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.rooms() })
      setIsEditDialogOpen(false)
      setSelectedRoom(null)
      resetForm()
      toast({
        title: t('common:success'),
        description: 'Room updated successfully',
      })
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: 'Failed to update room',
        variant: 'destructive',
      })
    },
  })

  // Delete room mutation
  const deleteMutation = useMutation({
    mutationFn: roomsApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.rooms() })
      setIsDeleteDialogOpen(false)
      setSelectedRoom(null)
      toast({
        title: t('common:success'),
        description: 'Room deleted successfully',
      })
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: 'Failed to delete room',
        variant: 'destructive',
      })
    },
  })

  const resetForm = () => {
    setFormData({
      site_id: sites && sites.length > 0 ? sites[0].id : 1,
      name: '',
      color: '#3788D8',
      capacity: undefined,
      google_calendar_id: '',
    })
  }

  const handleCreate = () => {
    createMutation.mutate(formData)
  }

  const handleEdit = (room: Room) => {
    setSelectedRoom(room)
    setFormData({
      site_id: room.site_id,
      name: room.name,
      color: room.color || '#3788D8',
      capacity: room.capacity,
      google_calendar_id: room.google_calendar_id || '',
    })
    setIsEditDialogOpen(true)
  }

  const handleUpdate = () => {
    if (!selectedRoom) return
    updateMutation.mutate({ id: selectedRoom.id, data: formData })
  }

  const handleDeleteClick = (room: Room) => {
    setSelectedRoom(room)
    setIsDeleteDialogOpen(true)
  }

  const handleDeleteConfirm = () => {
    if (!selectedRoom) return
    deleteMutation.mutate(selectedRoom.id)
  }

  const getSiteBadgeColor = (siteName: string) => {
    switch (siteName) {
      case 'SASAD':
        return 'bg-blue-100 text-blue-800'
      case 'TB':
        return 'bg-green-100 text-green-800'
      case 'ÚJBUDA':
        return 'bg-purple-100 text-purple-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Room Management</h1>
          <p className="text-gray-500 mt-2">Manage rooms and their configurations</p>
        </div>
        <Button onClick={() => setIsCreateDialogOpen(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Create Room
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex gap-2">
            <Button
              variant={siteFilter === undefined ? 'default' : 'outline'}
              onClick={() => setSiteFilter(undefined)}
            >
              All Sites
            </Button>
            {sites?.map((site) => (
              <Button
                key={site.id}
                variant={siteFilter === site.id ? 'default' : 'outline'}
                onClick={() => setSiteFilter(site.id)}
              >
                {site.name}
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Rooms Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {isLoading ? (
          Array.from({ length: 6 }).map((_, i) => (
            <Card key={i}>
              <CardHeader>
                <Skeleton className="h-6 w-32" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-20 w-full" />
              </CardContent>
            </Card>
          ))
        ) : rooms && rooms.length > 0 ? (
          rooms.map((room) => (
            <Card key={room.id} className="hover:shadow-lg transition-shadow">
              <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div className="flex items-center gap-2">
                  <DoorOpen className="h-5 w-5 text-muted-foreground" style={{ color: room.color }} />
                  <div>
                    <CardTitle className="text-lg">{room.name}</CardTitle>
                    <Badge className={`${getSiteBadgeColor(room.site?.name || '')} mt-1`}>{room.site?.name || '-'}</Badge>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Capacity:</span>
                  <span className="font-medium">{room.capacity || 'Flexible'}</span>
                </div>
                {room.google_calendar_id && (
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">GCal Sync:</span>
                    <Badge variant="outline" className="text-xs">Connected</Badge>
                  </div>
                )}
                <div className="flex gap-2 pt-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={() => handleEdit(room)}
                  >
                    <Pencil className="h-3 w-3 mr-1" />
                    Edit
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1 text-red-600 hover:text-red-700"
                    onClick={() => handleDeleteClick(room)}
                  >
                    <Trash2 className="h-3 w-3 mr-1" />
                    Delete
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))
        ) : (
          <Card className="col-span-full">
            <CardContent className="py-12 text-center">
              <DoorOpen className="h-12 w-12 mx-auto text-gray-400 mb-4" />
              <p className="text-gray-500">No rooms found</p>
            </CardContent>
          </Card>
        )}
      </div>

      {/* Create/Edit Dialog */}
      <Dialog open={isCreateDialogOpen || isEditDialogOpen} onOpenChange={(open) => {
        if (!open) {
          setIsCreateDialogOpen(false)
          setIsEditDialogOpen(false)
          resetForm()
        }
      }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{isEditDialogOpen ? 'Edit Room' : 'Create New Room'}</DialogTitle>
            <DialogDescription>
              {isEditDialogOpen ? 'Update room information' : 'Add a new room to the system'}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="site">Site *</Label>
              <Select value={formData.site_id?.toString()} onValueChange={(value) => setFormData({ ...formData, site_id: parseInt(value) })}>
                <SelectTrigger>
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
              <Label htmlFor="name">Room Name *</Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                placeholder="e.g., Gym, Massage Room"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="capacity">Capacity</Label>
              <Input
                id="capacity"
                type="number"
                min="1"
                value={formData.capacity || ''}
                onChange={(e) => setFormData({ ...formData, capacity: e.target.value ? parseInt(e.target.value) : undefined })}
                placeholder="Leave empty for flexible capacity"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="color">Color (Hex)</Label>
              <div className="flex gap-2">
                <Input
                  id="color"
                  value={formData.color}
                  onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                  placeholder="#3788D8"
                  maxLength={7}
                />
                <div
                  className="w-10 h-10 rounded border"
                  style={{ backgroundColor: formData.color }}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="google_calendar_id">Google Calendar ID (Optional)</Label>
              <Input
                id="google_calendar_id"
                value={formData.google_calendar_id}
                onChange={(e) => setFormData({ ...formData, google_calendar_id: e.target.value })}
                placeholder="calendar@group.calendar.google.com"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => {
              setIsCreateDialogOpen(false)
              setIsEditDialogOpen(false)
              resetForm()
            }}>
              Cancel
            </Button>
            <Button onClick={isEditDialogOpen ? handleUpdate : handleCreate} disabled={!formData.name || !formData.site_id}>
              {isEditDialogOpen ? 'Update' : 'Create'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Room</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete <strong>{selectedRoom?.name}</strong>? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDeleteConfirm} className="bg-red-600 hover:bg-red-700">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
