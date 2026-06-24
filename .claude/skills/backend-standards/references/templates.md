# Backend templates — Carmot API

Generic skeletons distilled from the `Academico/Matricula` module. Replace `Recurso`/`recurso`/`Modulo` placeholders and drop fields/methods that don't apply. Always check the actual closest sibling file first — these templates show shape and conventions, not an exhaustive field list.

## Controller

`app/Http/Controllers/Api/<Modulo>/RecursoController.php`

```php
<?php

namespace App\Http\Controllers\Api\Modulo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Modulo\StoreRecursoRequest;
use App\Http\Requests\Api\Modulo\UpdateRecursoRequest;
use App\Http\Resources\Api\Modulo\RecursoResource;
use App\Models\Modulo\Recurso;
use App\Traits\HasActiveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecursoController extends Controller
{
    use HasActiveStatus;

    /** Campos permitidos en store/update (excluye los gestionados automáticamente). */
    private const FILLABLE_FIELDS = [
        'campo_uno', 'campo_dos', 'status',
    ];

    public function __construct()
    {
        $this->middleware('permission:modulo_recursos')->only(['index', 'show', 'filters']);
        $this->middleware('permission:modulo_recursoCrear')->only(['store']);
        $this->middleware('permission:modulo_recursoEditar')->only(['update']);
        $this->middleware('permission:modulo_recursoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de recursos con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'include_trashed', 'only_trashed']);

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : [];

        $recursos = Recurso::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => RecursoResource::collection($recursos),
            'meta' => [
                'current_page' => $recursos->currentPage(),
                'last_page'    => $recursos->lastPage(),
                'per_page'     => $recursos->perPage(),
                'total'        => $recursos->total(),
                'from'         => $recursos->firstItem(),
                'to'           => $recursos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo recurso.
     */
    public function store(StoreRecursoRequest $request): JsonResponse
    {
        $data = $request->only(self::FILLABLE_FIELDS);
        $data['status'] = $data['status'] ?? 1;

        $recurso = Recurso::create($data);

        return response()->json([
            'message' => 'Recurso creado exitosamente.',
            'data'    => new RecursoResource($recurso),
        ], 201);
    }

    /**
     * Muestra el recurso especificado.
     */
    public function show(Request $request, Recurso $recurso): JsonResponse
    {
        return response()->json([
            'data' => new RecursoResource($recurso),
        ]);
    }

    /**
     * Actualiza el recurso especificado.
     */
    public function update(UpdateRecursoRequest $request, Recurso $recurso): JsonResponse
    {
        $recurso->update($request->only(self::FILLABLE_FIELDS));

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data'    => new RecursoResource($recurso),
        ]);
    }

    /**
     * Elimina el recurso (soft delete).
     */
    public function destroy(Recurso $recurso): JsonResponse
    {
        $recurso->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un recurso eliminado.
     */
    public function restore(int $id): JsonResponse
    {
        $recurso = Recurso::onlyTrashed()->findOrFail($id);
        $recurso->restore();

        return response()->json([
            'message' => 'Recurso restaurado exitosamente.',
            'data'    => new RecursoResource($recurso),
        ]);
    }

    /**
     * Elimina permanentemente un recurso.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $recurso = Recurso::onlyTrashed()->findOrFail($id);
        $recurso->forceDelete();

        return response()->json([
            'message' => 'Recurso eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene los catálogos de filtros y opciones de enumerados para el frontend.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
            ],
        ]);
    }
}
```

## FormRequest (Store)

`app/Http/Requests/Api/<Modulo>/StoreRecursoRequest.php`

```php
<?php

namespace App\Http\Requests\Api\Modulo;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecursoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campo_uno' => 'required|string|max:255',
            'campo_dos' => 'nullable|integer|exists:otra_tabla,id',
            'status'    => self::getStatusValidationRule(),
        ];
    }

    public function messages(): array
    {
        return array_merge([
            'campo_uno.required' => 'El campo uno es obligatorio.',
            'campo_dos.exists'   => 'El campo dos seleccionado no existe.',
        ], self::getStatusValidationMessages());
    }

    public function attributes(): array
    {
        return [
            'campo_uno' => 'campo uno',
            'campo_dos' => 'campo dos',
        ];
    }
}
```

`UpdateRecursoRequest` mirrors `StoreRecursoRequest` but switches required rules to `sometimes`/`nullable` as appropriate — keep both requests in sync when a field changes on one.

Use `withValidator()` only when a rule needs a DB lookup beyond `exists`/`unique` (e.g. "no duplicate active record for this combination of fields"), following the pattern in `StoreMatriculaRequest::withValidator()`.

## Resource

`app/Http/Resources/Api/<Modulo>/RecursoResource.php`

```php
<?php

namespace App\Http\Resources\Api\Modulo;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecursoResource extends JsonResource
{
    use HasActiveStatus;

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'campo_uno'   => $this->campo_uno,
            'status'      => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
```

## Routes

In `routes/<modulo>.php`, inside the existing authenticated group:

```php
use App\Http\Controllers\Api\Modulo\RecursoController;

Route::prefix('recursos')->group(function () {
    Route::get('trashed', [RecursoController::class, 'trashed'])->name('recursos.trashed');
    Route::get('filters', [RecursoController::class, 'filters'])->name('recursos.filters');
    Route::post('{id}/restore', [RecursoController::class, 'restore'])->name('recursos.restore');
    Route::delete('{id}/force-delete', [RecursoController::class, 'forceDelete'])->name('recursos.force-delete');
});
Route::apiResource('recursos', RecursoController::class);
```

Fixed-segment GET routes (`trashed`, `filters`, `statistics`, etc.) must be declared **before** `apiResource`, otherwise `{recurso}` swallows them.

## Feature test

`tests/Feature/Api/<Modulo>/RecursoTest.php`

```php
<?php

namespace Tests\Feature\Api\Modulo;

use App\Models\Modulo\Recurso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RecursoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'modulo_recursos', 'descripcion' => 'ver recursos']);
        Permission::create(['name' => 'modulo_recursoCrear', 'descripcion' => 'crear recursos']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['modulo_recursos', 'modulo_recursoCrear']);
    }

    /** @test */
    public function crea_un_recurso_correctamente(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recursos.store'), [
                'campo_uno' => 'valor',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.campo_uno', 'valor');

        $this->assertDatabaseHas('recursos', ['campo_uno' => 'valor']);
    }

    /** @test */
    public function rechaza_la_creacion_sin_permiso(): void
    {
        $usuarioSinPermiso = User::factory()->create();

        $response = $this->actingAs($usuarioSinPermiso)
            ->postJson(route('recursos.store'), ['campo_uno' => 'valor']);

        $response->assertForbidden();
    }

    /** @test */
    public function valida_los_campos_requeridos(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recursos.store'), []);

        $response->assertJsonValidationErrors(['campo_uno']);
    }
}
```

Cover, at minimum: happy path, missing-permission (403), validation failures (422), and any duplicate/edge case the FormRequest's `withValidator()` enforces.
