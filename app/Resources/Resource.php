<?php

namespace App\Resources;

use App\Table\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Base Resource Class
 * 
 * FilamentPHP-inspired resource system that allows pure PHP configuration
 * classes to automatically generate working table/form interfaces.
 */
abstract class Resource
{
    /**
     * The resource's associated Eloquent model class.
     */
    protected static ?string $model = null;
    
    /**
     * The resource title displayed in navigation.
     */
    protected static ?string $navigationLabel = null;
    
    /**
     * The resource navigation icon.
     */
    protected static ?string $navigationIcon = null;
    
    /**
     * The resource navigation group.
     */
    protected static ?string $navigationGroup = null;
    
    /**
     * The resource navigation sort order.
     */
    protected static ?int $navigationSort = null;
    
    /**
     * The resource slug for URLs.
     */
    protected static ?string $slug = null;
    
    /**
     * The model label (singular).
     */
    protected static ?string $modelLabel = null;
    
    /**
     * The plural model label.
     */
    protected static ?string $pluralModelLabel = null;
    
    /**
     * The record title attribute for identification.
     */
    protected static ?string $recordTitleAttribute = null;

    /**
     * Configure the resource table (FilamentPHP style).
     */
    public static function table(Table $table): Table
    {
        return $table;
    }
    
    /**
     * Get the resource's Eloquent model class.
     */
    public static function getModel(): string
    {
        return static::$model ?? throw new \Exception(
            'Resource [' . static::class . '] must define a $model property.'
        );
    }
    
    /**
     * Get the resource's Eloquent query builder.
     */
    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }
    
    /**
     * Get the resource slug for URLs.
     */
    public static function getSlug(): string
    {
        if (filled(static::$slug)) {
            return static::$slug;
        }
        
        return Str::kebab(static::getPluralModelLabel());
    }
    
    /**
     * Get the resource navigation label.
     */
    public static function getNavigationLabel(): string
    {
        if (filled(static::$navigationLabel)) {
            return static::$navigationLabel;
        }
        
        return static::getPluralModelLabel();
    }
    
    /**
     * Get the resource navigation icon.
     */
    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }
    
    /**
     * Get the resource navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }
    
    /**
     * Get the resource navigation sort order.
     */
    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }
    
    /**
     * Get the model label (singular).
     */
    public static function getModelLabel(): string
    {
        if (filled(static::$modelLabel)) {
            return static::$modelLabel;
        }
        
        return Str::kebab(class_basename(static::getModel()));
    }
    
    /**
     * Get the plural model label.
     */
    public static function getPluralModelLabel(): string
    {
        if (filled(static::$pluralModelLabel)) {
            return static::$pluralModelLabel;
        }
        
        return Str::plural(static::getModelLabel());
    }
    
    /**
     * Get the record title attribute.
     */
    public static function getRecordTitleAttribute(): ?string
    {
        return static::$recordTitleAttribute;
    }
    
    /**
     * Get the resource's available pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => [
                'component' => 'resources.list-records',
                'route' => '/',
            ],
            'create' => [
                'component' => 'resources.create-record',
                'route' => '/create',
            ],
            'edit' => [
                'component' => 'resources.edit-record',
                'route' => '/{record}/edit',
            ],
        ];
    }
    
    /**
     * Generate URL for a specific page within the resource.
     */
    public static function getUrl(string $name = 'index', array $parameters = []): string
    {
        $pages = static::getPages();
        $page = $pages[$name] ?? throw new \Exception("Page [{$name}] not found in resource.");
        
        $route = rtrim(static::getSlug() . $page['route'], '/');
        
        // Replace route parameters
        foreach ($parameters as $key => $value) {
            if ($value instanceof Model) {
                $value = $value->getRouteKey();
            }
            
            $route = str_replace("{{$key}}", $value, $route);
        }
        
        return '/' . trim($route, '/');
    }
    
    /**
     * Get the resource class name from the model.
     */
    public static function getResourceName(): string
    {
        return class_basename(static::class);
    }
    
    /**
     * Determine if the resource can be globally searched.
     */
    public static function canGloballySearch(): bool
    {
        return static::getRecordTitleAttribute() !== null;
    }
    
    /**
     * Get the global search title using the record title attribute.
     */
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $titleAttribute = static::getRecordTitleAttribute();
        
        if (!$titleAttribute) {
            return $record->getRouteKey();
        }
        
        return $record->getAttribute($titleAttribute) ?? $record->getRouteKey();
    }
    
    /**
     * Configure the resource with default settings.
     */
    public static function configureResource(): array
    {
        return [
            'model' => static::getModel(),
            'slug' => static::getSlug(),
            'navigationLabel' => static::getNavigationLabel(),
            'navigationIcon' => static::getNavigationIcon(),
            'navigationGroup' => static::getNavigationGroup(),
            'navigationSort' => static::getNavigationSort(),
            'modelLabel' => static::getModelLabel(),
            'pluralModelLabel' => static::getPluralModelLabel(),
            'recordTitleAttribute' => static::getRecordTitleAttribute(),
            'pages' => static::getPages(),
        ];
    }
}