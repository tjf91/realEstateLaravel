<?php

namespace Tests\Feature;

use App\Models\RealEstate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RealEstateApiTest extends TestCase
{
    use RefreshDatabase;

    // Helper to ensure we only expose list-view fields
    private const INDEX_FIELDS = ['id','name','type','city','country'];

    #[Test]
    public function index_lists_only_the_required_fields_and_is_desc_by_id(): void
    {
        // Arrange: create two records in order
        $a = RealEstate::factory()->create(['name' => 'Alpha']);   // lower id
        $b = RealEstate::factory()->create(['name' => 'Bravo']);   // higher id

        // Act
        $res = $this->getJson('/api/properties');

        // Assert
        $res->assertOk()->assertJsonStructure([self::INDEX_FIELDS]);

        $rows = $res->json();

        $keys = array_keys($rows[0]);
        $expected = self::INDEX_FIELDS;

        sort($keys);
        sort($expected);

        $this->assertSame($expected, $keys, 'Index must only expose id,name,type,city,country');


        // order check: should be DESC by id (latest first)
        $this->assertSame($b->id, $rows[0]['id'], 'Index should be ordered by id DESC');
        $this->assertSame($a->id, $rows[1]['id'], 'Index should be ordered by id DESC');

        // "type" is an alias of real_state_type
        $this->assertSame($b->real_state_type, $rows[0]['type'], 'Index "type" must mirror model real_state_type');

        // Ensure no extra fields leak on index
        $this->assertArrayNotHasKey('street', $rows[0], 'Index must not include street');
        $this->assertArrayNotHasKey('real_state_type', $rows[0], 'Index must not include real_state_type (we expose "type")');
    }

    #[Test]
    public function show_returns_full_record_for_existing_id(): void
    {
        // Arrange
        $estate = RealEstate::factory()->create([
            'internal_number' => '5-B',
            'comments' => 'Nice place',
        ]);

        // Act + Assert
        $this->getJson("/api/properties/{$estate->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $estate->id,
                'name' => $estate->name,
                'real_state_type' => $estate->real_state_type,
                'street' => $estate->street,
                'city' => $estate->city,
                'country' => $estate->country,
                'internal_number' => '5-B',
                'comments' => 'Nice place',
            ]);
    }

    #[Test]
    public function store_creates_a_valid_house_and_upcases_country(): void
    {
        // Arrange
        $payload = [
            'name' => 'Sunset Villa',
            'real_state_type' => 'house',
            'street' => '123 Palm Way',
            'external_number' => '12-A',
            'neighborhood' => 'Hills',
            'city' => 'Phoenix',
            'country' => 'us', // intentionally lowercased
            'rooms' => 3,
            'bathrooms' => 1.5,
            'comments' => 'Nice view',
        ];

        // Act
        $res = $this->postJson('/api/properties', $payload);

        // Assert
        $res->assertCreated()
            ->assertJsonFragment(['name' => 'Sunset Villa']);
        $created = $res->json();

        // Model mutator should upper-case the country
        $this->assertSame('US', $created['country'], 'Country should be uppercased by the model');

        $this->assertDatabaseHas('real_estates', [
            'id' => $created['id'],
            'name' => 'Sunset Villa',
            'country' => 'US',
        ]);
    }

    #[Test]
    public function store_requires_internal_number_for_department(): void
    {
        // Arrange (missing internal_number)
        $payload = [
            'name' => 'Skyline Apt',
            'real_state_type' => 'department',
            'street' => '500 Center St',
            'external_number' => '500',
            'neighborhood' => 'Downtown',
            'city' => 'Phoenix',
            'country' => 'US',
            'rooms' => 2,
            'bathrooms' => 1.0,
        ];

        // Act + Assert
        $this->postJson('/api/properties', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['internal_number']);
    }

    #[Test]
    public function store_requires_internal_number_for_commercial_ground(): void
    {
        // Arrange (missing internal_number)
        $payload = [
            'name' => 'Retail Spot',
            'real_state_type' => 'commercial_ground',
            'street' => 'Market Rd',
            'external_number' => 'A-7',
            'neighborhood' => 'Central',
            'city' => 'Phoenix',
            'country' => 'US',
            'rooms' => 0,
            'bathrooms' => 0.0, // allowed for commercial_ground
        ];

        // Act + Assert
        $this->postJson('/api/properties', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['internal_number']);
    }

    #[Test]
    public function update_rejects_bathrooms_zero_for_house_even_if_type_not_in_payload(): void
    {
        // Arrange: create a HOUSE
        $estate = RealEstate::factory()->create(['real_state_type' => 'house', 'bathrooms' => 1.5]);

        // Act + Assert: send only bathrooms=0
        $this->patchJson("/api/properties/{$estate->id}", ['bathrooms' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bathrooms']);
    }

    #[Test]
    public function update_allows_bathrooms_zero_when_type_is_land_via_same_request(): void
    {
        // Arrange: start as house
        $estate = RealEstate::factory()->create(['real_state_type' => 'house', 'bathrooms' => 1.5]);

        // Act + Assert: change type and bathrooms together
        $this->patchJson("/api/properties/{$estate->id}", [
            'real_state_type' => 'land',
            'bathrooms' => 0,
        ])
            ->assertOk()
            ->assertJsonFragment(['real_state_type' => 'land', 'bathrooms' => 0.0]);
    }

    #[Test]
    public function update_returns_the_newly_updated_record_and_persists_it(): void
    {
        // Arrange
        $estate = RealEstate::factory()->create(['comments' => null]);

        // Act
        $res = $this->patchJson("/api/properties/{$estate->id}", ['comments' => 'Updated note']);

        // Assert: response contains new value AND DB persisted it
        $res->assertOk()->assertJsonFragment(['comments' => 'Updated note']);
        $this->assertDatabaseHas('real_estates', [
            'id' => $estate->id,
            'comments' => 'Updated note',
        ]);
    }

    #[Test]
    public function destroy_soft_deletes_and_returns_deleted_record_and_subsequent_show_is_404(): void
    {
        // Arrange
        $estate = RealEstate::factory()->create();

        // Act: delete
        $res = $this->deleteJson("/api/properties/{$estate->id}");

        // Assert: 200 with returned record
        $res->assertOk()->assertJsonFragment(['id' => $estate->id]);

        // Soft-deleted in DB
        $this->assertSoftDeleted('real_estates', ['id' => $estate->id]);

        // Not listed on index
        $this->getJson('/api/properties')->assertOk()
            ->assertJsonMissing(['id' => $estate->id]);

        // Show now 404 because trashed models aren't bound by default
        $this->getJson("/api/properties/{$estate->id}")->assertNotFound();
    }

    #[Test]
    public function external_number_allows_only_alphanumerics_and_dash(): void
    {
        // Arrange: invalid external_number (space + punctuation)
        $payload = RealEstate::factory()->make([
            'external_number' => 'A 1 !', // invalid by regex
        ])->toArray();

        // Drop non-fillable/generated fields
        unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['deleted_at']);

        // Act + Assert
        $this->postJson('/api/properties', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['external_number']);
    }

    #[Test]
    public function country_must_be_two_letters_alpha(): void
    {
        // Arrange: invalid countries
        $bad1 = RealEstate::factory()->make(['country' => 'USA'])->toArray(); // 3 letters
        $bad2 = RealEstate::factory()->make(['country' => 'U1'])->toArray();  // alnum

        foreach ([$bad1, $bad2] as $payload) {
            unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['deleted_at']);
            $this->postJson('/api/properties', $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['country']);
        }
    }
}
