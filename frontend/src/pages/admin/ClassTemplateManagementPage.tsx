import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { classTemplatesApi, adminKeys } from '@/api/admin'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import { Plus, Pencil, Trash2, Dumbbell, DollarSign } from 'lucide-react'
import { useToast } from '@/hooks/use-toast'
import type { ClassTemplate, CreateClassTemplateRequest } from '@/types/admin'
import { AssignPricingDialog } from '@/components/pricing/AssignPricingDialog'

export default function ClassTemplateManagementPage() {
  const { t } = useTranslation(['admin', 'common'])
  const { toast } = useToast()
  const queryClient = useQueryClient()

  const [statusFilter, setStatusFilter] = useState<boolean | undefined>()
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false)
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false)
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false)
  const [isPricingDialogOpen, setIsPricingDialogOpen] = useState(false)
  const [selectedTemplate, setSelectedTemplate] = useState<ClassTemplate | null>(null)

  // Form state
  const [formData, setFormData] = useState<CreateClassTemplateRequest>({
    name: '',
    description: '',
    duration_minutes: 60,
    default_capacity: 10,
    credits_required: 1,
    color: '#3788D8',
    is_active: true,
  })

  // Fetch class templates
  const { data: templates, isLoading } = useQuery({
    queryKey: adminKeys.classTemplatesList({ is_active: statusFilter }),
    queryFn: () => classTemplatesApi.list({ is_active: statusFilter }),
  })

  // Create mutation
  const createMutation = useMutation({
    mutationFn: classTemplatesApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.classTemplates() })
      setIsCreateDialogOpen(false)
      resetForm()
      toast({
        title: t('common:success'),
        description: 'Class template created successfully',
      })
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: 'Failed to create class template',
        variant: 'destructive',
      })
    },
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: CreateClassTemplateRequest }) => classTemplatesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.classTemplates() })
      setIsEditDialogOpen(false)
      setSelectedTemplate(null)
      resetForm()
      toast({
        title: t('common:success'),
        description: 'Class template updated successfully',
      })
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: 'Failed to update class template',
        variant: 'destructive',
      })
    },
  })

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: classTemplatesApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.classTemplates() })
      setIsDeleteDialogOpen(false)
      setSelectedTemplate(null)
      toast({
        title: t('common:success'),
        description: 'Class template deleted successfully',
      })
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: 'Failed to delete class template',
        variant: 'destructive',
      })
    },
  })

  const resetForm = () => {
    setFormData({
      name: '',
      description: '',
      duration_minutes: 60,
      default_capacity: 10,
      credits_required: 1,
      color: '#3788D8',
      is_active: true,
    })
  }

  const handleCreate = () => {
    createMutation.mutate(formData)
  }

  const handleEdit = (template: ClassTemplate) => {
    setSelectedTemplate(template)
    setFormData({
      name: template.title,
      description: template.description || '',
      duration_minutes: template.duration_minutes,
      default_capacity: template.default_capacity,
      credits_required: template.credits_required || 1,
      color: template.color || '#3788D8',
      is_active: template.is_active,
    })
    setIsEditDialogOpen(true)
  }

  const handleUpdate = () => {
    if (!selectedTemplate) return
    updateMutation.mutate({ id: selectedTemplate.id, data: formData })
  }

  const handleDeleteClick = (template: ClassTemplate) => {
    setSelectedTemplate(template)
    setIsDeleteDialogOpen(true)
  }

  const handleDeleteConfirm = () => {
    if (!selectedTemplate) return
    deleteMutation.mutate(selectedTemplate.id)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Class Template Management</h1>
          <p className="text-gray-500 mt-2">Manage class types and their configurations</p>
        </div>
        <Button onClick={() => setIsCreateDialogOpen(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Create Template
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex gap-2">
            <Button
              variant={statusFilter === undefined ? 'default' : 'outline'}
              onClick={() => setStatusFilter(undefined)}
            >
              All Templates
            </Button>
            <Button
              variant={statusFilter === true ? 'default' : 'outline'}
              onClick={() => setStatusFilter(true)}
            >
              Active
            </Button>
            <Button
              variant={statusFilter === false ? 'default' : 'outline'}
              onClick={() => setStatusFilter(false)}
            >
              Inactive
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Templates Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {isLoading ? (
          Array.from({ length: 6 }).map((_, i) => (
            <Card key={i}>
              <CardHeader>
                <Skeleton className="h-6 w-32" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-20 w-full" />
              </CardContent>
            </Card>
          ))
        ) : templates && templates.length > 0 ? (
          templates.map((template) => (
            <Card key={template.id} className="hover:shadow-lg transition-shadow">
              <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div className="flex items-center gap-2">
                  <Dumbbell className="h-5 w-5 text-muted-foreground" style={{ color: template.color }} />
                  <div>
                    <CardTitle className="text-lg">{template.title}</CardTitle>
                    <Badge className={template.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}>
                      {template.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-3">
                {template.description && (
                  <p className="text-sm text-gray-600 line-clamp-2">{template.description}</p>
                )}
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Duration:</span>
                  <span className="font-medium">{template.duration_minutes} min</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Capacity:</span>
                  <span className="font-medium">{template.default_capacity} people</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Credits:</span>
                  <span className="font-medium">{template.credits_required || 1}</span>
                </div>
                <div className="flex gap-2 pt-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={() => {
                      setSelectedTemplate(template)
                      setIsPricingDialogOpen(true)
                    }}
                  >
                    <DollarSign className="h-3 w-3 mr-1" />
                    Price
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={() => handleEdit(template)}
                  >
                    <Pencil className="h-3 w-3 mr-1" />
                    Edit
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1 text-red-600 hover:text-red-700"
                    onClick={() => handleDeleteClick(template)}
                  >
                    <Trash2 className="h-3 w-3 mr-1" />
                    Delete
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))
        ) : (
          <Card className="col-span-full">
            <CardContent className="py-12 text-center">
              <Dumbbell className="h-12 w-12 mx-auto text-gray-400 mb-4" />
              <p className="text-gray-500">No class templates found</p>
            </CardContent>
          </Card>
        )}
      </div>

      {/* Create/Edit Dialog */}
      <Dialog open={isCreateDialogOpen || isEditDialogOpen} onOpenChange={(open) => {
        if (!open) {
          setIsCreateDialogOpen(false)
          setIsEditDialogOpen(false)
          resetForm()
        }
      }}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{isEditDialogOpen ? 'Edit Class Template' : 'Create New Class Template'}</DialogTitle>
            <DialogDescription>
              {isEditDialogOpen ? 'Update class template information' : 'Add a new class template to the system'}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="name">Template Name *</Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="e.g., Yoga, HIIT, Pilates"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="duration_minutes">Duration (minutes) *</Label>
                <Input
                  id="duration_minutes"
                  type="number"
                  min="15"
                  step="15"
                  value={formData.duration_minutes}
                  onChange={(e) => setFormData({ ...formData, duration_minutes: parseInt(e.target.value) })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="description">Description</Label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                placeholder="Brief description of the class"
                rows={3}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="default_capacity">Default Capacity *</Label>
                <Input
                  id="default_capacity"
                  type="number"
                  min="1"
                  value={formData.default_capacity}
                  onChange={(e) => setFormData({ ...formData, default_capacity: parseInt(e.target.value) })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="credits_required">Credits Required</Label>
                <Input
                  id="credits_required"
                  type="number"
                  min="1"
                  value={formData.credits_required}
                  onChange={(e) => setFormData({ ...formData, credits_required: parseInt(e.target.value) })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="color">Color (Hex)</Label>
              <div className="flex gap-2">
                <Input
                  id="color"
                  value={formData.color}
                  onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                  placeholder="#3788D8"
                  maxLength={7}
                />
                <div
                  className="w-10 h-10 rounded border"
                  style={{ backgroundColor: formData.color }}
                />
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <Switch
                id="is_active"
                checked={formData.is_active}
                onCheckedChange={(checked: boolean) => setFormData({ ...formData, is_active: checked })}
              />
              <Label htmlFor="is_active">Active (visible for booking)</Label>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => {
              setIsCreateDialogOpen(false)
              setIsEditDialogOpen(false)
              resetForm()
            }}>
              Cancel
            </Button>
            <Button onClick={isEditDialogOpen ? handleUpdate : handleCreate} disabled={!formData.name || formData.duration_minutes < 15 || formData.default_capacity < 1}>
              {isEditDialogOpen ? 'Update' : 'Create'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Class Template</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete <strong>{selectedTemplate?.title}</strong>? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDeleteConfirm} className="bg-red-600 hover:bg-red-700">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Assign Pricing Dialog */}
      {selectedTemplate && (
        <AssignPricingDialog
          open={isPricingDialogOpen}
          onOpenChange={setIsPricingDialogOpen}
          classTemplateId={selectedTemplate.id}
          classTemplateName={selectedTemplate.title}
          onSuccess={() => queryClient.invalidateQueries({ queryKey: adminKeys.classTemplates() })}
        />
      )}
    </div>
  )
}
