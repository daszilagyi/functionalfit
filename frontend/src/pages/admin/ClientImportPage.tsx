import { useState, useCallback } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useDropzone } from 'react-dropzone'
import { Upload, FileText, CheckCircle, XCircle, AlertCircle, Download } from 'lucide-react'
import { clientsApi, type ClientImportResult } from '@/api/clients'
import { useToast } from '@/hooks/use-toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'

export default function ClientImportPage() {
  const { t } = useTranslation('admin')
  const { toast } = useToast()
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [importResult, setImportResult] = useState<ClientImportResult | null>(null)

  const importMutation = useMutation({
    mutationFn: (file: File) => clientsApi.importCsv(file),
    onSuccess: (data) => {
      setImportResult(data)
      setSelectedFile(null)
      toast({
        title: t('clientImport.success', 'Import sikeres'),
        description: t('clientImport.successDescription', '{{created}} új vendég létrehozva, {{updated}} frissítve', {
          created: data.summary.created,
          updated: data.summary.updated,
        }),
      })
    },
    onError: (error: Error & { response?: { data?: { message?: string } } }) => {
      toast({
        variant: 'destructive',
        title: t('clientImport.error', 'Hiba az importálás során'),
        description: error.response?.data?.message || error.message,
      })
    },
  })

  const onDrop = useCallback((acceptedFiles: File[]) => {
    if (acceptedFiles.length > 0) {
      setSelectedFile(acceptedFiles[0])
      setImportResult(null)
    }
  }, [])

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'text/csv': ['.csv'],
      'text/plain': ['.txt'],
    },
    maxFiles: 1,
    maxSize: 5 * 1024 * 1024, // 5MB
  })

  const handleImport = () => {
    if (selectedFile) {
      importMutation.mutate(selectedFile)
    }
  }

  const downloadSampleCsv = () => {
    const sampleData = `nev;email;tel;szolg;edzoi dij;belepo dij
Teszt Elek;teszt.elek@example.com;+36301234567;PT;8000;2000
Minta Kata;minta.kata@example.com;+36709876543;PT;7500;1500
Példa János;;;PT;6000;1000`

    const blob = new Blob([sampleData], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = 'vendeg_import_minta.csv'
    link.click()
  }

  return (
    <div className="container py-6 space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">{t('clientImport.title', 'Vendégek importálása')}</h1>
          <p className="text-muted-foreground mt-1">
            {t('clientImport.description', 'Vendégadatok és árkódok importálása CSV fájlból')}
          </p>
        </div>
        <Button variant="outline" onClick={downloadSampleCsv}>
          <Download className="w-4 h-4 mr-2" />
          {t('clientImport.downloadSample', 'Minta CSV letöltése')}
        </Button>
      </div>

      {/* Format info */}
      <Card>
        <CardHeader>
          <CardTitle>{t('clientImport.formatTitle', 'CSV formátum')}</CardTitle>
          <CardDescription>
            {t('clientImport.formatDescription', 'A CSV fájlnak pontosvesszővel (;) elválasztott értékeket kell tartalmaznia')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="bg-muted p-4 rounded-md font-mono text-sm">
            <p className="font-semibold mb-2">{t('clientImport.requiredColumns', 'Kötelező oszlopok:')}</p>
            <ul className="list-disc list-inside space-y-1 text-muted-foreground">
              <li><span className="text-foreground font-medium">nev</span> - {t('clientImport.colName', 'Vendég neve')}</li>
              <li><span className="text-foreground font-medium">email</span> - {t('clientImport.colEmail', 'Email cím (ha üres: potoldazemailcimet@fontos.hu)')}</li>
              <li><span className="text-foreground font-medium">tel</span> - {t('clientImport.colPhone', 'Telefonszám (ha üres: 0690555666)')}</li>
              <li><span className="text-foreground font-medium">szolg</span> - {t('clientImport.colService', 'Szolgáltatás típus kódja (pl. PT)')}</li>
              <li><span className="text-foreground font-medium">edzoi dij</span> - {t('clientImport.colTrainerFee', 'Edzői díj (HUF)')}</li>
              <li><span className="text-foreground font-medium">belepo dij</span> - {t('clientImport.colEntryFee', 'Belépő díj (HUF)')}</li>
            </ul>
          </div>
        </CardContent>
      </Card>

      {/* File upload */}
      <Card>
        <CardHeader>
          <CardTitle>{t('clientImport.uploadTitle', 'Fájl feltöltése')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div
            {...getRootProps()}
            className={`
              border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors
              ${isDragActive ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-primary/50'}
              ${selectedFile ? 'border-green-500 bg-green-50 dark:bg-green-950/20' : ''}
            `}
          >
            <input {...getInputProps()} />
            {selectedFile ? (
              <div className="flex flex-col items-center gap-2">
                <FileText className="w-12 h-12 text-green-500" />
                <p className="font-medium">{selectedFile.name}</p>
                <p className="text-sm text-muted-foreground">
                  {(selectedFile.size / 1024).toFixed(1)} KB
                </p>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2">
                <Upload className="w-12 h-12 text-muted-foreground" />
                <p className="font-medium">
                  {isDragActive
                    ? t('clientImport.dropHere', 'Engedd el a fájlt itt')
                    : t('clientImport.dragOrClick', 'Húzd ide a CSV fájlt vagy kattints a kiválasztáshoz')}
                </p>
                <p className="text-sm text-muted-foreground">
                  {t('clientImport.maxSize', 'Maximum 5MB, .csv vagy .txt fájl')}
                </p>
              </div>
            )}
          </div>

          {selectedFile && (
            <div className="flex justify-center gap-4 mt-4">
              <Button
                variant="outline"
                onClick={() => {
                  setSelectedFile(null)
                  setImportResult(null)
                }}
              >
                {t('common.cancel', 'Mégse')}
              </Button>
              <Button
                onClick={handleImport}
                disabled={importMutation.isPending}
              >
                {importMutation.isPending ? t('common.loading', 'Betöltés...') : t('clientImport.import', 'Importálás')}
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Loading state */}
      {importMutation.isPending && (
        <Card>
          <CardContent className="py-6">
            <div className="space-y-3">
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-4 w-1/2" />
            </div>
          </CardContent>
        </Card>
      )}

      {/* Results */}
      {importResult && (
        <>
          {/* Summary */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <Card>
              <CardContent className="pt-6">
                <div className="text-2xl font-bold">{importResult.summary.total}</div>
                <p className="text-sm text-muted-foreground">{t('clientImport.totalRows', 'Összes sor')}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="text-2xl font-bold text-green-600">{importResult.summary.created}</div>
                <p className="text-sm text-muted-foreground">{t('clientImport.created', 'Létrehozva')}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="text-2xl font-bold text-blue-600">{importResult.summary.updated}</div>
                <p className="text-sm text-muted-foreground">{t('clientImport.updated', 'Frissítve')}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="text-2xl font-bold text-red-600">{importResult.summary.errors}</div>
                <p className="text-sm text-muted-foreground">{t('clientImport.errors', 'Hibák')}</p>
              </CardContent>
            </Card>
          </div>

          {/* Errors */}
          {importResult.errors.length > 0 && (
            <Card className="border-destructive bg-destructive/10">
              <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-destructive">
                  <AlertCircle className="h-5 w-5" />
                  {t('clientImport.errorsTitle', 'Hibák az importálás során')}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="space-y-1">
                  {importResult.errors.map((error, index) => (
                    <li key={index} className="text-sm">
                      <span className="font-medium">{t('clientImport.row', 'Sor')} {error.row}:</span> {error.error}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}

          {/* Imported clients table */}
          {importResult.imported.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t('clientImport.importedClients', 'Importált vendégek')}</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="rounded-md border overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t('clientImport.status', 'Státusz')}</TableHead>
                        <TableHead>{t('clientImport.name', 'Név')}</TableHead>
                        <TableHead>{t('clientImport.email', 'Email')}</TableHead>
                        <TableHead>{t('clientImport.phone', 'Telefon')}</TableHead>
                        <TableHead>{t('clientImport.service', 'Szolgáltatás')}</TableHead>
                        <TableHead className="text-right">{t('clientImport.trainerFee', 'Edzői díj')}</TableHead>
                        <TableHead className="text-right">{t('clientImport.entryFee', 'Belépő díj')}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {importResult.imported.map((client, index) => (
                        <TableRow key={index}>
                          <TableCell>
                            {client.created ? (
                              <Badge variant="default" className="bg-green-500">
                                <CheckCircle className="w-3 h-3 mr-1" />
                                {t('clientImport.new', 'Új')}
                              </Badge>
                            ) : (
                              <Badge variant="secondary">
                                <XCircle className="w-3 h-3 mr-1" />
                                {t('clientImport.existing', 'Frissítve')}
                              </Badge>
                            )}
                          </TableCell>
                          <TableCell className="font-medium">{client.name}</TableCell>
                          <TableCell className="text-muted-foreground">{client.email}</TableCell>
                          <TableCell className="text-muted-foreground">{client.phone}</TableCell>
                          <TableCell>{client.service_type || '-'}</TableCell>
                          <TableCell className="text-right">{client.trainer_fee.toLocaleString()} Ft</TableCell>
                          <TableCell className="text-right">{client.entry_fee.toLocaleString()} Ft</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  )
}
