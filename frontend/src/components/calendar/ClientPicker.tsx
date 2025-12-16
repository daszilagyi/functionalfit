import { useState, useEffect, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { clientsApi } from '@/api/clients'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Search, X, Wrench } from 'lucide-react'

interface ClientPickerProps {
  value?: string
  onChange: (clientId: string, clientName: string) => void
  error?: string
  required?: boolean
}

export function ClientPicker({ onChange, error, required = false }: ClientPickerProps) {
  const { t } = useTranslation('calendar')
  const [searchQuery, setSearchQuery] = useState('')
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const [isOpen, setIsOpen] = useState(false)
  const [selectedClientName, setSelectedClientName] = useState('')

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
    enabled: debouncedQuery.length >= 2,
    staleTime: 5 * 60 * 1000, // 5 minutes
  })

  const handleSelect = useCallback((clientId: string, clientName: string) => {
    onChange(clientId, clientName)
    setSelectedClientName(clientName)
    setSearchQuery('')
    setIsOpen(false)
  }, [onChange])

  const handleClear = useCallback(() => {
    onChange('', '')
    setSelectedClientName('')
    setSearchQuery('')
    setIsOpen(false)
  }, [onChange])

  return (
    <div className="space-y-2">
      <Label htmlFor="client-picker">
        {t('event.clientName')}
        {required && <span className="text-destructive ml-1">*</span>}
      </Label>
      
      {selectedClientName ? (
        <div className="flex items-center gap-2">
          <Input
            value={selectedClientName}
            readOnly
            className="flex-1"
            data-testid="client-picker-selected"
          />
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={handleClear}
            aria-label="Clear client"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      ) : (
        <div className="relative">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              id="client-picker"
              type="text"
              placeholder={t('event.searchClient')}
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

          {isOpen && debouncedQuery.length >= 2 && (
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
                        className="w-full px-3 py-2 text-left hover:bg-accent transition-colors"
                        onClick={() => handleSelect(client.id, client.user.name)}
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
      )}

      {error && (
        <p className="text-sm text-destructive" role="alert">
          {t(error)}
        </p>
      )}
    </div>
  )
}
