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

    // Casts for JSON and boolean columns
    protected $casts = [
        'sizes' => 'array',  // Automatically convert the 'sizes' column to an array
        'bestseller' => 'boolean',  // Automatically convert the 'bestseller' column to boolean
        'date' => 'datetime',  // Treat this as a DateTime instance
    ];

    // Relationship with the category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relationship with the supplier
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
