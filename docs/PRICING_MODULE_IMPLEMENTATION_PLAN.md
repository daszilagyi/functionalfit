# Pricing Module Implementation Plan

## Összefoglaló

Ez a dokumentum a `pricing_module_spec.md` alapján készült implementációs tervet tartalmazza, amely a szolgáltatástípus-alapú árazási modult vezeti be a FunctionalFit rendszerbe.

---

## 1. A két megközelítés összehasonlítása

### Jelenlegi rendszer (már implementálva)

A jelenlegi rendszer **class template alapú** árazás:

| Tábla | Cél | Kapcsolatok |
|-------|-----|-------------|
| `class_pricing_defaults` | Alapértelmezett árak csoportos órákhoz | `class_template_id` (FK) |
| `client_class_pricing` | Vendég-specifikus árak | `client_id` + `class_template_id` VAGY `class_occurrence_id` |
| `events.pricing_id` | Event-hez rendelt árazás | FK -> `class_pricing_defaults` |

**Árlogika prioritás:**
1. Client + occurrence specific
2. Client + template general
3. Template default
4. MissingPricingException

### Új specifikáció (pricing_module_spec.md)

Az új specifikáció **szolgáltatás típus alapú** árazást kér:

| Tábla | Cél | Kapcsolatok |
|-------|-----|-------------|
| `service_types` | Szolgáltatás típusok (GYOGYTORNA, PT, MASSZAZS) | Önálló entitás |
| `client_price_codes` | Vendég árkódok szolgáltatás típusonként | `client_id` + `service_type_id` |
| `events.service_type_id` | Event szolgáltatás típusa | FK -> `service_types` |

**Árlogika:**
- Email alapján azonosítás
- Szolgáltatás típus alapján árkód feloldás
- Regisztrációkor automatikus árkód generálás

---

## 2. Javaslat: Hibrid megközelítés (B opció bővítve)

**JAVASOLT:** A meglévő rendszer bővítése szolgáltatás típus támogatással

### Előnyök:
1. **Visszafelé kompatibilitás** - A meglévő `ClassPricingDefault` és `ClientClassPricing` táblák és frontend UI-k működőképes maradnak
2. **Fokozatos migráció** - Az új funkciók hozzáadhatók anélkül, hogy a meglévő rendszert megbontanák
3. **Rugalmasság** - Mind a class template, mind a service type alapú árazás használható lesz
4. **Kisebb kockázat** - Nem kell az egész pricing rendszert újraírni

### Architektúra

```
                    +-------------------+
                    |   service_types   |
                    +-------------------+
                           |
                           | 1:N
                           v
+------------------------+     +------------------------+
| class_pricing_defaults |     |  client_price_codes    |
| (class_template_id)    |     | (service_type_id +     |
| (service_type_id) NEW  |     |  client_id)            |
+------------------------+     +------------------------+
           |                              |
           | FK                           | FK
           v                              v
    +------------+                  +------------+
    |   events   |                  |  clients   |
    | pricing_id |                  |  user_id   |
    | service_   |                  +------------+
    | type_id    |                        |
    | NEW        |                        | FK
    +------------+                        v
                                    +------------+
                                    |   users    |
                                    |   email    |
                                    +------------+
```

---

## 3. Részletes implementációs lépések

### Phase 1: Adatbázis migrációk

#### Migration 1: `create_service_types_table`
```php
Schema::create('service_types', function (Blueprint $table) {
    $table->id();
    $table->string('code', 64)->unique();
    $table->string('name', 255);
    $table->text('description')->nullable();
    $table->unsignedInteger('default_entry_fee_brutto')->default(0);
    $table->unsignedInteger('default_trainer_fee_brutto')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index('code');
    $table->index('is_active');
});
```

#### Migration 2: `create_client_price_codes_table`
```php
Schema::create('client_price_codes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
    $table->string('client_email', 255); // Redundáns a gyors lookup-hoz
    $table->foreignId('service_type_id')->constrained('service_types')->onDelete('restrict');
    $table->string('price_code', 64)->nullable();
    $table->unsignedInteger('entry_fee_brutto');
    $table->unsignedInteger('trainer_fee_brutto');
    $table->string('currency', 3)->default('HUF');
    $table->timestamp('valid_from');
    $table->timestamp('valid_until')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
    $table->timestamps();

    $table->index(['client_email', 'service_type_id', 'is_active'], 'idx_client_price_codes_lookup');
    $table->index(['client_id', 'service_type_id'], 'idx_client_price_codes_client');
    $table->index(['valid_from', 'valid_until'], 'idx_client_price_codes_validity');
});
```

