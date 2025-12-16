import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { classesApi, classKeys } from '@/api/classes'
import { ClassListFilters } from '@/types/class'
import { Skeleton } from '@/components/ui/skeleton'
import { ClassCard } from '@/components/classes/ClassCard'

export default function ClassesPage() {
  const { t } = useTranslation('classes')
  const [filters] = useState<ClassListFilters>({
    has_capacity: true,
    status: 'scheduled',
  })

  const { data: classes, isLoading, error } = useQuery({
    queryKey: classKeys.list(filters),
    queryFn: () => classesApi.list(filters),
    staleTime: 2 * 60 * 1000, // 2 minutes
  })

  if (isLoading) {
    return (
      <div className="space-y-4">
        {[1, 2, 3].map(i => (
          <Skeleton key={i} className="h-32 w-full" />
        ))}
      </div>
    )
  }

  if (error) {
    return <div className="text-destructive">{t('errors.loadFailed')}</div>
  }

  return (
    <div className="container py-6">
      <h1 className="text-3xl font-bold mb-6">{t('title')}</h1>

      {/* TODO: Add ClassFilters component here */}

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {classes?.map(classOccurrence => (
          <ClassCard key={classOccurrence.id} classOccurrence={classOccurrence} />
        ))}
      </div>

      {classes?.length === 0 && (
        <div className="text-center text-muted-foreground py-12">
          {t('common:noData')}
        </div>
      )}
    </div>
  )
}
