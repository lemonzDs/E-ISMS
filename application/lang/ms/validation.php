<?php

return [
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi apabila :other ialah :value.',
    'email' => ':attribute mesti alamat e-mel yang sah.',
    'string' => ':attribute mesti teks.',
    'integer' => ':attribute mesti nombor bulat.',
    'boolean' => ':attribute mesti pilihan yang sah.',
    'file' => ':attribute mesti fail yang sah.',
    'mimes' => ':attribute mesti fail jenis :values.',
    'in' => 'Pilihan :attribute tidak sah.',
    'exists' => 'Pilihan :attribute tidak ditemui.',
    'unique' => ':attribute sudah digunakan.',
    'regex' => 'Format :attribute tidak sah.',
    'max' => ['string' => ':attribute tidak boleh melebihi :max aksara.', 'file' => ':attribute tidak boleh melebihi :max KB.', 'numeric' => ':attribute tidak boleh melebihi :max.'],
    'min' => ['string' => ':attribute mesti sekurang-kurangnya :min aksara.', 'numeric' => ':attribute mesti sekurang-kurangnya :min.'],
    'attributes' => ['email' => 'E-mel', 'password' => 'Kata laluan', 'name' => 'Nama', 'department_id' => 'Bahagian', 'role' => 'Peranan', 'code' => 'Kod', 'is_active' => 'Status akaun', 'q' => 'Carian', 'status' => 'Status', 'action' => 'Tindakan'],
];
