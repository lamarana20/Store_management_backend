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
        'image',
        'category_id',
        'supplier_id',
        'stock_quantity',
        'price',
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
