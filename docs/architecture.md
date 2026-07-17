# Dinner Decider application architecture

Status: Proposed architecture for the MVP  
Last reviewed: 2026-07-17  
Source of functional scope: Dinner Decider MVP Product Specification.docx  
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

- The application is an early Laravel starter with no Dinner Decider domain models or tables yet.
- The runtime is PHP 8.5.8, Laravel 13.20.0, Livewire 4.3.3, Flux UI 2.15.0, and MySQL 8.4.
- composer.json currently declares PHP ^8.3, so PHP 8.3 is the supported language minimum unless the constraint is deliberately raised; this architecture does not require PHP 8.5-only syntax.
- Laravel Sail provides the Docker development environment.
- The UI convention is Livewire 4 page single-file components under resources/views/pages, using the pages namespace and the lightning-bolt filename prefix.
- Authentication is provided by Fortify with registration, password reset, email verification routes, two-factor authentication, passkeys, and login throttling.
- The application uses database-backed sessions, cache, and queues. The queue tables exist, but no application job, worker service, or scheduled task exists.
- PHPUnit 12, Larastan level 7, and Pint are configured.
- The existing application layer contains focused Fortify actions and an invokable Logout action.
- The current database contains only users, authentication, session, cache, and queue infrastructure. Both the application and testing schemas have been migrated.
- There are currently 56 routes, most supplied by Fortify, Livewire, Flux, and Boost.
- All product data is still to be introduced, so no legacy domain rewrite is needed.

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

### 2.3 Technical constraints

- MySQL is the production-shaped persistence target.
- All calculation code must avoid binary floating point.
- The supported measurement system is metric plus semantic count units and explicitly defined packages.
- Existing Laravel conventions and the App root namespace must remain intact.
- Product code remains compatible with the declared PHP ^8.3 constraint until the runtime/support policy is changed and tested.
- The first frontend remains server-driven Livewire and Blade; no separate SPA or API is required.
- User-facing writes must be authorized and validated server-side even when the interface already constrains input.
- Core correctness cannot depend on a queue worker because the current Compose stack does not run one.

### 2.4 Existing inconsistencies and incomplete patterns

These are findings, not changes made by this document:

| Finding | Impact | Recommended treatment |
| --- | --- | --- |
| Fortify email verification is enabled, but App\Models\User does not implement Illuminate\Contracts\Auth\MustVerifyEmail. | The verified middleware does not enforce verification for this model even though verification routes and tests exist. | Fix before protecting product routes with verified middleware; add a test proving an unverified user is denied. |
| CI runs PHP 8.3 and Node 22, while Sail runs PHP 8.5 and Node 24. | PHP 8.3 does exercise the declared minimum, but CI does not exercise the actual container runtime; the intended support policy is undocumented. | Document the minimum/runtime policy and use runtime parity or an explicit minimum-plus-runtime matrix. |
| .env.example selects MySQL at host mysql, but the GitHub Actions workflow declares no MySQL service and runs composer setup, which migrates immediately. | A clean CI run is expected to fail before tests. | Add a MySQL service to CI or provide an explicit CI SQLite configuration. MySQL integration coverage is preferred for locking and decimals. |
| composer.json post-create-project still creates an SQLite file even though the project is MySQL-first. | Setup paths communicate conflicting database choices. | Remove the stale SQLite setup step when setup is next touched. |
| The database queue is configured with after_commit false and no worker runs in Compose. | Queued side effects can race transactions and will not be processed locally unless a worker is started manually. | Keep MVP correctness synchronous; use explicit after-commit interfaces for future queued work and add a worker before relying on it. |
| boost.json has Sail disabled and Codex starts Boost through host PHP while DB_HOST is mysql. | Documentation tools work, but database-aware Boost tools may not reach the Docker network from host PHP. | Reconfigure the MCP command through Sail/Docker when Boost database tools are needed. |
| The dashboard, logo, welcome screen, repository links, and README are still starter placeholders. | No domain navigation or product language exists yet. | Replace gradually during the feature milestones; this is not an architectural rewrite. |
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

**Owns:** Ingredient catalogue entries, aliases, categories, staple status, units, compatibility rules, package definitions, quantity parsing, normalization, arithmetic, and display formatting.

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

**Owns:** The active DinnerPlan, PlannedDinner occurrences, selected servings, optional date, order, state transitions, requirement snapshots, allocation of pantry entries, shortfalls, reservation release, and cooking consumption.

**Depends on:** Recipes, Pantry, and Measurements.

This is the transactional center of the application. It coordinates rather than duplicating recipe and pantry rules.

### 6.6 Recommendations

**Owns:** Deterministic recipe matching, score calculation, match details, and sorting.

**Depends on:** Read-only recipe requirements, serving scaling, and current pantry availability.

**Does not own:** Pantry changes, reservations, dinner selections, or persisted recommendation rows.

### 6.7 Groceries

