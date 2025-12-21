<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    // No usa BelongsToBranch porque las categorías suelen ser globales
    protected $fillable = ['name'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}