# Dinner Decider application architecture

Status: Active architecture; Stage 1 implemented

Last reviewed: 2026-07-20

Source of functional scope: Dinner Decider MVP Product Specification.docx  
Resolved product decisions: Dinner Decider — Remaining MVP Product Decisions
Target runtime: PHP 8.5, Laravel 13, Livewire 4, Flux UI 2, MySQL 8.4

## Reading this document

This document distinguishes three kinds of statements:

- **Confirmed repository fact** describes code or configuration that exists now.
- **MVP decision** is the architecture to implement for the eight defined epics.
- **Future option** is a deliberate extension point, not work required now.

The architecture is a development reference, not an instruction to implement every directory or class immediately. New classes should be introduced only with the milestone that needs them.

## 1. Purpose and architectural goals

Dinner Decider must support one connected and reliable flow:

> Add pantry stock → receive pantry-aware recommendations → select dinners → reserve ingredients → update availability → generate groceries → cook dinner → consume reserved stock.

The architecture therefore prioritizes:

1. Exact and explainable quantity calculations.
2. Transactional reservation and consumption behavior.
3. Clear ownership of user data and business rules.
4. Thin delivery code in Livewire pages and HTTP controllers.
5. Focused application actions for business operations.
6. Eloquent and Laravel conventions instead of a parallel framework.
7. Deterministic, independently testable recommendation, allocation, scaling, and grocery algorithms.
8. An MVP-sized modular monolith that can grow without being split prematurely.

The design must make invalid states difficult to create: incompatible units must not match, stock must not become negative, reservations must not be applied twice, and a cooked dinner must not be consumed twice.

## 2. Project context and constraints

### 2.1 Confirmed repository facts

- The application now includes the Stage 1 measurement kernel and user-owned ingredient and recipe catalogue domains; pantry, planning, recommendations, and groceries remain future stages.
- The runtime is PHP 8.5.8, Laravel 13.20.0, Livewire 4.3.3, Flux UI 2.15.0, and MySQL 8.4.
- composer.json declares PHP ^8.3. CI supports PHP 8.3/Node 22.12 as the minimum pair and PHP 8.5/Node 24 as the preferred Docker runtime; this architecture does not require PHP 8.5-only syntax.
- Laravel Sail provides the Docker development environment.
- The UI convention is Livewire 4 page single-file components under resources/views/pages, using the pages namespace and the lightning-bolt filename prefix.
- Authentication is provided by Fortify with registration, password reset, enforced email verification, two-factor authentication, passkeys, and login throttling.
- The application uses database-backed sessions and cache. Queues execute synchronously for the MVP; database queue tables are retained, but no application job, worker service, or scheduled task exists.
- PHPUnit 12, Larastan level 7, and Pint are configured.
- The existing application layer contains focused Fortify actions and an invokable Logout action.
- The database includes Stage 1 ingredient, package, alias, recipe, recipe-line, step, category, and tag tables in addition to the starter infrastructure.
- Authenticated, verified product routes provide ingredient and recipe catalogue management and recipe serving previews.
- Later-stage product data is still to be introduced, and no legacy domain rewrite is needed.

### 2.2 Functional scope

The MVP specification defines eight epics:

1. Ingredient and Measurement System
2. Recipe Catalogue
3. Pantry Management
4. Recipe Serving Scaling
5. Pantry-Based Recommendations
6. Dinner Plan Management
7. Ingredient Reservation Lifecycle
8. Grocery List Generation

Explicitly excluded from the MVP are AI recipe generation, recipe imports, substitutions, dietary profiles, shared households, expiry tracking, package rounding, prices, barcode scanning, imperial units, approximate conversions, and automatic multi-dinner plan generation. The architecture must not smuggle these features back in as speculative abstractions.

The resolved decisions add basic Cooked/Cancelled dinner history inside the rolling plan because it is needed for snapshots, duplication, and restore. This does not add the richer cooking-history/ratings feature excluded by the original specification.

### 2.3 Technical constraints

- MySQL is the production-shaped persistence target.
- All calculation code must avoid binary floating point.
- The supported measurement system is metric plus semantic count units and explicitly defined packages.
- Existing Laravel conventions and the App root namespace must remain intact.
- Product code remains compatible with the declared PHP ^8.3 constraint until the runtime/support policy is changed and tested.
- The first frontend remains server-driven Livewire and Blade; no separate SPA or API is required.
- User-facing writes must be authorized and validated server-side even when the interface already constrains input.
- Core correctness cannot depend on a queue worker because the current Compose stack does not run one.

### 2.4 Baseline hardening status and remaining incomplete patterns

Stage 0 resolved the original runtime and configuration inconsistencies. Remaining findings are deliberately postponed until their related feature is in scope:

| Finding or former gap | Current status | Follow-up |
| --- | --- | --- |
| Fortify email verification was enabled without App\Models\User implementing Illuminate\Contracts\Auth\MustVerifyEmail. | Resolved: the contract is implemented and an unverified-route test proves enforcement. | Apply auth and verified middleware to MVP product routes. |
| CI and Docker used different undocumented PHP/Node pairs. | Resolved: CI tests PHP 8.3/Node 22 and PHP 8.5/Node 24, and the support policy is documented. | Raise minimums only through an explicit compatibility decision. |
| CI had no MySQL service despite the MySQL-oriented environment. | Resolved: both CI matrix jobs use a health-checked MySQL 8.4 service and run migrations before tests. | Add targeted locking and decimal integration tests with their domain features. |
| Composer's post-create hook created an SQLite file. | Resolved: the stale hook was removed and setup uses MySQL. | Keep pure unit tests database-free rather than introducing a second database engine. |
| The database queue was selected without a worker. | Resolved for the MVP: the default is sync and database queue infrastructure is retained but inactive. | Add a supervised worker and after-commit behavior before the first queued feature. |
| boost.json has Sail disabled and Codex starts Boost through host PHP while DB_HOST is mysql. | Documentation tools work, but database-aware Boost tools may not reach the Docker network from host PHP. | Reconfigure the MCP command through Sail/Docker when Boost database tools are needed. |
| The dashboard, logo, welcome screen, repository links, and README are still starter placeholders. | Stage 1 ingredient and recipe navigation now exists, while the remaining starter surfaces have not been replaced. | Replace gradually during later feature milestones; this is not an architectural rewrite. |
| The generated welcome view contains a large inline style block and some starter components contain substantial inline Alpine behavior. | This differs from the repository guideline that new JS and CSS should live in dedicated assets or component files. | Do not copy this pattern into product features; migrate only when those views are touched. |
| Existing tests use RefreshDatabase while repository guidance prefers LazilyRefreshDatabase. | Current tests are valid but may become slower as the schema grows. | Adopt LazilyRefreshDatabase for new domain feature tests and migrate existing tests opportunistically. |

## 3. Architectural principles

### 3.1 Laravel first

Use routes, middleware, Livewire pages, Form Requests, policies, Eloquent, model factories, events, jobs, notifications, the service container, and database transactions as Laravel intends. Do not create custom replacements for the container, ORM, event dispatcher, or validation system.

### 3.2 Business operations have one entry point

Each state-changing use case has a focused action such as PlanDinner, ChangePlannedDinnerServings, CancelPlannedDinner, or MarkDinnerCooked. Delivery code calls the action; it does not reproduce the transaction or allocation algorithm.

### 3.3 Models own local behavior; actions own orchestration

An Eloquent model owns relationships, casts, scopes, and state rules local to that model. For example, PlannedDinner can assert that only a planned dinner may be cooked. An action owns a workflow spanning multiple models, such as locking pantry entries, consuming reservations, changing dinner status, and regenerating groceries.

### 3.4 Exact quantities have one canonical meaning

Every exact quantity is normalized to a compatible base unit for calculations while retaining the entered amount and unit for editing and display. Binary floats are prohibited in domain calculations and persisted quantity columns.

### 3.5 Derived data has an explicit source of truth

- Pantry total is persisted on PantryEntry.
- Reserved pantry quantity is the sum of active IngredientReservation rows.
- Available pantry quantity is total minus reserved.
- A planned requirement is a snapshot persisted for a PlannedDinner.
- Grocery generated quantities are rebuilt from planned requirements and reservations.
- Recommendation results are calculated on demand for the MVP and are not a second mutable source of truth.

### 3.6 Correctness is synchronous; side effects may be asynchronous

Reservation, consumption, requirement snapshots, and grocery regeneration complete synchronously within the user-facing use case. Email, analytics, image processing, or cache warming may later use after-commit events and queues. A missing queue worker must never make inventory incorrect.

### 3.7 Ownership and authorization are explicit

MVP catalogue and planning data belong to one User. Every aggregate-root query is user-scoped, and every mutation is policy-authorized. A future household model is a migration, not a polymorphic owner abstraction added today.

### 3.8 Optimize only where the epics create pressure

Use eager loading, indexes, aggregate queries, pagination, and deterministic algorithms from the beginning. Do not add Redis, Horizon, a search engine, microservices, CQRS, event sourcing, or generic repositories before measurements demonstrate a need.

## 4. Chosen architectural style

### 4.1 Style

**MVP decision: Laravel-first modular monolith with feature-oriented namespaces, an action-oriented application layer, and a light domain model.**

The code stays in one Laravel application and one MySQL database. Conventional top-level directories remain recognizable. Within those directories, classes are grouped by feature where that improves navigation:

- App\Actions\Pantry\AddPantryStock
- App\Services\Measurements\UnitConverter
- App\Services\Recommendations\RecommendationEngine
- App\Policies\RecipePolicy
- resources/views/pages/recipes/⚡index.blade.php

This is not a strict layered or hexagonal implementation. Eloquent models remain both persistence models and domain entities where that is practical. Boundaries are enforced through dependency direction and focused public APIs, not through an interface around every class.

### 4.2 Why it fits

- The product is one cohesive workflow with strong transactional coupling.
- Laravel and Livewire already solve delivery, persistence, validation, authorization, and dependency injection.
- A small team can navigate conventional directories quickly.
- Pure calculation services and value objects isolate the risky parts without isolating the entire application from Laravel.
- The architecture supports all eight epics without paying the operational cost of distributed systems.

### 4.3 Alternatives considered

| Alternative | Why it was not selected | Reconsider when |
| --- | --- | --- |
| Full Clean Architecture with separate Domain, Application, Infrastructure, and adapter models | Duplicates models and mapping code before the domain is proven; fights Eloquent and slows the MVP. | Multiple delivery mechanisms or persistence technologies genuinely require isolation. |
| Package-per-module or nwidart-style modules | Adds service providers, package boundaries, and discovery overhead for tightly related modules. | Teams deploy modules independently or the monolith becomes difficult to navigate despite feature namespaces. |
| Microservices | Reservations, pantry stock, and grocery generation need one transaction today. Distributed consistency would be worse. | Independent scaling and team ownership outweigh transactional simplicity. |
| Transaction scripts directly in Livewire components | Fast initially, but duplicates rules across UI paths and makes race conditions difficult to test. | Not recommended; focused actions are the minimum useful boundary. |
| Rich domain model with no application actions | Cross-model transactions and Laravel delivery concerns would leak into entities. | Not recommended for the orchestration-heavy workflows here. |

### 4.4 Trade-off

The modular boundaries are conventions rather than physically enforced packages. Developers must review dependencies and keep feature code from reaching across modules arbitrarily. Larastan and focused tests provide feedback, but discipline remains necessary.

## 5. High-level system overview

~~~mermaid
flowchart TB
    Browser["Browser"]

    subgraph Presentation["Presentation"]
        Livewire["Livewire 4 pages and forms"]
        Controllers["HTTP controllers"]
        BladeFlux["Blade components and Flux UI"]
    end

    subgraph Application["Application orchestration"]
        Actions["Feature actions"]
        Queries["Focused query objects"]
        Policies["Policies and authorization"]
    end

    subgraph Domain["Domain capabilities"]
        Measurements["Ingredients and measurements"]
        Recipes["Recipe catalogue and scaling"]
        Pantry["Pantry balances and allocation"]
        Planning["Dinner plans and reservations"]
        Recommendations["Recommendation engine"]
        Groceries["Grocery calculator"]
    end

    subgraph Infrastructure["Laravel infrastructure"]
        Eloquent["Eloquent models"]
        Storage["Laravel filesystem"]
        Events["Events, listeners, jobs"]
        MySQL[("MySQL 8.4")]
    end

    Browser --> Livewire
    Browser --> Controllers
    Livewire --> BladeFlux
    Livewire --> Policies
    Controllers --> Policies
    Livewire --> Actions
    Controllers --> Actions
    Livewire --> Queries
    Controllers --> Queries

    Actions --> Measurements
    Actions --> Recipes
    Actions --> Pantry
    Actions --> Planning
    Actions --> Groceries
    Queries --> Recommendations

    Recipes --> Measurements
    Pantry --> Measurements
    Planning --> Recipes
    Planning --> Pantry
    Recommendations --> Recipes
    Recommendations --> Pantry
    Groceries --> Planning
    Groceries --> Measurements

    Actions --> Eloquent
    Queries --> Eloquent
    Eloquent --> MySQL
    Actions --> Storage
    Actions --> Events
