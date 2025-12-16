import { useEffect } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { usersApi, adminKeys } from '@/api/admin'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useToast } from '@/hooks/use-toast'
import { ClientPriceCodesSection } from './ClientPriceCodesSection'
import type { UserWithProfile, UpdateUserRequest } from '@/types/admin'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

// Validation schema
const userEditSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  email: z.string().email('Invalid email address'),
  phone: z.string().optional(),
  status: z.enum(['active', 'inactive', 'suspended']),
  // Client-specific
  date_of_birth: z.string().optional(),
  emergency_contact_name: z.string().optional(),
  emergency_contact_phone: z.string().optional(),
  notes: z.string().optional(),
  // Staff-specific
  specialization: z.string().optional(),
  bio: z.string().optional(),
  default_hourly_rate: z.number().optional(),
  is_available_for_booking: z.boolean().optional(),
})

type UserEditFormData = z.infer<typeof userEditSchema>

interface UserEditModalProps {
  user: UserWithProfile | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

export function UserEditModal({
  user,
  open,
  onOpenChange,
  onSuccess,
}: UserEditModalProps) {
  const { t } = useTranslation(['admin', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()

  const form = useForm<UserEditFormData>({
    resolver: zodResolver(userEditSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      status: 'active',
      date_of_birth: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      notes: '',
      specialization: '',
      bio: '',
      default_hourly_rate: undefined,
      is_available_for_booking: true,
    },
  })

  // Reset form when user changes
  useEffect(() => {
    if (user && open) {
      form.reset({
        name: user.name,
        email: user.email,
        phone: user.phone || '',
        status: user.status,
        // Client fields
        date_of_birth: user.client?.date_of_birth?.split('T')[0]?.split(' ')[0] || '',
        emergency_contact_name: user.client?.emergency_contact_name || '',
        emergency_contact_phone: user.client?.emergency_contact_phone || '',
        notes: user.client?.notes || '',
        // Staff fields
        specialization: user.staff_profile?.specialization || '',
        bio: user.staff_profile?.bio || '',
        default_hourly_rate: user.staff_profile?.default_hourly_rate,
        is_available_for_booking:
          user.staff_profile?.is_available_for_booking ?? true,
      })
    }
  }, [user, open, form])

  const updateMutation = useMutation({
    mutationFn: (data: UpdateUserRequest) => usersApi.update(user!.id, data),
    onSuccess: async () => {
      // Invalidate all user-related queries and wait for refetch
      await queryClient.invalidateQueries({ queryKey: adminKeys.users() })
      toast({
        title: t('admin:users.updateSuccess', 'User updated successfully'),
      })
      onOpenChange(false)
      onSuccess?.()
    },
    onError: (error: AxiosError<ApiError>) => {
      const message =
        error.response?.data?.message || t('admin:users.updateError', 'Failed to update user')
      toast({
        variant: 'destructive',
        title: t('common:error', 'Error'),
        description: message,
      })
    },
  })

  const onSubmit = form.handleSubmit((data) => {
    const updateData: UpdateUserRequest = {
      name: data.name,
      email: data.email,
      phone: data.phone || undefined,
      status: data.status,
    }

    // Add role-specific fields
    if (user?.role === 'client') {
      updateData.date_of_birth = data.date_of_birth || undefined
      updateData.emergency_contact_name = data.emergency_contact_name || undefined
      updateData.emergency_contact_phone = data.emergency_contact_phone || undefined
      updateData.notes = data.notes || undefined
    } else if (user?.role === 'staff' || user?.role === 'admin') {
      updateData.specialization = data.specialization || undefined
      updateData.bio = data.bio || undefined
      updateData.default_hourly_rate = data.default_hourly_rate
      updateData.is_available_for_booking = data.is_available_for_booking
    }

    updateMutation.mutate(updateData)
  })

  const handleClose = () => {
    onOpenChange(false)
    form.reset()
  }

  if (!user) return null

  // For clients, show tabs to include price codes section
  const isClient = user.role === 'client'

  const renderBasicForm = () => (
    <form onSubmit={onSubmit} className="space-y-4">
      {/* Basic Fields */}
      <div className="grid gap-4">
        <div className="grid gap-2">
          <Label htmlFor="name">{t('admin:users.name', 'Name')}</Label>
          <Input
            id="name"
            {...form.register('name')}
            disabled={updateMutation.isPending}
          />
          {form.formState.errors.name && (
            <span className="text-sm text-red-500">
              {form.formState.errors.name.message}
            </span>
          )}
        </div>

        <div className="grid gap-2">
          <Label htmlFor="email">{t('admin:users.email', 'Email')}</Label>
          <Input
            id="email"
            type="email"
            {...form.register('email')}
            disabled={updateMutation.isPending}
          />
          {form.formState.errors.email && (
            <span className="text-sm text-red-500">
              {form.formState.errors.email.message}
            </span>
          )}
        </div>

        <div className="grid gap-2">
          <Label htmlFor="phone">{t('admin:users.phone', 'Phone')}</Label>
          <Input
            id="phone"
            {...form.register('phone')}
            disabled={updateMutation.isPending}
          />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="status">{t('admin:users.statusLabel', 'Status')}</Label>
          <Select
            value={form.watch('status')}
            onValueChange={(value: 'active' | 'inactive' | 'suspended') =>
              form.setValue('status', value)
            }
            disabled={updateMutation.isPending}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">
                {t('admin:users.status.active', 'Active')}
              </SelectItem>
              <SelectItem value="inactive">
                {t('admin:users.status.inactive', 'Inactive')}
              </SelectItem>
              <SelectItem value="suspended">
                {t('admin:users.status.suspended', 'Suspended')}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Client-specific fields */}
      {user.role === 'client' && (
        <div className="grid gap-4 border-t pt-4">
          <h4 className="font-medium text-sm text-muted-foreground">
            {t('admin:users.clientDetails', 'Client Details')}
          </h4>

          <div className="grid gap-2">
            <Label htmlFor="date_of_birth">
              {t('admin:users.dateOfBirth', 'Date of Birth')}
            </Label>
            <Input
              id="date_of_birth"
              type="date"
              {...form.register('date_of_birth')}
              disabled={updateMutation.isPending}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="emergency_contact_name">
              {t('admin:users.emergencyContactName', 'Emergency Contact Name')}
            </Label>
            <Input
              id="emergency_contact_name"
              {...form.register('emergency_contact_name')}
              disabled={updateMutation.isPending}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="emergency_contact_phone">
              {t('admin:users.emergencyContactPhone', 'Emergency Contact Phone')}
            </Label>
            <Input
              id="emergency_contact_phone"
              {...form.register('emergency_contact_phone')}
              disabled={updateMutation.isPending}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="notes">{t('admin:users.notes', 'Notes')}</Label>
            <Input
              id="notes"
              {...form.register('notes')}
              disabled={updateMutation.isPending}
            />
          </div>
        </div>
      )}

      {/* Staff-specific fields */}
      {(user.role === 'staff' || user.role === 'admin') && (
        <div className="grid gap-4 border-t pt-4">
          <h4 className="font-medium text-sm text-muted-foreground">
            {t('admin:users.staffDetails', 'Staff Details')}
          </h4>

          <div className="grid gap-2">
            <Label htmlFor="specialization">
              {t('admin:users.specialization', 'Specialization')}
            </Label>
            <Input
              id="specialization"
              {...form.register('specialization')}
              disabled={updateMutation.isPending}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="bio">{t('admin:users.bio', 'Bio')}</Label>
            <Input
              id="bio"
              {...form.register('bio')}
              disabled={updateMutation.isPending}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="default_hourly_rate">
              {t('admin:users.hourlyRate', 'Hourly Rate (HUF)')}
            </Label>
            <Input
              id="default_hourly_rate"
              type="number"
              {...form.register('default_hourly_rate', { valueAsNumber: true })}
              disabled={updateMutation.isPending}
            />
          </div>
        </div>
      )}

      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          onClick={handleClose}
          disabled={updateMutation.isPending}
        >
          {t('common:cancel', 'Cancel')}
        </Button>
        <Button type="submit" disabled={updateMutation.isPending}>
          {updateMutation.isPending
            ? t('common:saving', 'Saving...')
            : t('common:save', 'Save')}
        </Button>
      </DialogFooter>
    </form>
  )

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className={isClient ? 'sm:max-w-[700px]' : 'sm:max-w-[500px]'}>
        <DialogHeader>
          <DialogTitle>
            {t('admin:users.editUser', 'Edit User')}: {user.name}
          </DialogTitle>
        </DialogHeader>

        {isClient && user.client?.id ? (
          <Tabs defaultValue="details" className="w-full">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="details">{t('admin:users.clientDetails', 'Client Details')}</TabsTrigger>
              <TabsTrigger value="pricing">{t('admin:clientPriceCodes.title', 'Price Codes')}</TabsTrigger>
            </TabsList>
            <TabsContent value="details" className="mt-4">
              {renderBasicForm()}
            </TabsContent>
            <TabsContent value="pricing" className="mt-4">
              <ClientPriceCodesSection clientId={user.client.id} />
            </TabsContent>
          </Tabs>
        ) : (
          renderBasicForm()
        )}
      </DialogContent>
    </Dialog>
  )
}
