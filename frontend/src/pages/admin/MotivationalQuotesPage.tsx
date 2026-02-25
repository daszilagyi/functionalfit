import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { PlusCircle, Edit, Trash2 } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/hooks/use-toast'
import { motivationalQuotesApi, adminKeys } from '@/api/admin'

const quoteSchema = z.object({
  text: z.string().min(1).max(500),
})
type QuoteFormData = z.infer<typeof quoteSchema>

interface MotivationalQuote {
  id: number
  text: string
}

export default function MotivationalQuotesPage() {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [createOpen, setCreateOpen] = useState(false)
  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [selected, setSelected] = useState<MotivationalQuote | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: adminKeys.motivationalQuotes(),
    queryFn: () => motivationalQuotesApi.list(),
  })
  const quotes: MotivationalQuote[] = data?.data ?? []

  const createForm = useForm<QuoteFormData>({ resolver: zodResolver(quoteSchema), defaultValues: { text: '' } })
  const editForm = useForm<QuoteFormData>({ resolver: zodResolver(quoteSchema) })

  const createMutation = useMutation({
    mutationFn: (data: QuoteFormData) => motivationalQuotesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.motivationalQuotes() })
      toast({ title: t('motivationalQuotes.createSuccess') })
      setCreateOpen(false)
      createForm.reset()
    },
    onError: () => toast({ variant: 'destructive', title: t('motivationalQuotes.createError') }),
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: QuoteFormData }) => motivationalQuotesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.motivationalQuotes() })
      toast({ title: t('motivationalQuotes.updateSuccess') })
      setEditOpen(false)
      setSelected(null)
    },
    onError: () => toast({ variant: 'destructive', title: t('motivationalQuotes.updateError') }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => motivationalQuotesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.motivationalQuotes() })
      toast({ title: t('motivationalQuotes.deleteSuccess') })
      setDeleteOpen(false)
      setSelected(null)
    },
    onError: () => toast({ variant: 'destructive', title: t('motivationalQuotes.deleteError') }),
  })

  const handleEdit = (quote: MotivationalQuote) => {
    setSelected(quote)
    editForm.reset({ text: quote.text })
    setEditOpen(true)
  }

  const handleDelete = (quote: MotivationalQuote) => {
    setSelected(quote)
    setDeleteOpen(true)
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('motivationalQuotes.title')}</h1>
          <p className="text-gray-500 mt-2">{t('motivationalQuotes.subtitle')}</p>
        </div>
        <Button onClick={() => setCreateOpen(true)}>
          <PlusCircle className="h-4 w-4 mr-2" />
          {t('motivationalQuotes.addQuote')}
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t('motivationalQuotes.title')} ({quotes.length})</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => <Skeleton key={i} className="h-12 w-full" />)}
            </div>
          ) : quotes.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-12">#</TableHead>
                  <TableHead>{t('motivationalQuotes.quoteText')}</TableHead>
                  <TableHead className="text-right w-28">{t('common:actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {quotes.map((quote) => (
                  <TableRow key={quote.id}>
                    <TableCell className="text-muted-foreground text-sm">{quote.id}</TableCell>
                    <TableCell className="max-w-xl">
                      <p className="text-sm leading-relaxed">{quote.text}</p>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button variant="outline" size="sm" onClick={() => handleEdit(quote)}>
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button variant="destructive" size="sm" onClick={() => handleDelete(quote)}>
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">{t('motivationalQuotes.noQuotesFound')}</p>
          )}
        </CardContent>
      </Card>

      {/* Create Dialog */}
      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('motivationalQuotes.addQuote')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={createForm.handleSubmit((data) => createMutation.mutate(data))} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="create-text">{t('motivationalQuotes.quoteText')}</Label>
              <Textarea
                id="create-text"
                rows={4}
                {...createForm.register('text')}
                disabled={createMutation.isPending}
              />
              {createForm.formState.errors.text && (
                <p className="text-sm text-destructive">{createForm.formState.errors.text.message}</p>
              )}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                {t('common:cancel')}
              </Button>
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending ? t('common:loading') : t('common:create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit Dialog */}
      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('motivationalQuotes.editQuote')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={editForm.handleSubmit((data) => selected && updateMutation.mutate({ id: selected.id, data }))} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="edit-text">{t('motivationalQuotes.quoteText')}</Label>
              <Textarea
                id="edit-text"
                rows={4}
                {...editForm.register('text')}
                disabled={updateMutation.isPending}
              />
              {editForm.formState.errors.text && (
                <p className="text-sm text-destructive">{editForm.formState.errors.text.message}</p>
              )}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
                {t('common:cancel')}
              </Button>
              <Button type="submit" disabled={updateMutation.isPending}>
                {updateMutation.isPending ? t('common:loading') : t('common:save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirm */}
      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('motivationalQuotes.deleteQuote')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('motivationalQuotes.deleteConfirmation')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => selected && deleteMutation.mutate(selected.id)}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleteMutation.isPending ? t('common:loading') : t('common:delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
