<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{   
    use HasFactory;
    
    protected $fillable = ['category_id','name','slug','description','price','stock','image','is_active'];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function scopeActive($q){ return $q->where('is_active', true); }

    public function getRouteKeyName(): string { return 'slug'; }
}