#### Migration 3: `add_service_type_id_to_events_table`
```php
Schema::table('events', function (Blueprint $table) {
    $table->foreignId('service_type_id')
        ->nullable()
        ->after('pricing_id')
        ->constrained('service_types')
        ->nullOnDelete();

    $table->index('service_type_id', 'idx_events_service_type');
});
```

#### Migration 4 (opcionális): `add_service_type_id_to_class_pricing_defaults_table`
```php
Schema::table('class_pricing_defaults', function (Blueprint $table) {
    $table->foreignId('service_type_id')
        ->nullable()
        ->after('class_template_id')
        ->constrained('service_types')
        ->nullOnDelete();
});
```

---

### Phase 2: Backend modellek

#### Új: `App\Models\ServiceType`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'default_entry_fee_brutto',
        'default_trainer_fee_brutto',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_entry_fee_brutto' => 'integer',
            'default_trainer_fee_brutto' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function clientPriceCodes(): HasMany
    {
        return $this->hasMany(ClientPriceCode::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

#### Új: `App\Models\ClientPriceCode`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPriceCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'client_email',
        'service_type_id',
        'price_code',
        'entry_fee_brutto',
        'trainer_fee_brutto',
        'currency',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_fee_brutto' => 'integer',
            'trainer_fee_brutto' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidAt($query, Carbon $atTime)
    {
        return $query->where('valid_from', '<=', $atTime)
            ->where(function ($q) use ($atTime) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $atTime);
            });
    }

    public function scopeForClientAndServiceType($query, int $clientId, int $serviceTypeId)
    {
        return $query->where('client_id', $clientId)
            ->where('service_type_id', $serviceTypeId);
    }
}
```

#### Meglévő modellek bővítése

**Event model** (`backend/app/Models/Event.php`):
```php
// Hozzáadni a $fillable tömbhöz:
'service_type_id',

// Új reláció:
public function serviceType(): BelongsTo
{
    return $this->belongsTo(ServiceType::class);
}
```

**Client model** (`backend/app/Models/Client.php`):
```php
// Új reláció:
public function priceCodes(): HasMany
{
    return $this->hasMany(ClientPriceCode::class);
}
```

---

### Phase 3: Backend szolgáltatások

#### Új: `App\Services\PriceCodeService`
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MissingPricingException;
use App\Models\Client;
use App\Models\ClientPriceCode;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PriceCodeService
{
    /**
     * Resolve pricing by client email and service type code.
     * Used by staff UI when creating events.
     */
    public function resolveByEmailAndServiceType(
        string $clientEmail,
        string $serviceTypeCode
    ): array {
        // 1. Find active service type by code
        $serviceType = ServiceType::where('code', $serviceTypeCode)
            ->active()
            ->first();

        if (!$serviceType) {
            throw new MissingPricingException("Service type not found: {$serviceTypeCode}");
        }

        // 2. Find client by email (via users table)
        $user = User::where('email', $clientEmail)->first();
        if (!$user) {
            // Return service type defaults if no user found
            return $this->formatResponse($serviceType, 'service_type_default');
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            return $this->formatResponse($serviceType, 'service_type_default');
        }

        // 3. Query client_price_codes for active, valid price
        $priceCode = ClientPriceCode::where('client_id', $client->id)
            ->where('service_type_id', $serviceType->id)
            ->active()
            ->validAt(Carbon::now())
            ->orderBy('valid_from', 'desc')
            ->first();

        if ($priceCode) {
            return [
                'entry_fee_brutto' => $priceCode->entry_fee_brutto,
                'trainer_fee_brutto' => $priceCode->trainer_fee_brutto,
                'currency' => $priceCode->currency,
                'source' => 'client_price_code',
                'price_code' => $priceCode->price_code,
            ];
        }

        // 4. Fallback to service type defaults
        return $this->formatResponse($serviceType, 'service_type_default');
    }

    /**
     * Generate default price codes for a client on all active service types.
     * Called during client registration.
     */
    public function generateDefaultPriceCodes(Client $client, ?int $createdBy = null): void
    {
        // Get client email from user
        $email = $client->user?->email ?? '';

        if (empty($email)) {
            return; // Cannot create price codes without email
        }

        $activeServiceTypes = ServiceType::active()->get();

        foreach ($activeServiceTypes as $serviceType) {
            ClientPriceCode::create([
                'client_id' => $client->id,
                'client_email' => $email,
                'service_type_id' => $serviceType->id,
                'entry_fee_brutto' => $serviceType->default_entry_fee_brutto,
                'trainer_fee_brutto' => $serviceType->default_trainer_fee_brutto,
                'currency' => 'HUF',
                'valid_from' => Carbon::now(),
                'is_active' => true,
                'created_by' => $createdBy,
            ]);
        }
    }

    private function formatResponse(ServiceType $serviceType, string $source): array
    {
        return [
            'entry_fee_brutto' => $serviceType->default_entry_fee_brutto,
            'trainer_fee_brutto' => $serviceType->default_trainer_fee_brutto,
            'currency' => 'HUF',
            'source' => $source,
        ];
    }
}
```

