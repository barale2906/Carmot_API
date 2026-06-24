---
name: backend-standards
description: This skill should be used whenever the user asks to create, add, modify, fix, refactor, or review backend code in this Laravel project (Carmot API) — for example "crea un endpoint", "agrega un campo a matricula", "ajusta el controlador de X", "crea un nuevo modulo", "agrega una validacion", "arregla este bug en el backend", "agrega una ruta", "actualiza el FormRequest", "crea el resource", "agrega un test", or any task touching files under app/Http, app/Models, app/Services, app/Traits, routes/*.php, database/migrations, or tests/. Enforces this project's existing architecture, Laravel/PHP coding standards, and the mandatory test-update-and-run workflow before any change is considered done.
---

# Backend Development Standards — Carmot API

## Stack

Laravel 10, PHP 8.1, Sanctum (auth), spatie/laravel-permission (authorization), spatie/laravel-translatable, dedoc/scramble (API docs generated from code), PHPUnit.

## Role

Act as a senior backend engineer who already knows this codebase. Match existing patterns exactly instead of introducing new ones — consistency with the surrounding module beats personal preference. Never invent a new convention when an equivalent one already exists in another module.

## Architecture to respect

The app is organized by business module (Academico, Financiero, Crm, Configuracion, Dashboard). For any change, find and mirror the closest existing sibling in the same module before writing anything new.

- **Controllers** (`app/Http/Controllers/Api/<Modulo>/`) stay thin: validate via FormRequest, delegate filtering/sorting to model scopes/traits, build the response with a Resource. Do not put business logic, raw validation, or ad-hoc query building directly in the controller.
- **FormRequests** (`app/Http/Requests/Api/<Modulo>/Store...Request.php` / `Update...Request.php`) own all validation: `rules()`, `messages()` (Spanish), `attributes()` (Spanish), and `withValidator()` for cross-field/DB checks (e.g. duplicate detection). `authorize()` returns `true` — authorization is handled by route middleware, not here.
- **Resources** (`app/Http/Resources/Api/<Modulo>/`) shape every API response. Never return raw models or ad-hoc arrays from the controller.
- **Routes** live in the per-module file (`routes/academico.php`, `financiero.php`, `crm.php`, `configuracion.php`, `dashboard.php`), grouped with `Route::prefix(...)->group(...)` for custom actions plus `Route::apiResource(...)` for CRUD. Custom GET routes with fixed segments (`filters`, `statistics`, `trashed`, etc.) must be registered above the `apiResource` line so `{id}` doesn't shadow them.
- **Authorization** uses `spatie/laravel-permission` via `$this->middleware('permission:xxx')->only([...])` in the controller constructor — one line per action group, reusing the permission names already defined for that module.
- **Status field**: never hardcode status options or validation rules. Use the `HasActiveStatus` + `HasActiveStatusValidation` traits in models, requests, and resources (see `app/Traits/README_StatusSystem.md` and `app/Traits/README_TraitUsage.md`). If a class needs extra states beyond Activo/Inactivo, override `getActiveStatusOptions()` locally, the same way `StoreMatriculaRequest` adds "Anulado".
- **Soft deletes**: follow the existing `destroy` (soft) / `restore` / `forceDelete` / `trashed` pattern for any model that supports it.
- **Query scopes**: reuse `HasFilterScopes`, `HasRelationScopes`, `HasSortingScopes` and module-specific filter traits on models instead of writing inline `where`/`with`/`orderBy` chains in controllers.
- **JSON response envelope**: always `{"data": ..., "message"?: ...}`; paginated `index`/`trashed` endpoints add `"meta": {current_page, last_page, per_page, total, from, to}`. Match the HTTP status codes already in use (201 on store, 200 by default, 404 for manual not-found responses).
- **PHPDoc**: every public controller/request method gets a short Spanish docblock. Only add a longer explanation when there's a non-obvious business rule (see `precargaEstudiante` in `MatriculaController` for the bar to meet) — don't restate what the code already says.

Full copy-paste skeletons for each layer are in `references/templates.md`.

## Code quality bar

- Strict typed signatures (`JsonResponse`, `int`, `void`, etc.) matching the surrounding code.
- No dead code, no speculative abstractions, no parameters or branches for cases that can't occur.
- Reuse existing traits/scopes/helpers before writing new ones; if a genuinely new cross-cutting helper is needed, model it after the existing `app/Traits` pattern (small, single-purpose, documented like `HasActiveStatus`).
- Validate only at the FormRequest boundary; trust validated data inside the controller/model.
- Run `vendor/bin/pint` on touched files before considering formatting done, if Pint is available in the environment.

## Mandatory checklist for any backend change

Treat a change as incomplete until every applicable item is done — not just the one file the user mentioned:

1. **Explore first**: read the closest sibling controller/request/resource/route/test in the same module to confirm current conventions before writing code.
2. **Model layer**: migration + fillable/casts + relations + scopes/traits as needed.
3. **FormRequest(s)**: add/update `Store...Request` and `Update...Request` together — they usually diverge only in required vs sometimes rules.
4. **Controller**: update only the methods actually affected; leave the rest untouched.
5. **Resource**: add/update fields so the response matches what the controller now returns.
6. **Routes**: register new endpoints in the correct module route file, custom GET routes above `apiResource`.
7. **PHPDoc**: add/update docblocks for every method touched.
8. **Tests** (mandatory, not optional):
   - Update existing Feature tests affected by the change.
   - Add new tests for new behavior: happy path, validation failures, permission denial, relevant edge cases (duplicates, soft-deleted records, not-found).
   - Follow the existing test setup pattern: `RefreshDatabase`, `PermissionRegistrar::forgetCachedPermissions()`, create the specific permission, `givePermissionTo`, `actingAs`.
9. **Run the full test suite** (`php artisan test` or `vendor/bin/phpunit`) — not just the new/changed test file. Fix any regression before reporting the task done. Never report success without having actually run and seen the suite pass.

## Workflow

1. Identify the module and locate its existing controller/request/resource/route/test files as the reference pattern.
2. Implement layer by layer per the checklist above, reusing existing traits/scopes.
3. Update or add the corresponding tests.
4. Run the full test suite; iterate until green.
5. Report concisely what changed and the test result — no need to restate the standards followed.

## Additional resources

- **`references/templates.md`** — copy-paste skeletons for Controller, FormRequest, Resource, route registration, and Feature test, derived from this project's `Matricula` module.
- **`app/Traits/README_StatusSystem.md`** and **`app/Traits/README_TraitUsage.md`** — authoritative reference for the status system traits; read before touching any `status` field.
