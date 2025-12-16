import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/use-toast'
import { clientsApi } from '@/api/clients'
import {
  adminParticipantsApi,
  staffParticipantsApi,
  participantKeys,
  type ClassParticipant,
  type AddClassParticipantRequest,
} from '@/api/participants'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { UserPlus, X, Loader2, Users, Search } from 'lucide-react'

interface ParticipantManagerProps {
  occurrenceId: string | number
  isOwner: boolean
  capacity: number
  disabled?: boolean
}

export function ParticipantManager({
  occurrenceId,
  isOwner,
  capacity,
  disabled = false,
}: ParticipantManagerProps) {
  const { t } = useTranslation(['calendar', 'common'])
  const { user } = useAuth()
  const { toast } = useToast()
  const queryClient = useQueryClient()

  const [searchQuery, setSearchQuery] = useState('')
  const [selectedClientId, setSelectedClientId] = useState<string>('')
  const [skipPayment, setSkipPayment] = useState(false)
  const [removeDialogOpen, setRemoveDialogOpen] = useState(false)
  const [participantToRemove, setParticipantToRemove] = useState<ClassParticipant | null>(null)
  const [refundOnRemove, setRefundOnRemove] = useState(true)

  const isAdmin = user?.role === 'admin'
  const canManage = isAdmin || isOwner

  const participantsApi = isAdmin ? adminParticipantsApi : staffParticipantsApi

  // Fetch participants
  const { data: participantsData, isLoading } = useQuery({
    queryKey: participantKeys.classParticipants(occurrenceId),
    queryFn: () => participantsApi.listClassParticipants(occurrenceId),
    enabled: canManage,
  })

  // Search clients
  const { data: searchResults, isLoading: isSearching } = useQuery({
    queryKey: ['clients', 'search', searchQuery],
    queryFn: () => clientsApi.search(searchQuery),
    enabled: searchQuery.length >= 2 && canManage,
  })

  // Add participant mutation
  const addMutation = useMutation({
    mutationFn: (data: AddClassParticipantRequest) =>
      participantsApi.addClassParticipant(occurrenceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: participantKeys.classParticipants(occurrenceId) })
      toast({ title: t('participants.added') })
      setSelectedClientId('')
      setSkipPayment(false)
      setSearchQuery('')
    },
    onError: (error: Error) => {
      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: error.message,
      })
    },
  })

  // Remove participant mutation
  const removeMutation = useMutation({
    mutationFn: ({ clientId, refund }: { clientId: string; refund: boolean }) =>
      participantsApi.removeClassParticipant(occurrenceId, clientId, { refund }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: participantKeys.classParticipants(occurrenceId) })
      toast({ title: t('participants.removed') })
      setRemoveDialogOpen(false)
      setParticipantToRemove(null)
    },
    onError: (error: Error) => {
      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: error.message,
      })
    },
  })

  const handleAddParticipant = () => {
    if (!selectedClientId) return
    addMutation.mutate({
      client_id: parseInt(selectedClientId),
      skip_payment: isAdmin ? skipPayment : false,
    })
  }

  const handleRemoveClick = (participant: ClassParticipant) => {
    setParticipantToRemove(participant)
    setRefundOnRemove(true)
    setRemoveDialogOpen(true)
  }

  const handleConfirmRemove = () => {
    if (!participantToRemove) return
    removeMutation.mutate({
      clientId: participantToRemove.client_id,
      refund: refundOnRemove,
    })
  }

  if (!canManage) {
    return null
  }

  const participants = participantsData?.participants || []
  const bookedCount = participants.filter(p => p.status === 'booked' || p.status === 'attended').length
  const waitlistCount = participants.filter(p => p.status === 'waitlist').length

  const getStatusBadge = (status: string, paymentStatus: string) => {
    if (status === 'waitlist') {
      return <Badge variant="outline">{t('participants.waitlist')}</Badge>
    }
    if (paymentStatus === 'paid') {
      return <Badge variant="default">{t('participants.paid')}</Badge>
    }
    if (paymentStatus === 'unpaid') {
      return <Badge variant="destructive">{t('participants.unpaid')}</Badge>
    }
    if (paymentStatus === 'comped') {
      return <Badge variant="secondary">{t('participants.comped')}</Badge>
    }
    return <Badge variant="outline">{t('participants.pending')}</Badge>
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Users className="h-4 w-4" />
          <h3 className="font-semibold">{t('participants.title')}</h3>
        </div>
        <div className="text-sm text-muted-foreground">
          {bookedCount}/{capacity}
          {waitlistCount > 0 && ` (+${waitlistCount} ${t('participants.waitlist')})`}
        </div>
      </div>

      {/* Participants List */}
      {isLoading ? (
        <div className="flex items-center justify-center py-4">
          <Loader2 className="h-4 w-4 animate-spin" />
        </div>
      ) : participants.length === 0 ? (
        <p className="text-sm text-muted-foreground py-2">{t('participants.empty')}</p>
      ) : (
        <div className="space-y-2 max-h-48 overflow-y-auto">
          {participants.map((participant) => (
            <div
              key={participant.registration_id}
              className="flex items-center justify-between p-2 border rounded-lg"
            >
              <div className="flex flex-col">
                <span className="font-medium text-sm">{participant.client_name}</span>
                <span className="text-xs text-muted-foreground">{participant.client_email}</span>
              </div>
              <div className="flex items-center gap-2">
                {getStatusBadge(participant.status, participant.payment_status)}
                {!disabled && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-6 w-6"
                    onClick={() => handleRemoveClick(participant)}
                  >
                    <X className="h-3 w-3" />
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Add Participant */}
      {!disabled && (
        <div className="space-y-3 pt-2 border-t">
          {/* Search Input */}
          <div className="relative">
            <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder={t('participants.searchPlaceholder')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-8"
            />
          </div>

          {/* Search Results */}
          {searchQuery.length >= 2 && (
            <div className="space-y-2">
              {isSearching ? (
                <div className="flex items-center justify-center py-2">
                  <Loader2 className="h-4 w-4 animate-spin" />
                </div>
              ) : searchResults && searchResults.length > 0 ? (
                <Select value={selectedClientId} onValueChange={setSelectedClientId}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('participants.selectClient')} />
                  </SelectTrigger>
                  <SelectContent>
                    {searchResults.map((client) => (
                      <SelectItem key={client.id} value={client.id}>
                        {client.user.name} ({client.user.email})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <p className="text-sm text-muted-foreground">{t('participants.noResults')}</p>
              )}
            </div>
          )}

          {/* Skip payment option (admin only) */}
          {isAdmin && selectedClientId && (
            <div className="flex items-center space-x-2">
              <Checkbox
                id="skipPayment"
                checked={skipPayment}
                onCheckedChange={(checked) => setSkipPayment(checked === true)}
              />
              <Label htmlFor="skipPayment" className="text-sm">
                {t('participants.skipPayment')}
              </Label>
            </div>
          )}

          {/* Add Button */}
          {selectedClientId && (
            <Button
              onClick={handleAddParticipant}
              disabled={addMutation.isPending}
              className="w-full"
            >
              {addMutation.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin mr-2" />
              ) : (
                <UserPlus className="h-4 w-4 mr-2" />
              )}
              {t('participants.added').replace('hozzáadva', 'hozzáadása')}
            </Button>
          )}
        </div>
      )}

      {/* Remove Confirmation Dialog */}
      <AlertDialog open={removeDialogOpen} onOpenChange={setRemoveDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('participants.removeTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('participants.removeDescription', { name: participantToRemove?.client_name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="py-4">
            <div className="flex items-center space-x-2">
              <Checkbox
                id="refundOnRemove"
                checked={refundOnRemove}
                onCheckedChange={(checked) => setRefundOnRemove(checked === true)}
              />
              <Label htmlFor="refundOnRemove" className="text-sm">
                {t('participants.refundCredits')}
              </Label>
            </div>
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmRemove}
              disabled={removeMutation.isPending}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {removeMutation.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                t('participants.remove')
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