#### Regisztrációkor automatikus árkód generálás

**AuthController::registerQuick()** bővítése:
```php
// Importok hozzáadása:
use App\Services\PriceCodeService;

// A metódusban, a client létrehozása után:
public function registerQuick(RegisterQuickRequest $request): JsonResponse
{
    DB::beginTransaction();
    try {
        // ... existing user + client creation ...

        // NEW: Generate default price codes
        app(PriceCodeService::class)->generateDefaultPriceCodes(
            $client,
            auth()->id()
        );

        DB::commit();
        // ...
    } catch (\Exception $e) {
        DB::rollBack();
        // ...
    }
}
```

---

### Phase 4: Backend API-k

#### API útvonalak (`routes/api.php`)

```php
// Service Types (admin only)
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('service-types', ServiceTypeController::class);
    Route::patch('service-types/{serviceType}/toggle-active', [ServiceTypeController::class, 'toggleActive']);

    // Client Price Codes
    Route::get('clients/{client}/price-codes', [ClientPriceCodeController::class, 'index']);
    Route::post('clients/{client}/price-codes', [ClientPriceCodeController::class, 'store']);
    Route::patch('client-price-codes/{clientPriceCode}', [ClientPriceCodeController::class, 'update']);
    Route::delete('client-price-codes/{clientPriceCode}', [ClientPriceCodeController::class, 'destroy']);
});

// Pricing resolve (staff + admin)
Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    Route::get('pricing/resolve', [PricingController::class, 'resolve']);
});
```

#### Új: `ServiceTypeController`
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Requests\UpdateServiceTypeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;

class ServiceTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $serviceTypes = ServiceType::orderBy('name')->get();
        return ApiResponse::success($serviceTypes);
    }

    public function store(StoreServiceTypeRequest $request): JsonResponse
    {
        $serviceType = ServiceType::create($request->validated());
        return ApiResponse::created($serviceType);
    }

    public function show(ServiceType $serviceType): JsonResponse
    {
        return ApiResponse::success($serviceType);
    }

    public function update(UpdateServiceTypeRequest $request, ServiceType $serviceType): JsonResponse
    {
        $serviceType->update($request->validated());
        return ApiResponse::success($serviceType);
    }

    public function destroy(ServiceType $serviceType): JsonResponse
    {
        // Check for existing references before delete
        if ($serviceType->clientPriceCodes()->exists() || $serviceType->events()->exists()) {
            return ApiResponse::error('Cannot delete service type with existing references', 409);
        }

        $serviceType->delete();
        return ApiResponse::success(null, 'Service type deleted');
    }

    public function toggleActive(ServiceType $serviceType): JsonResponse
    {
        $serviceType->update(['is_active' => !$serviceType->is_active]);
        return ApiResponse::success($serviceType);
    }
}
```

#### Új: `ClientPriceCodeController`
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientPriceCodeRequest;
use App\Http\Requests\UpdateClientPriceCodeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Client;
use App\Models\ClientPriceCode;
use Illuminate\Http\JsonResponse;

class ClientPriceCodeController extends Controller
{
    public function index(Client $client): JsonResponse
    {
        $priceCodes = $client->priceCodes()
            ->with('serviceType')
            ->orderBy('service_type_id')
            ->get();

        return ApiResponse::success($priceCodes);
    }

    public function store(StoreClientPriceCodeRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();
        $data['client_id'] = $client->id;
        $data['client_email'] = $client->user?->email ?? '';
        $data['created_by'] = auth()->id();

        $priceCode = ClientPriceCode::create($data);
        $priceCode->load('serviceType');

        return ApiResponse::created($priceCode);
    }

    public function update(UpdateClientPriceCodeRequest $request, ClientPriceCode $clientPriceCode): JsonResponse
    {
        $clientPriceCode->update($request->validated());
        $clientPriceCode->load('serviceType');

        return ApiResponse::success($clientPriceCode);
    }

    public function destroy(ClientPriceCode $clientPriceCode): JsonResponse
    {
        $clientPriceCode->delete();
        return ApiResponse::success(null, 'Price code deleted');
    }
}
```

