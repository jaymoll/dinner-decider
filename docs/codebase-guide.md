# Dinner Decider codebase guide

This guide explains where the main parts of Dinner Decider live, what each part is responsible for, and how they work together. It is intended as an onboarding and day-to-day navigation reference.

For architectural decisions, domain invariants, and the implementation roadmap, see [architecture.md](architecture.md). For environment and deployment procedures, see [operations-runbook.md](operations-runbook.md).

## The codebase in one picture

Dinner Decider is a Laravel-first modular monolith. A typical request moves through the application like this:

```text
Browser
  -> route
  -> Livewire page
  -> form validation and policy authorization
  -> action (write) or query (read)
  -> calculation service or value object
  -> Eloquent models
  -> MySQL
```

The important boundary is between delivery code and business code:

- Livewire pages translate browser events into application calls and render results.
- Actions are the entry points for state-changing use cases.
- Queries assemble read models for screens with non-trivial data needs.
- Services and value objects contain reusable calculations and domain rules.
- Models represent persisted records, relationships, casts, and reusable scopes.

Dinner Decider is organized by technical role at the top level and by product feature inside each role. For example, dinner-planning mutations are under `app/Actions/DinnerPlans`, while their calculation helpers are under `app/Services/DinnerPlans`.

## Product areas

| Product area | Responsibility | Representative code |
| --- | --- | --- |
| Identity and access | Registration, login, password reset, email verification, two-factor authentication, passkeys, and account settings | [`app/Actions/Fortify`](../app/Actions/Fortify), [`app/Providers/FortifyServiceProvider.php`](../app/Providers/FortifyServiceProvider.php), [`resources/views/pages/auth`](../resources/views/pages/auth) |
| Ingredients and measurements | User-owned ingredient catalogue, aliases, packages, unit compatibility, exact decimal parsing, normalization, and display | [`app/Models/Ingredient.php`](../app/Models/Ingredient.php), [`app/Services/Measurements`](../app/Services/Measurements), [`app/ValueObjects/Quantity.php`](../app/ValueObjects/Quantity.php) |
| Recipes | Recipe metadata, ingredient lines, ordered steps, categories, tags, images, archives, and serving scaling | [`app/Actions/Recipes`](../app/Actions/Recipes), [`app/Models/Recipe.php`](../app/Models/Recipe.php), [`app/Services/Recipes/RecipeScaler.php`](../app/Services/Recipes/RecipeScaler.php) |
| Pantry | Stock entry creation and editing, merge behavior, availability, and reservation-aware balances | [`app/Actions/Pantry`](../app/Actions/Pantry), [`app/Models/PantryEntry.php`](../app/Models/PantryEntry.php), [`app/Queries/AvailablePantry.php`](../app/Queries/AvailablePantry.php) |
| Recommendations | Read-only, deterministic recipe scoring against currently available pantry stock | [`app/Queries/GetPantryAwareRecommendations.php`](../app/Queries/GetPantryAwareRecommendations.php), [`app/Services/Recommendations/RecommendationEngine.php`](../app/Services/Recommendations/RecommendationEngine.php) |
| Dinner plans and reservations | Rolling plan, recipe snapshots, serving/date/order changes, stock allocation, cancellation, restoration, and cooking | [`app/Actions/DinnerPlans`](../app/Actions/DinnerPlans), [`app/Models/PlannedDinner.php`](../app/Models/PlannedDinner.php), [`app/Services/DinnerPlans`](../app/Services/DinnerPlans) |
| Groceries | Generated shortfalls, manual checklist items, source contributions, checked state, and regeneration | [`app/Actions/Groceries`](../app/Actions/Groceries), [`app/Models/GroceryItem.php`](../app/Models/GroceryItem.php), [`app/Services/Groceries/GroceryCalculator.php`](../app/Services/Groceries/GroceryCalculator.php) |

The dependency direction generally follows:

```text
Ingredients and measurements
  -> recipes
  -> pantry and recommendations
  -> dinner plans and reservations
  -> groceries
```

Authentication and authorization apply across all of these areas. Recommendations read pantry and recipe state but do not mutate either. Grocery generation consumes dinner-plan facts but does not decide reservation priority.

## Application code

