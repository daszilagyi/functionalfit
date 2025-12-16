import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Copy, Check } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/button'

interface Shortcode {
  code: string
  description: string
  category: string
  example?: string
}

const shortcodes: Shortcode[] = [
  // Felhasználó adatok
  {
    code: '{{user.name}}',
    description: 'Felhasználó teljes neve',
    category: 'Felhasználó',
    example: 'Kovács Anna'
  },
  {
    code: '{{user.email}}',
    description: 'Felhasználó email címe',
    category: 'Felhasználó',
    example: 'kovacs.anna@example.com'
  },

  // Óra adatok
  {
    code: '{{class.title}}',
    description: 'Óra sablon neve',
    category: 'Óra',
    example: 'Morning Yoga'
  },
  {
    code: '{{class.starts_at}}',
    description: 'Óra kezdési időpontja',
    category: 'Óra',
    example: '2024-12-01 09:00'
  },
  {
    code: '{{class.ends_at}}',
    description: 'Óra befejezési időpontja',
    category: 'Óra',
    example: '2024-12-01 10:00'
  },
  {
    code: '{{class.room}}',
    description: 'Terem neve',
    category: 'Óra',
    example: 'Studio A'
  },

  // Edző adatok
  {
    code: '{{trainer.name}}',
    description: 'Edző neve',
    category: 'Edző',
    example: 'Kiss Péter'
  },

  // Foglalás adatok
  {
    code: '{{status}}',
    description: 'Foglalás státusza',
    category: 'Foglalás',
    example: 'booked vagy waitlist'
  },
  {
    code: '{{cancel_url}}',
    description: 'Foglalás lemondási URL',
    category: 'Foglalás',
    example: 'https://functionalfit.hu/cancel/abc123'
  },
  {
    code: '{{confirm_url}}',
    description: 'Foglalás megerősítési URL',
    category: 'Foglalás',
    example: 'https://functionalfit.hu/confirm/abc123'
  },

  // Változások követése
  {
    code: '{{old.starts_at}}',
    description: 'Előző kezdési időpont (módosításnál)',
    category: 'Változások',
    example: '2024-12-01 08:00'
  },
  {
    code: '{{new.starts_at}}',
    description: 'Új kezdési időpont (módosításnál)',
    category: 'Változások',
    example: '2024-12-01 09:00'
  },
  {
    code: '{{deleted_by}}',
    description: 'Törlést végző személy neve',
    category: 'Változások',
    example: 'Admin User'
  },
  {
    code: '{{modified_by}}',
    description: 'Módosítást végző személy neve',
    category: 'Változások',
    example: 'Admin User'
  },

  // Jelszó visszaállítás
  {
    code: '{{password_reset_url}}',
    description: 'Jelszó visszaállítási URL',
    category: 'Jelszó',
    example: 'https://functionalfit.hu/reset/abc123'
  },

  // Cég adatok (automatikusan kitöltődnek)
  {
    code: '{{company_name}}',
    description: 'Cég neve (automatikus)',
    category: 'Rendszer',
    example: 'FunctionalFit Egészségközpont'
  },
  {
    code: '{{support_email}}',
    description: 'Támogatási email cím (automatikus)',
    category: 'Rendszer',
    example: 'support@functionalfit.hu'
  },
  {
    code: '{{current_year}}',
    description: 'Aktuális év (automatikus)',
    category: 'Rendszer',
    example: '2024'
  }
]

const categories = Array.from(new Set(shortcodes.map(s => s.category)))

export default function EmailShortcodesPage() {
  const [copiedCode, setCopiedCode] = useState<string | null>(null)

  const copyToClipboard = async (code: string) => {
    try {
      await navigator.clipboard.writeText(code)
      setCopiedCode(code)
      setTimeout(() => setCopiedCode(null), 2000)
    } catch (err) {
      console.error('Failed to copy:', err)
    }
  }

  return (
    <div className="container mx-auto py-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Email Shortcode-ok</h1>
        <p className="text-muted-foreground mt-2">
          Elérhető változók az email sablonokban való használatra
        </p>
      </div>

      <Card className="bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800">
        <CardHeader>
          <CardTitle className="text-blue-900 dark:text-blue-100">
            Használati útmutató
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-blue-800 dark:text-blue-200">
          <p>
            <strong>Shortcode-ok használata:</strong> Másolja be az alábbi kódokat az email
            sablonok tárgy vagy tartalom mezőjébe. A rendszer automatikusan helyettesíti őket
            a megfelelő értékekkel.
          </p>
          <p>
            <strong>Példa:</strong> <code className="bg-blue-100 dark:bg-blue-900 px-1 py-0.5 rounded">
              Kedves {'{{'}{'}'}user.name{'}}'}!
            </code> → <code className="bg-blue-100 dark:bg-blue-900 px-1 py-0.5 rounded">
              Kedves Kovács Anna!
            </code>
          </p>
          <p>
            <strong>Automatikus változók:</strong> A "Rendszer" kategóriájú változók
            automatikusan kitöltődnek minden emailben, nem kell külön megadni őket.
          </p>
        </CardContent>
      </Card>

      {categories.map(category => {
        const categoryShortcodes = shortcodes.filter(s => s.category === category)

        return (
          <Card key={category}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                {category}
                <Badge variant="secondary">{categoryShortcodes.length}</Badge>
              </CardTitle>
              <CardDescription>
                {category === 'Rendszer' && 'Ezek a változók automatikusan kitöltődnek minden emailben'}
                {category === 'Felhasználó' && 'A címzett felhasználó adatai'}
                {category === 'Óra' && 'A foglalt óra részletei'}
                {category === 'Edző' && 'Az órát tartó edző adatai'}
                {category === 'Foglalás' && 'A foglalással kapcsolatos információk'}
                {category === 'Változások' && 'Módosítások és törlések követése'}
                {category === 'Jelszó' && 'Jelszó visszaállítással kapcsolatos információk'}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {categoryShortcodes.map(shortcode => (
                  <div
                    key={shortcode.code}
                    className="flex items-start justify-between p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                  >
                    <div className="flex-1 space-y-1">
                      <div className="flex items-center gap-2">
                        <code className="text-sm font-mono bg-muted px-2 py-1 rounded">
                          {shortcode.code}
                        </code>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => copyToClipboard(shortcode.code)}
                          className="h-6 w-6 p-0"
                        >
                          {copiedCode === shortcode.code ? (
                            <Check className="h-3 w-3 text-green-600" />
                          ) : (
                            <Copy className="h-3 w-3" />
                          )}
                        </Button>
                      </div>
                      <p className="text-sm text-muted-foreground">
                        {shortcode.description}
                      </p>
                      {shortcode.example && (
                        <p className="text-xs text-muted-foreground">
                          <span className="font-semibold">Példa:</span>{' '}
                          <code className="bg-muted px-1 py-0.5 rounded">
                            {shortcode.example}
                          </code>
                        </p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}
