import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, RefreshCw, Settings } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useToast } from '@/hooks/use-toast'
import { syncConfigsApi, syncLogsApi, googleCalendarSyncKeys } from '@/api/googleCalendarSync'
import type { GoogleCalendarSyncConfig } from '@/types/googleCalendar'
import { SyncConfigList } from '@/components/admin/GoogleCalendarSync/SyncConfigList'
import { SyncConfigDialog } from '@/components/admin/GoogleCalendarSync/SyncConfigDialog'
import { ImportWizard } from '@/components/admin/GoogleCalendarSync/ImportWizard'
import { ExportWizard } from '@/components/admin/GoogleCalendarSync/ExportWizard'
import { SyncLogsViewer } from '@/components/admin/GoogleCalendarSync/SyncLogsViewer'

export default function GoogleCalendarSyncPage() {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()

  // State
  const [configDialogOpen, setConfigDialogOpen] = useState(false)
  const [importWizardOpen, setImportWizardOpen] = useState(false)
  const [exportWizardOpen, setExportWizardOpen] = useState(false)
  const [selectedConfig, setSelectedConfig] = useState<GoogleCalendarSyncConfig | null>(null)
  const [activeTab, setActiveTab] = useState('configs')

  // Fetch configurations
  const { data: configs, isLoading: configsLoading } = useQuery({
    queryKey: googleCalendarSyncKeys.configsList(),
    queryFn: syncConfigsApi.list,
  })

  // Fetch logs
  const { data: logsData, isLoading: logsLoading } = useQuery({
    queryKey: googleCalendarSyncKeys.logsList(),
    queryFn: () => syncLogsApi.list({ per_page: 50 }),
    enabled: activeTab === 'logs',
  })

  // Delete config mutation
  const deleteConfigMutation = useMutation({
    mutationFn: (id: number) => syncConfigsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: googleCalendarSyncKeys.configs() })
      toast({
        title: t('googleCalendarSync.configDeleted'),
        description: t('googleCalendarSync.configDeletedDescription'),
      })
    },
    onError: (error: any) => {
      toast({
        title: t('common.error'),
        description: error.response?.data?.message || t('googleCalendarSync.deleteError'),
        variant: 'destructive',
      })
    },
  })

  const handleCreateConfig = () => {
    setSelectedConfig(null)
    setConfigDialogOpen(true)
  }

  const handleEditConfig = (config: GoogleCalendarSyncConfig) => {
    setSelectedConfig(config)
    setConfigDialogOpen(true)
  }

  const handleDeleteConfig = (id: number) => {
    if (confirm(t('googleCalendarSync.confirmDelete'))) {
      deleteConfigMutation.mutate(id)
    }
  }

  const handleImport = (config: GoogleCalendarSyncConfig) => {
    setSelectedConfig(config)
    setImportWizardOpen(true)
  }

  const handleExport = (config: GoogleCalendarSyncConfig) => {
    setSelectedConfig(config)
    setExportWizardOpen(true)
  }

  const handleConfigDialogClose = () => {
    setConfigDialogOpen(false)
    setSelectedConfig(null)
  }

  const handleImportWizardClose = () => {
    setImportWizardOpen(false)
    setSelectedConfig(null)
  }

  const handleExportWizardClose = () => {
    setExportWizardOpen(false)
    setSelectedConfig(null)
  }

  return (
    <div className="container mx-auto py-6 space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('googleCalendarSync.title')}</h1>
          <p className="text-muted-foreground">{t('googleCalendarSync.description')}</p>
        </div>
        <div className="flex gap-2">
          <Button onClick={handleCreateConfig}>
            <Plus className="h-4 w-4 mr-2" />
            {t('googleCalendarSync.newConfig')}
          </Button>
        </div>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
        <TabsList>
          <TabsTrigger value="configs">
            <Settings className="h-4 w-4 mr-2" />
            {t('googleCalendarSync.configurations')}
          </TabsTrigger>
          <TabsTrigger value="logs">
            <RefreshCw className="h-4 w-4 mr-2" />
            {t('googleCalendarSync.syncLogs')}
          </TabsTrigger>
        </TabsList>

        <TabsContent value="configs" className="space-y-4">
          <SyncConfigList
            configs={configs || []}
            isLoading={configsLoading}
            onEdit={handleEditConfig}
            onDelete={handleDeleteConfig}
            onImport={handleImport}
            onExport={handleExport}
          />
        </TabsContent>

        <TabsContent value="logs">
          <SyncLogsViewer
            logs={logsData?.data || []}
            isLoading={logsLoading}
            pagination={logsData?.meta}
          />
        </TabsContent>
      </Tabs>

      {/* Dialogs and Wizards */}
      <SyncConfigDialog
        open={configDialogOpen}
        onClose={handleConfigDialogClose}
        config={selectedConfig}
      />

      <ImportWizard
        open={importWizardOpen}
        onClose={handleImportWizardClose}
        config={selectedConfig}
      />

      <ExportWizard
        open={exportWizardOpen}
        onClose={handleExportWizardClose}
        config={selectedConfig}
      />
    </div>
  )
}