### `app/Actions`: state-changing use cases

An action is the main entry point for a business operation that changes state. Actions authorize the operation, coordinate models and services, define transaction boundaries, and protect cross-record invariants.

Examples:

- [`CreateRecipe.php`](../app/Actions/Recipes/CreateRecipe.php) authorizes recipe creation, stores an image, creates the aggregate in a transaction, and cleans up the file if persistence fails.
- [`PlanDinner.php`](../app/Actions/DinnerPlans/PlanDinner.php) snapshots a recipe, creates its requirements, locks the rolling plan, and reconciles reservations.
- [`ReconcilePlanReservations.php`](../app/Actions/DinnerPlans/ReconcilePlanReservations.php) rebuilds reservation state in priority order under database locks and then regenerates groceries.
- [`ToggleGroceryItemChecked.php`](../app/Actions/Groceries/ToggleGroceryItemChecked.php) owns the rules for changing checklist completion state.

Actions use a descriptive verb and expose a small public method, usually `handle(...)`. Livewire pages should call these actions instead of duplicating their transactions or domain decisions.

Use an action when a new operation:

- creates, updates, archives, restores, or deletes persisted state;
- spans multiple models or infrastructure resources;
- requires authorization;
- needs a transaction, lock, rollback, or retry boundary; or
- should behave identically from more than one delivery path.

### `app/Queries`: composed read operations

Query objects handle reads that are too involved to leave in a page component or model scope. They may eager-load several relationships, build a read-specific data structure, or coordinate a calculation service.

Examples:

- [`AvailablePantry.php`](../app/Queries/AvailablePantry.php) produces the reservation-aware pantry snapshot used by other features.
- [`GetPantryAwareRecommendations.php`](../app/Queries/GetPantryAwareRecommendations.php) loads the active recipe catalogue, scores every recipe, applies deterministic global sorting, and only then paginates the result.

Simple, feature-local reads can remain as scoped Eloquent queries in a Livewire computed property. Introduce a query object when the read has reusable rules, a bounded-query requirement, or a result shape that is meaningful outside one page.

### `app/Services`: reusable domain capabilities

Services contain calculations or infrastructure behavior that does not naturally belong to one Eloquent record. They are grouped by feature.

| Service group | Responsibility | Example |
| --- | --- | --- |
| `Services/Measurements` | Parse quantity input, normalize compatible units, and format amounts | [`UnitConverter.php`](../app/Services/Measurements/UnitConverter.php) |
| `Services/Recipes` | Scale immutable recipe quantities for a selected serving count | [`RecipeScaler.php`](../app/Services/Recipes/RecipeScaler.php) |
| `Services/Recommendations` | Score one loaded recipe against an immutable pantry snapshot | [`RecommendationEngine.php`](../app/Services/Recommendations/RecommendationEngine.php) |
| `Services/DinnerPlans` | Create requirement snapshots and allocate stock without persistence concerns | [`PantryAllocator.php`](../app/Services/DinnerPlans/PantryAllocator.php) |
| `Services/Groceries` | Convert requirement shortfalls into deterministic generated checklist data | [`GroceryCalculator.php`](../app/Services/Groceries/GroceryCalculator.php) |
| Root `Services` | Shared infrastructure behavior | [`RecipeImageStorage.php`](../app/Services/RecipeImageStorage.php) |

Pure calculation services should receive explicit inputs and return data objects or value objects. Database transactions and user authorization belong in actions, not in pure calculators.

### `app/Models`: persisted state and local model behavior

Models map database rows to PHP objects. They own:

- relationships;
- attribute casts and enum casts;
- fillable attributes;
- reusable query scopes; and
- behavior local to one record.

For example, [`Recipe.php`](../app/Models/Recipe.php) defines its ingredients, steps, categories, tags, owner, archive scopes, and search scope. [`PlannedDinner.php`](../app/Models/PlannedDinner.php) casts its status to `PlannedDinnerStatus` and provides `active`, `history`, and `priorityOrder` scopes.

Models should not become alternate controllers. A workflow that locks a plan, changes several records, and regenerates derived state belongs in an action.

The main persisted aggregates are:

