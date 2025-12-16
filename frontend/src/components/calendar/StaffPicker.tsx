import { useState, useEffect, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import apiClient from '@/api/client'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Search, X } from 'lucide-react'
import type { ApiResponse } from '@/types/api'

interface StaffUser {
  id: number
  name: string
  email: string
  role: string
  staff_profile?: {
    id: number
    specialization?: string
    is_available_for_booking: boolean
  }
}

interface StaffPickerProps {
  value?: string
  onChange: (staffId: string, staffName: string) => void
  error?: string
  required?: boolean
}

export function StaffPicker({ value, onChange, error, required = false }: StaffPickerProps) {
  const { t } = useTranslation('calendar')
  const [searchQuery, setSearchQuery] = useState('')
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const [isOpen, setIsOpen] = useState(false)
  const [selectedStaffName, setSelectedStaffName] = useState('')

  // Debounce search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedQuery(searchQuery)
    }, 300)

    return () => clearTimeout(timer)
  }, [searchQuery])

  // Search staff users (admin + staff roles)
  const { data: staffUsers, isLoading } = useQuery({
    queryKey: ['users', 'staff', debouncedQuery],
    queryFn: async () => {
      const response = await apiClient.get<ApiResponse<{ data: StaffUser[] }>>('/admin/users', {
        params: {
          role: 'staff,admin',
          search: debouncedQuery || undefined,
        }
      })
      return response.data.data.data
    },
    enabled: debouncedQuery.length >= 0, // Allow empty search to show all staff
    staleTime: 5 * 60 * 1000, // 5 minutes
  })

  // Load selected staff name if value is provided
  useEffect(() => {
    if (value && !selectedStaffName && staffUsers) {
      const staff = staffUsers.find(s => s.staff_profile?.id === parseInt(value))
      if (staff) {
        setSelectedStaffName(staff.name)
      }
    }
  }, [value, selectedStaffName, staffUsers])

  const handleSelect = useCallback((staffProfileId: number, staffName: string) => {
    onChange(String(staffProfileId), staffName)
    setSelectedStaffName(staffName)
    setSearchQuery('')
    setIsOpen(false)
  }, [onChange])

  const handleClear = useCallback(() => {
    onChange('', '')
    setSelectedStaffName('')
    setSearchQuery('')
    setIsOpen(false)
  }, [onChange])

  // Filter only staff with staff_profile and available for booking
  const availableStaff = staffUsers?.filter(
    user => user.staff_profile && user.staff_profile.is_available_for_booking
  ) || []

  return (
    <div className="space-y-2">
      <Label htmlFor="staff-picker">
        {t('event.staffName')}
        {required && <span className="text-destructive ml-1">*</span>}
      </Label>

      {selectedStaffName ? (
        <div className="flex items-center gap-2">
          <Input
            value={selectedStaffName}
            readOnly
            className="flex-1"
            data-testid="staff-picker-selected"
          />
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={handleClear}
            aria-label="Clear staff"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      ) : (
        <div className="relative">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              id="staff-picker"
              type="text"
              placeholder={t('event.searchStaff')}
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value)
                setIsOpen(true)
              }}
              onFocus={() => setIsOpen(true)}
              className="pl-10"
              data-testid="staff-search-input"
              aria-autocomplete="list"
              aria-expanded={isOpen}
            />
          </div>

          {isOpen && (
            <div className="absolute z-50 w-full mt-1 bg-popover border rounded-md shadow-md max-h-60 overflow-auto">
              {isLoading ? (
                <div className="p-3 text-sm text-muted-foreground text-center">
                  {t('common.loading')}
                </div>
              ) : availableStaff.length > 0 ? (
                <ul role="listbox" aria-label="Staff list">
                  {availableStaff.map((user) => (
                    <li key={user.id}>
                      <button
                        type="button"
                        className="w-full px-3 py-2 text-left hover:bg-accent transition-colors"
                        onClick={() => handleSelect(user.staff_profile!.id, user.name)}
                        data-testid={`staff-option-${user.id}`}
                      >
                        <div className="font-medium">{user.name}</div>
                        <div className="text-sm text-muted-foreground">
                          {user.email}
                          {user.staff_profile?.specialization && (
                            <> • {user.staff_profile.specialization}</>
                          )}
                        </div>
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <div className="p-3 text-sm text-muted-foreground text-center">
                  {t('event.noStaffFound')}
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {error && (
        <p className="text-sm text-destructive" role="alert">
          {t(error)}
        </p>
      )}
    </div>
  )
}
