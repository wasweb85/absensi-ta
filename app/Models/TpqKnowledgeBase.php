<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TpqKnowledgeBase extends Model
{
    use HasFactory;

    protected $table = 'tpq_knowledge_bases';

    protected $fillable = [
        'kategori',
        'judul',
        'kata_kunci',
        'konten',
        'is_active',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'urutan' => 'integer',
        ];
    }
}
