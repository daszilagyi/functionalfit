import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { clientsApi, clientKeys, type StaffClient, type UpdateClientRequest } from '@/api/clients'
import { staffClientPriceCodesApi, staffServiceTypesApi, clientPriceCodeKeys, serviceTypeKeys } from '@/api/serviceTypes'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
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

// Validation schema for client edit
const clientEditSchema = z.object({
  name: z.string().min(1, 'Név megadása kötelező'),
  email: z.string().email('Érvénytelen email cím'),
  phone: z.string().optional().nullable(),
  status: z.enum(['active', 'inactive']),
  date_of_birth: z.string().optional().nullable(),
  emergency_contact_name: z.string().optional().nullable(),
  emergency_contact_phone: z.string().optional().nullable(),
  notes: z.string().optional().nullable(),
})

type ClientEditFormData = z.infer<typeof clientEditSchema>

// Validation schema for price code
const priceCodeSchema = z.object({
  service_type_id: z.number().min(1, 'Szolgáltatás típus kiválasztása kötelező'),
  price_code: z.string().optional(),
  entry_fee_brutto: z.number().min(0, 'Belépődíj nem lehet negatív'),
  trainer_fee_brutto: z.number().min(0, 'Edzői díj nem lehet negatív'),
  currency: z.string().default('HUF'),
  valid_from: z.string().min(1, 'Érvényesség kezdete kötelező'),
  valid_until: z.string().optional().nullable(),
  is_active: z.boolean().default(true),
})

type PriceCodeFormValues = z.infer<typeof priceCodeSchema>

interface StaffClientEditModalProps {
  client: StaffClient | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('hu-HU', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount) + ' Ft'
}

