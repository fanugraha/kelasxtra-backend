<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Program extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
    ];
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
    public function questionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
