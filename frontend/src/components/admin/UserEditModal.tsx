import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertTriangle } from 'lucide-react'
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
import { Checkbox } from '@/components/ui/checkbox'
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
  role: z.enum(['client', 'staff', 'admin']),
  password: z.string().min(8, 'Password must be at least 8 characters').optional().or(z.literal('')),
  password_confirmation: z.string().optional().or(z.literal('')),
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
  daily_schedule_notification: z.boolean().optional(),
}).refine((data) => {
  if (data.password && data.password !== data.password_confirmation) {
    return false
  }
  return true
}, {
  message: "Passwords don't match",
  path: ["password_confirmation"],
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
  const [showPasswordSection, setShowPasswordSection] = useState(false)

  const form = useForm<UserEditFormData>({
    resolver: zodResolver(userEditSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      status: 'active',
      role: 'client',
      password: '',
      password_confirmation: '',
      date_of_birth: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      notes: '',
      specialization: '',
      bio: '',
      default_hourly_rate: undefined,
      is_available_for_booking: true,
      daily_schedule_notification: false,
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
        role: user.role,
        password: '',
        password_confirmation: '',
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
        daily_schedule_notification:
          user.staff_profile?.daily_schedule_notification ?? false,
      })
      setShowPasswordSection(false)
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

    // Include role if it changed
    if (data.role !== user?.role) {
      updateData.role = data.role
    }

    // Include password only if provided
    if (data.password && data.password.trim() !== '') {
      updateData.password = data.password
    }

    // Add role-specific fields based on the new role (or current role if unchanged)
    const targetRole = data.role
    if (targetRole === 'client') {
      updateData.date_of_birth = data.date_of_birth || undefined
      updateData.emergency_contact_name = data.emergency_contact_name || undefined
      updateData.emergency_contact_phone = data.emergency_contact_phone || undefined
      updateData.notes = data.notes || undefined
    } else if (targetRole === 'staff' || targetRole === 'admin') {
      updateData.specialization = data.specialization || undefined
      updateData.bio = data.bio || undefined
      updateData.default_hourly_rate = data.default_hourly_rate
      updateData.is_available_for_booking = data.is_available_for_booking
      updateData.daily_schedule_notification = data.daily_schedule_notification
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

        <div className="grid gap-2">
          <Label htmlFor="role">{t('admin:users.roleLabel', 'Role')}</Label>
          <Select
            value={form.watch('role')}
            onValueChange={(value: 'client' | 'staff' | 'admin') =>
              form.setValue('role', value)
            }
            disabled={updateMutation.isPending}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="client">
                {t('admin:users.role.client', 'Client')}
              </SelectItem>
              <SelectItem value="staff">
                {t('admin:users.role.staff', 'Staff')}
              </SelectItem>
              <SelectItem value="admin">
                {t('admin:users.role.admin', 'Admin')}
              </SelectItem>
            </SelectContent>
          </Select>
          {form.watch('role') !== user?.role && (
            <div className="flex items-start gap-2 text-sm text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-950/20 p-3 rounded-md">
              <AlertTriangle className="h-4 w-4 mt-0.5 flex-shrink-0" />
              <span>{t('admin:users.roleChangeWarning', 'Changing the role will affect the user\'s permissions and access.')}</span>
            </div>
          )}
        </div>
      </div>

      {/* Password Change Section */}
      <div className="border-t pt-4">
        <div className="flex items-center space-x-2 mb-4">
          <Checkbox
            id="change-password"
            checked={showPasswordSection}
            onCheckedChange={(checked) => {
              setShowPasswordSection(checked === true)
              if (!checked) {
                form.setValue('password', '')
                form.setValue('password_confirmation', '')
              }
            }}
            disabled={updateMutation.isPending}
          />
          <Label
            htmlFor="change-password"
            className="text-sm font-medium cursor-pointer"
          >
            {t('admin:users.changePassword', 'Change Password')}
          </Label>
        </div>

        {showPasswordSection && (
          <div className="grid gap-4 pl-6">
            <div className="grid gap-2">
              <Label htmlFor="password">{t('admin:users.newPassword', 'New Password')}</Label>
              <Input
                id="password"
                type="password"
                {...form.register('password')}
                disabled={updateMutation.isPending}
                placeholder={t('admin:users.passwordOptional', 'Leave empty to keep unchanged')}
              />
              {form.formState.errors.password && (
                <span className="text-sm text-red-500">
                  {t(form.formState.errors.password.message || 'admin:users.passwordMinLength', 'Password must be at least 8 characters')}
                </span>
              )}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">{t('admin:users.confirmPassword', 'Confirm Password')}</Label>
              <Input
                id="password_confirmation"
                type="password"
                {...form.register('password_confirmation')}
                disabled={updateMutation.isPending}
              />
              {form.formState.errors.password_confirmation && (
                <span className="text-sm text-red-500">
                  {t(form.formState.errors.password_confirmation.message || 'admin:users.passwordMismatch', 'Passwords don\'t match')}
                </span>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Client-specific fields */}
      {form.watch('role') === 'client' && (
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
      {(form.watch('role') === 'staff' || form.watch('role') === 'admin') && (
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

          <div className="flex items-center space-x-2">
            <Checkbox
              id="daily_schedule_notification"
              checked={form.watch('daily_schedule_notification')}
              onCheckedChange={(checked) =>
                form.setValue('daily_schedule_notification', checked === true)
              }
              disabled={updateMutation.isPending}
            />
            <Label
              htmlFor="daily_schedule_notification"
              className="text-sm font-normal cursor-pointer"
            >
              {t('admin:users.dailyScheduleNotification', 'Napi programértesítés')}
            </Label>
          </div>
        </div>
      )}

      <DialogFooter className="flex-col sm:flex-row gap-2 pt-4">
        <Button
          type="button"
          variant="outline"
          onClick={handleClose}
          disabled={updateMutation.isPending}
          className="w-full sm:w-auto"
        >
          {t('common:cancel', 'Cancel')}
        </Button>
        <Button type="submit" disabled={updateMutation.isPending} className="w-full sm:w-auto">
          {updateMutation.isPending
            ? t('common:saving', 'Saving...')
            : t('common:save', 'Save')}
        </Button>
      </DialogFooter>
    </form>
  )

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className={`max-h-[90vh] overflow-y-auto ${isClient ? 'w-[95vw] sm:max-w-[700px]' : 'w-[95vw] sm:max-w-[500px]'}`}>
        <DialogHeader>
          <DialogTitle className="text-lg sm:text-xl truncate pr-8">
            {t('admin:users.editUser', 'Edit User')}: {user.name}
          </DialogTitle>
        </DialogHeader>

        {isClient && user.client?.id ? (
          <Tabs defaultValue="details" className="w-full">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="details" className="text-xs sm:text-sm">{t('admin:users.clientDetails', 'Client Details')}</TabsTrigger>
              <TabsTrigger value="pricing" className="text-xs sm:text-sm">{t('admin:clientPriceCodes.title', 'Price Codes')}</TabsTrigger>
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
