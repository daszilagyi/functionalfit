import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Lock, CheckCircle, AlertCircle } from 'lucide-react'
import apiClient from '@/api/client'

const resetPasswordSchema = z.object({
  password: z.string().min(8, 'Password must be at least 8 characters'),
  password_confirmation: z.string().min(8, 'Password must be at least 8 characters'),
}).refine((data) => data.password === data.password_confirmation, {
  message: "Passwords don't match",
  path: ["password_confirmation"],
})

type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>

export default function ResetPasswordPage() {
  const { t } = useTranslation('auth')
  const [searchParams] = useSearchParams()
  const [error, setError] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [isSuccess, setIsSuccess] = useState(false)

  const token = searchParams.get('token')
  const email = searchParams.get('email')

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ResetPasswordFormData>({
    resolver: zodResolver(resetPasswordSchema),
  })

  const onSubmit = async (data: ResetPasswordFormData) => {
    if (!token || !email) {
      setError(t('reset_password_invalid_link'))
      return
    }

    setError(null)
    setIsLoading(true)

    try {
      await apiClient.post('/auth/reset-password', {
        token,
        email,
        password: data.password,
        password_confirmation: data.password_confirmation,
      })
      setIsSuccess(true)
    } catch (err: any) {
      setError(err.response?.data?.message || t('reset_password_error'))
    } finally {
      setIsLoading(false)
    }
  }

  // Check if we have the required parameters
  if (!token || !email) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <Card className="w-full max-w-md">
          <CardHeader>
            <CardTitle className="text-2xl">{t('reset_password_title')}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-3 rounded-md bg-red-50 p-4 text-red-800">
              <AlertCircle className="h-5 w-5 shrink-0" />
              <p className="text-sm">{t('reset_password_invalid_link')}</p>
            </div>
            <div className="mt-4">
              <Link to="/forgot-password">
                <Button variant="outline" className="w-full">
                  {t('request_new_link')}
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-2xl">{t('reset_password_title')}</CardTitle>
          <CardDescription>
            {t('reset_password_description')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isSuccess ? (
            <div className="space-y-4">
              <div className="flex items-center gap-3 rounded-md bg-green-50 p-4 text-green-800">
                <CheckCircle className="h-5 w-5 shrink-0" />
                <p className="text-sm">{t('reset_password_success')}</p>
              </div>
              <Link to="/login">
                <Button className="w-full">
                  {t('login_button')}
                </Button>
              </Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
              {error && (
                <div className="flex items-center gap-3 rounded-md bg-red-50 p-3 text-red-800">
                  <AlertCircle className="h-5 w-5 shrink-0" />
                  <p className="text-sm">{error}</p>
                </div>
              )}

              <div className="rounded-md bg-gray-50 p-3">
                <p className="text-sm text-muted-foreground">{t('email')}: <strong>{email}</strong></p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">{t('new_password')}</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="password"
                    type="password"
                    className="pl-10"
                    {...register('password')}
                    disabled={isLoading}
                  />
                </div>
                {errors.password && (
                  <p className="text-sm text-red-600">{errors.password.message}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password_confirmation">{t('confirm_password')}</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="password_confirmation"
                    type="password"
                    className="pl-10"
                    {...register('password_confirmation')}
                    disabled={isLoading}
                  />
                </div>
                {errors.password_confirmation && (
                  <p className="text-sm text-red-600">{errors.password_confirmation.message}</p>
                )}
              </div>

              <Button type="submit" className="w-full" disabled={isLoading}>
                {isLoading ? t('common:loading') : t('reset_password_button')}
              </Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