export function StaffClientEditModal({
  client,
  open,
  onOpenChange,
  onSuccess,
}: StaffClientEditModalProps) {
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [priceCodeModalOpen, setPriceCodeModalOpen] = useState(false)
  const [editingPriceCode, setEditingPriceCode] = useState<ClientPriceCode | null>(null)
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)

  // Client edit form
  const form = useForm<ClientEditFormData>({
    resolver: zodResolver(clientEditSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      status: 'active',
      date_of_birth: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      notes: '',
    },
  })

  // Price code form
  const priceCodeForm = useForm<PriceCodeFormValues>({
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

  // Reset form when client changes
  useEffect(() => {
    if (client && open) {
      form.reset({
        name: client.name,
        email: client.email || '',
        phone: client.phone || '',
        status: (client.status as 'active' | 'inactive') || 'active',
        date_of_birth: client.date_of_birth?.split('T')[0]?.split(' ')[0] || '',
        emergency_contact_name: client.emergency_contact_name || '',
        emergency_contact_phone: client.emergency_contact_phone || '',
        notes: client.notes || '',
      })
    }
  }, [client, open, form])

  // Fetch price codes
  const { data: priceCodes, isLoading: isLoadingPriceCodes } = useQuery({
    queryKey: clientPriceCodeKeys.listByClient(client?.id || 0),
    queryFn: () => staffClientPriceCodesApi.listByClient(client!.id),
    enabled: !!client?.id && open,
  })

  // Fetch service types (using staff API)
  const { data: serviceTypes } = useQuery({
    queryKey: ['staff', ...serviceTypeKeys.lists()],
    queryFn: staffServiceTypesApi.list,
    enabled: open,
  })

  // Client update mutation
  const updateMutation = useMutation({
    mutationFn: (data: UpdateClientRequest) => clientsApi.update(client!.id, data),
    onSuccess: () => {
      toast({ title: 'Vendég sikeresen frissítve' })
      queryClient.invalidateQueries({ queryKey: clientKeys.lists() })
      onSuccess?.()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: 'Hiba',
        description: error.response?.data?.message || 'Hiba a vendég frissítésekor',
      })
    },
  })

  // Price code mutations
  const createPriceCodeMutation = useMutation({
    mutationFn: (data: ClientPriceCodeFormData) => staffClientPriceCodesApi.create(client!.id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(client!.id) })
      toast({ title: 'Árkód létrehozva' })
      handleClosePriceCodeModal()
    },
    onError: () => {
      toast({ variant: 'destructive', title: 'Hiba', description: 'Hiba az árkód létrehozásakor' })
    },
  })

  const updatePriceCodeMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<ClientPriceCodeFormData> }) =>
      staffClientPriceCodesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(client!.id) })
      toast({ title: 'Árkód frissítve' })
      handleClosePriceCodeModal()
    },
    onError: () => {
      toast({ variant: 'destructive', title: 'Hiba', description: 'Hiba az árkód frissítésekor' })
    },
  })

  const deletePriceCodeMutation = useMutation({
    mutationFn: (id: number) => staffClientPriceCodesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(client!.id) })
      toast({ title: 'Árkód törölve' })
      setDeleteConfirmId(null)
    },
    onError: () => {
      toast({ variant: 'destructive', title: 'Hiba', description: 'Hiba az árkód törlésekor' })
    },
  })

  const toggleActiveMutation = useMutation({
    mutationFn: (id: number) => staffClientPriceCodesApi.toggleActive(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clientPriceCodeKeys.listByClient(client!.id) })
    },
  })

  const handleClose = () => {
    onOpenChange(false)
    form.reset()
  }

  const onSubmit = form.handleSubmit((data) => {
    updateMutation.mutate({
      name: data.name,
      email: data.email,
      phone: data.phone || undefined,
      status: data.status,
      date_of_birth: data.date_of_birth || undefined,
      emergency_contact_name: data.emergency_contact_name || undefined,
      emergency_contact_phone: data.emergency_contact_phone || undefined,
      notes: data.notes || undefined,
    })
  })

  const handleOpenCreatePriceCodeModal = () => {
    setEditingPriceCode(null)
    priceCodeForm.reset({
      service_type_id: 0,
      price_code: '',
      entry_fee_brutto: 0,
      trainer_fee_brutto: 0,
      currency: 'HUF',
      valid_from: new Date().toISOString().split('T')[0],
      valid_until: null,
      is_active: true,
    })
    setPriceCodeModalOpen(true)
  }

  const handleOpenEditPriceCodeModal = (priceCode: ClientPriceCode) => {
    setEditingPriceCode(priceCode)
    priceCodeForm.reset({
      service_type_id: priceCode.service_type_id,
      price_code: priceCode.price_code || '',
      entry_fee_brutto: priceCode.entry_fee_brutto,
      trainer_fee_brutto: priceCode.trainer_fee_brutto,
      currency: priceCode.currency,
      valid_from: priceCode.valid_from.split('T')[0],
      valid_until: priceCode.valid_until?.split('T')[0] || null,
      is_active: priceCode.is_active,
    })
    setPriceCodeModalOpen(true)
  }

  const handleClosePriceCodeModal = () => {
    setPriceCodeModalOpen(false)
    setEditingPriceCode(null)
    priceCodeForm.reset()
  }

  const onPriceCodeSubmit = priceCodeForm.handleSubmit((data) => {
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
      updatePriceCodeMutation.mutate({ id: editingPriceCode.id, data: formData })
    } else {
      createPriceCodeMutation.mutate(formData)
    }
  })

  const handleServiceTypeChange = (serviceTypeId: string) => {
    const id = parseInt(serviceTypeId, 10)
    priceCodeForm.setValue('service_type_id', id)

    const serviceType = serviceTypes?.find((st: ServiceType) => st.id === id)
    if (serviceType && !editingPriceCode) {
      priceCodeForm.setValue('entry_fee_brutto', serviceType.default_entry_fee_brutto)
      priceCodeForm.setValue('trainer_fee_brutto', serviceType.default_trainer_fee_brutto)
    }
  }

  const availableServiceTypes = serviceTypes?.filter(
    (st: ServiceType) =>
      st.is_active &&
      (!priceCodes?.some((pc: ClientPriceCode) => pc.service_type_id === st.id) || editingPriceCode?.service_type_id === st.id)
  )

  const isPriceCodePending = createPriceCodeMutation.isPending || updatePriceCodeMutation.isPending

  if (!client) return null

  return (
    <>
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Vendég szerkesztése: {client.name}</DialogTitle>
          </DialogHeader>

          <Tabs defaultValue="details" className="w-full">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="details">Alapadatok</TabsTrigger>
              <TabsTrigger value="pricing">Árkódok</TabsTrigger>
            </TabsList>

            <TabsContent value="details" className="mt-4">
              <form onSubmit={onSubmit} className="space-y-4">
                <div className="grid gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="name">Név <span className="text-destructive">*</span></Label>
                    <Input id="name" {...form.register('name')} disabled={updateMutation.isPending} />
                    {form.formState.errors.name && (
                      <span className="text-sm text-destructive">{form.formState.errors.name.message}</span>
                    )}
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="email">Email <span className="text-destructive">*</span></Label>
                    <Input id="email" type="email" {...form.register('email')} disabled={updateMutation.isPending} />
                    {form.formState.errors.email && (
                      <span className="text-sm text-destructive">{form.formState.errors.email.message}</span>
                    )}
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="phone">Telefonszám</Label>
                    <Input id="phone" {...form.register('phone')} disabled={updateMutation.isPending} />
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="status">Státusz</Label>
                    <Select
                      value={form.watch('status')}
                      onValueChange={(value: 'active' | 'inactive') => form.setValue('status', value)}
                      disabled={updateMutation.isPending}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="active">Aktív</SelectItem>
                        <SelectItem value="inactive">Inaktív</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="date_of_birth">Születési dátum</Label>
                    <Input id="date_of_birth" type="date" {...form.register('date_of_birth')} disabled={updateMutation.isPending} />
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="emergency_contact_name">Sürgősségi kapcsolattartó neve</Label>
                    <Input id="emergency_contact_name" {...form.register('emergency_contact_name')} disabled={updateMutation.isPending} />
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="emergency_contact_phone">Sürgősségi kapcsolattartó telefonszáma</Label>
                    <Input id="emergency_contact_phone" {...form.register('emergency_contact_phone')} disabled={updateMutation.isPending} />
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="notes">Megjegyzések</Label>
                    <Textarea id="notes" {...form.register('notes')} disabled={updateMutation.isPending} rows={3} />
                  </div>
                </div>

                <DialogFooter>
                  <Button type="button" variant="outline" onClick={handleClose} disabled={updateMutation.isPending}>
                    Mégse
                  </Button>
                  <Button type="submit" disabled={updateMutation.isPending}>
                    {updateMutation.isPending ? 'Mentés...' : 'Mentés'}
                  </Button>
                </DialogFooter>
              </form>
            </TabsContent>

            <TabsContent value="pricing" className="mt-4">
              <Card>
                <CardHeader className="flex flex-row items-center justify-between py-3">
                  <CardTitle className="text-lg">Árkódok</CardTitle>
                  <Button size="sm" onClick={handleOpenCreatePriceCodeModal}>
                    <Plus className="h-4 w-4 mr-2" />
                    Új árkód
                  </Button>
                </CardHeader>
                <CardContent>
                  {isLoadingPriceCodes ? (
                    <p className="text-center text-muted-foreground py-4">Betöltés...</p>
                  ) : priceCodes && priceCodes.length > 0 ? (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Szolgáltatás</TableHead>
                          <TableHead className="text-right">Belépődíj</TableHead>
                          <TableHead className="text-right">Edzői díj</TableHead>
                          <TableHead>Érvényesség</TableHead>
                          <TableHead>Státusz</TableHead>
                          <TableHead className="text-right">Műveletek</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {priceCodes.map((pc: ClientPriceCode) => (
                          <TableRow key={pc.id}>
                            <TableCell className="font-medium">
                              {pc.service_type?.name || pc.service_type_id}
                            </TableCell>
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
                                {pc.is_active ? 'Aktív' : 'Inaktív'}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-right">
                              <div className="flex justify-end gap-2">
                                <Button variant="ghost" size="icon" onClick={() => handleOpenEditPriceCodeModal(pc)}>
                                  <Pencil className="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => setDeleteConfirmId(pc.id)}>
                                  <Trash2 className="h-4 w-4 text-destructive" />
                                </Button>
                              </div>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : (
                    <p className="text-center text-muted-foreground py-8">
                      Nincsenek egyedi árkódok beállítva
                    </p>
                  )}
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </DialogContent>
      </Dialog>

      {/* Price Code Create/Edit Modal */}
      <Dialog open={priceCodeModalOpen} onOpenChange={handleClosePriceCodeModal}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle>
              {editingPriceCode ? 'Árkód szerkesztése' : 'Új árkód hozzáadása'}
            </DialogTitle>
          </DialogHeader>

          <form onSubmit={onPriceCodeSubmit} className="space-y-4">
            <div className="grid gap-4">
              <div className="grid gap-2">
                <Label>Szolgáltatás típus <span className="text-destructive">*</span></Label>
                <Select
                  value={priceCodeForm.watch('service_type_id') > 0 ? priceCodeForm.watch('service_type_id').toString() : ''}
                  onValueChange={handleServiceTypeChange}
                  disabled={isPriceCodePending}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Válassz szolgáltatás típust" />
                  </SelectTrigger>
                  <SelectContent>
                    {availableServiceTypes?.map((st: ServiceType) => (
                      <SelectItem key={st.id} value={st.id.toString()}>
                        {st.name} ({st.code})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="grid gap-2">
                <Label htmlFor="entry_fee_brutto">Belépődíj (Ft) <span className="text-destructive">*</span></Label>
                <Input
                  id="entry_fee_brutto"
                  type="number"
                  {...priceCodeForm.register('entry_fee_brutto', { valueAsNumber: true })}
                  disabled={isPriceCodePending}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="trainer_fee_brutto">Edzői díj (Ft) <span className="text-destructive">*</span></Label>
                <Input
                  id="trainer_fee_brutto"
                  type="number"
                  {...priceCodeForm.register('trainer_fee_brutto', { valueAsNumber: true })}
                  disabled={isPriceCodePending}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="valid_from">Érvényesség kezdete <span className="text-destructive">*</span></Label>
                <Input
                  id="valid_from"
                  type="date"
                  {...priceCodeForm.register('valid_from')}
                  disabled={isPriceCodePending}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="valid_until">Érvényesség vége (opcionális)</Label>
                <Input
                  id="valid_until"
                  type="date"
                  {...priceCodeForm.register('valid_until')}
                  disabled={isPriceCodePending}
                />
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={handleClosePriceCodeModal} disabled={isPriceCodePending}>
                Mégse
              </Button>
              <Button type="submit" disabled={isPriceCodePending}>
                {isPriceCodePending ? 'Mentés...' : 'Mentés'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={!!deleteConfirmId} onOpenChange={() => setDeleteConfirmId(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Árkód törlése</DialogTitle>
          </DialogHeader>
          <p>Biztosan törölni szeretnéd ezt az árkódot?</p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteConfirmId(null)}>
              Mégse
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteConfirmId && deletePriceCodeMutation.mutate(deleteConfirmId)}
              disabled={deletePriceCodeMutation.isPending}
            >
              {deletePriceCodeMutation.isPending ? 'Törlés...' : 'Törlés'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
