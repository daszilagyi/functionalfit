import { useState, useEffect, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { clientsApi } from '@/api/clients'
import { TECHNICAL_GUEST_ID, MAX_EVENT_GUESTS } from '@/lib/constants'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { useToast } from '@/hooks/use-toast'
import { Search, X, Wrench, UserPlus } from 'lucide-react'
import type { ClientSearchResult } from '@/types/client'

interface MultiClientPickerProps {
  value: number[]
  onChange: (clientIds: number[]) => void
  error?: string
  required?: boolean
  technicalGuestId?: number
}

export function MultiClientPicker({
  value,
  onChange,
  error,
  required = false,
  technicalGuestId = TECHNICAL_GUEST_ID
}: MultiClientPickerProps) {
  const { t } = useTranslation('calendar')
  const { toast } = useToast()
  const [searchQuery, setSearchQuery] = useState('')
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const [isOpen, setIsOpen] = useState(false)
  const [selectedClients, setSelectedClients] = useState<ClientSearchResult[]>([])

  // Debounce search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedQuery(searchQuery)
    }, 300)

    return () => clearTimeout(timer)
  }, [searchQuery])

  // Search clients
  const { data: clients, isLoading } = useQuery({
    queryKey: ['clients', 'search', debouncedQuery],
    queryFn: () => clientsApi.search(debouncedQuery),
    enabled: isOpen && debouncedQuery.length >= 1,
    staleTime: 5 * 60 * 1000, // 5 minutes
  })

  // Fetch client details for selected IDs
  useEffect(() => {
    const fetchClientDetails = async () => {
      if (value.length === 0) {
        setSelectedClients([])
        return
      }

      try {
        // Get unique IDs to fetch from backend
        const uniqueIds = Array.from(new Set(value))

        // Use batch endpoint to fetch unique clients
        const clientsMap = await clientsApi.batch(uniqueIds)

        // Create a map of ID to client data
        const clientDataMap = new Map(
          clientsMap.map(client => [parseInt(client.id), client])
        )

        // Map all IDs (including duplicates) to their client data
        const allClients = value.map(id => {
          const clientData = clientDataMap.get(id)
          if (clientData) {
            return clientData
          }
          // Fallback for missing data
          return {
            id: String(id),
            is_technical_guest: id === technicalGuestId,
            user: {
              id: id === technicalGuestId ? null : String(id),
              name: id === technicalGuestId ? 'Technikai Vendég' : `Client #${id}`,
              email: null,
              phone: null
            }
          }
        })

        setSelectedClients(allClients)
      } catch (error) {
        console.error('Failed to fetch client details:', error)
        // Fallback: create placeholder entries
        setSelectedClients(
          value.map(id => ({
            id: String(id),
            is_technical_guest: id === technicalGuestId,
            user: {
              id: id === technicalGuestId ? null : String(id),
              name: id === technicalGuestId ? 'Technikai Vendég' : `Client #${id}`,
              email: null,
              phone: null
            }
          }))
        )
      }
    }

    fetchClientDetails()
  }, [value, technicalGuestId])

  const handleSelect = useCallback((client: ClientSearchResult) => {
    const clientId = parseInt(client.id)

    // Check if already selected
    if (value.includes(clientId)) {
      toast({
        variant: 'destructive',
        title: t('event.duplicateGuest'),
      })
      return
    }

    // Check max guests limit
    if (value.length >= MAX_EVENT_GUESTS) {
      toast({
        variant: 'destructive',
        title: t('event.maxGuestsReached'),
      })
      return
    }

    onChange([...value, clientId])
    setSearchQuery('')
    setIsOpen(false)
  }, [value, onChange, t, toast])

  const handleRemove = useCallback((index: number) => {
    onChange(value.filter((_, i) => i !== index))
  }, [value, onChange])

  const handleAddTechnicalGuest = useCallback(() => {
    // Check max guests limit
    if (value.length >= MAX_EVENT_GUESTS) {
      toast({
        variant: 'destructive',
        title: t('event.maxGuestsReached'),
      })
      return
    }

    // Generate unique negative ID for each technical guest
    // Use timestamp + random number to ensure uniqueness
    const uniqueTechnicalGuestId = -(Date.now() + Math.floor(Math.random() * 1000))
    onChange([...value, uniqueTechnicalGuestId])
  }, [value, onChange, t, toast])

  return (
    <div className="space-y-2" data-testid="multi-client-picker">
      <Label htmlFor="multi-client-picker">
        {t('event.guests')}
        {required && <span className="text-destructive ml-1">*</span>}
      </Label>

      {/* Selected Clients */}
      {selectedClients.length > 0 && (
        <div className="space-y-2">
          <div className="text-sm text-muted-foreground">
            {t('event.guestCount', { count: selectedClients.length })}
          </div>
          <div className="flex flex-wrap gap-2">
            {selectedClients.map((client, index) => (
              <Badge
                key={`${client.id}-${index}`}
                variant="secondary"
                className="pl-3 pr-1 py-1 text-sm flex items-center gap-2"
                data-testid={`selected-client-${client.id}-${index}`}
              >
                <span className="flex items-center gap-1">
                  {client.user.name}
                  {client.is_technical_guest && (
                    <Wrench className="h-3 w-3" />
                  )}
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="h-4 w-4 p-0 hover:bg-transparent"
                  onClick={() => handleRemove(index)}
                  aria-label={t('event.removeGuest')}
                  data-testid={`remove-client-${client.id}-${index}`}
                >
                  <X className="h-3 w-3" />
                </Button>
              </Badge>
            ))}
          </div>
        </div>
      )}

      {/* No guests selected state */}
      {selectedClients.length === 0 && (
        <div className="text-sm text-muted-foreground p-3 border border-dashed rounded-md text-center">
          {t('event.noGuestsSelected')}
        </div>
      )}

      {/* Search Input */}
      <div className="relative">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            id="multi-client-picker"
            type="text"
            placeholder={t('event.searchGuest')}
            value={searchQuery}
            onChange={(e) => {
              setSearchQuery(e.target.value)
              setIsOpen(true)
            }}
            onFocus={() => setIsOpen(true)}
            className="pl-10"
            data-testid="client-search-input"
            aria-autocomplete="list"
            aria-expanded={isOpen}
          />
        </div>

        {/* Search Results Dropdown */}
        {isOpen && debouncedQuery.length >= 1 && (
          <div className="absolute z-50 w-full mt-1 bg-popover border rounded-md shadow-md max-h-60 overflow-auto">
            {isLoading ? (
              <div className="p-3 text-sm text-muted-foreground text-center">
                {t('common.loading')}
              </div>
            ) : clients && clients.length > 0 ? (
              <ul role="listbox" aria-label="Client list">
                {clients.map((client) => (
                  <li key={client.id}>
                    <button
                      type="button"
                      className="w-full px-3 py-2 text-left hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      onClick={() => handleSelect(client)}
                      disabled={value.includes(parseInt(client.id))}
                      data-testid={`client-option-${client.id}`}
                    >
                      <div className="flex items-center gap-2">
                        <span className="font-medium">{client.user.name}</span>
                        {client.is_technical_guest && (
                          <Badge variant="secondary" className="text-xs">
                            <Wrench className="h-3 w-3 mr-1" />
                            {t('event.technicalGuest')}
                          </Badge>
                        )}
                        {value.includes(parseInt(client.id)) && (
                          <Badge variant="outline" className="text-xs">
                            {t('event.alreadyAdded')}
                          </Badge>
                        )}
                      </div>
                      {client.user.email && (
                        <div className="text-sm text-muted-foreground">{client.user.email}</div>
                      )}
                    </button>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="p-3 text-sm text-muted-foreground text-center">
                {t('event.noClientsFound')}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Quick Add Technical Guest Button */}
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={handleAddTechnicalGuest}
        className="w-full"
        data-testid="add-technical-guest"
        disabled={value.length >= MAX_EVENT_GUESTS}
      >
        <UserPlus className="h-4 w-4 mr-2" />
        {t('event.addTechnicalGuest')}
      </Button>

      {/* Error Message */}
      {error && (
        <p className="text-sm text-destructive" role="alert">
          {t(error)}
        </p>
      )}
    </div>
  )
}
