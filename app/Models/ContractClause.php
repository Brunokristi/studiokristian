<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractClause extends Model
{
    protected $fillable = ['name', 'category'];
    public function versions(): HasMany { return $this->hasMany(ContractClauseVersion::class); }
}