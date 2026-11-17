<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Favorito personal de un elemento de un catálogo. No modifica el catálogo ni afecta a otros usuarios. */
class CatalogFavorite extends Model
{
    use BelongsToUser, HasUuids;

    protected $guarded = ['id'];
}
