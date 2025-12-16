import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQuickRegister } from '@/api/public'
import {
  quickRegisterSchema,
  type QuickRegisterFormData,
} from '@/lib/validations/auth'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useToast } from '@/hooks/use-toast'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

interface QuickRegisterModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
  onSwitchToLogin?: () => void
}

export function QuickRegisterModal({
  open,
  onOpenChange,
  onSuccess,
  onSwitchToLogin,
}: QuickRegisterModalProps) {
  const { t } = useTranslation('public')
  const { toast } = useToast()
  const registerMutation = useQuickRegister()

  const form = useForm<QuickRegisterFormData>({
    resolver: zodResolver(quickRegisterSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      phone: '',
    },
  })

  const handleSubmit = form.handleSubmit(async (data) => {
    try {
      await registerMutation.mutateAsync(data)

      toast({
        title: t('common:success'),
        description: t('publicClasses.quickRegister.success'),
      })

      form.reset()
      onOpenChange(false)
      onSuccess?.()
    } catch (error) {
      const axiosError = error as AxiosError<ApiError>
      const { status, data: errorData } = axiosError.response ?? {}

      let errorMessage = t('errors.registerFailed')

      if (status === 409) {
        // Email already taken
        errorMessage = t('publicClasses.quickRegister.emailTaken')
      } else if (status === 422 && errorData?.message) {
        errorMessage = errorData.message
      }

      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: errorMessage,
      })
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md" data-testid="quick-register-modal">
        <DialogHeader>
          <DialogTitle>{t('publicClasses.quickRegister.title')}</DialogTitle>
          <DialogDescription>
            {t('publicClasses.quickRegister.description')}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="name">
              {t('publicClasses.quickRegister.name')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Input
              id="name"
              {...form.register('name')}
              placeholder={t('publicClasses.quickRegister.namePlaceholder')}
              data-testid="register-name-input"
              aria-required="true"
            />
            {form.formState.errors.name && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.name.message ?? 'errors.required')}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="email">
              {t('publicClasses.quickRegister.email')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Input
              id="email"
              type="email"
              {...form.register('email')}
              placeholder={t('publicClasses.quickRegister.emailPlaceholder')}
              data-testid="register-email-input"
              aria-required="true"
            />
            {form.formState.errors.email && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.email.message ?? 'errors.required')}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="password">
              {t('publicClasses.quickRegister.password')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Input
              id="password"
              type="password"
              {...form.register('password')}
              placeholder={t('publicClasses.quickRegister.passwordPlaceholder')}
              data-testid="register-password-input"
              aria-required="true"
            />
            {form.formState.errors.password && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.password.message ?? 'errors.required')}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="phone">
              {t('publicClasses.quickRegister.phone')}
            </Label>
            <Input
              id="phone"
              type="tel"
              {...form.register('phone')}
              placeholder={t('publicClasses.quickRegister.phonePlaceholder')}
              data-testid="register-phone-input"
            />
            {form.formState.errors.phone && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.phone.message ?? '')}
              </p>
            )}
          </div>

          <DialogFooter className="flex-col sm:flex-row gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={registerMutation.isPending}
              className="w-full sm:w-auto"
            >
              {t('common:cancel')}
            </Button>
            <Button
              type="submit"
              disabled={registerMutation.isPending}
              data-testid="register-submit-btn"
              className="w-full sm:w-auto"
            >
              {registerMutation.isPending
                ? t('common:loading')
                : t('publicClasses.quickRegister.submit')}
            </Button>
          </DialogFooter>
        </form>

        {onSwitchToLogin && (
          <div className="text-center text-sm text-muted-foreground border-t pt-4">
            {t('publicClasses.quickRegister.haveAccount')}{' '}
            <Button
              variant="link"
              className="p-0 h-auto font-semibold"
              onClick={() => {
                onOpenChange(false)
                onSwitchToLogin()
              }}
              data-testid="switch-to-login-btn"
            >
              {t('publicClasses.quickRegister.loginLink')}
            </Button>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
