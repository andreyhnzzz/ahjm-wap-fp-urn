<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DDDStructure extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:ddd
                            {context : The Bounded Context (e.g., IdentityAccess)}
                            {entity : The Root Entity (e.g., Role)}
                            {--E|event : Scaffold Domain Event}
                            {--O|vo : Scaffold Value Object}
                            {--X|exception : Scaffold Domain Exception}
                            {--L|livewire : Scaffold Livewire Component (Primary Adapter)}
                            {--P|policy : Scaffold Authorization Policy}
                            {--A|api : Scaffold an HTTP API adapter (Controller, FormRequest, routes). Opt-in only — this app is 100% Livewire SPA, so Livewire components (and their Form Objects) are the default Presentation adapter. Only add this when the business genuinely needs to expose a REST endpoint.}
                            {--F|force : Overwrite an existing module (deletes it first)}';

    /**
     * The console command description.
     */
    protected $description = 'Scaffolds a pragmatic, high-cohesion DDD directory structure tailored for the TALL stack.';

    /**
     * Stubs generated unconditionally — the minimum viable slice through
     * every layer for a Livewire-first module. No HTTP adapter by
     * default: keeping Controller/FormRequest/routes out of the base
     * scaffold avoids maintaining a primary adapter nothing calls.
     */
    private const REQUIRED_STUBS = [
        'repository.stub',
        'dto.stub',
        'use_case.stub',
        'contract.stub',
        'entity.stub',
    ];

    /**
     * Stubs gated behind a single flag, keyed by their option name.
     */
    private const OPTIONAL_STUBS = [
        'event' => 'event.stub',
        'vo' => 'value_object.stub',
        'exception' => 'exception.stub',
        'livewire' => 'livewire.stub',
        'policy' => 'policy.stub',
    ];

    /**
     * The HTTP API adapter is a single opt-in unit — Controller, Request
     * and routes only make sense together, so one flag (--api) toggles
     * all three rather than three separate flags.
     */
    private const API_STUBS = [
        'route.stub',
        'controller.stub',
        'request.stub',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $context = Str::studly((string) $this->argument('context'));
        $entity  = Str::studly((string) $this->argument('entity'));
        $basePath = base_path("src/{$context}/{$entity}");

        $stubsToGenerate = $this->resolveStubsToGenerate();

        // Fail fast: never touch the filesystem if a required stub is missing.
        // A half-written module is worse than no module.
        if (!$this->assertStubsExist($stubsToGenerate)) {
            return Command::FAILURE;
        }

        // Guard clause: Prevent accidental overwrites of existing domain rules.
        // Immutability of the core domain is a strict architectural boundary,
        // with a deliberate, explicit escape hatch for iterative development.
        if (File::exists($basePath)) {
            if (!$this->option('force')) {
                $this->error("Critical: Architecture boundaries for {$context}\\{$entity} already exist.");
                $this->line("Aborting operation to preserve domain integrity. Use --force to regenerate.");
                return Command::FAILURE;
            }

            $this->warn("--force supplied: removing existing {$context}\\{$entity} module.");
            File::deleteDirectory($basePath);
        }

        // A DDD scaffold without a matching PSR-4 map is dead code the moment
        // it's written. This guarantees the module is actually loadable.
        $this->ensureNamespaceIsAutoloaded();

        $this->info("Scaffolding lean DDD structure for {$context}\\{$entity}...");

        try {
            $this->createDirectories($basePath);
            $this->generateBaseStubs($basePath, $context, $entity);
            $advancedGenerated = $this->generateOptionalStubs($basePath, $context, $entity);
        } catch (Throwable $e) {
            $this->error("Generation failed: {$e->getMessage()}");
            $this->line('Rolling back to preserve domain integrity...');
            File::deleteDirectory($basePath);
            return Command::FAILURE;
        }

        $this->newLine();

        if (!$advancedGenerated) {
            $this->line("No advanced components requested. (Use --help for available options).");
        }

        // Wire the new module into the container / auth layer automatically.
        // Still a static array under the hood — no runtime reflection — just
        // written at scaffold-time instead of by hand.
        $this->wireIntoContainer($context, $entity);

        $this->newLine();
        $this->info("Pragmatic DDD Scaffolding for {$entity} completed successfully.");

        return Command::SUCCESS;
    }

    // ---------------------------------------------------------------
    // Pre-flight validation
    // ---------------------------------------------------------------
    /**
     * @return array<int, string>
     */
    private function resolveStubsToGenerate(): array
    {
        $stubs = self::REQUIRED_STUBS;

        foreach (self::OPTIONAL_STUBS as $option => $stub) {
            if ($this->option($option)) {
                $stubs[] = $stub;
            }
        }

        if ($this->option('api')) {
            $stubs = array_merge($stubs, self::API_STUBS);
        }

        return $stubs;
    }
    /**
     * @param array<int, string> $stubs
     */
    private function assertStubsExist(array $stubs): bool
    {
        $missing = array_values(array_filter(
            $stubs,
            fn(string $stub) => !File::exists(base_path("stubs/ddd/{$stub}"))
        ));

        if (empty($missing)) {
            return true;
        }

        $this->error('Cannot scaffold: the following stub files are missing:');
        foreach ($missing as $stub) {
            $this->line("  - stubs/ddd/{$stub}");
        }
        $this->line('Nothing was written to disk.');

        return false;
    }

    /**
     * Guarantees classes under src/ are actually resolvable by Composer.
     * Idempotent: only writes composer.json if the mapping is missing.
     */
    private function ensureNamespaceIsAutoloaded(): void
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            $this->warn('composer.json not found — skipping PSR-4 verification.');
            return;
        }

        $composer = json_decode(File::get($composerPath), true);

        if (isset($composer['autoload']['psr-4']['Src\\'])) {
            return;
        }

        $composer['autoload']['psr-4']['Src\\'] = 'src/';

        File::put(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        $this->warn('Registered "Src\\\\" => "src/" under composer.json psr-4 autoload.');
        $this->warn('Run `composer dump-autoload` before using the generated classes.');
    }

    // ---------------------------------------------------------------
    // Generation
    // ---------------------------------------------------------------

    private function createDirectories(string $basePath): void
    {
        // Define strict layer boundaries.
        // Note: We intentionally omit DAOs and API resources to prevent overengineering.
        // We leverage Eloquent strictly as an infrastructure persistence mechanism.
        $directories = [
            // 1. Domain Layer (Pure PHP, zero framework coupling)
            "{$basePath}/Domain/Entities",
            "{$basePath}/Domain/Contracts",
            "{$basePath}/Domain/ValueObjects",
            "{$basePath}/Domain/Events",
            "{$basePath}/Domain/Exceptions",

            // 2. Application Layer (Use Case orchestration and DTO boundaries)
            "{$basePath}/Application/UseCases",
            "{$basePath}/Application/DTOs",

            // 3. Infrastructure Layer (Concrete implementations, e.g., Eloquent Repositories)
            "{$basePath}/Infrastructure/Persistence/Repositories",

            // 4. Presentation Layer (Primary Adapters). Livewire is the
            // default and only guaranteed adapter — Controllers/Requests/
            // Routes are HTTP-specific and only created with --api.
            "{$basePath}/Presentation/Livewire",
            "{$basePath}/Presentation/Policies",
        ];

        if ($this->option('api')) {
            $directories[] = "{$basePath}/Presentation/Controllers";
            $directories[] = "{$basePath}/Presentation/Requests";
            $directories[] = "{$basePath}/Presentation/Routes";
        }

        foreach ($directories as $dir) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $this->info("Architectural boundaries established successfully.");
    }

    private function generateBaseStubs(string $basePath, string $context, string $entity): void
    {
        $this->newLine();
        $this->info("Generating base stubs across layers...");

        // Infrastructure Layer: Relying on Repositories to abstract Eloquent logic.
        $this->generateFromStub('repository.stub', "{$basePath}/Infrastructure/Persistence/Repositories/Eloquent{$entity}Repository.php", $context, $entity);

        // Application Layer: Establishing strict data boundaries.
        $this->generateFromStub('dto.stub', "{$basePath}/Application/DTOs/{$entity}DTO.php", $context, $entity);
        $this->generateFromStub('use_case.stub', "{$basePath}/Application/UseCases/Create{$entity}UseCase.php", $context, $entity);

        // Domain Layer: Defining the core contract and business entity.
        $this->generateFromStub('contract.stub', "{$basePath}/Domain/Contracts/{$entity}RepositoryInterface.php", $context, $entity);
        $this->generateFromStub('entity.stub', "{$basePath}/Domain/Entities/{$entity}.php", $context, $entity);
    }

    private function generateOptionalStubs(string $basePath, string $context, string $entity): bool
    {
        $this->newLine();
        $this->info("Evaluating flags for advanced domain components...");
        $generated = false;

        if ($this->option('event')) {
            $this->generateFromStub('event.stub', "{$basePath}/Domain/Events/{$entity}CreatedEvent.php", $context, $entity);
            $generated = true;
        }
        if ($this->option('vo')) {
            $this->generateFromStub('value_object.stub', "{$basePath}/Domain/ValueObjects/{$entity}NameVO.php", $context, $entity);
            $generated = true;
        }
        if ($this->option('exception')) {
            $this->generateFromStub('exception.stub', "{$basePath}/Domain/Exceptions/{$entity}NotFoundException.php", $context, $entity);
            $generated = true;
        }
        if ($this->option('livewire')) {
            // Generates a Single File Component (Livewire flavor) acting as
            // the default primary adapter for this module.
            $this->generateFromStub('livewire.stub', "{$basePath}/Presentation/Livewire/{$entity}Component.php", $context, $entity);
            $generated = true;
        }
        if ($this->option('policy')) {
            $this->generateFromStub('policy.stub', "{$basePath}/Presentation/Policies/{$entity}Policy.php", $context, $entity);
            $generated = true;
        }
        if ($this->option('api')) {
            // Opt-in HTTP adapter. Only scaffold this when the business
            // genuinely needs a REST endpoint — the default Presentation
            // adapter for this app is Livewire, not Controllers.
            $this->generateFromStub('route.stub', "{$basePath}/Presentation/Routes/web.php", $context, $entity);
            $this->generateFromStub('controller.stub', "{$basePath}/Presentation/Controllers/{$entity}Controller.php", $context, $entity);
            $this->generateFromStub('request.stub', "{$basePath}/Presentation/Requests/{$entity}Request.php", $context, $entity);
            $generated = true;
        }

        return $generated;
    }

    /**
     * Reads a stub file, replaces structural placeholders, and writes it to the destination path.
     */
    private function generateFromStub(string $stubName, string $destination, string $context, string $entity): void
    {
        $stubPath = base_path("stubs/ddd/{$stubName}");

        // Defensive: assertStubsExist() already guarantees this pre-flight,
        // but a generator that can silently skip a layer is worse than one
        // that fails loudly and lets handle() roll back.
        if (!File::exists($stubPath)) {
            throw new RuntimeException("Missing stub file: {$stubPath}");
        }

        $content = File::get($stubPath);

        $content = str_replace(
            ['{{Context}}', '{{Entity}}', '{{context_lower}}', '{{entity_lower}}'],
            [$context, $entity, Str::lower($context), Str::lower($entity)],
            $content
        );

        File::put($destination, $content);

        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $destination);
        $this->line("<fg=green>✓ Generated:</> {$relativePath}");
    }

    // ---------------------------------------------------------------
    // Container / DI wiring
    // ---------------------------------------------------------------

    /**
     * Auto-registers the new bindings in DomainServiceProvider so the module
     * is resolvable immediately — no manual array editing, no runtime
     * reflection or filesystem scanning cost at boot.
     */
    private function wireIntoContainer(string $context, string $entity): void
    {
        $providerPath = app_path('Providers/DomainServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->warn('DomainServiceProvider.php not found — skipping automatic wiring.');
            return;
        }

        $interface = "\\Src\\{$context}\\{$entity}\\Domain\\Contracts\\{$entity}RepositoryInterface::class";
        $implementation = "\\Src\\{$context}\\{$entity}\\Infrastructure\\Persistence\\Repositories\\Eloquent{$entity}Repository::class";

        $this->injectArrayEntry(
            $providerPath,
            'domainBindings',
            "        {$interface} => {$implementation},",
            $interface,
            "repository binding for {$context}\\{$entity}"
        );

        if ($this->option('policy')) {
            $entityClass = "\\Src\\{$context}\\{$entity}\\Domain\\Entities\\{$entity}::class";
            $policyClass = "\\Src\\{$context}\\{$entity}\\Presentation\\Policies\\{$entity}Policy::class";

            $this->injectArrayEntry(
                $providerPath,
                'domainPolicies',
                "        {$entityClass} => {$policyClass},",
                $entityClass,
                "policy binding for {$context}\\{$entity}"
            );
        }
    }

    /**
     * Idempotently inserts a line before the closing bracket of a
     * `private array $property = [...]` declaration in $path.
     */
    private function injectArrayEntry(
        string $path,
        string $property,
        string $line,
        string $uniqueMarker,
        string $label
    ): void {
        $content = File::get($path);

        if (str_contains($content, $uniqueMarker)) {
            $this->line("<fg=yellow>⚠ Skipped:</> {$label} already registered.");
            return;
        }

        $pattern = '/(private array \$' . preg_quote($property, '/') . ' = \[)(.*?)(\n(\s*)\];)/s';

        if (!preg_match($pattern, $content)) {
            $this->warn("Could not locate \${$property} in " . basename($path) . " — add this binding manually:");
            $this->line(trim($line));
            return;
        }

        $content = preg_replace_callback($pattern, function (array $matches) use ($line) {
            return $matches[1] . $matches[2] . "\n{$line}" . $matches[3];
        }, $content, 1);

        File::put($path, $content);
        $this->line("<fg=green>✓ Wired:</> {$label} into " . basename($path));
    }
}