~~~

The system is deployed as a web application plus MySQL. In development, Sail runs both containers. The browser uses regular HTTP and Livewire requests; there is no separate frontend API boundary.

## 6. Domain or module boundaries

### 6.1 Identity and Access

**Owns:** User authentication, email verification, password resets, passkeys, two-factor authentication, session security, and user-level authorization.

**Existing implementation:** Fortify, App\Models\User, App\Actions\Fortify, App\Providers\FortifyServiceProvider, account settings Livewire pages.

**Boundary:** Other modules may reference User as owner but must not reimplement authentication. Product policies determine whether a user may act on domain records.

### 6.2 Ingredients and Measurements

**Owns:** Ingredient catalogue entries, aliases, categories, staple and current-availability state, units, compatibility rules, package definitions, quantity parsing, normalization, arithmetic, and display formatting.

**Depends on:** Identity only for ownership.

**Used by:** Recipes, Pantry, Planning, Recommendations, and Groceries.

This is the lowest-level product module. It must not depend on recipes, pantry stock, or dinner plans.

### 6.3 Recipes

**Owns:** Recipes, ingredient lines, ordered steps, tags/categories, recipe images, archive state, default servings, and serving scaling.

**Depends on:** Ingredients and Measurements.

**Does not own:** Pantry matches, reservations, planned servings, or grocery requirements.

### 6.4 Pantry

**Owns:** User pantry entries, total stock, compatibility-aware balance queries, stock merging, and stock-allocation rules.

**Depends on:** Ingredients and Measurements.

**Exposes:** Available balances calculated as total stock minus active reservations. It does not decide which recipe to recommend.

### 6.5 Dinner Planning and Reservations

**Owns:** One rolling DinnerPlan per user, active PlannedDinner ordering, cooked/cancelled history, duplication/restoration, selected servings, optional date, recipe/requirement snapshots, priority-based allocation of pantry entries, shortfalls, reservation release, and cooking consumption.

**Depends on:** Recipes, Pantry, and Measurements.

This is the transactional center of the application. It coordinates rather than duplicating recipe and pantry rules.

### 6.6 Recommendations

**Owns:** Deterministic recipe matching, score calculation, match details, and sorting.

**Depends on:** Read-only recipe requirements, serving scaling, and current pantry availability.

**Does not own:** Pantry changes, reservations, dinner selections, or persisted recommendation rows.

### 6.7 Groceries

**Owns:** One grocery list for the rolling dinner plan, generated items, temporary generated-quantity overrides, manual items, check/change state, categories, source contributions, completed-item clearing, and regeneration.

**Depends on:** PlannedDinnerRequirement, IngredientReservation, Ingredients, and Measurements.

It consumes planning facts; it does not create reservations or change pantry stock.

### 6.8 Dependency direction

Dependencies point toward stable concepts:

~~~text
Identity
   └─ Ingredients and Measurements
        ├─ Recipes ───────────────┐
        ├─ Pantry ────────────────┼─ Dinner Planning and Reservations ─ Groceries
        └─ Recipes + Pantry ─ Recommendations
~~~

Recommendations and Groceries must not call each other. Cross-module mutations are coordinated by an application action rather than by circular model callbacks.

### 6.9 Epic coverage

| Epic | Primary module | Supporting components |
| --- | --- | --- |
| 1. Ingredient and Measurement System | Ingredients and Measurements | Quantity value object, QuantityInputParser, UnitConverter, QuantityFormatter, ingredient policies |
| 2. Recipe Catalogue | Recipes | Minimum fields, nullable metadata, RecipeForm, image placeholder/storage, archive/history snapshots |
| 3. Pantry Management | Pantry | Stock/availability actions, package-aware balances, full-priority allocator, reservations aggregate |
| 4. Recipe Serving Scaling | Recipes and Measurements | RecipeScaler using original amounts and fixed-precision arithmetic |
| 5. Pantry-Based Recommendations | Recommendations | RecommendationQuery, configurable Q/F/P/M/I engine, detailed explanations |
| 6. Dinner Plan Management | Dinner Planning | Rolling DinnerPlan, active/history actions, duplicate/restore, complete snapshots |
| 7. Ingredient Reservation Lifecycle | Dinner Planning and Pantry | Priority reconciliation, transactions/locks, confirmation, IngredientReservation |
| 8. Grocery List Generation | Groceries | GroceryCalculator, temporary overrides, increase-sensitive checks, stable identities/contributions |

## 7. Request and data flow

### 7.1 Read flow

1. A Livewire page or controller receives a request.
2. Authentication middleware establishes the user.
3. The page/controller invokes a focused query or a simple user-scoped Eloquent query.
4. The query eager-loads only required relationships and returns models or explicit result data.
5. The Livewire page renders Blade and Flux components.
6. No database query is executed from a Blade expression.

Simple CRUD pages may query Eloquent directly when the query is short and used once. Recommendation results and aggregate pantry balances warrant query objects because their shape and performance are substantial.

### 7.2 Write flow

1. Livewire Form object or Form Request validates input structure and normalizes locale-specific text input.
2. A policy authorizes the requested operation.
3. The delivery layer passes validated primitives or small data objects to a feature action.
4. The action opens a transaction if multiple records or invariants are involved.
5. Rows are locked in a stable order when stock or reservation state can race.
6. Models and domain services enforce compatibility, arithmetic, and state invariants.
7. The action persists all authoritative and synchronous derived state.
8. The transaction commits or fully rolls back.
9. Meaningful after-commit events may notify non-critical listeners.
10. The delivery layer redirects, navigates, or shows a Flux toast.

### 7.3 Typical mutation flow

~~~mermaid
sequenceDiagram
    actor User
    participant Page as Livewire page
    participant Form as Livewire Form
    participant Policy as Laravel policy
    participant Action as Feature action
    participant Domain as Domain services
    participant DB as MySQL
    participant Event as After-commit event

    User->>Page: Submit validated intent
    Page->>Form: Validate and normalize input
    Form-->>Page: Validated data
    Page->>Policy: Authorize operation
    Policy-->>Page: Allowed
    Page->>Action: handle(data, user, model)
    Action->>DB: Begin transaction
    Action->>DB: Lock affected rows in stable order
    Action->>Domain: Enforce invariants and calculate
    Domain-->>Action: Exact result objects
    Action->>DB: Persist authoritative state
    Action->>DB: Persist synchronous derived state
    DB-->>Action: Commit
    Action->>Event: Dispatch business fact after commit
    Action-->>Page: Model or result
    Page-->>User: Redirect, navigation, or toast
~~~

Authorization is repeated at the server action boundary when an action may be invoked outside the original UI. UI visibility is convenience, not security.

## 8. Laravel directory and namespace structure

### 8.1 Proposed tree

Only directories needed by the current milestone should be created.

~~~text
app/
├── Actions/
│   ├── Fortify/                         existing
│   ├── Ingredients/
│   ├── Recipes/
│   ├── Pantry/
│   ├── DinnerPlans/
│   └── Groceries/
├── Data/
│   ├── Measurements/
│   ├── Recommendations/
│   └── Groceries/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/Api/V1/                future only
├── Jobs/                                only when a real async task exists
├── Listeners/                           only with a concrete event consumer
├── Livewire/
│   ├── Actions/                         existing Logout action
│   └── Forms/
├── Models/
├── Notifications/                       only when product notifications exist
├── Policies/
├── Queries/
├── Rules/
├── Services/
│   ├── Measurements/
│   ├── Recipes/
│   ├── Pantry/
│   ├── Recommendations/
│   └── Groceries/
├── ValueObjects/
└── Providers/

config/
├── dinner-decider.php
├── measurements.php
└── recommendations.php

resources/views/
├── components/
├── layouts/
└── pages/
    ├── ingredients/
    ├── recipes/
    ├── pantry/
    ├── recommendations/
    ├── dinner-plan/
    └── groceries/

routes/
├── web.php
├── settings.php
└── product.php

tests/
├── Unit/
│   ├── Measurements/
│   ├── Recipes/
│   ├── Pantry/
│   ├── Recommendations/
│   └── Groceries/
├── Feature/
│   ├── Ingredients/
│   ├── Recipes/
│   ├── Pantry/
│   ├── Recommendations/
│   ├── DinnerPlans/
│   └── Groceries/
└── Integration/
    └── Reservations/
~~~

The proposed routes/product.php is included from routes/web.php. This keeps the existing auth/settings routes stable without introducing a custom route loader.

### 8.2 Directory responsibilities

| Directory | Responsibility | Belongs here | Must not go here | Project example |
| --- | --- | --- | --- | --- |
| app/Actions/{Feature} | One state-changing application use case and its transaction boundary | Orchestration, calls to policies/services/models, transaction and locks | Reusable arithmetic, HTTP response formatting, generic helper methods | App\Actions\DinnerPlans\MarkDinnerCooked |
| app/Data/{Feature} | Immutable input and result shapes where nested arrays become unclear | QuantityInput, IngredientMatch, RecommendationResult, AllocationResult | Eloquent queries, persistence, service-container lookups | App\Data\Recommendations\IngredientMatch |
| app/Enums | Closed sets with domain meaning | UnitCode, MeasurementGroup, PlannedDinnerStatus, GroceryItemSource | User-editable catalogue data or translated labels with database identity | App\Enums\UnitCode |
| app/Events | Meaningful facts that have a concrete consumer | DinnerCooked, RecipeArchived, if and when listeners need them | Commands such as RecalculateEverything or correctness-critical orchestration | App\Events\DinnerCooked |
| app/Exceptions | Named domain/application failures | IncompatibleUnits, InvalidDinnerTransition, ActiveReservationsPreventRemoval | Validation bags, swallowed infrastructure errors | App\Exceptions\DinnerAlreadyProcessed |
| app/Http/Controllers | Traditional HTTP endpoints and future API adapters | Image response/upload endpoints, future API controllers | Allocation, scaling, recommendation, or grocery algorithms | App\Http\Controllers\RecipeImageController |
| app/Http/Requests | Validation and authorization for controller requests | StoreRecipeRequest for a future API or non-Livewire endpoint | Business transactions and model creation | App\Http\Requests\Recipes\StoreRecipeRequest |
| app/Http/Resources/Api/V1 | Future JSON representation layer | Versioned API resources if a mobile/public API is approved | Current Livewire view models | App\Http\Resources\Api\V1\RecipeResource |
| app/Jobs | Retriable work that may finish after the response | Future image processing or external recipe import | Reservation, cooking, or grocery correctness | App\Jobs\ProcessRecipeImage |
| app/Listeners | Side-effect reaction to a business fact | Cache invalidation, analytics, queued notification dispatch | Core state transitions that must share the initiating transaction | App\Listeners\RecordDinnerCookedMetric |
| app/Livewire/Forms | Complex Livewire form state and validation | Nested recipe ingredients, pantry quantity input | Eloquent querying unrelated to the form or cross-model transactions | App\Livewire\Forms\RecipeForm |
| app/Models | Eloquent persistence entities with relationships, casts, scopes, and local behavior | Recipe, PantryEntry, PlannedDinner, GroceryItem | Large cross-aggregate workflows or view formatting | App\Models\IngredientReservation |
| app/Notifications | User-facing Laravel notifications | Future dinner reminders or failed import notices | Domain events or synchronous inventory rules | App\Notifications\DinnerReminder |
| app/Policies | Model/resource authorization | Ownership and allowed actions by status | Input validation or query construction | App\Policies\RecipePolicy |
| app/Queries | Reusable or complex read models | Eager-loaded recipe search, pantry balances, recommendation input loading | Writes, hidden transactions, or generic repository CRUD | App\Queries\RecipeRecommendationsQuery |
| app/Rules | Reusable Laravel validation rules | CompatibleUnitForIngredient, PositiveDecimalQuantity | Conversion algorithms or database writes | App\Rules\CompatibleUnitForIngredient |
| app/Services/Measurements | Pure or focused measurement algorithms | Parsing, normalization, compatibility, formatting | Eloquent writes or request access | App\Services\Measurements\UnitConverter |
| app/Services/Recipes | Reusable recipe calculations | Scaling an immutable requirement set | Recipe CRUD orchestration | App\Services\Recipes\RecipeScaler |
| app/Services/Pantry | Stock matching and allocation algorithms | Build an AllocationResult from requirements and balances | Opening transactions or rendering UI | App\Services\Pantry\PantryAllocator |
| app/Services/Recommendations | Deterministic scoring and explanations | RecommendationEngine | Querying the authenticated user from globals | App\Services\Recommendations\RecommendationEngine |
| app/Services/Groceries | Deterministic grouping and grocery calculation | GroceryCalculator | Persisting manual items or authorizing users | App\Services\Groceries\GroceryCalculator |
| app/ValueObjects | Immutable concepts with equality and invariants | Quantity, CompatibilityKey | Eloquent identity, service-container dependencies, mutable state | App\ValueObjects\Quantity |
| config/dinner-decider.php | MVP presentation conventions | Europe/Amsterdam display timezone, DD-MM-YYYY, 24-hour time, Monday week start | Per-user mutable preferences or translated content | display_timezone |
| config/measurements.php | Environment-independent measurement policy | Calculation scale, fraction display thresholds, supported defaults | User-specific ingredients or mutable unit records | Calculation scale of 6 |
| config/recommendations.php | One source for recommendation factors | Quantity/full/partial/missing/incompatible weights | User-specific rankings or database queries | quantity_coverage weight of 60 |
| resources/views/pages/{feature} | Livewire 4 page components using existing SFC convention | Page state, calls to forms/actions/queries, Flux composition | Business algorithms and unscoped database access | resources/views/pages/pantry/⚡index.blade.php |
| tests/Unit/{feature} | Fast isolated domain tests | Quantity, scaler, allocator, scoring, formatter tests | HTTP or database assertions | QuantityTest |
| tests/Feature/{feature} | User-visible behavior through Laravel/Livewire | Validation, policy, persistence, page interactions | Direct testing of private implementation details | PlanDinnerTest |
| tests/Integration/Reservations | MySQL-specific transaction behavior | Locking, idempotency, decimal persistence | General UI assertions | CookDinnerTransactionTest |

