import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { clientsApi, clientKeys, type StaffClient, type CreateClientRequest, type ClientListParams } from '@/api/clients'
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
import { UserPlus, Search, Pencil, ArrowUpDown } from 'lucide-react'
import { useToast } from '@/hooks/use-toast'
import { format } from 'date-fns'
import { hu } from 'date-fns/locale'
import { StaffClientEditModal } from '@/components/staff/StaffClientEditModal'

type SortField = 'name' | 'email' | 'created_at'
type SortDir = 'asc' | 'desc'

export default function StaffClientsPage() {
  const { t } = useTranslation(['staff', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [sortBy, setSortBy] = useState<SortField>('name')
  const [sortDir, setSortDir] = useState<SortDir>('asc')
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [selectedClient, setSelectedClient] = useState<StaffClient | null>(null)
  const [newClient, setNewClient] = useState<CreateClientRequest>({ name: '', email: '', phone: '' })

  // Build query params
  const queryParams: ClientListParams = {
    search: search || undefined,
    sort_by: sortBy,
    sort_dir: sortDir,
  }

  // Fetch clients
  const { data: clientsData, isLoading } = useQuery({
    queryKey: clientKeys.list(queryParams),
    queryFn: () => clientsApi.list(queryParams),
  })

  const clients = clientsData?.data || []

  // Create client mutation
  const createMutation = useMutation({
    mutationFn: (data: CreateClientRequest) => clientsApi.create(data),
    onSuccess: () => {
      toast({
        title: t('common:success'),
        description: 'Vendég sikeresen létrehozva',
      })
      queryClient.invalidateQueries({ queryKey: clientKeys.lists() })
      setCreateModalOpen(false)
      setNewClient({ name: '', email: '', phone: '' })
    },
    onError: (error: any) => {
      toast({
        title: t('common:error'),
        description: error.response?.data?.message || 'Hiba a vendég létrehozásakor',
        variant: 'destructive',
      })
    },
  })

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!newClient.name.trim() || !newClient.email.trim()) {
      toast({
        title: t('common:error'),
        description: 'A név és email cím megadása kötelező',
        variant: 'destructive',
      })
      return
    }
    createMutation.mutate(newClient)
  }

  const getStatusBadgeVariant = (status: string): 'default' | 'destructive' => {
    return status === 'active' ? 'default' : 'destructive'
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight">Vendégek</h1>
          <p className="text-gray-500 mt-1 sm:mt-2 text-sm sm:text-base">Vendégek listázása és hozzáadása</p>
        </div>
        <Button onClick={() => setCreateModalOpen(true)} className="w-full sm:w-auto">
          <UserPlus className="h-4 w-4 mr-2" />
          Új vendég
        </Button>
      </div>

      {/* Search and Sort */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex flex-col sm:flex-row gap-4">
            {/* Search */}
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Keresés név, email vagy telefonszám alapján..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-10"
              />
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

      {/* Clients List */}
      <Card>
        <CardHeader>
          <CardTitle>Vendégek listája</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[...Array(5)].map((_, i) => (
                <Skeleton key={i} className="h-16 w-full" />
              ))}
            </div>
          ) : clients.length > 0 ? (
            <div className="space-y-2">
              {clients.map((client: StaffClient) => (
                <div
                  key={client.id}
                  className="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors gap-3"
                >
                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2 mb-1">
                      <h3 className="font-medium truncate">{client.name}</h3>
                      <Badge variant={getStatusBadgeVariant(client.status)}>
                        {client.status === 'active' ? 'Aktív' : 'Inaktív'}
                      </Badge>
                    </div>
                    <div className="flex flex-col sm:flex-row sm:items-center sm:gap-4 text-sm text-gray-500 gap-1">
                      {client.email && <span className="truncate">{client.email}</span>}
                      {client.phone && <span className="hidden sm:inline">·</span>}
                      {client.phone && <span>{client.phone}</span>}
                      <span className="hidden sm:inline">·</span>
                      <span className="text-xs sm:text-sm">{format(new Date(client.created_at), 'yyyy. MM. dd.', { locale: hu })}</span>
                    </div>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full sm:w-auto shrink-0"
                    onClick={() => {
                      setSelectedClient(client)
                      setEditModalOpen(true)
                    }}
                  >
                    <Pencil className="h-4 w-4 mr-2" />
                    Szerkesztés
                  </Button>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-center text-gray-500 py-12">
              {search ? 'Nincs találat a keresésre' : 'Még nincsenek vendégek'}
            </p>
          )}
        </CardContent>
      </Card>

      {/* Create Client Modal */}
      <Dialog open={createModalOpen} onOpenChange={setCreateModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Új vendég hozzáadása</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleCreateSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="name">Név <span className="text-destructive">*</span></Label>
              <Input
                id="name"
                value={newClient.name}
                onChange={(e) => setNewClient({ ...newClient, name: e.target.value })}
                placeholder="Vendég neve"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="email">Email cím <span className="text-destructive">*</span></Label>
              <Input
                id="email"
                type="email"
                value={newClient.email}
                onChange={(e) => setNewClient({ ...newClient, email: e.target.value })}
                placeholder="pelda@email.com"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="phone">Telefonszám</Label>
              <Input
                id="phone"
                type="tel"
                value={newClient.phone || ''}
                onChange={(e) => setNewClient({ ...newClient, phone: e.target.value })}
                placeholder="+36 30 123 4567"
              />
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setCreateModalOpen(false)}
                disabled={createMutation.isPending}
              >
                Mégse
              </Button>
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending ? 'Mentés...' : 'Létrehozás'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit Client Modal */}
      <StaffClientEditModal
        client={selectedClient}
        open={editModalOpen}
        onOpenChange={setEditModalOpen}
        onSuccess={() => {
          setEditModalOpen(false)
          setSelectedClient(null)
        }}
      />
    </div>
  )
}
