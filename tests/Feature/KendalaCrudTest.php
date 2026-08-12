<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Bidang;
use App\Models\Kendala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KendalaCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_mencatat_kendala_dari_halaman_bidang(): void
    {
        $bidang = Bidang::factory()->create();

        $this->actingAs($this->operator())
            ->post(route('kendala.store', $bidang), [
                'uraian' => 'Batas bidang belum disepakati.',
                'tanggal_catat' => '2026-03-01',
                'dicatat_oleh' => 'Operator Kantah',
            ])
            ->assertRedirect(route('bidang.show', $bidang))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kendala', [
            'bidang_id' => $bidang->id,
            'uraian' => 'Batas bidang belum disepakati.',
            'tanggal_selesai' => null,
        ]);
    }

    public function test_kendala_ditutup_dengan_tanggal_selesai(): void
    {
        $kendala = Kendala::factory()->create(['tanggal_catat' => '2026-03-01']);

        $this->actingAs($this->operator())
            ->put(route('kendala.update', $kendala), [
                'uraian' => $kendala->uraian,
                'tanggal_catat' => '2026-03-01',
                'tanggal_selesai' => '2026-04-10',
                'dicatat_oleh' => $kendala->dicatat_oleh,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($kendala->refresh()->selesai());
    }

    public function test_tanggal_selesai_tidak_boleh_mendahului_tanggal_catat(): void
    {
        $kendala = Kendala::factory()->create(['tanggal_catat' => '2026-03-01']);

        $this->actingAs($this->operator())
            ->put(route('kendala.update', $kendala), [
                'uraian' => $kendala->uraian,
                'tanggal_catat' => '2026-03-01',
                'tanggal_selesai' => '2026-02-01',
                'dicatat_oleh' => $kendala->dicatat_oleh,
            ])
            ->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_viewer_tidak_boleh_mencatat_atau_menghapus_kendala(): void
    {
        $bidang = Bidang::factory()->create();
        $kendala = Kendala::factory()->create(['bidang_id' => $bidang->id]);
        $viewer = User::factory()->peran(Peran::Viewer)->create();

        $this->actingAs($viewer)
            ->post(route('kendala.store', $bidang), [
                'uraian' => 'Coba menulis.',
                'tanggal_catat' => '2026-03-01',
                'dicatat_oleh' => 'Viewer',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)->delete(route('kendala.destroy', $kendala))->assertForbidden();
    }

    public function test_operator_menghapus_kendala(): void
    {
        $kendala = Kendala::factory()->create();

        $this->actingAs($this->operator())
            ->delete(route('kendala.destroy', $kendala))
            ->assertRedirect(route('bidang.show', $kendala->bidang_id));

        $this->assertDatabaseMissing('kendala', ['id' => $kendala->id]);
    }

    private function operator(): User
    {
        return User::factory()->peran(Peran::Operator)->create();
    }
}