### 8.3 Naming conventions

- Product actions use a verb phrase and a public handle method: PlanDinner::handle.
- Models are singular; tables are plural snake_case.
- Feature directories use plural domain names where natural.
- Backed enum cases use TitleCase, for example PlannedDinnerStatus::Planned.
- User-facing routes are plural and named with dot notation, for example recipes.index.
- Livewire pages follow the installed pages namespace and lightning-bolt naming.
- Existing package-mandated methods such as Fortify CreateNewUser::create remain unchanged.

## 9. Responsibilities of each layer or component

### 9.1 Livewire pages

Livewire pages are delivery adapters. They:

- Hold UI state or delegate complex state to a Livewire Form.
- Validate at submission and optionally on blur for user feedback.
- Authorize every mutation.
- Load display data through user-scoped Eloquent queries or query objects.
- Call exactly one top-level action for a business mutation.
- Translate expected domain failures into field errors or Flux callouts.
- Render with Flux free components where available.

They do not open database transactions, calculate conversions, allocate stock, or regenerate grocery rows directly.

Small page components remain SFCs. A large component with significant PHP plus component-specific JS/CSS should be converted to a Livewire 4 multi-file component rather than growing one very large Blade file.

### 9.2 HTTP controllers

Controllers are used only where HTTP semantics add value: a future JSON API, file delivery, or non-Livewire form endpoint. Controller methods stay thin, use Form Requests, authorize resources, call actions/queries, and return redirects, views, or API Resources.

The application does not create controllers merely to place a class between a Livewire page and an action.

### 9.3 Form Requests and Livewire Forms

- Form Requests are for controller-driven requests.
- Livewire Forms are for complex Livewire state such as a recipe with ordered ingredient rows.
- Simple Livewire pages may define a rules method locally.
- Both parse supported decimal, simple-fraction, mixed-fraction, and common Unicode-fraction input before creating QuantityInput.
- Both validate structure and ownership references, then the action/domain layer rechecks business invariants.

Validated arrays are acceptable for small flat inputs. Data objects are used when nested quantity/ingredient rows would otherwise require undocumented array shapes across several layers.

### 9.4 Actions

Actions define application use cases. They:

- Receive explicit User/model/data arguments.
- Use constructor injection.
- Authorize defensively when callable from more than one adapter.
- Open transactions for multi-record changes.
- Lock rows when inventory can race.
- Call pure calculation services.
- Persist state through Eloquent.
- Return a model or result object.

There is no generic IngredientService or DinnerPlanManager. Class names describe concrete operations.

### 9.5 Eloquent models

Models define:

- Fillable/hidden attributes following the current attribute-based model convention.
- Typed casts, including backed enums, immutable dates, booleans, and decimal strings.
- Relationships with return types.
- Reusable local scopes.
- Local state questions and transitions.
- Computed quantities that do not query unexpectedly in a loop.

Models do not reach into the authenticated session, render UI, or coordinate unrelated aggregate roots.

### 9.6 Domain services and value objects

Services exist for algorithms that do not naturally belong to one entity:

- QuantityInputParser
- UnitConverter
- QuantityFormatter
- RecipeScaler
- PantryAllocator
- RecommendationEngine
- GroceryCalculator

They accept explicit inputs and return values/result objects. Their unit tests need no HTTP request and, wherever possible, no database.

### 9.7 Queries

Query objects are selective, not a repository layer. They are introduced for:

- Recommendation candidate loading and scoring input.
- Pantry totals/reserved/available aggregation.
- Grocery list display with dinner contributions.
- Recipe search with categories/tags and pagination.

Simple model lookups remain in the calling page/action. Queries must eager-load required relationships, select only needed columns where useful, and apply explicit ordering.

### 9.8 Repositories

**MVP decision: no Eloquent repository interfaces.**

Eloquent already provides persistence abstraction, relationships, scopes, factories, and transaction integration. Wrapping every model in CRUD repositories would duplicate Laravel and obscure query capabilities.

A repository or gateway interface is justified only at an external boundary, for example a future RecipeImportSource implemented by multiple remote providers. Reconsider if a module must support a second persistence mechanism or complex persistence operations need a stable interface used by multiple callers.

### 9.9 API resources and view models

No API layer is required for the MVP. Livewire pages can render Eloquent models or purpose-built result data. RecommendationResult and IngredientMatch are domain/read result objects, not JSON API resources.

If a public/mobile API is approved, add versioned controllers and Eloquent API Resources under App\Http\Resources\Api\V1, reusing the same actions and policies.

## 10. Domain model overview

### 10.1 Terminology

- **Recipe** is the reusable definition: name, default servings, instructions, and ingredients.
- **PlannedDinner** is one selected occurrence of a Recipe for a chosen serving count.
- **Dish** is acceptable UI language but is not a separate model. Creating both Dish and Recipe would duplicate the same concept.
- **PantryEntry** is a user-owned stock balance in one compatible representation.
- **Ingredient availability** uses two separate fields on the user-owned Ingredient: is_staple describes normal household behaviour and is_currently_available is the temporary availability override. The state is not duplicated on PantryEntry.
- **IngredientReservation** allocates part of a PantryEntry to one planned requirement.
- **PlannedDinnerRequirement** is an immutable-at-selection snapshot of what that dinner needs.
- **IngredientPackage** is an architecture term introduced for the reusable package definition implied by the epic, such as one can containing 400 g.

Availability truth table:

| is_staple | is_currently_available | Calculation behaviour |
| --- | --- | --- |
| true | true | Assume covered without exact stock; do not reserve/deduct/generate. |
| true | false | Treat as missing; generate exact shortfall or Required non-exact check. |
| false | true | Use normal PantryEntry totals/reservations. |
| false | false | Ignore recorded stock temporarily and treat requirements as missing. |

### 10.2 Entity relationships