#### Pricing resolve endpoint bővítése (`PricingController`)
```php
public function resolve(Request $request): JsonResponse
{
    $request->validate([
        'client_email' => 'required|email',
        'service_type_code' => 'required|string',
    ]);

    try {
        $pricing = app(PriceCodeService::class)->resolveByEmailAndServiceType(
            $request->input('client_email'),
            $request->input('service_type_code')
        );

        return ApiResponse::success($pricing);
    } catch (MissingPricingException $e) {
        return ApiResponse::error($e->getMessage(), 404);
    }
}
```

---

### Phase 5: Frontend implementáció

#### TypeScript típusok (`frontend/src/types/serviceType.ts`)
```typescript
export interface ServiceType {
  id: number;
  code: string;
  name: string;
  description: string | null;
  default_entry_fee_brutto: number;
  default_trainer_fee_brutto: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface ClientPriceCode {
  id: number;
  client_id: number;
  client_email: string;
  service_type_id: number;
  service_type?: ServiceType;
  price_code: string | null;
  entry_fee_brutto: number;
  trainer_fee_brutto: number;
  currency: string;
  valid_from: string;
  valid_until: string | null;
  is_active: boolean;
  created_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface PricingResolveResponse {
  entry_fee_brutto: number;
  trainer_fee_brutto: number;
  currency: string;
  source: 'client_price_code' | 'service_type_default';
  price_code?: string;
}
```

#### API kliens bővítése (`frontend/src/api/client.ts`)
```typescript
// Service Types
export const getServiceTypes = () => api.get<ServiceType[]>('/admin/service-types');
export const createServiceType = (data: Partial<ServiceType>) =>
  api.post<ServiceType>('/admin/service-types', data);
export const updateServiceType = (id: number, data: Partial<ServiceType>) =>
  api.patch<ServiceType>(`/admin/service-types/${id}`, data);
export const deleteServiceType = (id: number) =>
  api.delete(`/admin/service-types/${id}`);
export const toggleServiceTypeActive = (id: number) =>
  api.patch<ServiceType>(`/admin/service-types/${id}/toggle-active`);

// Client Price Codes
export const getClientPriceCodes = (clientId: number) =>
  api.get<ClientPriceCode[]>(`/admin/clients/${clientId}/price-codes`);
export const createClientPriceCode = (clientId: number, data: Partial<ClientPriceCode>) =>
  api.post<ClientPriceCode>(`/admin/clients/${clientId}/price-codes`, data);
export const updateClientPriceCode = (id: number, data: Partial<ClientPriceCode>) =>
  api.patch<ClientPriceCode>(`/admin/client-price-codes/${id}`, data);
export const deleteClientPriceCode = (id: number) =>
  api.delete(`/admin/client-price-codes/${id}`);

// Pricing Resolve
export const resolvePricing = (clientEmail: string, serviceTypeCode: string) =>
  api.get<PricingResolveResponse>('/pricing/resolve', {
    params: { client_email: clientEmail, service_type_code: serviceTypeCode }
  });
```

#### Új oldalak

1. **ServiceTypesPage** (`/admin/service-types`)
   - CRUD táblázat a szolgáltatás típusokhoz
   - Aktív/inaktív toggle
   - Alapértelmezett árak beállítása

2. **ClientPriceCodesSection** - Vendég adatlapon belüli komponens
   - Táblázat a vendég árkódjaival szolgáltatás típusonként
   - Szerkesztés modal
   - Új árkód hozzáadása

#### EventFormModal bővítése
- Service type dropdown hozzáadása
- Vendég kiválasztásakor + service type kiválasztásakor automatikus ár kitöltés a `/pricing/resolve` API-ból
- "Hiányzó árkód" figyelmeztetés ha nincs találat

---

### Phase 6: Tesztek

