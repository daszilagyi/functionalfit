import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Edit, Trash2, Eye, Send, History } from 'lucide-react'
import { format } from 'date-fns'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { TiptapEditor } from '@/components/ui/tiptap-editor'
import { useToast } from '@/hooks/use-toast'
import { emailTemplatesApi, adminKeys } from '@/api/admin'
import type { EmailTemplate, EmailTemplateVersion } from '@/types/admin'
import {
  updateEmailTemplateSchema,
  sendTestEmailSchema,
  type UpdateEmailTemplateFormData,
  type SendTestEmailFormData
} from '@/lib/validations/admin'

export default function EmailTemplatesPage() {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()

  // State management
  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [previewDialogOpen, setPreviewDialogOpen] = useState(false)
  const [testEmailDialogOpen, setTestEmailDialogOpen] = useState(false)
  const [versionHistoryDialogOpen, setVersionHistoryDialogOpen] = useState(false)
  const [selectedTemplate, setSelectedTemplate] = useState<EmailTemplate | null>(null)
  const [previewHtml, setPreviewHtml] = useState<string>('')
  const [searchQuery, setSearchQuery] = useState('')
  const [activeFilter, setActiveFilter] = useState<boolean | undefined>(undefined)

  // Fetch templates
  const { data: templates, isLoading } = useQuery({
    queryKey: adminKeys.emailTemplatesList({ search: searchQuery, is_active: activeFilter }),
    queryFn: () => emailTemplatesApi.list({ search: searchQuery, is_active: activeFilter }),
  })

  // Fetch variables
  const { data: variables } = useQuery({
    queryKey: adminKeys.emailTemplateVariables(),
    queryFn: () => emailTemplatesApi.getVariables(),
  })

  // Fetch versions when needed
  const { data: versions, isLoading: versionsLoading } = useQuery({
    queryKey: adminKeys.emailTemplateVersions(selectedTemplate?.id || 0),
    queryFn: () => emailTemplatesApi.getVersions(selectedTemplate!.id),
    enabled: versionHistoryDialogOpen && !!selectedTemplate,
  })

  // Edit form
  const editForm = useForm<UpdateEmailTemplateFormData>({
    resolver: zodResolver(updateEmailTemplateSchema),
  })

  // Test email form
  const testEmailForm = useForm<SendTestEmailFormData>({
    resolver: zodResolver(sendTestEmailSchema),
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateEmailTemplateFormData }) =>
      emailTemplatesApi.update(id, data),
    onSuccess: () => {
      // Invalidate all email templates queries to refresh the list
      queryClient.invalidateQueries({ queryKey: ['admin', 'emailTemplates'] })
      toast({
        title: t('emailTemplates.updateSuccess'),
        description: t('emailTemplates.updateSuccess'),
      })
      setEditDialogOpen(false)
      setSelectedTemplate(null)
      editForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('emailTemplates.updateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => emailTemplatesApi.delete(id),
    onSuccess: () => {
      // Invalidate all email templates queries to refresh the list
      queryClient.invalidateQueries({ queryKey: ['admin', 'emailTemplates'] })
      toast({
        title: t('emailTemplates.deleteSuccess'),
        description: t('emailTemplates.deleteSuccess'),
      })
      setDeleteDialogOpen(false)
      setSelectedTemplate(null)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('emailTemplates.deleteError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Preview mutation
  const previewMutation = useMutation({
    mutationFn: (id: number) => emailTemplatesApi.preview(id, {}),
    onSuccess: (data) => {
      // Extract HTML from response - data is already unwrapped { preview: string, variables_used: Record }
      const htmlContent = data.preview || ''
      setPreviewHtml(htmlContent)
      setPreviewDialogOpen(true)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Send test email mutation
  const sendTestMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: SendTestEmailFormData }) =>
      emailTemplatesApi.sendTest(id, data),
    onSuccess: () => {
      toast({
        title: t('emailTemplates.testEmailSuccess'),
        description: t('emailTemplates.testEmailSuccess'),
      })
      setTestEmailDialogOpen(false)
      testEmailForm.reset()
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('emailTemplates.testEmailError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Restore version mutation
  const restoreMutation = useMutation({
    mutationFn: ({ id, versionId }: { id: number; versionId: number }) =>
      emailTemplatesApi.restore(id, versionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.emailTemplatesList() })
      queryClient.invalidateQueries({ queryKey: adminKeys.emailTemplateVersions(selectedTemplate?.id || 0) })
      toast({
        title: t('emailTemplates.restoreSuccess'),
        description: t('emailTemplates.restoreSuccess'),
      })
      setVersionHistoryDialogOpen(false)
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('emailTemplates.restoreError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Handlers
  const handleEdit = (template: EmailTemplate) => {
    setSelectedTemplate(template)
    editForm.reset({
      subject: template.subject,
      html_body: template.html_body,
      fallback_body: template.fallback_body,
      is_active: template.is_active,
    })
    setEditDialogOpen(true)
  }

  const handleUpdate = (data: UpdateEmailTemplateFormData) => {
    if (selectedTemplate) {
      updateMutation.mutate({ id: selectedTemplate.id, data })
    }
  }

  const handleDelete = (template: EmailTemplate) => {
    setSelectedTemplate(template)
    setDeleteDialogOpen(true)
  }

  const confirmDelete = () => {
    if (selectedTemplate) {
      deleteMutation.mutate(selectedTemplate.id)
    }
  }

  const handlePreview = (template: EmailTemplate) => {
    setSelectedTemplate(template)
    previewMutation.mutate(template.id)
  }

  const handleSendTest = (template: EmailTemplate) => {
    setSelectedTemplate(template)
    setTestEmailDialogOpen(true)
  }

  const submitTestEmail = (data: SendTestEmailFormData) => {
    if (selectedTemplate) {
      sendTestMutation.mutate({ id: selectedTemplate.id, data })
    }
  }

  const handleVersionHistory = (template: EmailTemplate) => {
    setSelectedTemplate(template)
    setVersionHistoryDialogOpen(true)
  }

  const handleRestoreVersion = (version: EmailTemplateVersion) => {
    if (selectedTemplate) {
      restoreMutation.mutate({ id: selectedTemplate.id, versionId: version.id })
    }
  }

  const handleToggleActive = (template: EmailTemplate) => {
    updateMutation.mutate({
      id: template.id,
      data: { is_active: !template.is_active },
    })
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('emailTemplates.title')}</h1>
          <p className="text-gray-500 mt-2">{t('emailTemplates.subtitle')}</p>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex gap-4 flex-wrap">
            <div className="flex-1 min-w-[200px]">
              <Input
                placeholder={t('emailTemplates.searchPlaceholder')}
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                data-testid="email-templates-search-input"
              />
            </div>
            <div className="flex gap-2">
              <Button
                variant={activeFilter === undefined ? 'default' : 'outline'}
                onClick={() => setActiveFilter(undefined)}
              >
                {t('emailTemplates.allStatus')}
              </Button>
              <Button
                variant={activeFilter === true ? 'default' : 'outline'}
                onClick={() => setActiveFilter(true)}
              >
                {t('emailTemplates.activeOnly')}
              </Button>
              <Button
                variant={activeFilter === false ? 'default' : 'outline'}
                onClick={() => setActiveFilter(false)}
              >
                {t('emailTemplates.inactiveOnly')}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Available Variables Card */}
      {variables && variables.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">{t('emailTemplates.availableVariables')}</CardTitle>
            <CardDescription>{t('emailTemplates.variablesHelp')}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
              {variables.map((variable) => (
                <div key={variable.name} className="text-sm">
                  <code className="bg-gray-100 px-2 py-1 rounded text-xs font-mono">
                    {`{{${variable.name}}}`}
                  </code>
                  <p className="text-xs text-gray-500 mt-1">{variable.description}</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Templates Table */}
      <Card>
        <CardHeader>
          <CardTitle>{t('emailTemplates.templatesList')}</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : templates && templates.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('emailTemplates.slug')}</TableHead>
                  <TableHead>{t('emailTemplates.subject')}</TableHead>
                  <TableHead>{t('emailTemplates.isActive')}</TableHead>
                  <TableHead>{t('emailTemplates.version')}</TableHead>
                  <TableHead>{t('emailTemplates.updatedAt')}</TableHead>
                  <TableHead className="text-right">{t('common:actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {templates.map((template) => (
                  <TableRow key={template.id} data-testid={`email-template-row-${template.id}`}>
                    <TableCell className="font-mono text-sm">{template.slug}</TableCell>
                    <TableCell>{template.subject}</TableCell>
                    <TableCell>
                      <Switch
                        checked={template.is_active}
                        onCheckedChange={() => handleToggleActive(template)}
                        data-testid={`toggle-active-${template.id}`}
                      />
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">v{template.version}</Badge>
                    </TableCell>
                    <TableCell className="text-sm text-gray-500">
                      {format(new Date(template.updated_at), 'yyyy-MM-dd HH:mm')}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handlePreview(template)}
                          data-testid={`preview-btn-${template.id}`}
                          title={t('emailTemplates.previewTemplate')}
                        >
                          <Eye className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleSendTest(template)}
                          data-testid={`send-test-btn-${template.id}`}
                          title={t('emailTemplates.sendTestEmail')}
                        >
                          <Send className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleVersionHistory(template)}
                          data-testid={`version-history-btn-${template.id}`}
                          title={t('emailTemplates.versionHistory')}
                        >
                          <History className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleEdit(template)}
                          data-testid={`edit-btn-${template.id}`}
                        >
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => handleDelete(template)}
                          data-testid={`delete-btn-${template.id}`}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">{t('emailTemplates.noTemplatesFound')}</p>
          )}
        </CardContent>
      </Card>

      {/* Edit Dialog */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto" data-testid="edit-template-dialog">
          <DialogHeader>
            <DialogTitle>{t('emailTemplates.editTemplate')}</DialogTitle>
            <DialogDescription>{t('emailTemplates.editTemplateDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={editForm.handleSubmit(handleUpdate)} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="edit-subject">{t('emailTemplates.subject')}</Label>
              <Input
                id="edit-subject"
                data-testid="edit-subject-input"
                {...editForm.register('subject')}
              />
              {editForm.formState.errors.subject && (
                <p className="text-sm text-destructive">{t(editForm.formState.errors.subject.message!)}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-html-body">{t('emailTemplates.htmlBody')}</Label>
              <Controller
                name="html_body"
                control={editForm.control}
                render={({ field }) => (
                  <TiptapEditor
                    value={field.value || ''}
                    onChange={field.onChange}
                    placeholder={t('emailTemplates.htmlBodyPlaceholder')}
                  />
                )}
              />
              {editForm.formState.errors.html_body && (
                <p className="text-sm text-destructive">{t(editForm.formState.errors.html_body.message!)}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-fallback-body">{t('emailTemplates.fallbackBody')}</Label>
              <Textarea
                id="edit-fallback-body"
                data-testid="edit-fallback-body-textarea"
                rows={6}
                {...editForm.register('fallback_body')}
              />
              {editForm.formState.errors.fallback_body && (
                <p className="text-sm text-destructive">{t(editForm.formState.errors.fallback_body.message!)}</p>
              )}
            </div>
            <div className="flex items-center space-x-2">
              <Switch
                id="edit-is-active"
                checked={editForm.watch('is_active')}
                onCheckedChange={(checked) => editForm.setValue('is_active', checked)}
                data-testid="edit-is-active-switch"
              />
              <Label htmlFor="edit-is-active">{t('emailTemplates.isActive')}</Label>
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditDialogOpen(false)}
              >
                {t('common:cancel')}
              </Button>
              <Button
                type="submit"
                disabled={updateMutation.isPending}
                data-testid="submit-edit-btn"
              >
                {updateMutation.isPending ? t('common:loading') : t('common:save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent data-testid="delete-template-dialog">
          <AlertDialogHeader>
            <AlertDialogTitle>{t('emailTemplates.deleteTemplate')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('emailTemplates.deleteTemplateConfirmation', { slug: selectedTemplate?.slug })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              data-testid="confirm-delete-btn"
            >
              {deleteMutation.isPending ? t('common:loading') : t('common:delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Preview Dialog */}
      <Dialog open={previewDialogOpen} onOpenChange={setPreviewDialogOpen}>
        <DialogContent className="max-w-5xl max-h-[90vh]" data-testid="preview-template-dialog">
          <DialogHeader>
            <DialogTitle>{t('emailTemplates.previewTitle')}</DialogTitle>
            <DialogDescription>{t('emailTemplates.previewDescription')}</DialogDescription>
          </DialogHeader>
          <div className="border rounded-lg overflow-hidden bg-white" style={{ minHeight: '400px' }}>
            <iframe
              srcDoc={previewHtml}
              className="w-full h-[600px]"
              title="Email Preview"
              sandbox="allow-same-origin"
              data-testid="preview-iframe"
            />
          </div>
          <DialogFooter>
            <Button onClick={() => setPreviewDialogOpen(false)}>
              {t('common:close')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Send Test Email Dialog */}
      <Dialog open={testEmailDialogOpen} onOpenChange={setTestEmailDialogOpen}>
        <DialogContent data-testid="send-test-email-dialog">
          <DialogHeader>
            <DialogTitle>{t('emailTemplates.testEmailTitle')}</DialogTitle>
            <DialogDescription>{t('emailTemplates.testEmailDescription')}</DialogDescription>
          </DialogHeader>
          <form onSubmit={testEmailForm.handleSubmit(submitTestEmail)} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="test-email">{t('emailTemplates.testEmailAddress')}</Label>
              <Input
                id="test-email"
                type="email"
                data-testid="test-email-input"
                {...testEmailForm.register('email')}
              />
              {testEmailForm.formState.errors.email && (
                <p className="text-sm text-destructive">{t(testEmailForm.formState.errors.email.message!)}</p>
              )}
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setTestEmailDialogOpen(false)}
              >
                {t('common:cancel')}
              </Button>
              <Button
                type="submit"
                disabled={sendTestMutation.isPending}
                data-testid="submit-test-email-btn"
              >
                {sendTestMutation.isPending ? t('common:loading') : t('emailTemplates.sendTestEmail')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Version History Dialog */}
      <Dialog open={versionHistoryDialogOpen} onOpenChange={setVersionHistoryDialogOpen}>
        <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto" data-testid="version-history-dialog">
          <DialogHeader>
            <DialogTitle>{t('emailTemplates.versionHistoryTitle')}</DialogTitle>
            <DialogDescription>{t('emailTemplates.versionHistoryDescription')}</DialogDescription>
          </DialogHeader>
          {versionsLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-20 w-full" />
              ))}
            </div>
          ) : versions && versions.length > 0 ? (
            <div className="space-y-4">
              {versions.map((version) => (
                <Card key={version.id}>
                  <CardContent className="pt-4">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-2">
                          <Badge>v{version.version}</Badge>
                          <span className="text-sm text-gray-500">
                            {format(new Date(version.created_at), 'yyyy-MM-dd HH:mm')}
                          </span>
                          {version.created_by_user && (
                            <span className="text-sm text-gray-500">
                              by {version.created_by_user.name}
                            </span>
                          )}
                        </div>
                        <p className="text-sm font-medium mb-1">{version.subject}</p>
                        <p className="text-xs text-gray-500 line-clamp-2">{version.fallback_body}</p>
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleRestoreVersion(version)}
                        disabled={restoreMutation.isPending}
                        data-testid={`restore-version-btn-${version.id}`}
                      >
                        {t('emailTemplates.restoreVersion')}
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">{t('emailTemplates.noVersionsFound')}</p>
          )}
          <DialogFooter>
            <Button onClick={() => setVersionHistoryDialogOpen(false)}>
              {t('common:close')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
