<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_depan_mengarahkan_tamu_ke_form_masuk(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_depan_mengarahkan_pengguna_masuk_ke_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