#### Backend tesztek
- `PriceCodeServiceTest` - árkód feloldás logika
- `ServiceTypeControllerTest` - CRUD API
- `ClientPriceCodeControllerTest` - CRUD API
- `AuthController::registerQuick` bővítés teszt - árkód generálás

#### Frontend E2E tesztek
- Service type kezelés
- Client price code kezelés
- Event létrehozás automatikus árkitöltéssel

---

## 4. Migrációs stratégia és visszafelé kompatibilitás

### 4.1 Fokozatos bevezetés

1. **Phase 1-2:** Adatbázis + modellek - nem befolyásolja a meglévő funkcionalitást
2. **Phase 3-4:** Backend szolgáltatások + API-k - párhuzamosan működnek a meglévő rendszerrel
3. **Phase 5:** Frontend - új funkciók hozzáadása, meglévő UI-k érintetlenül maradnak
4. **Phase 6:** Tesztek

### 4.2 Meglévő funkciók biztosítása

- A `ClassPricingDefault` és `ClientClassPricing` táblák ÉS funkciók **változatlanul maradnak**
- A meglévő `PricingPage` és `SettlementsPage` frontend oldalak **működőképes maradnak**
- A `PricingService::resolvePrice()` metódus **változatlan marad** - a csoportos órák árazása továbbra is így működik

### 4.3 Email hozzáférés

**Fontos megjegyzés:** A `clients` tábla NEM tartalmaz `email` mezőt közvetlenül. Az email a `users` táblából érhető el a `clients.user_id` kapcsolaton keresztül.

A `client_price_codes.client_email` mező redundáns tárolást biztosít a gyors kereséshez, de a rendszernek figyelnie kell:
- Létrehozáskor: az email automatikusan a `users.email`-ből kerüljön
- Kereséskor: a `client_email` oszlopot használjuk az indexelt kereséshez

---

## 5. Kockázatok és megoldások

| Kockázat | Megoldás |
|----------|----------|
| Email nem egyedi a clients-ben | A `users` tábla tartalmazza az emailt, és ott egyedi. A `client_price_codes.client_email` indexelt redundáns mező. |
| Service type és class template keveredése | Mindkettő használható párhuzamosan - az events táblán mind `pricing_id` mind `service_type_id` van |
| Meglévő adatok migrációja | Nem szükséges - a meglévő rendszer továbbra is működik, az új funkciók opcionálisak |

---

## 6. Kritikus fájlok az implementációhoz

### Adatbázis migrációk
- `backend/database/migrations/2025_12_XX_000001_create_service_types_table.php`
- `backend/database/migrations/2025_12_XX_000002_create_client_price_codes_table.php`
- `backend/database/migrations/2025_12_XX_000003_add_service_type_id_to_events_table.php`

### Backend
- `backend/app/Models/ServiceType.php` (új)
- `backend/app/Models/ClientPriceCode.php` (új)
- `backend/app/Models/Event.php` (bővítés)
- `backend/app/Models/Client.php` (bővítés)
- `backend/app/Services/PriceCodeService.php` (új)
- `backend/app/Http/Controllers/Api/ServiceTypeController.php` (új)
- `backend/app/Http/Controllers/Api/ClientPriceCodeController.php` (új)
- `backend/app/Http/Controllers/Api/PricingController.php` (bővítés)
- `backend/routes/api.php` (bővítés)

### Frontend
- `frontend/src/types/serviceType.ts` (új)
- `frontend/src/api/client.ts` (bővítés)
- `frontend/src/pages/admin/ServiceTypesPage.tsx` (új)
- `frontend/src/components/clients/ClientPriceCodesSection.tsx` (új)
- `frontend/src/components/events/EventFormModal.tsx` (bővítés)

---

## 7. Implementációs sorrend összefoglaló

1. **Migrációk létrehozása és futtatása**
2. **Modellek létrehozása** (ServiceType, ClientPriceCode)
3. **Meglévő modellek bővítése** (Event, Client)
4. **PriceCodeService implementálása**
5. **Kontrollerek létrehozása** (ServiceTypeController, ClientPriceCodeController)
6. **PricingController bővítése** resolve endpointtal
7. **API útvonalak regisztrálása**
8. **FormRequest validációk létrehozása**
9. **Frontend TypeScript típusok**
10. **API kliens bővítése**
11. **ServiceTypesPage implementálása**
12. **ClientPriceCodesSection komponens**
13. **EventFormModal bővítése**
14. **Tesztek írása**
15. **i18n fordítások** (hu/en)