**Owns:** One grocery list for the active dinner plan, generated items, manual items, check state, categories, source contributions, and regeneration.

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
| 2. Recipe Catalogue | Recipes | Recipe actions, Eloquent relationships, RecipeForm, image storage, archive behavior |
| 3. Pantry Management | Pantry | Pantry actions, balance query, allocator, reservations aggregate |
| 4. Recipe Serving Scaling | Recipes and Measurements | RecipeScaler using original amounts and fixed-precision arithmetic |
| 5. Pantry-Based Recommendations | Recommendations | RecommendationQuery, RecommendationEngine, detailed match result objects |
| 6. Dinner Plan Management | Dinner Planning | DinnerPlan, PlannedDinner actions, policies, requirement snapshots |
| 7. Ingredient Reservation Lifecycle | Dinner Planning and Pantry | PantryAllocator, transactions, row locks, IngredientReservation |
| 8. Grocery List Generation | Groceries | GroceryCalculator, stable generated-item identities, contribution rows |

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
└── measurements.php

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
| config/measurements.php | Environment-independent measurement policy | Calculation scale, fraction display thresholds, supported defaults | User-specific ingredients or mutable unit records | Calculation scale of 6 |
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
- **Staple status** lives on the user-owned Ingredient rather than being duplicated on PantryEntry, because a staple intentionally needs no exact stock entry. staple_needs_purchase is its explicit grocery override.
- **IngredientReservation** allocates part of a PantryEntry to one planned requirement.
- **PlannedDinnerRequirement** is an immutable-at-selection snapshot of what that dinner needs.
- **IngredientPackage** is an architecture term introduced for the reusable package definition implied by the epic, such as one can containing 400 g.

### 10.2 Entity relationships