- `User` owns the user-specific catalogue and product data.
- `Ingredient` owns aliases and package definitions.
- `Recipe` owns ingredient lines and ordered steps.
- `DinnerPlan` owns active and historical `PlannedDinner` records.
- `PlannedDinner` owns requirement snapshots, which own pantry reservations.
- `GroceryList` owns generated/manual items and generated-item contributions.

### `app/Data`: typed values passed between layers

Data classes are small immutable carriers for inputs and results. They make non-trivial array shapes explicit without turning transient calculation results into database models.

Examples:

- [`QuantityInput.php`](../app/Data/Measurements/QuantityInput.php) describes an amount before normalization.
- [`PantryAvailability.php`](../app/Data/Pantry/PantryAvailability.php) is the snapshot consumed by recommendation scoring.
- [`RecommendationResult.php`](../app/Data/Recommendations/RecommendationResult.php) carries a scored recipe and its explanation to the UI.
- [`CookResult.php`](../app/Data/DinnerPlans/CookResult.php) tells the caller whether cooking completed or requires confirmation.
- [`GroceryCalculationItem.php`](../app/Data/Groceries/GroceryCalculationItem.php) describes one generated row before persistence.

Use a data class for a structured message between components. Use an Eloquent model for persisted identity, and a value object when the type must enforce behavior or invariants.

### `app/ValueObjects`: immutable domain concepts

Value objects validate and operate on domain concepts that have rules beyond their individual fields.

[`Quantity.php`](../app/ValueObjects/Quantity.php) stores the entered and normalized decimal amounts, enforces compatible measurement groups, and supports comparison, addition, subtraction, and scaling. [`CompatibilityKey.php`](../app/ValueObjects/CompatibilityKey.php) gives pantry and requirement matching one canonical meaning.

Quantity calculations use decimal strings and BCMath. Do not convert domain amounts to PHP floats; that would reintroduce binary rounding errors into stock, reservations, and groceries.

### `app/Enums`: closed sets of valid states

Enums replace free-form strings where the application has a fixed vocabulary.

Examples include:

- [`PlannedDinnerStatus.php`](../app/Enums/PlannedDinnerStatus.php): planned, cooked, or cancelled;
- [`QuantityType.php`](../app/Enums/QuantityType.php): exact or non-exact requirements;
- [`RequirementCoverage.php`](../app/Enums/RequirementCoverage.php): full, partial, missing, incompatible, staple, and related coverage states;
- [`UnitCode.php`](../app/Enums/UnitCode.php): supported metric and semantic count units; and
- [`GroceryItemSource.php`](../app/Enums/GroceryItemSource.php): generated or manual items.

Enums are also used as Eloquent casts and Laravel validation rules, keeping storage, business logic, and form validation aligned.

### `app/Policies`: ownership and authorization

Policies answer whether the current user may perform an operation on a model. Most product data is user-owned, so policies prevent one user from reading or changing another user's records.

[`RecipePolicy.php`](../app/Policies/RecipePolicy.php), for example, allows an owner to view a recipe, allows only active recipes to be updated or archived, and allows only archived recipes to be restored.

Delivery code may authorize early for a better failure boundary, while mutation actions also authorize the operation they own. Always scope user-selected IDs to their aggregate or owner even when a policy check also exists.

### `app/Livewire/Forms`: interactive form state and validation

Livewire form objects keep large, reusable form state out of page components. They own field defaults, validation, nested row manipulation, and conversion from an existing model into editable state.

[`RecipeForm.php`](../app/Livewire/Forms/RecipeForm.php) is the largest example. It:

- validates recipe metadata, image constraints, ingredients, and steps;
- verifies that selected ingredients and packages belong to the user;
- enforces exact versus non-exact quantity field combinations; and
- maintains stable temporary keys while nested rows are reordered.

Form objects validate input but do not replace actions. After validation, the page passes the result to an action such as `CreateRecipe` or `UpdateRecipe`.

### `app/Rules`: reusable field-level validation

Custom Laravel validation rules hold validation that is reused across forms or that benefits from a named type.

- [`PositiveDecimalQuantity.php`](../app/Rules/PositiveDecimalQuantity.php) accepts supported decimal and fraction input through the shared parser.
- [`CompatibleUnitForIngredient.php`](../app/Rules/CompatibleUnitForIngredient.php) rejects measurement units that do not match an ingredient.

