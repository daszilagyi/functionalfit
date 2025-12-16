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
import { useToast } from '@/hooks/use-toast'
import type { CreateUserRequest } from '@/types/admin'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

// Validation schema for creating a new user
const userCreateSchema = z.object({
  role: z.enum(['client', 'staff', 'admin']),
  name: z.string().min(1, 'Name is required'),
  email: z.string().email('Invalid email address'),
  phone: z.string().optional(),
  password: z.string().min(8, 'Password must be at least 8 characters'),
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
})

type UserCreateFormData = z.infer<typeof userCreateSchema>

interface UserCreateModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

export function UserCreateModal({
  open,
  onOpenChange,
  onSuccess,
}: UserCreateModalProps) {
  const { t } = useTranslation(['admin', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()

  const form = useForm<UserCreateFormData>({
    resolver: zodResolver(userCreateSchema),
    defaultValues: {
      role: 'client',
      name: '',
      email: '',
      phone: '',
      password: '',
      status: 'active',
      date_of_birth: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      notes: '',
      specialization: '',
      bio: '',
      default_hourly_rate: undefined,
    },
  })

  const selectedRole = form.watch('role')

  const createMutation = useMutation({
    mutationFn: (data: CreateUserRequest) => usersApi.create(data),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: adminKeys.users() })
      toast({
        title: t('admin:users.createSuccess', 'User created successfully'),
      })
      onOpenChange(false)
      form.reset()
      onSuccess?.()
    },
    onError: (error: AxiosError<ApiError>) => {
      const message =
        error.response?.data?.message || t('admin:users.createError', 'Failed to create user')
      toast({
        variant: 'destructive',
        title: t('common:error', 'Error'),
        description: message,
      })
    },
  })

  const onSubmit = form.handleSubmit((data) => {
    const createData: CreateUserRequest = {
      role: data.role,
      name: data.name,
      email: data.email,
      phone: data.phone || undefined,
      password: data.password,
      status: data.status,
    }

    // Add role-specific fields
    if (data.role === 'client') {
      createData.date_of_birth = data.date_of_birth || undefined
      createData.emergency_contact_name = data.emergency_contact_name || undefined
      createData.emergency_contact_phone = data.emergency_contact_phone || undefined
      createData.notes = data.notes || undefined
    } else if (data.role === 'staff' || data.role === 'admin') {
      createData.specialization = data.specialization || undefined
      createData.bio = data.bio || undefined
      createData.default_hourly_rate = data.default_hourly_rate
    }

    createMutation.mutate(createData)
  })

  const handleClose = () => {
    onOpenChange(false)
    form.reset()
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>
            {t('admin:users.createUser', 'Create User')}
          </DialogTitle>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-4">
          {/* Role Selection */}
          <div className="grid gap-2">
            <Label htmlFor="role">{t('admin:users.role.label', 'Role')}</Label>
            <Select
              value={form.watch('role')}
              onValueChange={(value: 'client' | 'staff' | 'admin') =>
                form.setValue('role', value)
              }
              disabled={createMutation.isPending}
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
            {form.formState.errors.role && (
              <span className="text-sm text-red-500">
                {form.formState.errors.role.message}
              </span>
            )}
          </div>

          {/* Basic Fields */}
          <div className="grid gap-4">
            <div className="grid gap-2">
              <Label htmlFor="name">{t('admin:users.name', 'Name')}</Label>
              <Input
                id="name"
                {...form.register('name')}
                disabled={createMutation.isPending}
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
                autoComplete="off"
                {...form.register('email')}
                disabled={createMutation.isPending}
              />
              {form.formState.errors.email && (
                <span className="text-sm text-red-500">
                  {form.formState.errors.email.message}
                </span>
              )}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">{t('admin:users.password', 'Password')}</Label>
              <Input
                id="password"
                type="password"
                autoComplete="new-password"
                {...form.register('password')}
                disabled={createMutation.isPending}
              />
              {form.formState.errors.password && (
                <span className="text-sm text-red-500">
                  {form.formState.errors.password.message}
                </span>
              )}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="phone">{t('admin:users.phone', 'Phone')}</Label>
              <Input
                id="phone"
                {...form.register('phone')}
                disabled={createMutation.isPending}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="status">{t('admin:users.statusLabel', 'Status')}</Label>
              <Select
                value={form.watch('status')}
                onValueChange={(value: 'active' | 'inactive' | 'suspended') =>
                  form.setValue('status', value)
                }
                disabled={createMutation.isPending}
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
          {selectedRole === 'client' && (
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
                  disabled={createMutation.isPending}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="emergency_contact_name">
                  {t('admin:users.emergencyContactName', 'Emergency Contact Name')}
                </Label>
                <Input
                  id="emergency_contact_name"
                  {...form.register('emergency_contact_name')}
                  disabled={createMutation.isPending}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="emergency_contact_phone">
                  {t('admin:users.emergencyContactPhone', 'Emergency Contact Phone')}
                </Label>
                <Input
                  id="emergency_contact_phone"
                  {...form.register('emergency_contact_phone')}
                  disabled={createMutation.isPending}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="notes">{t('admin:users.notes', 'Notes')}</Label>
                <Input
                  id="notes"
                  {...form.register('notes')}
                  disabled={createMutation.isPending}
                />
              </div>
            </div>
          )}

          {/* Staff-specific fields */}
          {(selectedRole === 'staff' || selectedRole === 'admin') && (
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
                  disabled={createMutation.isPending}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="bio">{t('admin:users.bio', 'Bio')}</Label>
                <Input
                  id="bio"
                  {...form.register('bio')}
                  disabled={createMutation.isPending}
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
                  disabled={createMutation.isPending}
                />
              </div>
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={createMutation.isPending}
            >
              {t('common:cancel', 'Cancel')}
            </Button>
            <Button type="submit" disabled={createMutation.isPending}>
              {createMutation.isPending
                ? t('common:saving', 'Saving...')
                : t('common:create', 'Create')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
