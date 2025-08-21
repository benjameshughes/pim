<?php

namespace Database\Factories;

use App\Models\ChannelFieldDefinition;
use App\Models\ChannelFieldMapping;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelFieldMapping>
 */
class ChannelFieldMappingFactory extends Factory
{
    protected $model = ChannelFieldMapping::class;

    public function definition(): array
    {
        $mappingTypes = ['pim_field', 'static_value', 'expression', 'custom'];
        $mappingType = $this->faker->randomElement($mappingTypes);
        $mappingLevel = $this->faker->randomElement(['global', 'product', 'variant']);

        return [
            'field_definition_id' => ChannelFieldDefinition::factory(),
            'product_id' => $this->faker->optional(0.6)->randomElement([
                null, // Global mapping
                Product::factory(),
            ]),
            'variant_scope' => $this->generateVariantScope($mappingLevel),
            'mapping_type' => $mappingType,
            'source_field' => $this->generateSourceField($mappingType),
            'static_value' => $this->generateStaticValue($mappingType),
            'mapping_expression' => $this->generateExpression($mappingType),
            'transformation_rules' => $this->faker->optional(0.3)->randomElement([
                ['uppercase' => true],
                ['prefix' => 'Product: ', 'suffix' => ' - New'],
                ['max_length' => 100, 'truncate_suffix' => '...'],
                ['trim' => true, 'strip_html' => true],
            ]),
            'mapping_level' => $mappingLevel,
            'priority' => $this->faker->numberBetween(1, 10),
            'notes' => $this->faker->optional(0.4)->sentence(),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
        ];
    }

    /**
     * Generate variant scope based on mapping level
     */
    private function generateVariantScope(string $mappingLevel): ?string
    {
        if ($mappingLevel !== 'variant') {
            return null;
        }

        return $this->faker->randomElement([
            'color:Red',
            'size:Large',
            'color:Blue,size:Medium',
            'material:Cotton',
            'style:Classic',
        ]);
    }

    /**
     * Generate source field based on mapping type
     */
    private function generateSourceField(string $mappingType): ?string
    {
        if ($mappingType !== 'pim_field') {
            return null;
        }

        return $this->faker->randomElement([
            'name',
            'description',
            'sku',
            'brand',
            'manufacturer',
            'category',
            'weight',
            'dimensions',
            'price',
            'cost_price',
        ]);
    }

    /**
     * Generate static value based on mapping type
     */
    private function generateStaticValue(string $mappingType): ?string
    {
        if ($mappingType !== 'static_value') {
            return null;
        }

        return $this->faker->randomElement([
            'Fixed Brand Name',
            'Standard Description',
            '99.99',
            'NEW',
            'Electronics > Gadgets',
            'Free Shipping Available',
        ]);
    }

    /**
     * Generate expression based on mapping type
     */
    private function generateExpression(string $mappingType): ?string
    {
        if ($mappingType !== 'expression') {
            return null;
        }

        return $this->faker->randomElement([
            '{{ product.name }} - {{ variant.color }}',
            '{{ product.brand }} {{ product.name }}',
            '{{ product.sku }}-{{ variant.size }}',
            '{{ product.name | upper }} ({{ product.category }})',
            'Price: ${{ product.price | number_format(2) }}',
        ]);
    }

    /**
     * Create a global mapping
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => null,
            'variant_scope' => null,
            'mapping_level' => 'global',
        ]);
    }

    /**
     * Create a product-specific mapping
     */
    public function forProduct(?Product $product = null): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product?->id ?? Product::factory(),
            'variant_scope' => null,
            'mapping_level' => 'product',
        ]);
    }

    /**
     * Create a variant-specific mapping
     */
    public function forVariant(?Product $product = null, ?string $variantScope = null): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product?->id ?? Product::factory(),
            'variant_scope' => $variantScope ?? 'color:Red',
            'mapping_level' => 'variant',
        ]);
    }

    /**
     * Create a PIM field mapping
     */
    public function pimField(string $sourceField): static
    {
        return $this->state(fn (array $attributes) => [
            'mapping_type' => 'pim_field',
            'source_field' => $sourceField,
            'static_value' => null,
            'mapping_expression' => null,
        ]);
    }

    /**
     * Create a static value mapping
     */
    public function staticValue(string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'mapping_type' => 'static_value',
            'source_field' => null,
            'static_value' => $value,
            'mapping_expression' => null,
        ]);
    }

    /**
     * Create an expression mapping
     */
    public function expression(string $expression): static
    {
        return $this->state(fn (array $attributes) => [
            'mapping_type' => 'expression',
            'source_field' => null,
            'static_value' => null,
            'mapping_expression' => $expression,
        ]);
    }

    /**
     * Create an active mapping
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive mapping
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a mapping with transformations
     */
    public function withTransformations(array $rules): static
    {
        return $this->state(fn (array $attributes) => [
            'transformation_rules' => $rules,
        ]);
    }

    /**
     * Create a high priority mapping
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(8, 10),
        ]);
    }

    /**
     * Create a low priority mapping
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(1, 3),
        ]);
    }
}
