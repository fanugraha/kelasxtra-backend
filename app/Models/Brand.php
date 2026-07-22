<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name', 'slug', 'domain'];

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