~~~mermaid
erDiagram
    USER ||--o{ INGREDIENT : owns
    USER ||--o{ RECIPE : owns
    USER ||--o{ PANTRY_ENTRY : owns
    USER ||--o| DINNER_PLAN : has

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
| Ingredient | user_id, name, normalized_name, category, preferred group/unit, is_staple, staple_needs_purchase, archived_at | Name is unique per user after normalization; preferred unit must match its group; a staple is assumed available, while the purchase flag explicitly includes it as a grocery check item. |
| IngredientAlias | ingredient_id, name, normalized_name | Alias is unique within the owning ingredient/user catalogue and resolves to exactly one Ingredient for that user. |
| IngredientPackage | ingredient_id, package type, label, content amount/unit/normalized amount nullable | Known contents must be mass or volume compatible; unknown packages compare only by the same package definition ID. |
| Recipe | user_id, name, description, default_servings, times, difficulty, cuisine, meal_type, image_path, source_url, archived_at | Default servings is at least one; archived recipes remain readable for planned/history references; edits do not mutate existing planned snapshots. |
| RecipeIngredient | recipe_id, ingredient_id, quantity type, entered amount/unit, normalized amount, compatibility key, package ID, description, optional status, position | Exact rows require positive amount and compatible unit; non-exact rows require a description and have no numeric calculation amount. |
| RecipeStep | recipe_id, instruction, position | Position is unique within recipe; blank steps are rejected. |
| PantryEntry | user_id, ingredient_id, display unit, total normalized amount, compatibility key, unknown-package ID, merge key | Total is non-negative; total cannot be reduced below active reservations; compatible additions merge according to merge key. |
| DinnerPlan | user_id | Exactly one active MVP plan per user; future multiple plans may relax this constraint. |
| PlannedDinner | dinner_plan_id, recipe_id, servings, planned_date, status, position, cooked_at, cancelled_at | Same recipe may appear more than once; servings is positive; only Planned may transition to Cooked or Cancelled; Cooked is terminal for MVP. |
| PlannedDinnerRequirement | planned_dinner_id, source recipe ingredient, base and scaled quantity snapshots, compatibility key, missing amount, display metadata | Snapshot survives recipe edits/archive; missing is never negative; non-exact rows have no exact reservation. |
| IngredientReservation | requirement_id, pantry_entry_id, normalized amount | Amount is positive and compatible; one requirement/entry pair is unique; summed reservations never exceed pantry total. |
| GroceryList | dinner_plan_id, regenerated_at | One per active plan. |
| GroceryItem | grocery_list_id, ingredient_id nullable, source, generation_key nullable, quantity/description, category, checked_at | Generated and manual items are distinguishable; manual rows survive regeneration; stable generated identity preserves checked state. |
| GroceryItemContribution | grocery_item_id, requirement_id, normalized contribution nullable | Explains which planned dinner created each grocery quantity/check item. |

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

The optional non-exact status from the epic is carried into the planned snapshot and grocery explanation. Its allowed values and behaviour must be product-defined before implementation; the architecture does not invent an enum prematurely.

#### CompatibilityKey

CompatibilityKey identifies a calculation bucket:

- mass
- volume
- count:piece
- count:clove
- count:slice
- count:leaf
- count:stalk
- count:sprig
- package:{ingredient_package_id} for a package without known metric contents

Known packages normalize to their content compatibility key. For example, a 400 g can of tomatoes participates in mass comparisons. The original package remains available for display.

#### Unit definitions

UnitCode and MeasurementGroup are PHP backed enums because MVP units are a closed, code-governed set. Conversion factors and display metadata are code/configuration, not user-editable database rows. IngredientPackage remains data because package sizes are user/catalogue definitions.

Supported initial units:

- Mass: mg, g, kg; base g.
- Volume: ml, l, tsp, tbsp; base ml; 5 ml per tsp and 15 ml per tbsp.
- Semantic count: piece, clove, slice, leaf, stalk, sprig; each is its own compatibility group.
- Package type labels: can, jar, pack, bag, bottle.

Adding a genuinely user-configurable unit catalogue is a future option. Do not create database-managed conversion formulas for the MVP.

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

PantryEntry is an aggregate balance, so it stores total_normalized_amount, compatibility_key, merge_key, and a compatible display_unit. It does not claim to preserve every stock addition's original unit: after adding 1 kg and 500 g there is one 1,500 g balance, formatted in the ingredient's preferred/display unit. A known-content package merges into its metric compatibility balance; only an unknown-content package retains IngredientPackage identity in its merge key. Preserving every addition would require an inventory ledger/lot model, which expiry/audit requirements do not justify for the MVP.

The server derives all normalized and merge fields; clients never submit them as trusted values. The intentional duplication on recipe/snapshot rows preserves understandable editing, while normalized values make comparisons and indexes practical.

### 11.4 Table outline and constraints

This is a design outline, not migration code.

| Table | Key constraints and indexes |
| --- | --- |
| ingredients | FK user_id cascade; unique user_id + normalized_name; index user_id + archived_at; preferred unit/group validated by action |
| ingredient_aliases | FK ingredient_id cascade; unique ingredient_id + normalized_name |
| ingredient_packages | FK ingredient_id cascade; index ingredient_id + package_type; positive content check when content exists |
| recipes | FK user_id cascade; index user_id + archived_at + name; positive default_servings check |
| recipe_ingredients | FK recipe_id cascade; FK ingredient_id restrict; unique recipe_id + position; index ingredient_id; exact/non-exact check constraints where practical |
| recipe_steps | FK recipe_id cascade; unique recipe_id + position |
| recipe_categories and tags | FK user_id cascade; unique user_id + normalized_name |
| category_recipe and recipe_tag | Composite primary/unique keys and cascading parent FKs |
| pantry_entries | FK user_id cascade; FK ingredient_id restrict; unique user_id + ingredient_id + merge_key; index user_id + compatibility_key |
| dinner_plans | FK user_id cascade; unique user_id for MVP |
| planned_dinners | FK dinner_plan_id cascade; FK recipe_id restrict; index plan_id + status + position; index planned_date |
| planned_dinner_requirements | FK planned_dinner_id cascade; nullable source recipe ingredient FK null on delete; index ingredient_id + compatibility_key |
| ingredient_reservations | FK requirement_id cascade; FK pantry_entry_id restrict; unique requirement_id + pantry_entry_id; index pantry_entry_id |
| grocery_lists | FK dinner_plan_id cascade; unique dinner_plan_id |
| grocery_items | FK grocery_list_id cascade; nullable ingredient FK restrict; unique list_id + source + generation_key for generated rows; index category + checked_at |
| grocery_item_contributions | FK grocery_item_id cascade; FK requirement_id cascade; unique item_id + requirement_id |

MySQL nullable uniqueness must be considered when implementing manual grocery items. Generated rows should always have a non-null generation_key; manual rows may have null and are not deduplicated automatically.

### 11.5 Ownership strategy

For the MVP, Ingredient, Recipe, PantryEntry, and DinnerPlan are directly user-owned. Children inherit ownership through required parents. Queries use explicit ownership constraints such as whereBelongsTo($user) or parent-scoped bindings; a hidden global user scope is avoided because it can conceal data in maintenance jobs and tests.

Future shared households should add a Household and membership model, create one personal household per existing user, backfill household_id, and then update policies and queries. A polymorphic owner_type/owner_id is deliberately postponed because it would complicate every MVP query without a current shared-ownership requirement.

### 11.6 Archive and delete behavior

- Recipes and Ingredients use explicit archived_at business state rather than destructive deletion when referenced.
- Recipe deletion from the UI archives if it has planning/history references.
- PlannedDinner cancellation retains the occurrence and releases reservations.
- PantryEntry deletion is rejected while active reservations exist.
- User deletion must cascade all user-owned product data in a tested transaction.
- Deployed migrations are immutable; changes use new migrations.

### 11.7 Transactions and locking

Use DB::transaction with a small deadlock retry count for reservation-sensitive actions. Use pessimistic lockForUpdate for:

- the PlannedDinner being changed or cooked,
- affected PantryEntry rows,
- existing reservations being released or consumed,
- the active DinnerPlan/GroceryList when regeneration writes generated items.

Lock rows in ascending primary-key order to reduce deadlocks. Never call an external HTTP service or send mail while holding the transaction.

The following operations are transaction boundaries:

- Add or reduce pantry stock when reservations/shortfalls may change.
- Plan a dinner.
- Change planned servings.
- Cancel/remove a planned dinner.
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
| Ingredient | Required name, normalized per-user uniqueness, optional archive state |
| Exact quantity | Positive parseable decimal/fraction input, normalized scale no greater than six, supported unit, ingredient/unit compatibility |
| Non-exact quantity | Explicit non-exact type, required description, optional product-defined status; amount and unit must be absent |
| Package quantity | Positive package count plus an IngredientPackage belonging to the same ingredient |
| Recipe | Name, positive original servings, at least one requirement and one instruction, unique positive positions |
| Pantry adjustment | Positive amount and compatible unit/package; a reduction may not cross reserved stock |
| Planned dinner | Owned active recipe, positive servings, a plan date/status transition allowed by the action |

The client may provide immediate feedback, but server-side validation remains authoritative.

### 12.2 Domain invariants

Value objects, model methods, and actions re-check invariants that matter to stored state. Examples are:

- Quantity never contains a float, zero/negative amount, unsupported scale, or incompatible measurement group.
- PlannedDinner follows Planned to Cooked or Cancelled and cannot transition out of a terminal state.
- A reservation never exceeds the entry's available quantity.
- Cooking consumes each reservation at most once.
- An archived recipe cannot be newly selected.
- A recipe requirement and an ingredient package must reference the same ingredient.

Do not duplicate all UI rules in every layer. Re-check only rules required for correctness or security, and express calculations through shared value objects/services.

### 12.3 Exception and user-error policy

Expected business conflicts use small, named exceptions in App\Exceptions, such as InsufficientAvailablePantry or InvalidDinnerTransition. The calling Livewire component converts these into a form or banner error. Authorization failures remain AuthorizationException responses, missing owned records remain 404 where appropriate, and unexpected failures are allowed to reach Laravel's exception handler and logs.

| Failure | User response | Log level |
| --- | --- | --- |
| Invalid input | Field-level 422-style validation feedback | None |
| Stale pantry availability during selection | Clear retry/change-servings message | Notice or structured info |
| Forbidden owned resource | 403, or 404 when concealing existence | Security context at warning only if suspicious/repeated |
| Deadlock after configured retries | Generic retry message | Error with operation IDs |
| Integration/queue failure | Non-blocking status and retry where possible | Error |
| Programming/database failure | Generic error page; no internals | Error with trace |

Actions must not catch Throwable merely to return false. A transaction rolls back on an exception; the presentation boundary decides how an expected exception is displayed.

## 13. Authentication and authorization strategy

### 13.1 Authentication

Laravel Fortify remains the authentication backend and the existing Livewire/Flux pages remain the UI. The present features—registration, password reset, password confirmation, passkeys, and two-factor authentication—are compatible with this architecture and should not be replaced.

Email verification is enabled in config/fortify.php and the dashboard route uses verified middleware. However, App\Models\User currently does not implement Illuminate\Contracts\Auth\MustVerifyEmail. That means the configured verification requirement is not effective. This is an existing inconsistency to correct when authentication work is next in scope, with a feature test proving an unverified user cannot reach verified routes.

MVP product routes require auth and, once the model contract issue is corrected, verified. Sensitive account operations continue to use password confirmation and Fortify's existing rate limits.

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

The repository currently uses the database queue connection, has queue.connections.database.after_commit set to false, and has no worker service in compose.yaml. No MVP core behaviour may depend on a worker until deployment supplies a supervised worker. Before the first queued feature, enable after-commit dispatch at the connection or job/event level, add operational worker configuration, and test the failure/retry path.

### 14.3 Notifications

There is no required notification in the epics. Postpone notification classes for the MVP. Likely opt-in additions are a dinner reminder and a low-stock/grocery-ready notification. Notifications should use Laravel channels and user preferences; they must not be emitted for every recalculation by default.

## 15. Recommendation and pantry-consumption workflow

This workflow is intentionally split into a read-only recommendation calculation and transactionally safe planning/consumption commands. A recommendation is advice based on a snapshot; PlanDinner rechecks availability while holding locks.

### 15.1 Deterministic recommendation calculation

GetPantryAwareRecommendations loads active recipes with ordered requirements, ingredients, packages, and the current user's pantry availability in bounded eager-loaded queries. RecommendationEngine then calculates in memory using decimal strings.

For each scaled requirement:

1. Scale from the immutable recipe amount and original servings, never from a previously rounded display amount.
2. Treat a user-designated staple as covered for scoring, with no reservation or consumption. Its staple_needs_purchase flag affects groceries, not recommendation availability.
3. For an exact comparable requirement, calculate coverage ratio as min(available / required, 1).
4. For a non-exact requirement, report its description and whether it is a staple/present as explanatory information only. Exclude it from every scoring term and exact reservation.
5. For a known-content package, convert package count to its declared metric content.
6. For an unknown-content package, compare only the identical IngredientPackage definition. It is incompatible with grams, millilitres, or a different package definition.
7. Record missing, partially covered, and incompatible requirements for explanation.

Use one documented score for MVP:

score = 60 × mean quantity coverage + 25 × requirement presence coverage + 15 × fully covered requirement coverage

Mean quantity coverage is the mean of each exact requirement's capped available/required ratio. Presence coverage is the proportion with any compatible quantity available. Fully covered coverage is the proportion whose available quantity meets the requirement. A staple contributes one to all three terms.

All three terms are represented from zero to one before weighting and calculated over exact measurable requirements only. If a recipe has no exact requirements, its numeric score is zero and the explanation says it cannot be quantity-matched. Rank by descending score, then fewer exact missing requirements, fewer exact incompatible requirements, recipe name, and recipe ID. Round only the displayed score.

The exact weights are a product assumption, not a permanent domain truth. Keep them in config/recommendations.php and cover them with golden examples. Reconsider when real usage data or a user-controlled preference model exists. Do not add AI, embeddings, a rules engine, or persisted recommendation rows for the MVP.

Each recommendation view model includes enough explanation to answer “why?”:

- scaled servings;
- coverage score;
- fully available ingredients;
- partial and missing amounts;
- incompatible unit/package cases;
- staples assumed available and any purchase-needed override;
- non-exact check items, clearly excluded from scoring.

### 15.2 Planning and reservation allocation

PlanDinner runs a database transaction:

1. Authorize the recipe and dinner plan; reject archived recipes.
2. Lock the current plan and relevant pantry entries in ascending ID order.
3. create the PlannedDinner in Planned state.
4. Snapshot scaled requirements into PlannedDinnerRequirement.
5. Allocate each exact requirement across compatible PantryEntry rows deterministically, creating IngredientReservation rows only up to available quantities.
6. Store the remaining shortfall on the requirement. Non-exact requirements have presence/missing status but no numeric reservation.
7. Regenerate affected generated grocery items and their contribution rows.

Allocation order is stable: exact native compatibility first, then convertible known-content packages, then ascending pantry-entry ID. The initial schema has no expiry date, so “first expiring” is deliberately postponed. If expiry tracking is added, earliest expiry becomes the first allocation key.

Recipe edits do not change an existing planned dinner because its requirements are snapshots. Changing a planned dinner's servings recalculates from its snapshot base/original servings, releases the old reservations, and reallocates in one transaction. This is slightly more storage than referring to the live recipe, but it preserves what the user actually planned and prevents silent grocery changes.

Adding pantry stock or changing staple state invokes ReconcilePlanReservations for requirements of the affected ingredient in the current plan. It preserves existing reservations, then allocates newly available quantity by planned date, PlannedDinner ID, and requirement ID; finally it updates shortfalls and regenerates groceries in the same transaction. Reducing stock never steals a reservation and is rejected below the reserved total. This deterministic first-planned-first-served rule is an MVP assumption that can later become an explicit user priority.

### 15.3 Cooking and cancellation

MarkDinnerCooked locks the PlannedDinner, its reservation rows, and affected pantry entries. It verifies Planned state, subtracts exactly the reserved amount from each pantry total, deletes or marks the reservations consumed according to the chosen audit implementation, sets Cooked with cooked_at, and regenerates the plan's generated grocery items. It never performs a second “best effort” pantry calculation at cook time.

CancelDinner locks the same aggregate, releases reservations without changing pantry totals, sets Cancelled with cancelled_at, and regenerates the grocery list. Terminal actions are idempotent for duplicate browser submissions: repeating the same terminal command returns the existing terminal result; attempting the opposite terminal transition fails.

### 15.4 End-to-end workflow

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
    H --> I[Allocate partial or full reservations]
    I --> J[Record remaining shortfalls]
    J --> K[Regenerate generated grocery items]
    K --> L[Commit PlannedDinner]
    L --> M{Later action}
    M -- Change servings --> N[Release and reallocate in one transaction]
    N --> K
    M -- Cancel --> O[Release reservations]
    O --> P[Set Cancelled and regenerate groceries]
    M -- Cook --> Q[Lock dinner, reservations, and pantry]
    Q --> R[Deduct each reserved amount exactly once]
    R --> S[Set Cooked and regenerate groceries]
~~~

### 15.5 Concurrency guarantees

- Available equals total minus active reservations, calculated from locked rows inside mutation transactions.
- Reducing/removing pantry stock is rejected when it would take total below reserved.
- Two simultaneous PlanDinner requests serialize on affected pantry entries; the second sees the first reservation.
- A unique constraint prevents duplicate reservation allocation for one planned requirement and pantry entry.
- Dinner terminal state and timestamps, plus transaction locks, prevent double consumption.
- The recommendation screen may become stale, but the planning action never promises stale quantities.

## 16. Grocery-list generation workflow

The MVP has one current GroceryList per DinnerPlan. It contains generated items and user-created manual items. Generated items are derived from planned requirement shortfalls; manual items are never rewritten by generation.

### 16.1 Generation algorithm

RegenerateGroceryList executes within the calling plan transaction:

1. Load active PlannedDinnerRequirement shortfalls for Planned dinners.
2. Exclude numeric requirements with no shortfall. Exclude staples unless staple_needs_purchase is set; when it is set, produce one non-quantified “replenish/check” item rather than inventing tracked stock.
3. Group exact shortfalls by ingredient and compatibility key. Convertible mass/volume/count amounts use normalized units; unknown packages group only by IngredientPackage ID.
4. Group non-exact missing requirements by ingredient and normalized note, displaying “as needed” rather than inventing an amount.
5. Sum with BCMath and format through QuantityFormatter.
6. Upsert each generated GroceryItem by a stable generation_key.
7. Replace its GroceryItemContribution rows with the exact planned requirements and amounts that produced it.
8. Remove generated items whose keys no longer occur. Leave manual items untouched.

A generation key is derived from versioned, canonical data such as ingredient ID, compatibility group, normalized unit or package ID, and non-exact note. It is not based on display text. Preserve checked state when the same generation key remains after regeneration; a materially different key is a new unchecked item.

The user can therefore see that “750 g potatoes” consists of, for example, 300 g for one dinner and 450 g for another. Contribution rows are explanatory trace data, not a second source of quantity truth. MarkStapleForPurchase provides the explicit staple override required by the epics and is cleared deliberately by the user rather than by regeneration.

### 16.2 Purchase-unit behaviour

Generated quantities express the recipe shortfall in a compatible metric/count/package unit. The MVP does not optimize how many retail packs to buy, infer package sizes, compare prices, or round to supermarket quantities. Unknown packages remain counts of the exact package definition. Known packages may be displayed either as normalized metric quantity or a package count only when conversion is exact and unambiguous.

Manual GroceryItem rows are fully editable. For the MVP, generated ingredient/quantity fields remain calculator-owned so regeneration cannot silently preserve a false shortfall; users may edit their category/check state or add a separate manual item. If product requires persistent generated-quantity overrides, add explicit override fields while retaining calculated_amount and contributions—do not overwrite the calculated source of truth.

### 16.3 Idempotency and updates

Regeneration is idempotent: the same plan state produces the same generated keys, amounts, and contributions. It runs after planning, changing servings, cancelling, cooking, and relevant pantry adjustments. A checked item remains checked across a same-key amount change, which favours preserving the user's shopping progress; the UI should visibly flag the changed amount. Whether an increased checked amount should auto-uncheck is an open product question recorded in section 24.

If regeneration later becomes expensive, preserve the same pure GroceryCalculator and stable-key contract while moving only projection refresh to a queued, version-checked job. That is a future scaling option, not an MVP requirement.

## 17. Testing strategy

The test suite uses PHPUnit 12 through Laravel's test runner. At the time of review it contains 33 passing starter/authentication/settings tests with 81 assertions, but no Dinner Decider domain tests because product code has not yet been implemented.

### 17.1 Test layers

| Test type | Purpose | Project examples |
| --- | --- | --- |
| Unit | Pure rules, no Laravel container or database | Quantity arithmetic; QuantityInputParser; UnitConverter matrix; RecipeScaler; recommendation score/tie-breaks; GroceryCalculator grouping |
| Application/feature | Actions, validation, policies, Eloquent relationships, and transactions | PlanDinner partial reservation; reject stock below reserved; cook exactly once; archive recipe; grocery idempotency |
| Livewire feature | Page behaviour, authorization, validation, and rendered state | Create recipe form; pantry adjustment errors; recommendation explanations; change servings; check/manual grocery items |
| MySQL integration | Behaviour SQLite cannot prove | DECIMAL round trips; unique constraints; lock ordering; two competing reservations; generated-key indexes |
| Browser/end-to-end, selective | A few high-value JavaScript/browser interactions | Complete first recipe-to-cooked-dinner journey; passkey/2FA only where browser APIs require it |

Pure services should have exhaustive data providers around boundaries. Example conversion cases include decimal comma, very small/large allowed values, g↔kg, ml↔l, tsp/tbsp, compatible and incompatible count units, known packages, unknown packages, non-exact amounts, and multiple rounds of scaling without drift.

New database tests should use LazilyRefreshDatabase as required by AGENTS.md. Existing starter tests use RefreshDatabase; that difference is acceptable until those files are touched, then migrate them in small verified batches. Factories define valid defaults and named states such as archived, nonExact, staple, planned, cooked, and cancelled.

### 17.2 Epic acceptance coverage

| Epic | Minimum architectural acceptance tests |
| --- | --- |
| 1. Ingredients and measurement | Exact/non-exact creation, compatibility matrix, package definitions, decimal precision |
| 2. Recipe catalogue | Ordered requirements/instructions, serving metadata, edit/archive ownership |
| 3. Pantry | Merge key, add/reduce stock, total/reserved/available, staple behaviour |
| 4. Serving scaling | Scale from original quantities, fraction/decimal input, no cumulative rounding |
| 5. Pantry recommendations | Deterministic scores, tie-breaks, explanations, partial/incompatible coverage |
| 6. Dinner plan | Add/change/cancel owned recipes with requirement snapshots |
| 7. Reservation lifecycle | Partial allocation, concurrent selection, release, exactly-once cooking |
| 8. Grocery list | Aggregation, contribution trace, manual preservation, checked-state/idempotent regeneration |

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

- Keep the existing framework health endpoint for process health. Add dependency checks only to a protected/deployment health mechanism so public requests do not expose database details.
- Run migrations as a single release step before new containers accept traffic. Never run destructive schema changes and application cutovers without a compatible transition.
- Back up MySQL and test restoration. A backup is not proven until restore verification succeeds.
- Configure failed-job retention and alerts before enabling queued features.
- Use UTC for stored timestamps and convert at the presentation boundary; the application locale/date display remains a product decision.
- Set production APP_DEBUG=false, rotate logs, and keep environment secrets out of images and source control.
- Document worker, scheduler, backup, migration, and rollback commands in an operations/runbook document when deployment is introduced.

### 19.3 Current operational gaps

The Docker development environment is usable, but the following repository state should be addressed independently of architecture implementation:

- The CI workflow targets PHP 8.3/Node 22 while the Sail container runs PHP 8.5/Node 24. Either a documented minimum-version matrix or runtime parity is needed.
- The workflow runs composer setup against the MySQL-oriented .env.example but declares no MySQL service, so a clean CI run is unlikely to prove the configured database path.
- Composer's post-create script still creates database/database.sqlite even though MySQL is the configured application database.
- Database queue configuration has after_commit disabled and Compose has no queue worker. This is harmless while nothing is queued but must be corrected before queued behaviour ships.
- boost.json says sail is false and .codex/config.toml invokes host php while DB_HOST=mysql is only resolvable on the Compose network. Database-aware tooling may therefore fail outside the container.

These are incremental configuration fixes, not reasons to reorganize the application.

## 20. Security considerations

Security is enforced through ordinary Laravel controls plus domain-specific ownership and transaction rules.

### 20.1 Application controls

- Require authenticated, verified sessions for product screens after correcting the User verification contract.
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
- User deletion/export and retention behaviour must be designed before real personal data is collected.
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
| ADR-06 / MVP | Model a fixed UnitCode/measurement compatibility matrix in code/config and ingredient-specific package definitions in MySQL. | User-defined global unit conversion is unsafe; treating package names as plain strings loses equality/content; a universal conversion table overgeneralizes count units. | New supported units require a code release. Reconsider editable units only with a concrete administration/use case. |
| ADR-07 / MVP | Persist PantryEntry total; persist reservations; derive reserved and available. | Persisting total, reserved, and available creates three mutable truths; ledger-only inventory is more complex for the MVP. | Aggregation is required to display availability. Add cached counters only after measured query pressure and invariant-preserving updates. |
| ADR-08 / MVP | Snapshot PlannedDinnerRequirement when a dinner is selected. | Reading live RecipeIngredient rows saves space but silently changes existing plans after recipe edits; full recipe versioning is more machinery. | Snapshots duplicate data. Reconsider full immutable recipe versions if audit/history/export requirements expand. |
| ADR-09 / MVP | Use synchronous MySQL transactions and pessimistic locks for reservation, stock, cooking, and generated grocery changes. | Optimistic retry everywhere complicates the UI; queued/eventual reservation permits double allocation; distributed locks add another system. | Contention can reduce throughput. Reconsider lock granularity only with measured contention and concurrency tests. |
| ADR-10 / MVP | Calculate recommendations on demand with a deterministic weighted score and explicit explanations. | Random ordering, opaque AI, persisted ranking tables, and a rules engine are not justified by the epics. | Weights are product assumptions and calculations grow with catalogue size. Reconsider/cache/version when measured latency or user research demands it. |
| ADR-11 / MVP | Derive generated grocery items from requirement shortfalls using stable keys and contribution rows; preserve manual rows separately. | Storing only display strings loses traceability; rebuilding the whole list loses checks/manual work; event-sourced projections overcomplicate recovery. | Regeneration writes several rows. Reconsider async projection only when versioned idempotency and UI staleness are acceptable. |
| ADR-12 / MVP | Treat staples as covered and do not reserve/deduct them; exclude them from groceries unless staple_needs_purchase is explicitly set. | Infinite pantry quantities conflate preference and stock; reserving staples contradicts assumed availability; requiring a numeric pantry entry defeats the staple story. | Exact staple stock remains unknown. Reconsider if “usually stocked but currently out” must affect recommendations as well as groceries. |
| ADR-13 / MVP | Use per-user ownership and explicit policies/query scoping. | Premature household tenancy/polymorphic owners burden all foreign keys and policies. | A future household migration is required. Reconsider when shared planning is approved, before significant multi-user product data exists if possible. |
| ADR-14 / MVP | Use Livewire 4 pages and Flux components, with Livewire Form objects for substantial forms. | A React/Vue SPA duplicates validation/API work; Blade-only forms make highly interactive planning less direct. | Server round trips require careful eager loading/loading states. Reconsider a separate client only when offline/native/API requirements appear. |
| ADR-15 / Growth | Emit events only for real after-commit side effects; queue only slow/retriable edge work. | Event-driven orchestration hides core control flow and makes correctness depend on workers. | The action knows its core collaborators. Split when a side effect can independently fail and eventual consistency is acceptable. |
| ADR-16 / MVP | Use MySQL as development/test production-parity database for persistence-sensitive tests. | SQLite is fast but does not prove MySQL DECIMAL, locking, or concurrency behaviour. | MySQL tests cost startup time. Pure unit tests remain fast; use targeted integration tests and parallel CI services. |
| ADR-17 / MVP | Archive ingredients/recipes with explicit archived_at state; retain historical plan snapshots. | Hard deletion breaks history; applying SoftDeletes indiscriminately can hide required joins and complicate unique keys. | Queries must explicitly filter active catalogue data. Reconsider SoftDeletes only for a separately defined recovery requirement. |
| ADR-18 / Postponed | Do not add DTOs universally. Use immutable data/value objects only for quantities, calculation inputs/results, and unstable external boundaries. | DTO-per-request/model creates mapping code without a boundary; raw arrays are too weak for calculation contracts. | Some actions accept models and named scalars. Introduce a DTO when argument count/shape or serialization makes the contract clearer. |

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
6. Put local invariants and transitions on the relevant model/value object; put cross-aggregate operations in actions with DB::transaction.
7. Lock affected rows in stable ascending-ID order. Do no external I/O inside a transaction.
8. Use foreign keys, unique/check constraints where MySQL supports the invariant, and duplicate the critical check in domain code for useful errors.
9. Scope Eloquent relations/queries explicitly and eager-load; do not introduce lazy-loading-dependent loops.
10. Do not add a generic BaseRepository, BaseService, universal Result wrapper, or application-specific dependency injection framework.
11. Deployed migrations are immutable. Use additive/compatible schema transitions and a later cleanup migration.
12. Use Storage disks for files, config for tunable values, and environment variables only from config files.

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

### Stage 0 — Baseline hardening

- Correct MustVerifyEmail integration and add the verified-route test.
- Decide/document supported PHP/Node versions and make CI use a MySQL service.
- Remove or clarify the SQLite post-create step for this MySQL-only project.
- Confirm Dutch/English UI locale, Europe/Amsterdam display timezone, and decimal input policy while retaining UTC storage.
- Add ext-bcmath to Composer platform requirements because product correctness depends on it; the Docker runtime already has the extension.
- Keep database queue features disabled unless a worker/after-commit setup is added.

This stage resolves existing inconsistencies; it does not introduce domain layering for its own sake.

### Stage 1 — Measurement, catalogue, and serving scale (Epics 1, 2, and 4)

1. Add measurement enums, Quantity, QuantityInputParser, converter/formatter, and unit tests.
2. Add Ingredient and IngredientPackage migrations/models/policies/factories.
3. Add Recipe, RecipeIngredient, and RecipeStep migrations/models/policies/factories.
4. Implement create/update/archive actions and Livewire/Flux catalogue pages as complete vertical slices.
5. Add RecipeScaler and a servings preview with exact/non-exact/package acceptance tests.

Exit condition: an authenticated user can maintain ingredients/recipes and reliably scale a recipe without pantry concepts.

### Stage 2 — Pantry and recommendations (Epics 3 and 5)

1. Add PantryEntry schema/model and deterministic merge key.
2. Implement stock/staple actions, policies, and pantry UI.
3. Implement AvailablePantry query and RecommendationEngine with documented scoring fixtures.
4. Build recommendation results/explanations and query-count tests.

Exit condition: pantry totals are precise and active recipes receive deterministic, explainable pantry-aware rankings.

### Stage 3 — Dinner planning and reservation lifecycle (Epics 6 and 7)

1. Add DinnerPlan, PlannedDinner, PlannedDinnerRequirement, and IngredientReservation schema/models.
2. Implement PlanDinner with snapshot/allocation/locking.
3. Implement change servings, cancel, and cook transitions.
4. Reconcile affected current-plan shortfalls after stock additions/staple changes.
5. Add MySQL concurrency, rollback, and exactly-once consumption tests.
6. Build the planning/calendar/list UI with explicit state and loading/error feedback.

Exit condition: simultaneous planning cannot over-reserve, and cook/cancel transitions preserve pantry invariants.

### Stage 4 — Grocery list (Epic 8)

1. Add GroceryList, GroceryItem, and GroceryItemContribution schema/models.
2. Implement pure GroceryCalculator and transactional RegenerateGroceryList.
3. Connect regeneration to all relevant plan/pantry actions.
4. Add manual items, checking, contribution explanations, and idempotency tests.

Exit condition: generated shortfalls remain synchronized without overwriting manual items or shopping state.

### Stage 5 — MVP hardening and release

- Run accessibility, responsive, security, and end-to-end flow reviews.
- Seed a realistic Dutch metric dataset and establish query/response baselines.
- Add image storage only if needed for MVP acceptance.
- Create deployment/backup/restore/worker runbooks.
- Resolve open product questions that affect visible behaviour.

Avoid creating every proposed folder up front. A directory appears with its first concrete class. Existing settings SFCs and starter tests may migrate toward these conventions only when touched; no big-bang rewrite is warranted.

## 24. Open questions and assumptions

### 24.1 Confirmed repository facts

- The application root namespace is App.
- It runs Laravel 13, PHP 8.5, Livewire 4, Flux UI, Fortify, MySQL 8.4, and a Sail/Docker development environment.
- Authentication/settings/passkey/2FA starter code exists; no Dinner Decider domain tables or code exist yet.
- BCMath and pdo_mysql are installed in the application container.
- Product data is not yet present in the reviewed development/testing databases.

### 24.2 Architectural assumptions pending product confirmation

| Assumption used by this design | Consequence if changed |
| --- | --- |
| Product data belongs to one user for MVP. | Shared use requires Household membership, policy, and foreign-key migration. |
| Each user has one persistent current DinnerPlan, with Cooked/Cancelled PlannedDinner history retained inside it. | Multiple named/overlapping or archived plans need different uniqueness and grocery-list rules. |
| Recipe edits do not mutate existing planned-dinner snapshots. | Live-linked plans would require reallocation prompts and a different audit story. |
| Staples are assumed fully available and are never reserved or consumed; staple_needs_purchase creates a non-quantified grocery check item without changing recommendation coverage. | A “staple but out of stock” state that affects recommendations needs another explicit availability override. |
| Non-exact requirements are reported for explanation/grocery checks but do not affect recommendation scores, reservations, or deductions. | Quantifying them later requires editing the recipe ingredient or adding a separately defined estimate. |
| Adding pantry stock allocates newly available quantity to active shortfalls by planned date, planned-dinner ID, then requirement ID; existing reservations remain stable. | A user-choice or priority model changes reconciliation and grocery results. |
| Cooking with a remaining shortfall is allowed only after explicit confirmation and deducts only recorded reservations. | Requiring full coverage would block cooking until pantry is reconciled. |
| Grocery checking records shopping progress but does not automatically add stock to the pantry. | A purchase/receive workflow needs quantity confirmation and a separate action. |
| Dutch metric input accepts comma or point decimal separators but rejects ambiguous thousands notation. | Broader localization needs locale-aware parsing and formatting tests. |
| Recipe/pantry images are optional, stored through a public disk initially. | Private images or CDN transformations require access URLs and processing jobs. |

### 24.3 Product questions to resolve

1. Does “dinner plan” mean a rolling list, a calendar week, or multiple named plans? What determines the current plan?
2. May users plan an archived recipe already in history, duplicate a dinner occurrence, or restore a cancelled dinner?
3. Should cooking be blocked while any exact/non-exact requirement is missing, or is the explicit-confirmation assumption acceptable?
4. When pantry stock is added, should automatic allocation favour the earliest dinner, user priority, or ask the user?
5. Can a staple be temporarily marked unavailable without losing its staple designation?
6. Should a checked generated grocery item become unchecked when its amount increases?
7. Should checked/removed generated items remain in shopping history, and for how long?
8. Are known package quantities displayed as package counts, normalized metric amounts, or both? What rounding is acceptable?
9. Are count units strictly ingredient-specific as assumed, and can “2 cloves garlic” ever convert to a measured mass?
10. Which recipe fields are required beyond the epic minimum: cuisine, tags, preparation/cooking time, notes, and image?
11. Is the initial interface Dutch, English, or switchable? Which week start/date format should planning use?
12. What are account deletion, data export, image retention, and historical dinner retention requirements?
13. Are recommendation weights acceptable for MVP, or does product want pantry quantity coverage to dominate differently?
14. Must generated grocery quantities be directly editable, and if so should an override survive later plan/pantry recalculation?
15. What values and behaviour should the epic's optional non-exact ingredient status support?

These questions do not prevent implementing the measurement/catalogue foundation. Questions 1–6 should be answered before finalizing dinner-plan/reservation/grocery acceptance criteria.

### 24.4 Epic support verification

The final architecture maps every epic to concrete owners and workflows:

- Ingredient/measurement: Ingredient, IngredientPackage, Quantity, unit compatibility, decimal persistence.
- Recipe catalogue: Recipe aggregate, ordered requirements/instructions, actions/policies/archive.
- Pantry: PantryEntry totals, staple state, merge key, owned stock actions.
- Serving scaling: RecipeScaler from immutable originals with display-only rounding.
- Pantry recommendations: AvailablePantry query, deterministic RecommendationEngine, explanation view models.
- Dinner plan: DinnerPlan/PlannedDinner plus snapshotted requirements and state transitions.
- Reservation lifecycle: locked partial reservations, derived availability, release and exactly-once consumption.
- Grocery list: derived stable-key items, contribution trace, manual/check state, transactional regeneration.

No epic requires a microservice, generic repository, event-sourced aggregate, or asynchronous core workflow.