~~~mermaid
erDiagram
    USER ||--o{ INGREDIENT : owns
    USER ||--o{ RECIPE : owns
    USER ||--o{ PANTRY_ENTRY : owns
    USER ||--|| DINNER_PLAN : has

    INGREDIENT ||--o{ INGREDIENT_ALIAS : has
    INGREDIENT ||--o{ INGREDIENT_PACKAGE : defines
    INGREDIENT ||--o{ RECIPE_INGREDIENT : referenced_by
    INGREDIENT ||--o{ PANTRY_ENTRY : stocked_as

    RECIPE ||--o{ RECIPE_INGREDIENT : contains
    RECIPE ||--o{ RECIPE_STEP : contains
    RECIPE }o--o{ RECIPE_CATEGORY : categorized_by
    RECIPE }o--o{ TAG : tagged_with

    DINNER_PLAN ||--o{ PLANNED_DINNER : contains
    RECIPE ||--o{ PLANNED_DINNER : selected_as
    PLANNED_DINNER ||--o{ PLANNED_DINNER_REQUIREMENT : snapshots
    PLANNED_DINNER_REQUIREMENT ||--o{ INGREDIENT_RESERVATION : allocated_by
    PANTRY_ENTRY ||--o{ INGREDIENT_RESERVATION : supplies

    DINNER_PLAN ||--|| GROCERY_LIST : generates
    GROCERY_LIST ||--o{ GROCERY_ITEM : contains
    GROCERY_ITEM ||--o{ GROCERY_ITEM_CONTRIBUTION : explains
    PLANNED_DINNER_REQUIREMENT ||--o{ GROCERY_ITEM_CONTRIBUTION : contributes
~~~

### 10.3 Core entities and invariants

| Entity | Important data | Invariants and behavior |
| --- | --- | --- |
| User | Existing identity/auth fields | Owns all MVP aggregate roots; product data is inaccessible to other users. |
| Ingredient | user_id, name, normalized_name, category, preferred group/unit, is_staple, is_currently_available, archived_at | Name is unique per user after normalization; preferred unit must match its group. Defaults are non-staple/currently available. An available staple needs no exact stock; unavailable state masks stock without deleting it. |
| IngredientAlias | ingredient_id, name, normalized_name | Alias is unique within the owning ingredient/user catalogue and resolves to exactly one Ingredient for that user. |
| IngredientPackage | ingredient_id, package type, label, content amount/unit/normalized amount nullable | Known contents must be mass or volume compatible; unknown packages compare only by the same package definition ID. |
| Recipe | user_id, name, description, default_servings, times, difficulty, cuisine, meal_type, notes, image_path, source_url, archived_at | Name/default servings/one ingredient/one step are required. Other metadata is nullable without invented defaults. Archived recipes are excluded from editing/recommendations but may be planned through the archive/history flow using snapshots. |
| RecipeIngredient | recipe_id, ingredient_id, quantity type, entered amount/unit, normalized amount, compatibility key, package ID, description, non_exact_status, position | Exact rows require positive amount and compatible unit. Non-exact rows require a description and NonExactStatus::Required or Optional, with no numeric calculation amount. |
| RecipeStep | recipe_id, instruction, position | Position is unique within recipe; blank steps are rejected. |
| PantryEntry | user_id, ingredient_id, display unit, total normalized amount, compatibility key, package ID, merge key | Total is non-negative; any reduction/removal reconciles reservations so their sum never exceeds the resulting total; compatible additions merge according to representation-aware merge key. |
| DinnerPlan | user_id | Exactly one rolling MVP list per user; there is no week/name/current-plan selector. Planned rows form the active list and Cooked/Cancelled rows form history. |
| PlannedDinner | dinner_plan_id, recipe_id nullable, recipe name/metadata snapshot, servings, planned_date, status, position, cooked_at, cancelled_at, restored_at | Same recipe/snapshot may appear more than once; servings is positive; Planned may become Cooked or Cancelled, Cancelled may be restored to Planned through a reallocation action, and Cooked is terminal. |
| PlannedDinnerRequirement | planned_dinner_id, source recipe ingredient nullable, ingredient/display/base/scaled snapshots, compatibility key, missing amount, unresolved_at_cooking, unresolved reason | Snapshot survives recipe edits/archive/deletion policy changes; missing is never negative; non-exact rows have Required/Optional status and no exact reservation. |
| IngredientReservation | requirement_id, pantry_entry_id, normalized amount | Amount is positive and compatible; one requirement/entry pair is unique; summed reservations never exceed pantry total. |
| GroceryList | dinner_plan_id, regenerated_at | Exactly one for the user's rolling DinnerPlan. |
| GroceryItem | grocery_list_id, ingredient_id nullable, source, generation_key nullable, calculated amount/description, temporary override amount/unit, is_manually_adjusted, category, checked_at, previous amount, quantity_increased_at | Generated and manual items are distinguishable. Generated overrides clear on recalculation; an increased calculated quantity unchecks the row and records enough change state to explain why. |
| GroceryItemContribution | grocery_item_id, requirement_id, normalized contribution nullable | Explains which planned dinner created each grocery quantity/check item. |

The rolling DinnerPlan is a conceptual singleton and has a unique user_id. EnsureDinnerPlan creates it idempotently during first product onboarding/access (and may also be called after registration) so existing starter users are supported without a schema migration that mixes data backfill into DDL.

### 10.4 Quantity value concepts

#### Quantity

App\ValueObjects\Quantity represents only an exact measurable quantity. It contains a decimal string, UnitCode, MeasurementGroup, CompatibilityKey, and normalized base amount. It is immutable and supports compare, add, subtract, and scale only when compatibility is proven.

The value object never silently converts:

- mass to volume,
- count units to mass,
- whole items to slices,
- cloves to bulbs,
- exact values to non-exact descriptions.

#### Non-exact requirement

“Salt to taste” is not represented as Quantity with zero. RecipeIngredient and PlannedDinnerRequirement use QuantityType::NonExact plus a required description. This keeps non-exact rows out of recommendation scoring and exact reservations while allowing explanatory display and grocery check items.

App\Enums\NonExactStatus has exactly Required and Optional. Required non-exact ingredients are covered by an available staple or positive pantry presence while the ingredient is currently available; otherwise they generate a grocery check and may appear in unresolved-cooking confirmation. Optional ingredients do not count as missing, affect scoring, or generate groceries; the UI may show them separately and users may add them manually.

#### CompatibilityKey

CompatibilityKey identifies a calculation bucket:

- mass
- volume
- count:{ingredient_id}:piece
- count:{ingredient_id}:clove
- count:{ingredient_id}:bulb
- count:{ingredient_id}:slice
- count:{ingredient_id}:leaf
- count:{ingredient_id}:stalk
- count:{ingredient_id}:sprig
- package:{ingredient_package_id} for a package without known metric contents

Every comparison also requires the same Ingredient. Count compatibility explicitly includes ingredient plus semantic unit, so tomato pieces cannot match another ingredient's pieces and tomato slices cannot match tomato pieces. Known packages normalize to their content compatibility key for calculation while retaining package identity for display. For example, a 400 g can of tomatoes participates in tomato-mass comparisons but can still render as “1 can — 400 g”.

#### Unit definitions

UnitCode and MeasurementGroup are PHP backed enums because MVP units are a closed, code-governed set. Conversion factors and display metadata are code/configuration, not user-editable database rows. IngredientPackage remains data because package sizes are user/catalogue definitions.

Supported initial units:

- Mass: mg, g, kg; base g.
- Volume: ml, l, tsp, tbsp; base ml; 5 ml per tsp and 15 ml per tbsp.
- Semantic count: piece, clove, bulb, slice, leaf, stalk, sprig; each is ingredient-specific and not convertible to another count unit or mass.
- Package type labels: can, jar, pack, bag, bottle.

Adding a genuinely user-configurable unit catalogue is a future option. Do not create database-managed conversion formulas for the MVP.

#### Display policy

QuantityFormatter retains full calculation precision but normally renders at most two decimal places and removes trailing zeroes. Common fractions may be used for count/package display. Recipe and pantry views show meaningful known-package context plus normalized metric content, for example “2 cans × 400 g — 800 g total”. Generated groceries lead with the exact metric requirement and may add source context such as “equivalent to 1.5 × 400 g cans”; they never round up to supermarket packages.

## 11. Database and persistence strategy

### 11.1 MySQL and Eloquent

MySQL remains the authoritative store. Eloquent is used directly through models, relationships, scopes, and focused queries. Every aggregate root has user ownership either directly or through a required parent.

Use Laravel migrations generated by Artisan, foreign keys, reversible down methods, and indexes matching actual filters, joins, uniqueness, and ordering.

### 11.2 Decimal representation

**MVP decision: DECIMAL(18,6) columns plus decimal strings and BCMath in PHP.**

- Never cast a domain amount to float or double.
- Eloquent decimal casts return strings and may be used for persistence access.
- App\ValueObjects\Quantity performs arithmetic using BCMath at calculation scale 6.
- Add ext-bcmath to Composer platform requirements when the measurement implementation begins; it is already present in the Sail PHP 8.5 image.
- Round only at explicit domain boundaries using one documented half-up policy.
- Re-scaling always starts from the stored base recipe amount, not a previously scaled result.

Six decimal places support mg normalized to g, common cooking fractions, and repeated serving ratios while remaining readable. Reconsider the scale only if real inputs require more precision; changing scale requires a migration and calculation test review.

### 11.3 Entered, display, and normalized fields

An exact RecipeIngredient stores:

- entered_amount: the canonical decimal string produced from the user's decimal or fraction input;
- entered_unit: the selected UnitCode;
- normalized_amount: the base calculation amount;
- compatibility_key: the bucket used for safe comparison;
- ingredient_package_id: an optional package definition.

PlannedDinnerRequirement snapshots the source display metadata plus its base and scaled normalized values. IngredientReservation stores the exact normalized allocated amount and compatibility metadata; its related requirement, pantry entry, and formatter provide readable display.

PantryEntry is an aggregate balance, so it stores total_normalized_amount, compatibility_key, merge_key, and a compatible display_unit. Direct compatible quantities merge into a metric/count balance: after adding 1 kg and 500 g there is one 1,500 g balance. Package entries use IngredientPackage in the merge key, including known packages, so two 400 g cans can remain “2 cans × 400 g” while allocation aggregates their 800 g with other compatible tomato-mass entries. Different known package definitions remain separate display rows but jointly satisfy metric requirements. Unknown packages match only the same package definition. Preserving individual purchase lots would require a ledger/lot model, which expiry/audit requirements do not justify for the MVP.

The server derives all normalized and merge fields; clients never submit them as trusted values. The intentional duplication on recipe/snapshot rows preserves understandable editing, while normalized values make comparisons and indexes practical.

### 11.4 Table outline and constraints

This is a design outline, not migration code.

| Table | Key constraints and indexes |
| --- | --- |
| ingredients | FK user_id cascade; unique user_id + normalized_name; index user_id + archived_at; is_staple default false/is_currently_available default true mirrored in model attributes; boolean indexes only if measured |
| ingredient_aliases | FK ingredient_id cascade; unique ingredient_id + normalized_name |
| ingredient_packages | FK ingredient_id cascade; index ingredient_id + package_type; positive content check when content exists |
| recipes | FK user_id cascade; index user_id + archived_at + name; positive default_servings check |
| recipe_ingredients | FK recipe_id cascade; FK ingredient_id restrict; unique recipe_id + position; index ingredient_id; exact/non-exact check constraints where practical |
| recipe_steps | FK recipe_id cascade; unique recipe_id + position |
| recipe_categories and tags | FK user_id cascade; unique user_id + normalized_name |
| category_recipe and recipe_tag | Composite primary/unique keys and cascading parent FKs |
| pantry_entries | FK user_id cascade; FK ingredient_id restrict; unique user_id + ingredient_id + merge_key; index user_id + compatibility_key |
| dinner_plans | FK user_id cascade; unique user_id for MVP |
| planned_dinners | FK dinner_plan_id cascade; nullable FK recipe_id null on permanent recipe deletion; snapshot columns required; indexes plan_id + status + planned_date + position and plan_id + status + created_at |
| planned_dinner_requirements | FK planned_dinner_id cascade; nullable source recipe ingredient FK null on delete; snapshot/non-exact/unresolved-at-cooking columns; index ingredient_id + compatibility_key |
| ingredient_reservations | FK requirement_id cascade; FK pantry_entry_id restrict; unique requirement_id + pantry_entry_id; index pantry_entry_id |
| grocery_lists | FK dinner_plan_id cascade; unique dinner_plan_id |
| grocery_items | FK grocery_list_id cascade; nullable ingredient FK restrict; unique list_id + source + generation_key for generated rows; calculated/override/change columns; indexes list_id + checked_at and category |
| grocery_item_contributions | FK grocery_item_id cascade; FK requirement_id cascade; unique item_id + requirement_id |

MySQL nullable uniqueness must be considered when implementing manual grocery items. Generated rows should always have a non-null generation_key; manual rows may have null and are not deduplicated automatically.

### 11.5 Ownership strategy

For the MVP, Ingredient, Recipe, PantryEntry, and DinnerPlan are directly user-owned. Children inherit ownership through required parents. Queries use explicit ownership constraints such as whereBelongsTo($user) or parent-scoped bindings; a hidden global user scope is avoided because it can conceal data in maintenance jobs and tests.

Future shared households should add a Household and membership model, create one personal household per existing user, backfill household_id, and then update policies and queries. A polymorphic owner_type/owner_id is deliberately postponed because it would complicate every MVP query without a current shared-ownership requirement.

### 11.6 Archive and delete behavior

- Recipes and Ingredients use explicit archived_at business state rather than destructive deletion when referenced.
- Archived recipes are excluded from ordinary editing/recommendations. Archive/history actions may snapshot an archived recipe into a new dinner, duplicate a historical occurrence, or restore the recipe to the catalogue.
- PlannedDinner stores recipe metadata and requirement snapshots so history remains accurate if the source recipe is archived or later permanently removed.
- Permanent recipe deletion is not a normal MVP UI operation. Any future administrative deletion removes its image through Storage and nulls snapshot source references without destroying dinner history.
- PlannedDinner cancellation retains the occurrence and releases reservations.
- Direct PantryEntry deletion is rejected while reservations exist; RemovePantryEntry requires confirmation, releases/reconciles affected reservations, then deletes safely.
- Self-service account deletion, export, and configurable retention are postponed. A future explicit deletion/anonymisation action must handle files and owned rows transactionally; the schema must not make export or anonymisation impossible.
- Deployed migrations are immutable; changes use new migrations.

### 11.7 Transactions and locking

Use DB::transaction with a small deadlock retry count for reservation-sensitive actions. Use pessimistic lockForUpdate for:

- the PlannedDinner being changed/cooked and all affected active Planned rows,
- affected PantryEntry rows,
- existing reservations being released or consumed,
- the rolling DinnerPlan/GroceryList when regeneration writes generated items.

Lock rows in ascending primary-key order to reduce deadlocks. Never call an external HTTP service or send mail while holding the transaction.

The following operations are transaction boundaries:

- Add, reduce, or remove pantry stock and change ingredient availability/staple state.
- Plan from catalogue/archive/history or duplicate a dinner.
- Change planned servings, date, or rolling-list position.
- Cancel, restore, or remove a planned dinner.
- Mark a dinner cooked.
- Regenerate generated grocery items when invoked by those operations.

Simple recipe/ingredient CRUD may use ordinary Eloquent writes unless multiple dependent rows must be replaced atomically.

### 11.8 Persistence trade-offs

Storing normalized and display values adds columns and requires disciplined writes. The alternative—normalizing every query from arbitrary entered units—would make aggregate matching, locking, and indexing error-prone. All creation/update actions therefore centralize quantity persistence so the representations cannot drift.

## 12. Validation and error-handling strategy

Validation has two complementary levels. Boundary validation tells a user whether submitted data has the right shape. Domain validation prevents invalid state regardless of whether an action was called from Livewire, an Artisan command, a test, or a future API.

### 12.1 Boundary validation

- Livewire screens use dedicated App\Livewire\Forms form objects once a form has more than a few fields or is reused. Small single-field interactions may validate directly in the component.
- Conventional HTTP endpoints use App\Http\Requests Form Requests. Controllers must call validated or safe rather than pass request input wholesale.
- Validation rules reference enums and model ownership. An ID existing in a table is not enough; the record must also belong to the authenticated user when it is user-owned.
- QuantityInputParser accepts decimal comma or point, simple fractions such as 1/2, mixed fractions such as 1 1/2, and supported Unicode fraction glyphs. It rejects division by zero, ambiguous thousands separators, unsupported expressions, and values that exceed calculation precision.
- Every collection has explicit maximum sizes, and every string has an explicit maximum length.
- Public error messages are translatable and identify the field or operation without revealing SQL, stack traces, or other users' records.

Representative rules include:

| Input | Boundary rules |
| --- | --- |
| Ingredient | Required name, normalized per-user uniqueness, compatible preferred unit, is_staple, and is_currently_available booleans |
| Exact quantity | Positive parseable decimal/fraction input, normalized scale no greater than six, supported unit, ingredient/unit compatibility |
| Non-exact quantity | Explicit non-exact type, required description, Required/Optional status; amount and unit must be absent |
| Package quantity | Positive package count plus an IngredientPackage belonging to the same ingredient |
| Recipe | Required name, positive default servings, at least one ingredient and one instruction; optional metadata remains nullable; unique positive positions |
| Pantry adjustment | Non-negative resulting total and compatible unit/package; reductions/removals require locked reservation reconciliation and explicit removal confirmation where applicable |
| Planned dinner | Owned active recipe, authorized archived recipe/archive snapshot, or stored historical snapshot; positive servings, optional date, allowed state/action transition |
| Generated grocery edit | Positive amount and compatible unit for the generated ingredient/key; writes only temporary override fields |

Recipe description, preparation/cooking time, cuisine, meal type, difficulty, categories, tags, notes, image, and source URL are optional. Missing metadata stays null/unknown: it does not block recommendations and is not assigned a guessed default, but the recipe cannot match a filter for a value it lacks. A missing image uses a standard presentation placeholder rather than a fake stored path.

The client may provide immediate feedback, but server-side validation remains authoritative.

### 12.2 Domain invariants

Value objects, model methods, and actions re-check invariants that matter to stored state. Examples are:

- Quantity never contains a float, zero/negative amount, unsupported scale, or incompatible measurement group.
- PlannedDinner follows Planned to Cooked or Cancelled; Cancelled may return to Planned only through RestoreCancelledDinner, while Cooked remains terminal.
- A reservation never exceeds the entry's available quantity.
- Cooking consumes each reservation at most once.
- An archived recipe is not editable/recommended or selectable from the ordinary active catalogue, but archive/history actions may snapshot it into a new occurrence without depending on later live changes.
- A recipe requirement and an ingredient package must reference the same ingredient.

Do not duplicate all UI rules in every layer. Re-check only rules required for correctness or security, and express calculations through shared value objects/services.

### 12.3 Exception and user-error policy

Expected business conflicts use small, named exceptions in App\Exceptions, such as InvalidDinnerTransition, PantryRemovalRequiresConfirmation, or UnresolvedRequirementsRequireConfirmation. The calling Livewire component converts these into a form, modal, or banner result. Authorization failures remain AuthorizationException responses, missing owned records remain 404 where appropriate, and unexpected failures are allowed to reach Laravel's exception handler and logs.

| Failure | User response | Log level |
| --- | --- | --- |
| Invalid input | Field-level 422-style validation feedback | None |
| Stale pantry availability during selection | Clear retry/change-servings message | Notice or structured info |
| Unresolved cook without current confirmation | Structured confirmation modal with missing/incompatible/unchecked summary | None |
| Forbidden owned resource | 403, or 404 when concealing existence | Security context at warning only if suspicious/repeated |
| Deadlock after configured retries | Generic retry message | Error with operation IDs |
| Integration/queue failure | Non-blocking status and retry where possible | Error |
| Programming/database failure | Generic error page; no internals | Error with trace |

Actions must not catch Throwable merely to return false. A transaction rolls back on an exception; the presentation boundary decides how an expected exception is displayed.

## 13. Authentication and authorization strategy

### 13.1 Authentication

Laravel Fortify remains the authentication backend and the existing Livewire/Flux pages remain the UI. The present features—registration, password reset, password confirmation, passkeys, and two-factor authentication—are compatible with this architecture and should not be replaced.

Email verification is enabled in config/fortify.php, App\Models\User implements Illuminate\Contracts\Auth\MustVerifyEmail, and the dashboard route uses verified middleware. Feature coverage proves an unverified user is redirected to the verification notice.

MVP product routes require auth and verified middleware. Sensitive account operations continue to use password confirmation and Fortify's existing rate limits.

### 13.2 Authorization

Use Laravel policies for Ingredient, Recipe, PantryEntry, DinnerPlan, PlannedDinner, and GroceryList. Ownership is checked through the aggregate root:

- ingredient, recipe, pantry entry, and dinner plan: user_id equals the current user's ID;
- planned dinner: its DinnerPlan belongs to the user;
- grocery list/item: its DinnerPlan/GroceryList belongs to the user;
- recipe requirement or reservation: authorize through its owned parent rather than creating public routes for child IDs.

Livewire public methods call authorize before loading or mutating protected state. HTTP controllers use authorize or policy-aware route binding. Actions accept an already authorized model/user context but still scope all lookups by owner so a missed presentation check cannot turn a foreign ID into a mutation.

Do not use an implicit global user scope on every model. Explicit owner relationships and query constraints are easier to audit, test, and later adapt to households. Do not add roles or a permissions package for the single-user MVP.

### 13.3 Future shared households

Shared households are outside the MVP. If introduced, add a Household aggregate and membership/role policy, migrate existing user-owned data into one household per user, then change ownership foreign keys deliberately. Avoid a premature polymorphic owner_type/owner_id abstraction now: it would complicate every query and foreign key without serving an MVP use case.

## 14. Events, jobs, queues, and notifications

### 14.1 Synchronous core, asynchronous edges

Planning, reserving, consuming, and grocery regeneration are consistency-critical and remain synchronous inside their application actions. A successful UI response therefore means the transaction is complete. Recommendation scoring is also synchronous and pure for MVP-scale data.

The epics' “recalculate recommendations” requirement does not require a RecalculateRecommendations event because rankings are not persisted: every recommendation query reads current available stock. Grocery rows are persisted for shopping state, so their regeneration is called synchronously by the initiating action.

Events are not used to hide the core workflow. Introduce a past-tense event only when it has at least one real, independent after-commit consumer. Likely examples are:

- DinnerCooked, consumed later by analytics or an activity feed;
- PantryLowStockDetected, consumed by an opt-in notification;
- GroceryListChanged, consumed by a future real-time or integration adapter.

An event payload contains stable scalar IDs, not a large serialized Eloquent object graph. Events that could be dispatched from a transaction must be emitted after commit so listeners cannot observe rolled-back state.

### 14.2 Jobs and queues

Jobs are appropriate for slow, retriable, externally visible work: image processing, importing a future recipe source, sending reminders, exporting data, or syncing a grocery provider. Jobs must:

- be idempotent or carry a unique operation key;
- reload and re-authorize/validate current state rather than trust stale serialized models;
- define timeouts, retry/backoff, and failed-job behaviour;
- log correlation IDs and the owned aggregate ID;
- be dispatched after the database commit when dependent on new state.

Do not queue reservation allocation, dinner cooking, or core grocery calculation. Queue failure must not leave the application's truth half-applied.

The repository currently uses the sync queue connection and has no worker service in compose.yaml. Database queue configuration and tables remain available but inactive. Before the first queued feature, enable after-commit dispatch at the connection or job/event level, add supervised worker configuration, and test failure and retry paths.

### 14.3 Notifications

There is no required notification in the epics. Postpone notification classes for the MVP. Likely opt-in additions are a dinner reminder and a low-stock/grocery-ready notification. Notifications should use Laravel channels and user preferences; they must not be emitted for every recalculation by default.

## 15. Recommendation and pantry-consumption workflow

This workflow is intentionally split into a read-only recommendation calculation and transactionally safe planning/consumption commands. A recommendation is advice based on a snapshot; PlanDinner rechecks availability while holding locks.

### 15.1 Deterministic recommendation calculation

GetPantryAwareRecommendations loads active recipes with ordered requirements, ingredients, packages, and the current user's pantry availability in bounded eager-loaded queries. RecommendationEngine then calculates in memory using decimal strings.

For each scaled requirement:

1. Scale from the immutable recipe amount and original servings, never from a previously rounded display amount.
2. Treat an ingredient as unlimited staple coverage only when is_staple and is_currently_available are both true. An unavailable staple is missing; a non-staple marked unavailable ignores recorded pantry stock until re-enabled.
3. For an exact comparable requirement, calculate coverage ratio as min(available / required, 1).
4. For a non-exact requirement, report Required/Optional status and coverage as explanatory information only. Exclude it from every scoring term and exact reservation.
5. For a known-content package, convert package count to its declared metric content.
6. For an unknown-content package, compare only the identical IngredientPackage definition. It is incompatible with grams, millilitres, or a different package definition.
7. Record missing, partially covered, and incompatible requirements for explanation.

Use one documented score for MVP:

ranking score = clamp(60Q + 20F − 10P − 10M − 10I, 0, 80)

Where:

- Q is mean exact quantity coverage;
- F is the proportion of exact ingredient lines fully available;
- P is the proportion partially available;
- M is the proportion completely missing;
- I is the proportion with incompatible measurements.

All factors are zero-to-one proportions over exact measurable requirements. The categories F/P/M/I are mutually exclusive; incompatible lines contribute zero quantity coverage. An available staple counts as fully covered. Non-exact lines are excluded. The ranking value is deliberately a score, not a pantry-coverage percentage; display quantity coverage separately. If a recipe has no exact requirements, its score is zero and the explanation says it cannot be quantity-matched. Rank by descending score, then fewer incompatible/missing/partial exact lines, recipe name, and recipe ID. Round only the displayed score.

The weights, including the initial 10-point incompatibility penalty, live once in config/recommendations.php and are covered by golden examples. They are an accepted MVP starting point but remain adjustable after user testing. Do not duplicate them in controllers/queries or add AI, embeddings, a rules engine, or persisted recommendation rows.

Each recommendation view model includes enough explanation to answer “why?”:

- scaled servings;
- coverage score;
- fully available ingredients;
- partial and missing amounts;
- incompatible unit/package cases;
- available and temporarily unavailable staples;
- Required/Optional non-exact items, clearly excluded from scoring.

### 15.2 Planning and reservation allocation

There is one rolling DinnerPlan for each user. Its Planned occurrences are the active ordered list; Cooked and Cancelled occurrences remain queryable as history, so no plan selector, plan name, or week boundary exists.

PlanDinner runs a database transaction for an active catalogue recipe:

1. Authorize the recipe and rolling plan; ordinary catalogue planning requires an active recipe.
2. Lock the rolling plan, affected Planned rows, relevant pantry entries, and reservations in stable order.
3. Create the PlannedDinner in Planned state and snapshot recipe identity/metadata.
4. Snapshot scaled ingredients into PlannedDinnerRequirement, including Required/Optional non-exact status.
5. Reconcile every affected active requirement in dinner-priority order.
6. Store each exact shortfall and required non-exact coverage state.
7. Regenerate generated grocery items/contributions and return changed-item notices.

Dinner priority is:

1. dated dinners before undated dinners;
2. planned_date ascending;
3. rolling-list position ascending;
4. created_at ascending;
5. primary key ascending as a final deterministic tie-break.

For each prioritized requirement, PantryAllocator uses exact native compatibility first, then convertible known-content packages, then PantryEntry ID. Manual allocation is postponed; users influence priority through dates and list reordering.

Every operation that can change supply, demand, or priority—stock add/reduce/removal, ingredient availability/staple state, dinner add/restore/remove/cancel/cook, servings/date/position changes—runs ReconcilePlanReservations for affected ingredients. It releases affected active reservations and recalculates them from the beginning in priority order; it does not preserve allocations that now belong to an earlier dinner. Reductions may lower stock below the old reserved amount because reconciliation first resolves those reservations, but the resulting total and available balances may never be negative. Removing a pantry entry with reservations requires explicit confirmation and the same reconciliation.

Recipe edits do not change an existing planned dinner because recipe metadata and requirements are snapshots. Changing servings recalculates from the snapshot's original amounts, never a rounded scaled result.

### 15.3 Historical snapshots, duplication, and restoration

Archived recipes are excluded from ordinary recommendations/editing. PlanArchivedRecipe may snapshot an owned archived recipe from the archive screen, and PlanDinnerFromHistory may copy a Cooked/Cancelled snapshot without relying on the live recipe. Either flow may offer a separate RestoreRecipeToCatalogue action.

DuplicatePlannedDinner creates an independent Planned occurrence with the same recipe snapshot, serving count, ingredient configuration, and optional date when requested. It never copies reservations, shortfalls, checked groceries, or terminal status; requirements are recalculated and reservations are allocated from current stock.

RestoreCancelledDinner changes only a Cancelled occurrence back to Planned, records restored_at, recalculates requirements from its snapshot, and performs full priority reconciliation. Old reservations are never restored blindly. Repeating a restore on an already Planned occurrence is idempotent; Cooked occurrences cannot be restored.

### 15.4 Cooking, cancellation, and removal

MarkDinnerCooked locks the PlannedDinner, affected pantry/reservation rows, and grocery contributions, then refreshes unresolved state. Unresolved means an exact shortfall whose generated grocery contribution is absent/unchecked, an incompatible exact requirement, or an unavailable Required non-exact item whose check item is absent/unchecked. If unresolved state exists and confirmation was not supplied, the action throws UnresolvedRequirementsRequireConfirmation with a structured summary for the Livewire confirmation modal.

After explicit confirmation—or immediately when fully covered—the action subtracts exactly the reserved amounts from pantry totals, removes/consumes those reservations, stores unresolved_at_cooking details on requirement snapshots, sets Cooked with cooked_at, reallocates remaining stock to later Planned dinners, and regenerates groceries. Missing quantities are never deducted. The confirmation is revalidated inside the locked transaction so a stale modal cannot approve different hidden state.

CancelDinner releases reservations without changing pantry totals, sets Cancelled, reallocates later dinners, and regenerates groceries; the occurrence remains in history and may be restored. RemovePlannedDinner releases/reallocates in the same way but permanently removes an unprocessed occurrence from the rolling list. Repeating Cook/Cancel/Remove safely returns the existing result where possible, and a Cooked dinner can never deduct twice.

### 15.5 End-to-end workflow

~~~mermaid
flowchart TD
    A[User chooses servings] --> B[Load recipes and current pantry availability]
    B --> C[Scale immutable recipe requirements]
    C --> D[RecommendationEngine scores and explains coverage]
    D --> E{User selects recipe?}
    E -- No --> A
    E -- Yes --> F[PlanDinner transaction]
    F --> G[Lock plan and compatible pantry entries]
    G --> H[Snapshot planned requirements]
    H --> I[Reallocate active dinners in date and list priority]
    I --> J[Record remaining shortfalls]
    J --> K[Regenerate generated grocery items]
    K --> L[Commit PlannedDinner]
    L --> M{Later action}
    M -- Change servings, date, order, or pantry --> N[Release affected reservations and fully reallocate]
    N --> K
    M -- Cancel --> O[Release reservations]
    O --> P[Set Cancelled, reallocate, and regenerate]
    P --> T{Restore later?}
    T -- Yes --> N
    M -- Cook --> Q[Lock and summarize unresolved requirements]
    Q --> R{Unresolved and confirmed?}
    R -- No confirmation --> U[Show confirmation summary]
    U --> Q
    R -- Covered or confirmed --> V[Deduct each reservation exactly once]
    V --> S[Store unresolved history, set Cooked, reallocate, regenerate]
~~~

### 15.6 Concurrency guarantees

- Available equals total minus active reservations, calculated from locked rows inside mutation transactions.
- Reducing/removing stock first releases affected reservations and reallocates against the new non-negative total; availability never becomes negative.
- Two simultaneous planning/reordering/stock requests serialize on the rolling plan and affected pantry rows; the second recomputes the authoritative priority order.
- A unique constraint prevents duplicate reservation allocation for one planned requirement and pantry entry.
- Cooked state and timestamps, plus transaction locks, prevent double consumption; restoration is allowed only from Cancelled.
- The recommendation screen may become stale, but the planning action never promises stale quantities.

## 16. Grocery-list generation workflow

The rolling DinnerPlan has one GroceryList containing generated items and user-created manual items. Generated items are derived from active Planned requirement shortfalls; manual items are never rewritten by generation.

### 16.1 Generation algorithm

RegenerateGroceryList executes within the calling plan transaction:

1. Load active PlannedDinnerRequirement shortfalls for Planned dinners.
2. Exclude exact requirements with no shortfall. An available staple is excluded; a temporarily unavailable staple contributes its exact shortfall or a Required non-exact check item.
3. Group exact shortfalls by ingredient and compatibility key. Convertible mass/volume/count amounts use normalized units; unknown packages group only by IngredientPackage ID.
4. Group uncovered Required non-exact requirements by ingredient and normalized description, displaying a check item without inventing an amount. Exclude Optional rows.
5. Sum with BCMath and format through QuantityFormatter.
6. Upsert each generated GroceryItem by a stable generation_key, replacing any temporary user override with the new calculated result.
7. Replace its GroceryItemContribution rows with the exact planned requirements and amounts that produced it.
8. Remove generated items whose keys no longer occur. Leave manual items untouched.

A generation key is derived from versioned, canonical data such as ingredient ID, compatibility group, normalized unit or package ID, and Required non-exact description. It is not based on display text. A materially different key is a new unchecked item.

The user can therefore see that “750 g potatoes” consists of, for example, 300 g for one dinner and 450 g for another. Contribution rows are explanatory trace data, not a second source of quantity truth. SetIngredientAvailability toggles whether a staple is assumed covered and triggers reservation/grocery reconciliation without destroying pantry balances.

### 16.2 Purchase-unit behaviour

Generated quantities express the recipe shortfall in a compatible metric/count/package unit. The MVP does not optimize how many retail packs to buy, compare prices, or round to supermarket quantities. Unknown packages remain counts of the exact package definition. For known packages, groceries primarily show the exact normalized metric need and may add equivalent source-package context; they never round 1.5 packs to 2.

Manual GroceryItem rows are fully editable and unaffected by recalculation. EditGeneratedGroceryQuantity writes a temporary override amount/unit and marks the generated row manually adjusted while leaving calculated_amount and contribution rows unchanged. The override controls display until the next relevant recalculation, at which point regeneration clears it, replaces the display with the newly calculated amount, and includes the reset item in GroceryRegenerationResult so the UI may announce that the generated list was refreshed. Generated and manual source states remain visibly distinct.

### 16.3 Idempotency and updates

Regeneration is idempotent: the same plan state produces the same generated keys, calculated amounts, and contributions. It runs after dinner add/duplicate/restore/remove/cancel/cook, servings/date/order changes, reservation changes, ingredient availability changes, and pantry adjustments.

For a same-key generated item, compare the old and new calculated amounts before clearing any override:

- equal or lower requirement: preserve checked_at;
- increased requirement: clear checked_at, store previous_calculated_amount and quantity_increased_at, and show “Quantity changed from … to …”;
- compatibility/fundamental key change: replace it with a new unchecked item.

Checked items remain in the active list until ClearCompletedGroceries. Removing a manual item deletes it. Generated items disappear when no longer required. No separate shopping-history model, completed shopping session, or visible regeneration history is created for the MVP; ordinary timestamps/change metadata are enough for debugging and the active UI.

If regeneration later becomes expensive, preserve the same pure GroceryCalculator and stable-key contract while moving only projection refresh to a queued, version-checked job. That is a future scaling option, not an MVP requirement.

## 17. Testing strategy

The test suite uses PHPUnit 12 through Laravel's test runner. Stage 1 adds focused measurement, recipe-scaling, catalogue-action, policy, route, and Livewire tests. The Stage 1-focused verification set contains 47 passing tests with 94 assertions; the pre-existing starter, authentication, settings, configuration, and health tests remain available for full-suite verification.

### 17.1 Test layers

| Test type | Purpose | Project examples |
| --- | --- | --- |
| Unit | Pure rules, no Laravel container or database | Quantity/InputParser; ingredient-specific conversion matrix; configurable recommendation fixtures; priority allocator; grocery grouping/check-state transitions |
| Application/feature | Actions, validation, policies, Eloquent relationships, and transactions | Plan/duplicate/restore archived snapshot; full priority reconciliation; unresolved cook confirmation; transient grocery override; cook exactly once |
| Livewire feature | Page behaviour, authorization, validation, and rendered state | Required recipe fields; history/restore UI; unresolved summary modal; quantity-increased notice; English/Dutch-format presentation |
| MySQL integration | Behaviour SQLite cannot prove | DECIMAL round trips; unique constraints; stable lock order; competing reorder/stock/plan requests; generated-key indexes |
| Browser/end-to-end, selective | A few high-value JavaScript/browser interactions | Complete first recipe-to-cooked-dinner journey; passkey/2FA only where browser APIs require it |

Pure services should have exhaustive data providers around boundaries. Example cases include decimal comma, small/large values, g↔kg, ml↔l, tsp/tbsp, same-ingredient/same-count compatibility, cross-ingredient/count incompatibility, known-package dual display, unknown packages, Required/Optional non-exact amounts, display rounding/fractions, and scaling without drift.

New database tests should use LazilyRefreshDatabase as required by AGENTS.md. Existing starter tests use RefreshDatabase; that difference is acceptable until those files are touched, then migrate them in small verified batches. Factories define valid defaults and named states such as archived, nonExact, staple, planned, cooked, and cancelled.

### 17.2 Epic acceptance coverage

| Epic | Minimum architectural acceptance tests |
| --- | --- |
| 1. Ingredients and measurement | Exact/non-exact creation, compatibility matrix, package definitions, decimal precision |
| 2. Recipe catalogue | Minimum required fields, nullable metadata/unknown filters, image placeholder, archive/history-snapshot planning |
| 3. Pantry | Merge/package context, add/reduce/removal reconciliation, total/reserved/available, two-field staple availability |
| 4. Serving scaling | Scale from original quantities, fraction/decimal input, no cumulative rounding |
| 5. Pantry recommendations | Configured Q/F/P/M/I weights, tie-breaks, unavailable staples, non-exact exclusion, explanations |
| 6. Dinner plan | Rolling order/date priority, duplicate/history planning, cancel/restore, Cooked terminal snapshots |
| 7. Reservation lifecycle | Full priority reallocation, partial/concurrent selection, unresolved confirmation/history, exactly-once cooking |
| 8. Grocery list | Aggregation/package context, transient generated edits, increase-unchecks/decrease-preserves, manual preservation, clearing/no history |

### 17.3 Test rules

- Assert externally meaningful state and invariants, not framework implementation details.
- Test every policy with owner, other user, and unauthenticated cases.
- Use model factories; avoid opaque shared seed data.
- Freeze time for plan dates/status timestamps.
- Use Mail/Notification/Queue/Event fakes only at application boundaries; unit-test core results directly.
- Never substitute floats into a quantity test.
- Include failure and rollback assertions, not just happy paths.
- Add a regression test before correcting any discovered production bug.
- Keep query-count or eager-loading regression tests around the recommendation and grocery screens once realistic fixtures exist.

CI should run formatting/static checks selected by the team, unit/feature tests, and a MySQL integration job. The current workflow's PHP/Node versions and missing MySQL service must be resolved before it can be treated as reliable evidence; see sections 2 and 19.

## 18. Scalability and future expansion

### 18.1 Appropriate MVP scaling

The modular monolith scales vertically and across stateless web containers while MySQL remains the consistency boundary. Session/cache/queue are already configured for database-backed operation, although a production deployment may move cache/session and queues to Redis without changing domain actions.

MVP performance depends more on sound queries than distributed architecture:

- eager-load recommendation graphs in a bounded number of queries;
- index user ownership and active/status filters;
- index recipe requirements by recipe/position;
- index pantry entries by user/ingredient/merge key;
- index planned dinners by plan/status/date;
- index active reservations by pantry entry and planned requirement;
- uniquely index generated grocery keys per list;
- paginate recipe, pantry, and history screens;
- select only required columns for list views.

Do not cache mutable pantry availability or recommendation rankings initially. Measure first. If calculation becomes material, cache a per-user recommendation projection keyed by recipe-catalogue version, pantry version, and requested servings; invalidate by version rather than broad cache scans.

### 18.2 Planned seams for growth

The proposed seams allow these additions without imposing them now:

| Future need | Existing seam |
| --- | --- |
| Shared households | Explicit owner policies and user foreign keys can migrate to Household |
| External recipe import | Import job maps external data into the same CreateRecipe action/value rules |
| API/mobile client | Actions and policies remain; add versioned controllers/requests/resources |
| Internationalisation | English strings are presentation-only; central formatters/config isolate Dutch-style dates/decimals for later locale preferences |
| Nutrition/allergens | Ingredient metadata plus calculated recipe projection |
| Expiry-aware pantry | Add pantry lot/expiry model and change allocator ordering |
| Retail pack optimization | Add purchasing/package optimization after GroceryCalculator shortfalls |
| Recommendation preferences | Replace/configure scoring strategy behind the focused engine |
| Notifications | Consume committed events with queued listeners |
| Object storage/CDN | Storage disk configuration; no direct filesystem paths in domain code |
| Search | Begin with indexed MySQL queries, add an external index only when measured |

Adding a future seam does not mean adding an interface today. Extract an interface when there is a true external boundary, multiple implementations, or a testability problem not solved by Laravel fakes.

### 18.3 Deliberately postponed

Postpone microservices, event sourcing, CQRS read databases, generic repositories, a service bus, a rules engine, multi-tenancy packages, Redis, search services, websocket updates, and AI recommendation infrastructure. None solves a current epic, and each adds deployment/failure modes. Reconsider only with measured scale, a team ownership boundary, offline integration needs, or requirements that the modular monolith demonstrably cannot satisfy.

## 19. Observability, logging, and operational concerns

### 19.1 Structured operational context

Use Laravel logging with consistent context for important commands:

- request/correlation ID;
- authenticated user ID, never email/password/passkey material;
- action name;
- dinner plan/planned dinner/recipe IDs;
- affected pantry-entry count;
- retry attempt and duration;
- generated grocery-item count.

Log state-transition summaries, not raw recipe notes or complete request payloads. Do not log ordinary validation errors. Domain conflict logs should be low-volume and structured; unexpected exceptions retain stack traces in the server log but show a generic response to users.

Database transactions may be instrumented with duration and deadlock retry counts. Add slow-query monitoring and query-count checks around recommendations/grocery calculations once representative data exists. Metrics worth adding when an operational platform is selected include request latency/error rate, recommendation calculation duration, transaction retry count, queue depth/age/failures, and MySQL connection/lock pressure.

### 19.2 Runtime operations

- Keep the framework `/up` endpoint for process health. Docker checks it directly, while MySQL readiness is enforced separately through the database container health check and migration/integration gates. Add any future dependency details only to a protected deployment mechanism.
- Run migrations as a single release step before new containers accept traffic. Never run destructive schema changes and application cutovers without a compatible transition.
- Back up MySQL and test restoration. A backup is not proven until restore verification succeeds.
- Configure failed-job retention and alerts before enabling queued features.
- Keep stored timestamps in UTC and present MVP dates/times with the English interface's Netherlands-friendly policy: Europe/Amsterdam time, DD-MM-YYYY dates, 24-hour time, and Monday-first calendar controls.
- Set production APP_DEBUG=false, rotate logs, and keep environment secrets out of images and source control.
- Document worker, scheduler, backup, migration, and rollback commands in an operations/runbook document when deployment is introduced.

### 19.3 Current operational gaps

Stage 0 added a health-checked MySQL 8.4 CI service, minimum/runtime PHP and Node matrix, deterministic Composer/npm setup, and Docker liveness/readiness checks. Remaining operational work is feature- or deployment-driven:

- Database queues remain deliberately inactive until a supervised worker, after-commit dispatch, retry policy, and monitoring are introduced.
- boost.json says sail is false and .codex/config.toml invokes host php while DB_HOST=mysql is only resolvable on the Compose network. Reconfigure this only when database-aware Boost tooling is needed.
- Production backup/restore, scheduler, migration, rollback, and worker runbooks remain part of deployment hardening.

These are incremental operational tasks, not reasons to reorganize the application.

## 20. Security considerations

Security is enforced through ordinary Laravel controls plus domain-specific ownership and transaction rules.

### 20.1 Application controls

- Require authenticated, verified sessions for product screens; the User verification contract and enforcement coverage are in place.
- Authorize every read and mutation; scope relationship queries by the current owner to prevent insecure direct object references.
- Keep CSRF protection on state-changing web requests and use POST/PATCH/DELETE semantics instead of mutating GET routes.
- Use validated field lists and explicit model fillable/guarded decisions. Do not pass unfiltered arrays to create, update, forceFill, or query ordering.
- Escape user-provided names/notes in Blade/Flux. Render Markdown or rich text only through an allow-list sanitizer if that feature is later added.
- Let Eloquent/query builder bind values. Any raw ordering or expression must come from an allow-list, never request text.
- Rate-limit authentication and any future expensive recommendation/import endpoint.
- Regenerate sessions on login/logout through Fortify's established flow; preserve secure, HTTP-only, same-site cookie settings in production.

### 20.2 Files and external integrations

Recipe images are optional. Use Laravel Storage, generated filenames, explicit size limits, server-side MIME/content verification, safe image decoding/re-encoding, and a non-executable storage location. Do not trust extensions or expose an arbitrary local path. Future integrations keep credentials in environment/secret storage, use timeouts, validate responses, and run outside database transactions.

### 20.3 Data integrity and privacy

- Fixed-precision decimal handling prevents silent quantity corruption.
- Foreign keys, unique constraints, transitions, locks, and transactions defend invariants even under concurrent requests.
- Self-service deletion/export and custom retention are outside the MVP. Keep normalized ownership, nullable snapshot source references, and Storage-managed image paths so later export, deletion, or anonymisation remains feasible.
- Backups and logs contain personal data and require the same access/retention controls as the database.
- Passkey and two-factor secrets/recovery codes stay in the existing encrypted/hashed Fortify-compatible representation; never include them in logs or DTOs.
- Dependency updates and container base images should be scanned and patched routinely.

The MVP stores meal preferences and account data but no payment or medical data. Allergen support is explicitly outside scope; until it is implemented and verified, the UI must not imply that a recommendation is safe for an allergy.

## 21. Architectural decisions and rejected alternatives

The following decisions summarize the architecture. “MVP” is necessary to implement the current epics. “Growth” is a low-cost seam that makes a likely extension safer. “Postponed” means do not build it without a new requirement.

| ID / timing | Decision and why it fits | Alternatives not selected | Trade-off and reconsideration trigger |
| --- | --- | --- | --- |
| ADR-01 / MVP | Use a Laravel-first modular monolith. One deployment and one transaction boundary fit a small team and strongly related meal-planning data. | Microservices add network consistency and operations; full Clean/Hexagonal Architecture duplicates Laravel boundaries. | Modules are not independently deployable. Reconsider only when team/deployment boundaries or scale require independent services. |
| ADR-02 / MVP | Organize conventional App directories by role, then feature subnamespace where useful. | A parallel src/Domain/Application/Infrastructure tree or package-per-module would fight generators, policies, jobs, Livewire, and framework discovery. | Some feature files are distributed across Actions, Models, and Livewire. Consistent names and the module map mitigate this; extract a package only for a genuinely reusable/bounded subsystem. |
| ADR-03 / MVP | Keep controllers/Livewire components thin; use focused verb-named actions for use cases; keep local behaviour on Eloquent models/value objects. | Large controllers, “fat model does everything,” and a single DinnerService all create mixed responsibilities. | More small classes and naming decisions. Combine only when an action is a trivial one-line model operation with no rule or reuse. |
| ADR-04 / MVP | Use Eloquent directly and add purpose-named query objects only for complex recommendation/grocery reads. | Generic repository interfaces obscure Eloquent and duplicate its API; CQRS read stores are unnecessary. | Code is coupled to Laravel/MySQL, intentionally. Reconsider a repository at a real external persistence boundary or when two implementations exist. |
| ADR-05 / MVP | Store DECIMAL(18,6), carry decimal strings in PHP, and calculate with BCMath at scale six. | PHP floats silently drift; arbitrary-precision libraries add dependencies before needed; storing minor integer units cannot represent all unit groups as clearly. | Six decimal places and arithmetic helper discipline are required. Reconsider precision after real recipe/package data proves it inadequate. |
| ADR-06 / MVP | Model fixed UnitCode rules in code/config, ingredient-plus-semantic count compatibility, and ingredient-specific package definitions in MySQL. Preserve known package context while calculating in metric content. | User-defined global conversion is unsafe; package strings lose equality/content; universal count conversion would falsely combine cloves/bulbs/slices or different ingredients. | New units require a release and package display adds stored context. Reconsider approximate count-to-mass only as explicit ingredient rules after MVP. |
| ADR-07 / MVP | Persist PantryEntry total; persist reservations; derive reserved and available. | Persisting total, reserved, and available creates three mutable truths; ledger-only inventory is more complex for the MVP. | Aggregation is required to display availability. Add cached counters only after measured query pressure and invariant-preserving updates. |
| ADR-08 / MVP | Snapshot recipe identity/metadata and PlannedDinnerRequirement data for every occurrence. History-based planning, duplication, restore, and unresolved-at-cooking all use the snapshot. | Reading live RecipeIngredient rows saves space but silently changes history and fails after archive/deletion; full recipe versioning is more machinery. | Snapshots duplicate data. Reconsider full immutable recipe versions if catalogue audit/version comparison expands. |
| ADR-09 / MVP | Use synchronous MySQL transactions and pessimistic locks for reservation, stock, cooking, and generated grocery changes. | Optimistic retry everywhere complicates the UI; queued/eventual reservation permits double allocation; distributed locks add another system. | Contention can reduce throughput. Reconsider lock granularity only with measured contention and concurrency tests. |
| ADR-10 / MVP | Calculate recommendations on demand with configurable 60Q + 20F − 10P − 10M − 10I scoring and explicit explanations. | Random ordering, opaque AI, persisted rankings, and a rules engine are not justified. | Initial weights are product-approved but tunable in one config; calculation grows with catalogue size. Reconsider/cache/version with measured latency or research. |
| ADR-11 / MVP | Derive generated groceries with stable keys/contributions, transient user overrides, increase-sensitive checked state, and no long-term shopping history; preserve manual rows. | Display-only strings lose truth; persistent generated overrides become stale; rebuilding indiscriminately loses checks/manual work; shopping sessions add premature scope. | Regeneration owns more state transitions. Add completed shopping sessions only when shopping history is a real feature. |
| ADR-12 / MVP | Separate is_staple from is_currently_available. Only an available staple is assumed covered; temporarily unavailable ingredients create shortfalls/groceries without deleting pantry totals. | Infinite quantities conflate preference and state; a single staple flag cannot represent “normally stocked but out”; deleting stock loses user data. | Two booleans need an explicit truth table and reconciliation. Reconsider richer availability reasons only with a concrete UX need. |
| ADR-13 / MVP | Use per-user ownership and explicit policies/query scoping. | Premature household tenancy/polymorphic owners burden all foreign keys and policies. | A future household migration is required. Reconsider when shared planning is approved, before significant multi-user product data exists if possible. |
| ADR-14 / MVP | Use Livewire 4 pages and Flux components, with Livewire Form objects for substantial forms. | A React/Vue SPA duplicates validation/API work; Blade-only forms make highly interactive planning less direct. | Server round trips require careful eager loading/loading states. Reconsider a separate client only when offline/native/API requirements appear. |
| ADR-15 / Growth | Emit events only for real after-commit side effects; queue only slow/retriable edge work. | Event-driven orchestration hides core control flow and makes correctness depend on workers. | The action knows its core collaborators. Split when a side effect can independently fail and eventual consistency is acceptable. |
| ADR-16 / MVP | Use MySQL as development/test production-parity database for persistence-sensitive tests. | SQLite is fast but does not prove MySQL DECIMAL, locking, or concurrency behaviour. | MySQL tests cost startup time. Pure unit tests remain fast; use targeted integration tests and parallel CI services. |
| ADR-17 / MVP | Archive ingredients/recipes with explicit archived_at and retain independent dinner snapshots. Archived recipes are excluded from editing/recommendations but archive/history actions can plan snapshots, duplicate occurrences, and optionally restore recipes. | Hard deletion breaks history; SoftDeletes everywhere hides joins; forbidding archived/history planning contradicts the rolling workflow. | Queries/actions must distinguish active-catalogue selection from archive/history snapshot planning. Reconsider full recipe versioning only with broader audit needs. |
| ADR-18 / Postponed | Do not add DTOs universally. Use immutable data/value objects only for quantities, calculation inputs/results, and unstable external boundaries. | DTO-per-request/model creates mapping code without a boundary; raw arrays are too weak for calculation contracts. | Some actions accept models and named scalars. Introduce a DTO when argument count/shape or serialization makes the contract clearer. |
| ADR-19 / MVP | Use one rolling DinnerPlan per user. Planned rows are the ordered active list; Cooked/Cancelled rows are history, and Cancelled may be restored. | Calendar weeks/named plans impose management and a current-plan selector unrelated to irregular cooking. | The plan table is a singleton aggregate and history grows under it. Reconsider multiple plans only for an approved household/calendar use case. |
| ADR-20 / MVP | Reconcile affected reservations globally in explicit-date, position, creation order whenever supply, demand, or order changes. | Preserving old allocations violates earliest-dinner priority; manual allocation adds complex controls; queue-based reconciliation risks stale stock. | More rows are locked/rebuilt per mutation. Reconsider incremental optimization only after equivalent deterministic/concurrency tests and measured contention. |
| ADR-21 / MVP | NonExactStatus is exactly Required or Optional. Required may create a check/missing confirmation; Optional affects neither score nor automatic grocery generation. | A free-form status is inconsistent; extra statuses duplicate the written description and add ambiguous rules. | Two states may be limiting later. Add a status only with distinct calculation/UI behaviour. |

### 21.1 Decision ownership

Architecture changes should update this section in the same pull request as the implementing change. A decision may be revised when its trigger occurs; the table is guidance with rationale, not a ban on evidence-based evolution.

## 22. Implementation rules for future development

These rules translate the architecture into reviewable code conventions.

### 22.1 Use-case and presentation rules

1. Name application actions as imperative verbs in App\Actions\<Feature> and give them one public handle method.
2. Inject action/query dependencies through constructors or Livewire method injection; do not use the service container directly in business code.
3. Controllers coordinate HTTP only. Livewire components coordinate state/rendering only. Neither performs quantity arithmetic or opens multi-model transactions.
4. Use App\Livewire\Forms for substantial Livewire forms and App\Http\Requests for conventional endpoints.
5. Authorize at the presentation boundary and scope again in owned application queries.
6. Return models or focused result/view objects with domain meaning; do not return false for exceptional outcomes.
7. Use Flux components and Tailwind utility conventions already present. Keep new reusable Alpine behaviour outside large inline script blocks.

### 22.2 Domain and persistence rules

1. Never use a PHP float for ingredient, pantry, reservation, or grocery quantities.
2. Create Quantity through one parser/factory; persist display and normalized forms only through focused actions/model methods.
3. A non-exact amount is explicit state, not zero or null accidentally interpreted as zero.
4. Scale from the immutable source amount. Round only for display or a specifically documented storage boundary.
5. Use enums for closed state/unit sets and exhaustive match expressions when behaviour differs.
6. NonExactStatus has only Required and Optional; do not infer behaviour from description text.
7. Put local invariants and transitions on the relevant model/value object; put cross-aggregate operations in actions with DB::transaction.
8. Lock the rolling plan and affected rows in one stable order; reservation iteration follows planned-date/list/creation priority. Do no external I/O inside a transaction.
9. Use foreign keys, unique/check constraints where MySQL supports the invariant, and duplicate the critical check in domain code for useful errors.
10. Scope Eloquent relations/queries explicitly and eager-load; do not introduce lazy-loading-dependent loops.
11. Do not add a generic BaseRepository, BaseService, universal Result wrapper, or application-specific dependency injection framework.
12. Deployed migrations are immutable. Use additive/compatible schema transitions and a later cleanup migration.
13. Use Storage disks for files, config for tunable values, and environment variables only from config files.
14. Keep calculated grocery amounts/contributions separate from temporary display overrides.

### 22.3 Events, jobs, API, and external boundary rules

1. Core truth is updated synchronously. An event name is past tense and has a concrete consumer.
2. Dispatch transaction-dependent events/jobs after commit.
3. Jobs are idempotent, bounded, retriable, observable, and do not trust stale ownership/state.
4. External clients have an interface/adapter at App\Integrations\<Provider> and explicit timeouts/error translation. Do not wrap first-party Eloquent behind an interface.
5. Add API Resources/controllers only when an API is approved; do not pre-build parallel endpoints for Livewire pages.

### 22.4 Test and documentation rules

1. Every action has success, authorization, validation/invariant, and rollback/conflict coverage proportional to risk.
2. Every calculation service has table-driven unit tests including incompatible and boundary cases.
3. Reservation and quantity persistence behaviour is proven against MySQL.
4. New database tests use LazilyRefreshDatabase and factories.
5. A new state, unit, conversion, or ownership rule updates tests and this architecture document.
6. A future agent must read AGENTS.md, the relevant skill instructions, this document, and affected code before implementing an epic.

Relevant framework references:

- [Laravel 13 database transactions and deadlock retries](https://laravel.com/docs/13.x/database#database-transactions)
- [Laravel 13 Form Requests and validation](https://laravel.com/docs/13.x/validation#form-request-validation)
- [Laravel 13 authorization policies](https://laravel.com/docs/13.x/authorization#creating-policies)
- [Laravel 13 events after commit](https://laravel.com/docs/13.x/events#dispatching-events-after-database-transactions)
- [Laravel 13 queues](https://laravel.com/docs/13.x/queues)
- [Laravel 13 Eloquent casts](https://laravel.com/docs/13.x/eloquent-mutators#attribute-casting)
- [Livewire 4 components](https://livewire.laravel.com/docs/4.x/components), [forms](https://livewire.laravel.com/docs/4.x/forms), and [validation](https://livewire.laravel.com/docs/4.x/validation)

Prefer these framework mechanisms over custom equivalents.

## 23. Suggested incremental migration or implementation plan

There is no legacy Dinner Decider domain implementation to rewrite. The application is a clean Laravel/Livewire/Fortify starter, so build vertical slices while retaining its working authentication/settings code.

### Stage 0 — Baseline hardening (complete)

Completed on 17 July 2026:

- App\Models\User implements MustVerifyEmail and verified-route enforcement is tested.
- PHP 8.3/Node 22.12 minimums and the PHP 8.5/Node 24 Docker runtime are documented and tested in CI against MySQL 8.4.
- MySQL-only setup is deterministic; the stale SQLite post-create step is removed.
- The English-only Europe/Amsterdam presentation contract is configured while application storage remains UTC.
- ext-bcmath is a Composer platform requirement and is verified in the Docker runtime.
- Queues execute synchronously until a supervised worker and after-commit behavior are introduced.
- Docker enforces MySQL readiness and Laravel `/up` liveness during bounded startup.

Stage 0 resolved baseline inconsistencies without introducing domain layering.

### Stage 1 — Measurement, catalogue, and serving scale (Epics 1, 2, and 4) (complete)

Completed on 20 July 2026:

1. Added measurement enums (including ingredient-specific semantic-count compatibility), Quantity, QuantityInputParser, converter/formatter, and display-policy tests.
2. Added Ingredient, IngredientAlias, and IngredientPackage migrations, models, policies, factories, actions, and catalogue screens.
3. Added Recipe, RecipeIngredient with Required/Optional NonExactStatus, RecipeStep, RecipeCategory, and Tag persistence with policies, factories, and transactional actions.
4. Implemented required and nullable metadata, category/tag filtering, placeholders, optional validated image storage, create/update/archive/restore flows, and authenticated Livewire/Flux pages.
5. Added RecipeScaler and a serving preview that always scales from persisted source quantities and preserves non-exact and package context.

Exit condition met: an authenticated, verified user can maintain ingredients and recipes and reliably preview scaled recipes without pantry concepts.

### Stage 2 — Pantry and recommendations (Epics 3 and 5) (complete)

Completed on 20 July 2026:

1. Added the user-owned PantryEntry schema, model, factory, policy, relationships, non-negative decimal total, compatibility and deterministic merge keys, ownership indexes, and restricted ingredient/package deletion.
2. Added authorized pantry actions and PantryEntryForm. Additions normalize through the shared quantity kernel, merge under transaction locks with BCMath, and preserve explicit update/remove contracts with a Stage 3 reserved-balance seam fixed at zero for Stage 2.
3. Added AvailablePantry as the centralized stock read model. It exposes display rows, zero reserved balances, masked availability, calculation buckets, known-package metric aggregation, exact unknown-package isolation, and unlimited available staples.
4. Added the config-driven RecommendationEngine and GetPantryAwareRecommendations query. Every active owned recipe is scaled from persisted amounts, scored in memory with decimal strings, explained line by line, deterministically sorted, and only then paginated.
5. Added authenticated Livewire 4 page SFCs and Flux Free interfaces for pantry management and pantry-aware recommendations, while retaining the unranked recipe catalogue.
6. Made package content conversion immutable once a package is referenced by either a recipe requirement or pantry entry. Referenced definitions are retained if omitted during ingredient editing; a different content amount/unit requires a new definition.
7. Added focused PHPUnit coverage for exact mass/volume/count merging, semantic count isolation, package behavior, input/ownership rules, scoring fixtures, duplicate consumption, staples and temporary unavailability, non-exact explanations, deterministic tie-breaks, and bounded query counts.

Verification evidence on 20 July 2026: the Stage 2 targeted tests pass against MySQL 8.4 in the Sail container; Laravel Pint and Larastan also pass. The recommendation query remains at six queries as the recipe fixture count grows.

Exit condition met: pantry totals remain precise and non-negative, current availability is derived centrally, and every active recipe receives a deterministic, explainable pantry-aware ranking.

### Stage 3 — Dinner planning and reservation lifecycle (Epics 6 and 7)

1. Add singleton rolling DinnerPlan, snapshot-rich PlannedDinner/Requirement, and IngredientReservation schema/models.
2. Implement PlanDinner plus archive/history-snapshot planning and independent duplication.
3. Implement servings/date/order changes, cancel, restore, remove, and unresolved-confirmed cook transitions.
4. Reconcile affected reservations globally by date/list/creation priority after every supply/demand/order change.
5. Add MySQL concurrency, rollback, restoration, and exactly-once consumption tests.
6. Build active rolling-list/history UI with Monday-first optional dates and unresolved confirmation feedback.

Exit condition: simultaneous planning cannot over-reserve, and cook/cancel transitions preserve pantry invariants.

### Stage 4 — Grocery list (Epic 8)

1. Add GroceryList, GroceryItem, and GroceryItemContribution schema/models.
2. Implement pure GroceryCalculator and transactional RegenerateGroceryList with package context.
3. Connect regeneration to every relevant plan/pantry/availability action.
4. Add temporary generated-quantity edits, manual items, quantity-increase unchecking/notices, completed clearing, contribution explanations, and no-history/idempotency tests.

Exit condition: generated shortfalls remain synchronized without overwriting manual items or shopping state.

### Stage 5 — MVP hardening and release

- Run accessibility, responsive, security, and end-to-end flow reviews.
- Seed a realistic Dutch metric dataset and establish query/response baselines.
- Verify optional secure image upload/removal and the standard no-image placeholder; do not add advanced transformations/retention.
- Create deployment/backup/restore/worker runbooks.
- Verify every resolved product decision in section 24 through acceptance tests and an MVP walkthrough.

Avoid creating every proposed folder up front. A directory appears with its first concrete class. Existing settings SFCs and starter tests may migrate toward these conventions only when touched; no big-bang rewrite is warranted.

## 24. Open questions and assumptions

### 24.1 Confirmed repository facts

- The application root namespace is App.
- It runs Laravel 13, PHP 8.5, Livewire 4, Flux UI, Fortify, MySQL 8.4, and a Sail/Docker development environment.
- Authentication/settings/passkey/2FA starter code plus Stage 1 measurement/catalogue/recipe functionality and Stage 2 pantry/recommendation functionality exist; dinner planning, reservations, and groceries remain unimplemented.
- BCMath and pdo_mysql are installed in the application container.
- Stage 1 and Stage 2 schemas are migrated in the reviewed MySQL testing database; the default DatabaseSeeder creates a known test account and an idempotent demo catalogue covering ingredient, package, pantry, recipe, archive, and recommendation states.

### 24.2 Resolved MVP product decisions

The previously open product questions are resolved and are authoritative for MVP implementation:

| Decision | Architectural consequence |
| --- | --- |
| One rolling dinner list | One singleton DinnerPlan per user; Planned is the active ordered list, Cooked/Cancelled is history, with no week/name/current selector. |
| Historical/archive planning | Store complete dinner snapshots; allow archived-recipe and history-based planning, independent duplication, Cancelled restoration, and optional recipe-catalogue restoration. |
| Cooking with unresolved requirements | Require a current structured confirmation, deduct reservations only, and retain unresolved details in the Cooked snapshot. |
| Earliest-dinner allocation | Fully reconcile by explicit date, list position, creation time, and ID after every supply/demand/order change; no manual allocation UI. |
| Temporary staple unavailability | Store is_staple separately from is_currently_available; unavailable ingredients produce shortfalls without deleting stock. |
| Increased checked grocery amount | Clear checked_at on increase, retain it for equal/decreased amounts, and expose previous/new amount feedback. |
| No shopping history | Keep checked items only until cleared; delete removed manual/no-longer-required generated rows; no completed-session model. |
| Package display | Preserve package context and show package plus metric totals where meaningful; grocery need remains exact metric and never rounds to retail packs. |
| Ingredient-specific count units | Match ingredient plus semantic unit, include bulb, and prohibit automatic count-to-mass conversion. |
| Minimal recipe requirements | Require name, default servings, one ingredient, and one step; other metadata/image is nullable/unknown and does not block recommendation eligibility. |
| English with Dutch conventions | English-only MVP, metric units, DD-MM-YYYY, 24-hour time, Monday-first calendars, decimal comma or point. |
| Deferred account/data lifecycle | No self-service delete/export/custom retention; keep snapshots, ownership, Storage paths, and relationships future-safe. |
| Configurable recommendation weights | Use the Q/F/P/M/I formula in one config/scoring service, with quantity coverage dominant and non-exact rows excluded. |
| Temporary generated-quantity edits | Store a display override separate from calculated truth and clear it on any relevant recalculation; manual rows remain unchanged. |
| Required/Optional non-exact status | Required participates in coverage checks/grocery/cook confirmation; Optional is informational and never automatically missing/purchased. |

### 24.3 Remaining implementation assumptions and postponed scope

None of the original 15 product questions remains open. The following narrow implementation assumptions remain documented:

- The existing repository already requires accounts, so MVP ownership is per User. Household ownership remains a future migration.
- Manually removing an unprocessed Planned dinner deletes that occurrence; cancelling retains it in history. If product later wants removed-item history, use Cancelled rather than another status.
- Europe/Amsterdam is the presentation timezone implied by the Netherlands-friendly convention; timestamps remain UTC in storage.
- The initial incompatible-measurement penalty is 10 points in config/recommendations.php and may be tuned with the other accepted weights after user testing.
- Checking a grocery item records shopping progress but does not add stock automatically. A future receive-purchase action would require confirmed quantities.
- Optional images use the public Storage disk and standard secure upload/deletion behaviour; advanced retention/transformations remain postponed.
- Basic GroceryItem change timestamps are operational/UI state, not a user-facing shopping-history feature.

### 24.4 Epic support verification

The final architecture maps every epic to concrete owners and workflows:

- Ingredient/measurement: Ingredient, IngredientPackage, Quantity, ingredient-specific count compatibility, package/metric display, decimal persistence.
- Recipe catalogue: minimally required Recipe aggregate, nullable metadata, ordered ingredients/steps, image placeholder, actions/policies/archive/history snapshots.
- Pantry: PantryEntry totals/package context, separate staple/current availability, merge keys, owned stock actions.
- Serving scaling: RecipeScaler from immutable originals with display-only rounding.
- Pantry recommendations: AvailablePantry query, configurable Q/F/P/M/I RecommendationEngine, unavailable-staple handling, explanation view models.
- Dinner plan: singleton rolling DinnerPlan, active/history queries, complete snapshots, duplicate/cancel/restore/cook transitions.
- Reservation lifecycle: locked full-priority reconciliation, partial reservations, derived availability, unresolved confirmation/history, exactly-once consumption.
- Grocery list: stable generated keys/contributions, temporary overrides, increase-sensitive checking, manual/completed state, transactional regeneration without shopping history.

No epic requires a microservice, generic repository, event-sourced aggregate, or asynchronous core workflow.
