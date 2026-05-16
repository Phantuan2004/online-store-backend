<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = ['url', 'type', 'model_type', 'model_id', 'is_primary'];

    /**
     * Get the parent model (product, user, variant, etc.).
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
