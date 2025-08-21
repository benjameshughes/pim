<!DOCTYPE html>
<html>
<head>
    <title>Shopify Data Debug</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .product { border: 1px solid #ccc; margin: 10px 0; padding: 15px; background: #f9f9f9; }
        .variant { background: #fff; margin: 5px 0; padding: 10px; border-left: 3px solid #007cba; }
        .key { font-weight: bold; color: #d63384; }
        .value { color: #198754; }
        h1 { color: #0d6efd; }
        h2 { color: #6f42c1; }
        h3 { color: #fd7e14; }
        pre { background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Shopify Cached Data Debug</h1>
    
    @if(empty($cachedData))
        <p style="color: red;">❌ No cached data found. Try discovering products first.</p>
    @else
        <h2>📊 Summary</h2>
        <ul>
            <li><strong>Total Products:</strong> {{ count($cachedData) }}</li>
            <li><strong>Cache Key:</strong> shopify_products_{{ auth()->id() }}</li>
            <li><strong>Cache Status:</strong> Active</li>
        </ul>

        <h2>📦 Raw Data Structure</h2>
        
        @foreach($cachedData as $index => $product)
            <div class="product">
                <h3>Product {{ $index + 1 }}: {{ $product['title'] ?? 'No Title' }}</h3>
                
                <div>
                    <span class="key">ID:</span> <span class="value">{{ $product['id'] ?? 'N/A' }}</span><br>
                    <span class="key">Handle:</span> <span class="value">{{ $product['handle'] ?? 'N/A' }}</span><br>
                    <span class="key">Product Type:</span> <span class="value">{{ $product['product_type'] ?? 'N/A' }}</span><br>
                    <span class="key">Vendor:</span> <span class="value">{{ $product['vendor'] ?? 'N/A' }}</span><br>
                    <span class="key">Status:</span> <span class="value">{{ $product['status'] ?? 'N/A' }}</span><br>
                    <span class="key">Created At:</span> <span class="value">{{ $product['created_at'] ?? 'N/A' }}</span><br>
                </div>

                @if(isset($product['variants']) && is_array($product['variants']))
                    <h4>🔧 Variants ({{ count($product['variants']) }})</h4>
                    @foreach($product['variants'] as $vIndex => $variant)
                        <div class="variant">
                            <strong>Variant {{ $vIndex + 1 }}:</strong><br>
                            <span class="key">SKU:</span> <span class="value">{{ $variant['sku'] ?? 'NO SKU' }}</span><br>
                            <span class="key">Title:</span> <span class="value">{{ $variant['title'] ?? 'N/A' }}</span><br>
                            <span class="key">Option1:</span> <span class="value">{{ $variant['option1'] ?? 'N/A' }}</span><br>
                            <span class="key">Option2:</span> <span class="value">{{ $variant['option2'] ?? 'N/A' }}</span><br>
                            <span class="key">Option3:</span> <span class="value">{{ $variant['option3'] ?? 'N/A' }}</span><br>
                            <span class="key">Price:</span> <span class="value">${{ $variant['price'] ?? '0.00' }}</span><br>
                            <span class="key">Inventory:</span> <span class="value">{{ $variant['inventory_quantity'] ?? '0' }}</span><br>
                            @if(isset($variant['id']))
                                <span class="key">Variant ID:</span> <span class="value">{{ $variant['id'] }}</span><br>
                            @endif
                        </div>
                    @endforeach
                @else
                    <p style="color: orange;">⚠️ No variants found or variants not in expected format</p>
                @endif

                <details>
                    <summary><strong>🔍 Raw Product JSON</strong></summary>
                    <pre>{{ json_encode($product, JSON_PRETTY_PRINT) }}</pre>
                </details>
            </div>
        @endforeach

        <h2>🎯 SKU Analysis</h2>
        <div class="product">
            <h3>All SKUs Found:</h3>
            @php
                $allSkus = collect($cachedData)
                    ->flatMap(fn($p) => collect($p['variants'] ?? [])->pluck('sku'))
                    ->filter()
                    ->unique()
                    ->sort();
            @endphp
            
            @if($allSkus->isNotEmpty())
                <ul>
                    @foreach($allSkus as $sku)
                        <li><code>{{ $sku }}</code></li>
                    @endforeach
                </ul>
            @else
                <p style="color: red;">❌ No SKUs found in any variants!</p>
            @endif
        </div>

        <div class="product">
            <h3>Product Titles:</h3>
            <ul>
                @foreach($cachedData as $product)
                    <li>{{ $product['title'] ?? 'No Title' }}</li>
                @endforeach
            </ul>
        </div>

    @endif

    <hr style="margin: 30px 0;">
    <p><a href="{{ route('dashboard') }}">&larr; Back to Dashboard</a></p>
</body>
</html>