import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { useNavigate, Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import apiClient from '@/api/client'
import axios from 'axios'
import { useState } from 'react'

const loginSchema = z.object({
  email: z.string().email('Invalid email address'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
  remember: z.boolean().optional(),
})

type LoginFormData = z.infer<typeof loginSchema>

export default function LoginPage() {
  const { t } = useTranslation('auth')
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  })

  const onSubmit = async (data: LoginFormData) => {
    setError(null)
    setIsLoading(true)

    try {
      // Laravel Sanctum CSRF cookie (without /api prefix)
      const baseURL = import.meta.env.VITE_API_URL?.replace('/api/v1', '') || 'http://localhost:8000'
      await axios.get(`${baseURL}/sanctum/csrf-cookie`, { withCredentials: true })

      // Login request
      const response = await apiClient.post('/auth/login', {
        email: data.email,
        password: data.password,
        remember: data.remember,
      })

      // Store token if returned (API token mode)
      if (response.data.data?.token) {
        localStorage.setItem('auth_token', response.data.data.token)
      }

      // Redirect to dashboard
      navigate('/dashboard')
    } catch (err: any) {
      setError(err.response?.data?.message || t('invalid_credentials'))
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-2xl">{t('login_title')}</CardTitle>
          <CardDescription>
            FunctionalFit Calendar - Booking System
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            {error && (
              <div className="rounded-md bg-red-50 p-3 text-sm text-red-800">
                {error}
              </div>
            )}

            <div className="space-y-2">
              <Label htmlFor="email">{t('email')}</Label>
              <Input
                id="email"
                type="email"
                placeholder="valaki@example.com"
                {...register('email')}
                disabled={isLoading}
              />
              {errors.email && (
                <p className="text-sm text-red-600">{errors.email.message}</p>
              )}
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="password">{t('password')}</Label>
                <Link
                  to="/forgot-password"
                  className="text-sm text-primary hover:underline"
                >
                  {t('forgot_password')}
                </Link>
              </div>
              <Input
                id="password"
                type="password"
                {...register('password')}
                disabled={isLoading}
              />
              {errors.password && (
                <p className="text-sm text-red-600">{errors.password.message}</p>
              )}
            </div>

            <div className="flex items-center space-x-2">
              <input
                id="remember"
                type="checkbox"
                className="h-4 w-4 rounded border-gray-300"
                {...register('remember')}
                disabled={isLoading}
              />
              <Label htmlFor="remember" className="font-normal cursor-pointer">
                {t('remember_me')}
              </Label>
            </div>

            <Button type="submit" className="w-full" disabled={isLoading}>
              {isLoading ? t('common:loading') : t('login_button')}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
