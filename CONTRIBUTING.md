# Contributing to VERA

## Development Setup

Follow the installation steps in [README.md](./README.md) first. Once the app is running:

```bash
# Run tests
php artisan test --compact

# Run a specific test
php artisan test --compact --filter=TestName

# Watch for frontend changes
npm run dev

# Build frontend for production
npm run build

# Format PHP code
./vendor/bin/pint

# Run the queue worker (needed for archive embeddings)
php artisan queue:work
```

## Code Conventions

### PHP

- Follow PSR-12 via Laravel Pint (`./vendor/bin/pint`). CI enforces this.
- Use PHP 8 constructor property promotion wherever possible.
- Declare explicit return types and parameter type hints on all methods.
- Prefer PHPDoc blocks over inline comments. Reserve inline comments for genuinely non-obvious logic.
- Use `TitleCase` for Enum keys: `case Anthropic = 'anthropic'`, not `case ANTHROPIC`.
- Use curly braces for all control structures, even single-line bodies.

### Naming

- Variables and methods should be descriptive: `$isRegisteredForDiscounts`, not `$discount`.
- Controller methods follow Laravel's resource naming: `index`, `show`, `store`, `update`, `destroy`.
- Actions (in `app/Actions/`) are single-purpose and named for what they do: `BuildArchiveFile`.
- Builders (in `app/Builders/`) use a fluent interface and end with `build()`.
- Directors (in `app/Directors/`) orchestrate builders; they own the assembly logic.

### Frontend (React)

- Prettier is configured — run it before committing.
- Components live in `resources/js/components/`. Check for an existing one before writing a new one.
- Pages live in `resources/js/pages/`. Each page maps to a route in `app.jsx`.
- Hooks live in `resources/js/hooks/`. Custom hooks encapsulate all API interaction and state — pages and components should not call `fetch` directly.
- Use `resources/js/utils/api.js` for all API calls — it handles Sanctum CSRF and credentials automatically.

## Directory Structure

Stick to the existing directory layout. Do not create new top-level directories under `app/` without discussion. The key directories and their roles:

| Directory | Purpose |
|---|---|
| `app/Actions/` | Single-responsibility action classes |
| `app/Builders/` | Fluent builder classes (no side effects) |
| `app/Contracts/` | Interfaces |
| `app/Directors/` | Orchestrate builders; own assembly logic |
| `app/DTOs/` | Data transfer objects (readonly value holders) |
| `app/Enums/` | PHP 8 backed enums |
| `app/Http/Controllers/Api/` | API controllers (all Sanctum-protected) |
| `app/Jobs/` | Queued jobs (async work) |
| `app/Models/` | Eloquent models |
| `app/Retrieval/` | Vector retrieval / RAG services |
| `app/Rules/` | Custom validation rules |
| `app/Services/LlmProviders/` | LLM provider implementations |
| `app/Traits/` | Shared controller traits |

## Testing

Tests use [Pest](https://pestphp.com/). Most tests should be feature tests.

```bash
# Create a feature test
php artisan make:test --pest SomeFeatureTest

# Create a unit test
php artisan make:test --pest --unit SomeUnitTest
```

- Use model factories for test data. Check existing factory states before manually setting attributes.
- Do not delete tests without explicit approval.
- Do not write verification scripts or use tinker to verify things that tests already cover.

## Database

- **Never edit existing migration files.** Always create a new migration to modify the schema.
- When adding a new model, create a factory and seeder alongside it.
- Use `php artisan make:model --help` to see available options for scaffolding related files in one command.

## Adding a New LLM Provider

1. Create a class in `app/Services/LlmProviders/` implementing `App\Contracts\LlmProvider`.
2. Add a new case to `app/Enums/AiProviderFormat` and map it to the new class in `providerClass()`.
3. The new format will automatically be available in the Providers UI.

## Adding a New Theme

1. Create `resources/css/themes/<name>.css` — declare all required CSS custom properties under `[data-theme="<name>"]`. Use an existing theme file as reference.
2. Import it in `resources/css/styles.css`.
3. Add a new case to `app/Enums/Theme.php`.

The new theme will appear in the Settings page automatically — `SettingsController@show` reads `Theme::cases()` dynamically.

## Commits & Pull Requests

- Keep commits focused. One logical change per commit.
- Write commit messages in the imperative: `Add embedding config to README`, not `Added embedding config`.
- Do not commit `.env`, `storage/`, `public/build/`, or IDE files.
- Do not commit or push unless explicitly asked to.

## Deployment

VERA can be deployed via [Laravel Cloud](https://cloud.laravel.com/). Ensure the queue worker is running in production for archive embeddings to work.
