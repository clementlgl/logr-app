<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenFoodFacts
{
    protected string $baseUrl = 'https://world.openfoodfacts.org/api/v2';

    /**
     * Strip everything but digits and check it looks like an EAN-8/UPC/EAN-13/GTIN-14.
     */
    public static function normalizeBarcode(?string $barcode): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $barcode);

        return strlen($digits) >= 8 && strlen($digits) <= 14 ? $digits : null;
    }

    /**
     * Look up a product by barcode. Returns null when not found or on failure.
     */
    public function lookup(string $barcode): ?array
    {
        $barcode = self::normalizeBarcode($barcode);
        if (! $barcode) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => config('logr.user_agent')])
                ->timeout(10)
                ->get("{$this->baseUrl}/product/{$barcode}.json", [
                    'fields' => 'code,product_name,generic_name,brands,nutriments,categories_tags,image_front_url',
                ]);
        } catch (\Exception $e) {
            Log::warning('OpenFoodFacts: lookup failed', ['barcode' => $barcode, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed() || $response->json('status') !== 1) {
            return null;
        }

        $product = $response->json('product') ?? [];
        $name = trim($product['product_name'] ?? '');
        if ($name === '') {
            return null;
        }

        $brands = array_values(array_filter(array_map('trim', explode(',', $product['brands'] ?? ''))));
        $abv = $product['nutriments']['alcohol'] ?? $product['nutriments']['alcohol_100g'] ?? null;

        // Category tags look like "en:india-pale-ales"; turn them into words for style matching
        $categories = collect($product['categories_tags'] ?? [])
            ->map(fn ($tag) => str_replace('-', ' ', preg_replace('/^[a-z]{2}:/', '', $tag)))
            ->reject(fn ($tag) => in_array($tag, ['beverages', 'alcoholic beverages', 'beers', 'beverages and beverages preparations']))
            ->implode(', ');

        return [
            'barcode' => $barcode,
            'name' => $name,
            'brewery_name' => $brands[0] ?? null,
            'abv' => is_numeric($abv) ? round((float) $abv, 1) : null,
            'style' => $categories,
            'description' => trim($product['generic_name'] ?? '') ?: null,
            'image_url' => $product['image_front_url'] ?? null,
        ];
    }
}
