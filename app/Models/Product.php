<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'images',
        'category_id',
        'supplier_id',
        'stock_quantity',
        'price',
        'sub_category',
        'sizes',
        'bestseller',
        'date',
    ];

    // Casts pour les champs JSON et boolean
    protected $casts = [
        'sizes' => 'array',  // Convertir la colonne 'sizes' en tableau automatiquement
        'bestseller' => 'boolean',  // Convertir la colonne 'bestseller' en boolean
        'date' => 'datetime',  // Si tu veux le traiter comme un objet DateTime
    ];

    // Relation avec la catégorie
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relation avec le fournisseur
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
