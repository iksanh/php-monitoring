<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pesan Validasi
|--------------------------------------------------------------------------
|
| Hanya aturan yang benar-benar dipakai aplikasi ini yang diterjemahkan.
| Aturan lain jatuh ke bahasa Inggris bawaan Laravel lewat fallback locale,
| jadi berkas ini boleh ditambah seperlunya saja.
|
| Nama field yang terbaca manusia diatur di masing-masing Form Request lewat
| method attributes(), bukan di sini.
|
*/

return [

    'after_or_equal' => ':attribute harus berupa tanggal setelah atau sama dengan :date.',
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang sah.',
    'email' => ':attribute harus berupa alamat surel yang sah.',
    'enum' => 'Pilihan :attribute tidak sah.',
    'exists' => ':attribute yang dipilih tidak ada.',
    'in' => 'Pilihan :attribute tidak sah.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'numeric' => ':attribute harus berupa angka.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah dipakai.',

    'max' => [
        'array' => ':attribute paling banyak berisi :max item.',
        'file' => ':attribute paling besar :max kilobita.',
        'numeric' => ':attribute paling besar :max.',
        'string' => ':attribute paling panjang :max karakter.',
    ],

    'min' => [
        'array' => ':attribute paling sedikit berisi :min item.',
        'file' => ':attribute paling kecil :min kilobita.',
        'numeric' => ':attribute paling kecil :min.',
        'string' => ':attribute paling pendek :min karakter.',
    ],

    'password' => [
        'letters' => ':attribute harus memuat setidaknya satu huruf.',
        'mixed' => ':attribute harus memuat huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus memuat setidaknya satu angka.',
        'symbols' => ':attribute harus memuat setidaknya satu simbol.',
        'uncompromised' => ':attribute pernah bocor dalam kebocoran data. Pilih yang lain.',
    ],

];
