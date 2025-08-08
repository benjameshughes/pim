<?php

use App\Services\Import\Extractors\MadeToMeasureExtractor;
use App\Services\Import\Extractors\SmartDimensionExtractor;
use App\Services\Import\Analyzers\SkuPatternAnalyzer;
use App\Services\Import\Extractors\SmartAttributeExtractor;
use App\Models\ImportSession;

describe('Extraction Services', function () {
    beforeEach(function () {
        $this->actingAs(\App\Models\User::factory()->create());
    });

    describe('MadeToMeasureExtractor', function () {
        it('detects basic MTM keywords', function () {
            $extractor = new MadeToMeasureExtractor();
            
            $testCases = [
                'Made to Measure Curtains' => true,
                'MTM Curtains Red' => true,
                'Bespoke Window Treatment' => true,
                'Custom Size Blind 150x200' => true,
                'Made-to-measure Roman blind' => true,
                'Standard Curtain Blue' => false,
                'Ready made blind' => false,
                'Premium Fabric Sample' => false,
            ];
            
            foreach ($testCases as $text => $expected) {
                $result = $extractor->extract($text);
                expect($result['is_made_to_measure'])->toBe($expected, "Failed for text: $text");
            }
        });

        it('provides confidence scores', function () {
            $extractor = new MadeToMeasureExtractor();
            
            $highConfidence = $extractor->extract('Made to Measure Curtain Panel');
            expect($highConfidence['confidence'])->toBeGreaterThan(0.8);
            expect($highConfidence['confidence'])->toBeLessThanOrEqual(1.0);
            
            $mediumConfidence = $extractor->extract('Custom width blinds available');
            expect($mediumConfidence['confidence'])->toBeGreaterThan(0.4);
            expect($mediumConfidence['confidence'])->toBeLessThan(0.8);
            
            $lowConfidence = $extractor->extract('Standard size curtain');
            expect($lowConfidence['confidence'])->toBeLessThanOrEqual(0.4);
        });

        it('extracts MTM indicators and keywords', function () {
            $extractor = new MadeToMeasureExtractor();
            
            $result = $extractor->extract('Bespoke Made to Measure Roman Blind - Custom Colors Available');
            
            expect($result['is_made_to_measure'])->toBeTrue();
            expect($result['indicators'])->toContain('bespoke');
            expect($result['indicators'])->toContain('made to measure');
            expect($result['indicators'])->toContain('custom');
            expect($result['confidence'])->toBeGreaterThan(0.9);
        });

        it('handles case insensitive detection', function () {
            $extractor = new MadeToMeasureExtractor();
            
            $testCases = [
                'MADE TO MEASURE' => true,
                'mtm curtains' => true,
                'Bespoke Treatment' => true,
                'CUSTOM SIZE' => true,
            ];
            
            foreach ($testCases as $text => $expected) {
                $result = $extractor->extract($text);
                expect($result['is_made_to_measure'])->toBe($expected);
            }
        });

        it('provides detailed extraction metadata', function () {
            $extractor = new MadeToMeasureExtractor();
            
            $result = $extractor->extract('MTM Roman Blind - Made to Measure with Custom Brackets');
            
            expect($result)->toHaveKey('is_made_to_measure');
            expect($result)->toHaveKey('confidence');
            expect($result)->toHaveKey('indicators');
            expect($result)->toHaveKey('extraction_method');
            expect($result)->toHaveKey('matched_patterns');
            
            expect($result['extraction_method'])->toBe('keyword_matching');
            expect($result['matched_patterns'])->toBeArray();
            expect($result['matched_patterns'])->not->toBeEmpty();
        });
    });

    describe('SmartDimensionExtractor', function () {
        it('extracts dimensions from various formats', function () {
            $extractor = new SmartDimensionExtractor(['digits_only' => false]);
            
            $testCases = [
                '150cm x 200cm' => ['width' => 150, 'height' => 200, 'unit' => 'cm'],
                '120 x 180 cm' => ['width' => 120, 'height' => 180, 'unit' => 'cm'],
                '5ft x 6ft' => ['width' => 5, 'height' => 6, 'unit' => 'ft'],
                '1200mm drop' => ['drop' => 1200, 'unit' => 'mm'],
                'Width: 150cm, Drop: 200cm' => ['width' => 150, 'drop' => 200, 'unit' => 'cm'],
                '36" wide' => ['width' => 36, 'unit' => 'in'],
            ];
            
            foreach ($testCases as $text => $expected) {
                $result = $extractor->extract($text);
                expect($result['found_dimensions'])->toBeTrue("Failed to extract from: $text");
                
                foreach ($expected as $key => $value) {
                    expect($result['dimensions'])->toHaveKey($key, "Missing key $key for: $text");
                    expect($result['dimensions'][$key])->toBe($value, "Wrong value for $key in: $text");
                }
            }
        });

        it('extracts only digits when configured', function () {
            $extractor = new SmartDimensionExtractor(['digits_only' => true]);
            
            $result = $extractor->extract('150cm x 200cm Roman Blind');
            
            expect($result['found_dimensions'])->toBeTrue();
            expect($result['dimensions']['width'])->toBe(150);
            expect($result['dimensions']['height'])->toBe(200);
            expect($result['dimensions'])->not->toHaveKey('unit');
        });

        it('handles complex dimension descriptions', function () {
            $extractor = new SmartDimensionExtractor();
            
            $complexText = 'Roman Blind - Width: 120cm, Drop: 180cm, Depth: 5cm - White';
            $result = $extractor->extract($complexText);
            
            expect($result['found_dimensions'])->toBeTrue();
            expect($result['dimensions']['width'])->toBe(120);
            expect($result['dimensions']['drop'])->toBe(180);
            expect($result['dimensions']['depth'])->toBe(5);
            expect($result['dimensions']['unit'])->toBe('cm');
            expect($result['confidence'])->toBeGreaterThan(0.8);
        });

        it('provides confidence scores based on pattern quality', function () {
            $extractor = new SmartDimensionExtractor();
            
            $highConfidence = $extractor->extract('Width: 150cm, Drop: 200cm');
            expect($highConfidence['confidence'])->toBeGreaterThan(0.8);
            
            $mediumConfidence = $extractor->extract('150 x 200 approx');
            expect($mediumConfidence['confidence'])->toBeGreaterThan(0.5);
            expect($mediumConfidence['confidence'])->toBeLessThan(0.8);
            
            $lowConfidence = $extractor->extract('around 150cm wide maybe');
            expect($lowConfidence['confidence'])->toBeLessThan(0.6);
        });

        it('handles imperial and metric units', function () {
            $extractor = new SmartDimensionExtractor();
            
            // Metric
            $metricResult = $extractor->extract('150cm x 200mm');
            expect($metricResult['dimensions']['width'])->toBe(150);
            expect($metricResult['dimensions']['height'])->toBe(200);
            expect($metricResult['unit_system'])->toBe('metric');
            
            // Imperial  
            $imperialResult = $extractor->extract('5ft x 6ft');
            expect($imperialResult['dimensions']['width'])->toBe(5);
            expect($imperialResult['dimensions']['height'])->toBe(6);
            expect($imperialResult['unit_system'])->toBe('imperial');
        });

        it('identifies dimension types correctly', function () {
            $extractor = new SmartDimensionExtractor();
            
            $testCases = [
                'width 150cm' => 'width',
                'drop 200cm' => 'drop', 
                'height 180cm' => 'height',
                'length 300cm' => 'length',
                'depth 10cm' => 'depth',
                'diameter 50cm' => 'diameter',
            ];
            
            foreach ($testCases as $text => $expectedType) {
                $result = $extractor->extract($text);
                expect($result['dimensions'])->toHaveKey($expectedType);
            }
        });
    });

    describe('SkuPatternAnalyzer', function () {
        it('analyzes SKU patterns correctly', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            $skus = [
                'ABC-001-001',
                'ABC-001-002', 
                'ABC-001-003',
                'DEF-002-001',
                'DEF-002-002',
                'XYZ-003-001',
            ];
            
            $result = $analyzer->analyze($skus);
            
            expect($result['has_pattern'])->toBeTrue();
            expect($result['pattern_type'])->toBe('hierarchical');
            expect($result['confidence'])->toBeGreaterThan(0.8);
            expect($result['suggested_groups'])->toHaveCount(3);
            
            // Should group by first part (ABC, DEF, XYZ)
            expect($result['suggested_groups']['ABC'])->toContain('ABC-001-001');
            expect($result['suggested_groups']['ABC'])->toContain('ABC-001-002');
            expect($result['suggested_groups']['DEF'])->toContain('DEF-002-001');
        });

        it('detects sequential patterns', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            $skus = [
                'PROD001',
                'PROD002',
                'PROD003',
                'PROD004',
            ];
            
            $result = $analyzer->analyze($skus);
            
            expect($result['has_pattern'])->toBeTrue();
            expect($result['pattern_type'])->toBe('sequential');
            expect($result['base_pattern'])->toBe('PROD');
            expect($result['sequence_type'])->toBe('numeric');
        });

        it('detects size-based patterns', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            $skus = [
                'CURTAIN-S-RED',
                'CURTAIN-M-RED',
                'CURTAIN-L-RED',
                'CURTAIN-S-BLUE',
                'CURTAIN-M-BLUE',
            ];
            
            $result = $analyzer->analyze($skus);
            
            expect($result['has_pattern'])->toBeTrue();
            expect($result['pattern_type'])->toBe('attribute_based');
            expect($result['attributes_detected'])->toContain('size');
            expect($result['attributes_detected'])->toContain('color');
        });

        it('handles digit-based parent SKU patterns', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            $skus = [
                '001-001',
                '001-002', 
                '001-003',
                '002-001',
                '002-002',
            ];
            
            $result = $analyzer->analyze($skus);
            
            expect($result['has_pattern'])->toBeTrue();
            expect($result['pattern_type'])->toBe('numeric_hierarchical');
            expect($result['parent_extraction_method'])->toBe('first_digits');
            expect($result['suggested_groups']['001'])->toHaveCount(3);
            expect($result['suggested_groups']['002'])->toHaveCount(2);
        });

        it('provides confidence scores for pattern detection', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            // Strong pattern
            $strongPattern = $analyzer->analyze(['ABC-001-001', 'ABC-001-002', 'ABC-002-001', 'ABC-002-002']);
            expect($strongPattern['confidence'])->toBeGreaterThan(0.8);
            
            // Weak pattern
            $weakPattern = $analyzer->analyze(['RANDOM1', 'DIFFERENT2', 'ANOTHER3']);
            expect($weakPattern['confidence'])->toBeLessThan(0.3);
            
            // No pattern
            $noPattern = $analyzer->analyze(['COMPLETELY', 'RANDOM', 'SKUS']);
            expect($noPattern['has_pattern'])->toBeFalse();
            expect($noPattern['confidence'])->toBe(0);
        });

        it('suggests parent names from patterns', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            $skus = ['ROMAN-BLIND-150-RED', 'ROMAN-BLIND-200-BLUE', 'ROMAN-BLIND-150-WHITE'];
            $result = $analyzer->analyze($skus);
            
            expect($result['suggested_parent_name'])->toBe('Roman Blind');
            expect($result['name_extraction_method'])->toBe('common_prefix_words');
        });

        it('handles mixed pattern complexity', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            $mixedSkus = [
                'CURTAIN-001-RED-150',
                'CURTAIN-001-BLUE-150', 
                'CURTAIN-002-RED-200',
                'BLIND-001-WHITE',
                'BLIND-002-BLACK',
                'RANDOM-SKU',
            ];
            
            $result = $analyzer->analyze($mixedSkus);
            
            expect($result['pattern_complexity'])->toBe('mixed');
            expect($result['multiple_patterns'])->toBeTrue();
            expect($result['suggested_groups'])->toHaveKeys(['CURTAIN', 'BLIND']);
        });
    });

    describe('SmartAttributeExtractor', function () {
        it('extracts colors with word boundary detection', function () {
            $extractor = new SmartAttributeExtractor();
            
            $testCases = [
                'Red Curtain Panel' => ['red'],
                'Blackout Blind' => [], // Should not extract "black" from "blackout"
                'Navy Blue Roman Blind' => ['navy', 'blue'],
                'Off-White Curtain' => ['off-white'],
                'Burgundy and Gold Fabric' => ['burgundy', 'gold'],
            ];
            
            foreach ($testCases as $text => $expectedColors) {
                $result = $extractor->extractColors($text);
                expect($result['colors'])->toBe($expectedColors, "Failed for text: $text");
            }
        });

        it('extracts sizes with comprehensive detection', function () {
            $extractor = new SmartAttributeExtractor();
            
            $testCases = [
                'Small Roman Blind' => ['small'],
                'Extra Large Curtain' => ['extra large'],
                'XL Size Available' => ['xl'],
                'One Size Fits All' => ['one size'],
                'Size 16 Curtain Ring' => ['16'],
            ];
            
            foreach ($testCases as $text => $expectedSizes) {
                $result = $extractor->extractSizes($text);
                expect($result['sizes'])->toBe($expectedSizes, "Failed for text: $text");
            }
        });

        it('provides weighted confidence scoring', function () {
            $extractor = new SmartAttributeExtractor();
            
            // High confidence - exact color match
            $highResult = $extractor->extractColors('Pure Red Curtain');
            expect($highResult['confidence'])->toBeGreaterThan(0.8);
            
            // Medium confidence - partial match
            $mediumResult = $extractor->extractColors('Reddish Curtain'); 
            expect($mediumResult['confidence'])->toBeGreaterThan(0.4);
            expect($mediumResult['confidence'])->toBeLessThan(0.8);
            
            // Low confidence - weak indication
            $lowResult = $extractor->extractColors('Red-adjacent Color');
            expect($lowResult['confidence'])->toBeLessThan(0.5);
        });

        it('extracts multiple attributes simultaneously', function () {
            $extractor = new SmartAttributeExtractor();
            
            $result = $extractor->extractAll('Large Navy Blue Roman Blind - Made to Measure - 150cm x 200cm');
            
            expect($result['colors']['colors'])->toContain('navy');
            expect($result['colors']['colors'])->toContain('blue');
            expect($result['sizes']['sizes'])->toContain('large');
            expect($result['dimensions']['found_dimensions'])->toBeTrue();
            expect($result['made_to_measure']['is_made_to_measure'])->toBeTrue();
        });

        it('handles complex color variations', function () {
            $extractor = new SmartAttributeExtractor();
            
            $complexColors = [
                'Antique Gold',
                'Royal Blue', 
                'Forest Green',
                'Champagne Beige',
                'Midnight Black',
            ];
            
            foreach ($complexColors as $colorText) {
                $result = $extractor->extractColors("Curtain in $colorText");
                expect($result['colors'])->not->toBeEmpty("Failed to extract from: $colorText");
                expect($result['confidence'])->toBeGreaterThan(0.5);
            }
        });

        it('avoids false positive extractions', function () {
            $extractor = new SmartAttributeExtractor();
            
            $falsePositives = [
                'Blackout Blind' => 'black', // "black" should not be extracted from "blackout"
                'Greenhouse Effect' => 'green', // "green" should not be extracted from "greenhouse"
                'Bluetooth Speaker' => 'blue', // "blue" should not be extracted from "bluetooth"
            ];
            
            foreach ($falsePositives as $text => $shouldNotExtract) {
                $result = $extractor->extractColors($text);
                expect($result['colors'])->not->toContain($shouldNotExtract, "False positive for: $text");
            }
        });

        it('provides extraction metadata and methods', function () {
            $extractor = new SmartAttributeExtractor();
            
            $result = $extractor->extractColors('Bright Red Velvet Curtain');
            
            expect($result)->toHaveKey('colors');
            expect($result)->toHaveKey('confidence'); 
            expect($result)->toHaveKey('extraction_method');
            expect($result)->toHaveKey('matched_patterns');
            expect($result)->toHaveKey('word_boundaries_used');
            
            expect($result['extraction_method'])->toBe('dictionary_matching');
            expect($result['word_boundaries_used'])->toBeTrue();
        });
    });

    describe('Integration Scenarios', function () {
        it('handles complete product data extraction', function () {
            $productText = 'Made to Measure Large Navy Blue Roman Blind - Width: 150cm, Drop: 200cm - Blackout Fabric';
            
            $mtmExtractor = new MadeToMeasureExtractor();
            $dimensionExtractor = new SmartDimensionExtractor();
            $attributeExtractor = new SmartAttributeExtractor();
            
            $mtmResult = $mtmExtractor->extract($productText);
            $dimensionResult = $dimensionExtractor->extract($productText);
            $colorResult = $attributeExtractor->extractColors($productText);
            $sizeResult = $attributeExtractor->extractSizes($productText);
            
            // Verify all extractions work together
            expect($mtmResult['is_made_to_measure'])->toBeTrue();
            expect($dimensionResult['dimensions']['width'])->toBe(150);
            expect($dimensionResult['dimensions']['drop'])->toBe(200);
            expect($colorResult['colors'])->toContain('navy');
            expect($colorResult['colors'])->toContain('blue');
            expect($sizeResult['sizes'])->toContain('large');
            
            // Verify no false positives
            expect($colorResult['colors'])->not->toContain('black'); // From "blackout"
        });

        it('provides comprehensive extraction summary', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            
            $extractors = [
                'mtm' => new MadeToMeasureExtractor(),
                'dimensions' => new SmartDimensionExtractor(['digits_only' => true]),
                'attributes' => new SmartAttributeExtractor(),
            ];
            
            $productData = [
                'name' => 'Custom Size Large Red Roman Blind 150x200cm',
                'description' => 'Made to measure roman blind with premium fabric',
            ];
            
            $extractionResults = [];
            foreach ($extractors as $type => $extractor) {
                $text = $productData['name'] . ' ' . $productData['description'];
                
                switch ($type) {
                    case 'mtm':
                        $extractionResults[$type] = $extractor->extract($text);
                        break;
                    case 'dimensions':
                        $extractionResults[$type] = $extractor->extract($text);
                        break;
                    case 'attributes':
                        $extractionResults[$type] = $extractor->extractAll($text);
                        break;
                }
            }
            
            // Verify comprehensive extraction
            expect($extractionResults['mtm']['is_made_to_measure'])->toBeTrue();
            expect($extractionResults['dimensions']['dimensions']['width'])->toBe(150);
            expect($extractionResults['attributes']['colors']['colors'])->toContain('red');
            expect($extractionResults['attributes']['sizes']['sizes'])->toContain('large');
            
            // All extractions should have confidence scores
            foreach ($extractionResults as $type => $result) {
                if ($type === 'attributes') {
                    expect($result['colors'])->toHaveKey('confidence');
                    expect($result['sizes'])->toHaveKey('confidence');
                } else {
                    expect($result)->toHaveKey('confidence');
                }
            }
        });
    });
});