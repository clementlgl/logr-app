<?php

namespace Tests\Feature;

use App\Livewire\BeerForm;
use App\Livewire\BeerIndex;
use App\Models\Beer;
use App\Models\Brewery;
use App\Models\User;
use App\Services\OpenFoodFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BarcodeTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOpenFoodFacts(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/3080216052885.json*' => Http::response([
                'status' => 1,
                'product' => [
                    'product_name' => '1664',
                    'brands' => 'Kronenbourg, Carlsberg',
                    'generic_name' => 'Bière blonde',
                    'nutriments' => ['alcohol' => 5.5],
                    'categories_tags' => ['en:beverages', 'en:beers', 'en:lagers'],
                    'image_front_url' => 'https://images.openfoodfacts.org/images/products/308/021/605/2885/front_en.jpg',
                ],
            ]),
            'images.openfoodfacts.org/*' => Http::response('fake-jpeg', 200, ['Content-Type' => 'image/jpeg']),
            'world.openfoodfacts.org/*' => Http::response(['status' => 0, 'status_verbose' => 'product not found']),
        ]);
    }

    public function test_normalize_barcode(): void
    {
        $this->assertSame('3080216052885', OpenFoodFacts::normalizeBarcode(' 3 080216 052885 '));
        $this->assertNull(OpenFoodFacts::normalizeBarcode('1234'));
        $this->assertNull(OpenFoodFacts::normalizeBarcode('abc'));
    }

    public function test_open_food_facts_lookup_maps_product(): void
    {
        $this->fakeOpenFoodFacts();

        $product = app(OpenFoodFacts::class)->lookup('3080216052885');

        $this->assertSame('1664', $product['name']);
        $this->assertSame('Kronenbourg', $product['brewery_name']);
        $this->assertSame(5.5, $product['abv']);
        $this->assertSame('lagers', $product['style']);
        $this->assertNull(app(OpenFoodFacts::class)->lookup('0000000000000'));
    }

    public function test_lookup_fills_form_and_save_stores_barcode_and_photo(): void
    {
        Storage::fake('public');
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BeerForm::class)
            ->call('scanBarcode', '3080216052885')
            ->assertSet('name', '1664')
            ->assertSet('abv', 5.5)
            ->assertSet('style', ['Lager'])
            ->assertSet('brewerySearch', 'Kronenbourg')
            ->call('save');

        $beer = Beer::where('barcode', '3080216052885')->firstOrFail();
        $this->assertSame('1664', $beer->name);
        $this->assertSame('Kronenbourg', $beer->brewery->name);
        $this->assertNotNull($beer->photo_path);
        Storage::disk('public')->assertExists($beer->photo_path);
    }

    public function test_lookup_reuses_existing_brewery_and_keeps_typed_name(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();
        $brewery = Brewery::create(['name' => 'kronenbourg']);

        Livewire::actingAs($user)
            ->test(BeerForm::class)
            ->set('name', 'My name')
            ->call('scanBarcode', '3080216052885')
            ->assertSet('name', 'My name')
            ->assertSet('brewery_id', $brewery->id);

        $this->assertSame(1, Brewery::count());
    }

    public function test_unknown_barcode_keeps_code_for_manual_entry(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BeerForm::class)
            ->call('scanBarcode', '0000000000000')
            ->assertSet('name', '')
            ->assertSet('barcode', '0000000000000')
            ->set('name', 'Local Craft IPA')
            ->call('save');

        $this->assertDatabaseHas('beers', ['name' => 'Local Craft IPA', 'barcode' => '0000000000000']);
    }

    public function test_scanning_known_barcode_on_add_form_redirects_to_beer(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $beer = Beer::create(['name' => 'Known', 'barcode' => '3080216052885']);

        Livewire::actingAs($user)
            ->test(BeerForm::class)
            ->call('scanBarcode', '3080216052885')
            ->assertRedirect(route('beers.show', $beer));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openfoodfacts.org'));
    }

    public function test_barcode_must_be_unique(): void
    {
        $user = User::factory()->create();
        Beer::create(['name' => 'First', 'barcode' => '3080216052885']);
        $other = Beer::create(['name' => 'Second']);

        Livewire::actingAs($user)
            ->test(BeerForm::class, ['beer' => $other])
            ->set('barcode', '3080216052885')
            ->call('save')
            ->assertHasErrors(['barcode' => 'unique']);
    }

    public function test_invalid_barcode_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BeerForm::class)
            ->set('name', 'Beer')
            ->set('barcode', '12ab')
            ->call('save')
            ->assertHasErrors(['barcode' => 'regex']);
    }

    public function test_barcode_image_from_other_host_is_ignored(): void
    {
        Storage::fake('public');
        Http::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BeerForm::class)
            ->set('name', 'Beer')
            ->set('barcodeImageUrl', 'https://evil.example.com/x.jpg')
            ->call('save');

        $this->assertNull(Beer::where('name', 'Beer')->value('photo_path'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example.com'));
    }

    public function test_index_scan_redirects_to_known_beer_or_create_form(): void
    {
        $user = User::factory()->create();
        $beer = Beer::create(['name' => 'Known', 'barcode' => '3080216052885']);

        Livewire::actingAs($user)
            ->test(BeerIndex::class)
            ->call('scanBarcode', '3080216052885')
            ->assertRedirect(route('beers.show', $beer));

        Livewire::actingAs($user)
            ->test(BeerIndex::class)
            ->call('scanBarcode', '5410228142058')
            ->assertRedirect(route('beers.create', ['barcode' => '5410228142058']));
    }

    public function test_search_matches_barcode(): void
    {
        $user = User::factory()->create();
        Beer::create(['name' => 'Scanned Lager', 'barcode' => '3080216052885']);
        Beer::create(['name' => 'Other Beer']);

        Livewire::actingAs($user)
            ->test(BeerIndex::class)
            ->set('search', '3080216052885')
            ->assertSee('Scanned Lager')
            ->assertDontSee('Other Beer');
    }

    public function test_create_page_prefills_from_barcode_query(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();

        Livewire::withQueryParams(['barcode' => '3080216052885'])
            ->actingAs($user)
            ->test(BeerForm::class)
            ->assertSet('barcode', '3080216052885')
            ->assertSet('name', '1664');
    }
}
