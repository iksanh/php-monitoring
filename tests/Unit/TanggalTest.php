<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Tanggal;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TanggalTest extends TestCase
{
    public function test_format_pendek_memakai_nama_bulan_indonesia(): void
    {
        $this->assertSame('12 Agu 2026', Tanggal::pendek(CarbonImmutable::parse('2026-08-12')));
        $this->assertSame('01 Mei 2026', Tanggal::pendek(CarbonImmutable::parse('2026-05-01')));
        $this->assertSame('31 Des 2025', Tanggal::pendek(CarbonImmutable::parse('2025-12-31')));
    }

    public function test_format_panjang(): void
    {
        $this->assertSame('12 Agustus 2026', Tanggal::panjang(CarbonImmutable::parse('2026-08-12')));
    }

    public function test_format_waktu(): void
    {
        $this->assertSame('12 Agu 2026 09.30', Tanggal::waktu(CarbonImmutable::parse('2026-08-12 09:30:00')));
    }

    public function test_tanggal_kosong_ditampilkan_sebagai_tanda_hubung(): void
    {
        $this->assertSame(Tanggal::KOSONG, Tanggal::pendek(null));
        $this->assertSame(Tanggal::KOSONG, Tanggal::panjang(null));
        $this->assertSame(Tanggal::KOSONG, Tanggal::waktu(null));
        $this->assertSame('', Tanggal::input(null));
    }

    public function test_nilai_input_date(): void
    {
        $this->assertSame('2026-08-12', Tanggal::input(CarbonImmutable::parse('2026-08-12 17:00:00')));
    }
}
