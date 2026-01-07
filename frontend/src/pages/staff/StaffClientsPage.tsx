import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { clientsApi, clientKeys, type StaffClient, type CreateClientRequest } from '@/api/clients'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { UserPlus, Search, Pencil } from 'lucide-react'
import { useToast } from '@/hooks/use-toast'
import { format } from 'date-fns'
import { hu } from 'date-fns/locale'
import { StaffClientEditModal } from '@/components/staff/StaffClientEditModal'

export default function StaffClientsPage() {
  const { t } = useTranslation(['staff', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [selectedClient, setSelectedClient] = useState<StaffClient | null>(null)
  const [newClient, setNewClient] = useState<CreateClientRequest>({ name: '', email: '', phone: '' })

  // Fetch clients
  const { data: clientsData, isLoading } = useQuery({
    queryKey: clientKeys.list({ search: search || undefined }),
    queryFn: () => clientsApi.list({ search: search || undefined }),
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
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Vendégek</h1>
          <p className="text-gray-500 mt-2">Vendégek listázása és hozzáadása</p>
        </div>
        <Button onClick={() => setCreateModalOpen(true)}>
          <UserPlus className="h-4 w-4 mr-2" />
          Új vendég
        </Button>
      </div>

      {/* Search */}
      <Card>
        <CardContent className="pt-6">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <Input
              placeholder="Keresés név, email vagy telefonszám alapján..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-10"
            />
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
                  className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors"
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-1">
                      <h3 className="font-medium">{client.name}</h3>
                      <Badge variant={getStatusBadgeVariant(client.status)}>
                        {client.status === 'active' ? 'Aktív' : 'Inaktív'}
                      </Badge>
                    </div>
                    <div className="flex items-center gap-4 text-sm text-gray-500">
                      {client.email && <span>{client.email}</span>}
                      {client.phone && <span>· {client.phone}</span>}
                      <span>· {format(new Date(client.created_at), 'yyyy. MM. dd.', { locale: hu })}</span>
                    </div>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
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