Rules should report user-facing input errors. Business-state conflicts and concurrency decisions still belong in actions.

### `app/Exceptions`: expected domain interruptions

The custom exceptions represent business flows that require explicit confirmation rather than an unexpected system failure.

- [`PantryEntryRemovalRequiresConfirmation.php`](../app/Exceptions/PantryEntryRemovalRequiresConfirmation.php) protects removal that affects active reservations.
- [`UnresolvedRequirementsRequireConfirmation.php`](../app/Exceptions/UnresolvedRequirementsRequireConfirmation.php) defines an exception representation for unresolved cooking requirements. The current `MarkDinnerCooked` flow returns the same confirmation information in `CookResult`.

### `app/Providers`: application bootstrapping

Service providers configure framework behavior:

- [`AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) selects immutable dates, blocks destructive production database commands, and defines production password defaults.
- [`FortifyServiceProvider.php`](../app/Providers/FortifyServiceProvider.php) connects authentication actions and views and configures login, two-factor, and passkey rate limits.

Provider registration lives in [`bootstrap/providers.php`](../bootstrap/providers.php).

## Presentation code

### `routes`

[`routes/web.php`](../routes/web.php) is the web entry point. It exposes the public home page, protects the product under `auth` and `verified` middleware, and includes the more focused route files.

- [`routes/product.php`](../routes/product.php) maps product URLs to Livewire pages.
- [`routes/settings.php`](../routes/settings.php) maps account settings and passkey discovery.
- [`routes/console.php`](../routes/console.php) is reserved for Artisan console routing and scheduling.

Static route segments such as `/recipes/archive` must appear before model-bound routes such as `/recipes/{recipe}` so that the router does not interpret `archive` as a model identifier.

### `resources/views/pages`: routable Livewire pages

Product screens use Livewire 4 single-file components. The PHP component and Blade template live together in files prefixed with `⚡`, such as:

- [`pages/recipes/⚡create.blade.php`](../resources/views/pages/recipes/%E2%9A%A1create.blade.php);
- [`pages/recommendations/⚡index.blade.php`](../resources/views/pages/recommendations/%E2%9A%A1index.blade.php); and
- [`pages/dinner-plans/⚡index.blade.php`](../resources/views/pages/dinner-plans/%E2%9A%A1index.blade.php).

A page is responsible for:

- public UI state and URL state;
- handling browser events;
- calling validation, authorization, actions, and queries;
- computed properties needed for rendering;
- redirects, flashes, toasts, and modal state; and
- composing Flux UI and Blade components.

A page should not implement decimal allocation, reservation transactions, or other reusable domain logic.

Livewire does not persist private injected services between requests. The recommendations page demonstrates the established pattern of restoring a query dependency in `boot(...)`.

### `resources/views/components`: reusable view fragments

Blade components contain repeated markup and presentation behavior:

- [`components/recipes/form.blade.php`](../resources/views/components/recipes/form.blade.php) renders the shared nested recipe form;
- [`components/dinner-date-picker.blade.php`](../resources/views/components/dinner-date-picker.blade.php) encapsulates the accessible date picker; and
- components under `ingredients` and `pantry` render feature-specific form sections.

Use a component when markup is shared, when a page becomes difficult to scan, or when a UI control owns meaningful presentation behavior. Keep database queries out of Blade components.

### `resources/views/layouts`, `partials`, and `flux`

- `layouts` define the authenticated and authentication page shells.
- `partials` contain small shared document fragments such as the HTML head.
- `flux` contains local Flux component customizations and icons.

### `resources/css` and `resources/js`

[`resources/css/app.css`](../resources/css/app.css) is the Tailwind application stylesheet. [`resources/js/app.js`](../resources/js/app.js) is the main JavaScript entry point, while [`resources/js/passkeys.js`](../resources/js/passkeys.js) contains passkey-specific browser behavior.

Vite compiles these sources. Files under `public/build` are generated artifacts and should not be edited by hand.

## Framework and infrastructure

### `bootstrap`

[`bootstrap/app.php`](../bootstrap/app.php) creates the Laravel application, registers web/console/health routes, and configures middleware and exception rendering. [`bootstrap/providers.php`](../bootstrap/providers.php) lists application service providers.

### `config`

Configuration files hold environment-independent defaults and read environment variables where deployment-specific values are needed.

Important application-specific files are:

- [`config/dinner-decider.php`](../config/dinner-decider.php) for timezone, date/time, locale-style, and measurement presentation;
- [`config/measurements.php`](../config/measurements.php) for decimal precision, display rounding, fractions, and input limits; and
- [`config/recommendations.php`](../config/recommendations.php) for score weights, bounds, and page size.

Business code should read configuration with `config(...)`; application classes should not call `env(...)` directly.

### `database/migrations`

Migrations are the versioned schema history. They create authentication infrastructure first, then the ingredient/recipe catalogue, pantry, dinner plan and reservations, and grocery projection.

When changing persisted data, inspect the corresponding model, factory, policies, actions, and tests as well as the migration. Database constraints are a final protection for invariants such as ownership relationships and singleton records; application validation does not replace them.

### `database/factories`

Factories create concise, realistic model graphs in tests. Each product model has a corresponding factory, and relationships are normally expressed with `for(...)`.

For example:

```php
$recipe = Recipe::factory()->for($user)->create();
RecipeIngredient::factory()->for($recipe)->for($ingredient)->create();
```

Prefer factories over manually filling every database column in a test. Add factory states when the same meaningful setup is repeated.

### `database/seeders`

[`DatabaseSeeder.php`](../database/seeders/DatabaseSeeder.php) creates the local demo account only outside production. [`StageOneCatalogueSeeder.php`](../database/seeders/StageOneCatalogueSeeder.php) builds the catalogue, packages, pantry, and recipes, while [`StageThreeDinnerPlanSeeder.php`](../database/seeders/StageThreeDinnerPlanSeeder.php) uses application actions to build coherent plan and grocery state.

Seeders provide development/demo data. Tests should remain independently reproducible with factories.

### `storage` and `public`

`storage` contains logs, framework caches, sessions, compiled views, and application-managed files. Recipe images are managed through Laravel storage by `RecipeImageStorage`.

`public/index.php` is the HTTP front controller. Static assets and Vite output are served from `public`, but generated build files should only change through the frontend build.

## Tests

Tests mirror both product features and architectural layers:

- `tests/Feature/Auth` and `tests/Feature/Settings` cover authentication and account flows.
- Feature folders such as `Recipes`, `Pantry`, `DinnerPlans`, and `Groceries` cover persistence, authorization, Livewire behavior, and connected workflows.
- `tests/Unit` covers pure calculations and value objects without needing the database.
- [`MvpJourneyTest.php`](../tests/Feature/MvpJourneyTest.php) verifies the connected pantry-to-recommendation-to-plan-to-grocery-to-cooking workflow.
- [`ReadPathPerformanceTest.php`](../tests/Feature/Performance/ReadPathPerformanceTest.php) protects bounded query counts for important screens.

Examples of the distinction:

- [`RecipeLivewireTest.php`](../tests/Feature/Recipes/RecipeLivewireTest.php) drives a Livewire form and asserts persisted recipe state.
- [`RecommendationEngineTest.php`](../tests/Unit/Recommendations/RecommendationEngineTest.php) constructs calculation inputs directly and verifies scores without a database.
- [`DinnerPlanConcurrencyTest.php`](../tests/Feature/DinnerPlans/DinnerPlanConcurrencyTest.php) exercises transaction and locking behavior against MySQL.

Put a test at the narrowest layer that proves the behavior. A pure calculator usually needs a unit test; an action that changes several records needs a feature test; a user interaction needs a Livewire feature test.

## End-to-end examples

### Creating a recipe

```text
POST-like Livewire event
  -> pages/recipes/⚡create.blade.php::save()
  -> RecipeForm::validated()
  -> CreateRecipe::handle()
  -> RecipeImageStorage + database transaction
  -> SaveRecipeDetails
  -> Recipe, RecipeIngredient, RecipeStep, category, and tag models
  -> redirect to recipes.show
```

The page owns the interaction and redirect. `RecipeForm` owns nested input validation. `CreateRecipe` owns authorization, the transaction, and filesystem rollback. `SaveRecipeDetails` owns persistence of the recipe's child collections and quantity normalization.

### Reading recommendations and planning one

```text
GET /recommendations
  -> recommendations Livewire page
  -> GetPantryAwareRecommendations
      -> AvailablePantry
      -> RecommendationEngine for every active recipe
  -> globally sorted RecommendationResult objects
  -> rendered explanation

Click "Plan dinner"
  -> PlanDinner
  -> recipe and requirement snapshots
  -> ReconcilePlanReservations
  -> PantryAllocator
  -> RegenerateGroceryList
```

The recommendation half is read-only. Planning crosses into a mutation action and synchronously updates all derived reservation and grocery state before returning.

### Cooking a dinner

```text
Click "Cook"
  -> dinner-plan Livewire page
  -> MarkDinnerCooked
  -> current shortfalls checked
  -> optional confirmation fingerprint
  -> reserved pantry stock consumed once
  -> dinner moved to cooked history
  -> reservations and groceries reconciled
```

The Livewire page owns modal state. The action owns stale-confirmation protection, exactly-once consumption, transaction locking, and the final state transition.

## Where should new code go?

| If the new code... | Put it in... |
| --- | --- |
| Handles a routed screen or browser event | `resources/views/pages/<feature>` |
| Holds reusable Livewire form fields and validation | `app/Livewire/Forms` |
| Renders reusable markup or a UI control | `resources/views/components` |
| Changes business state | `app/Actions/<Feature>` |
| Builds a reusable or performance-sensitive read result | `app/Queries` |
| Performs reusable calculation or infrastructure work | `app/Services/<Feature>` |
| Represents a structured transient input or result | `app/Data/<Feature>` |
| Represents an immutable concept with behavior and invariants | `app/ValueObjects` |
| Represents a closed vocabulary or state set | `app/Enums` |
| Adds reusable user-input validation | `app/Rules` |
| Maps a persisted entity and its relationships | `app/Models` |
| Decides whether a user may operate on a model | `app/Policies` |
| Changes the database schema | `database/migrations` |
| Creates test records | `database/factories` |
| Proves pure calculation behavior | `tests/Unit/<Feature>` |
| Proves persistence, authorization, HTTP, or Livewire behavior | `tests/Feature/<Feature>` |

Do not add a new top-level directory for a single class. Follow the nearest existing feature pattern and introduce a new boundary only when it has a concrete responsibility.

## Cross-cutting conventions to preserve

1. **Treat quantities as decimal strings.** Persist decimal columns, calculate with BCMath, and round only for display.
2. **Keep user ownership explicit.** Scope model lookups to the authenticated user or a user-owned aggregate and enforce policies.
3. **Put transaction boundaries around complete use cases.** Reservation, cooking, and grocery invariants must not be partially committed.
4. **Regenerate derived projections from authoritative facts.** Reservations and generated groceries are rebuilt; they are not independent sources of truth.
5. **Preserve history with snapshots.** Planned dinners retain recipe and requirement details even if the source recipe later changes or is archived.
6. **Keep core correctness synchronous.** The MVP does not rely on a queue worker for pantry, reservation, or grocery state.
7. **Eager-load intentionally.** Screens and query objects should avoid hidden N+1 queries, and important read paths have query-count tests.
8. **Keep pages thin.** A Livewire method coordinates input and output; actions and services own reusable decisions.
9. **Test at the affected boundary.** Update unit, feature, Livewire, concurrency, or performance coverage according to the behavior changed.
10. **Update documentation with behavior.** Architectural decisions belong in `docs/architecture.md`; operational procedures belong in `docs/operations-runbook.md`; this guide should remain aligned with the actual directory responsibilities.

## Useful navigation commands

```powershell
# List application routes.
php artisan route:list --except-vendor

# Find a class or method.
rg "class PlanDinner|function handle" app

# Run one focused test file.
php artisan test --compact tests/Feature/DinnerPlans/DinnerPlanningTest.php

# Run the configured formatter after changing PHP.
vendor/bin/pint --dirty --format agent
```

When working through Sail, run the PHP commands inside the application container as shown in the root [`README.md`](../README.md).
