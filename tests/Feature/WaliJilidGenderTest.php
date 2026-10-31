<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliJilidGenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_gender_binaan_attribute_default_all(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'gender_binaan' => 'all',
        ]);
    }
}
