<?php

namespace App\Atom\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Make Resource Command
 * 
 * FilamentPHP-inspired Artisan command to generate resource classes.
 * Creates pure PHP resource classes that work across different systems.
 */
#[AsCommand(name: 'atom:resource')]
class MakeResourceCommand extends GeneratorCommand implements PromptsForMissingInput
{
    /**
     * The console command name.
     */
    protected $name = 'atom:resource';

    /**
     * The console command description.
     */
    protected $description = 'Create a new resource class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Resource';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        // We'll use the inline stub content instead of external files
        return '';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Atom\Resources';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = $this->getStubContent();

        return $this->replaceNamespace($stub, $name)
            ->replaceModel($stub, $name)
            ->replaceClass($stub, $name);
    }

    /**
     * Replace the model for the given stub.
     */
    protected function replaceModel(string &$stub, string $name): static
    {
        $modelName = $this->getModelName($name);
        $modelClass = $this->getModelClass($modelName);

        $stub = str_replace(
            ['{{ model }}', '{{ modelClass }}', '{{ modelVariable }}'],
            [$modelName, $modelClass, Str::camel($modelName)],
            $stub
        );

        return $this;
    }

    /**
     * Get the desired class name from the input.
     */
    protected function getNameInput(): string
    {
        $name = trim($this->argument('name'));
        
        // If the name doesn't end with "Resource", add it
        if (!str_ends_with($name, 'Resource')) {
            $name .= 'Resource';
        }
        
        return $name;
    }

    /**
     * Get the model name from the resource name.
     */
    protected function getModelName(string $name): string
    {
        $model = $this->option('model');

        if ($model) {
            return $model;
        }

        // Remove "Resource" suffix and get the model name
        $resourceName = class_basename($name);
        
        if (str_ends_with($resourceName, 'Resource')) {
            return substr($resourceName, 0, -8); // Remove "Resource"
        }

        return $resourceName;
    }

    /**
     * Get the fully qualified model class name.
     */
    protected function getModelClass(string $modelName): string
    {
        $modelNamespace = $this->option('model-namespace') ?: 'App\\Models';
        
        return rtrim($modelNamespace, '\\') . '\\' . $modelName;
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The model class name'],
            ['model-namespace', null, InputOption::VALUE_OPTIONAL, 'The model namespace', 'App\\Models'],
            ['simple', 's', InputOption::VALUE_NONE, 'Create a simple resource with minimal configuration'],
            ['generate', 'g', InputOption::VALUE_NONE, 'Generate table configuration based on model'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing resource'],
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     */
    protected function afterPromptingForMissingArguments($input, $output): void
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        // Prompt for model if not provided
        if (!$this->option('model')) {
            $model = $this->components->ask('What model should this resource be for?');
            $input->setOption('model', $model);
        }
    }

    /**
     * Determine if the user provided any options.
     */
    protected function didReceiveOptions($input): bool
    {
        return collect($this->getOptions())
            ->reject(fn (array $option) => $option[2] === InputOption::VALUE_NONE)
            ->some(fn (array $option) => $input->getOption($option[0]));
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result !== false) {
            $this->displaySuccessMessage();
            $this->suggestNextSteps();
        }

        return $result;
    }

    /**
     * Display success message with helpful information.
     */
    protected function displaySuccessMessage(): void
    {
        $resourceClass = $this->qualifyClass($this->getNameInput());
        $modelClass = $this->getModelClass($this->getModelName($resourceClass));

        $this->components->info(sprintf(
            'Resource [%s] created successfully.',
            $resourceClass
        ));

        if (!class_exists($modelClass)) {
            $this->components->warn(sprintf(
                'Model [%s] does not exist. You may need to create it first.',
                $modelClass
            ));
        }
    }

    /**
     * Suggest next steps to the user.
     */
    protected function suggestNextSteps(): void
    {
        $resourceName = class_basename($this->qualifyClass($this->getNameInput()));
        
        $this->line('');
        $this->components->twoColumnDetail('Next steps:', '');
        $this->components->twoColumnDetail('• Configure your resource table', 'Edit the table() method');
        $this->components->twoColumnDetail('• Add navigation settings', 'Set $navigationLabel, $navigationIcon, etc.');
        $this->components->twoColumnDetail('• Test your resource', 'Visit /' . Str::kebab(Str::plural($this->getModelName($resourceName))));
        $this->components->twoColumnDetail('• View all resources', 'Run php artisan resources:list');
        
        if ($this->option('generate')) {
            $this->line('');
            $this->components->info('Auto-generation is not yet implemented. Please configure your table manually.');
        }
    }

    /**
     * Get the stub file content.
     */
    protected function getStubContent(): string
    {
        $stub = <<<'PHP'
<?php

namespace {{ namespace }};

use {{ modelClass }};
use App\Atom\Resources\Resource;
use App\Atom\Tables\Column;
use App\Atom\Tables\Filter;
use App\Atom\Tables\Action;
use App\Atom\Tables\HeaderAction;
use App\Atom\Tables\BulkAction;
use App\Atom\Tables\Table;

/**
 * {{ model }} Resource
 * 
 * Resource class for managing {{ model }} records across different systems.
 */
class {{ class }} extends Resource
{
    /**
     * The resource's associated Eloquent model.
     */
    protected static ?string $model = {{ modelClass }}::class;
    
    /**
     * The resource navigation label.
     */
    protected static ?string $navigationLabel = '{{ model }}s';
    
    /**
     * The resource navigation icon.
     */
    protected static ?string $navigationIcon = 'cube';
    
    /**
     * The resource navigation group.
     */
    protected static ?string $navigationGroup = null;
    
    /**
     * The model label (singular).
     */
    protected static ?string $modelLabel = '{{ modelVariable }}';
    
    /**
     * The plural model label.
     */
    protected static ?string $pluralModelLabel = '{{ modelVariable }}s';
    
    /**
     * The record title attribute for identification.
     */
    protected static ?string $recordTitleAttribute = 'name';
    
    /**
     * Configure the resource table.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('id')
                    ->label('ID')
                    ->sortable(),

                Column::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                Column::make('created_at')
                    ->label('Created')
                    ->sortable(),
            ])
            ->filters([
                // Add your filters here
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create {{ model }}')
                    ->icon('plus')
                    ->color('primary'),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('pencil')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
                    
                Action::make('delete')
                    ->label('Delete')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->delete();
                        session()->flash('success', '{{ model }} deleted successfully.');
                    }),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        {{ modelClass }}::whereIn('id', $records)->delete();
                        session()->flash('success', count($records) . ' {{ modelVariable }}s deleted successfully.');
                    }),
            ]);
    }
}
PHP;

        return $stub;
    }

    /**
     * Write the generated stub to file.
     */
    protected function writeToFile($path, $content): void
    {
        $this->makeDirectory($path);

        $this->files->put($path, $content);
    }
}