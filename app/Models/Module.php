<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'modules';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'key',
        'title',
        'route_name',
        'href',
        'icon',
        'section',
        'ordering',
        'active',
        'description',
        'permissions_required'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'parent_id' => 'integer',
        'ordering' => 'integer',
        'active' => 'string',
        'permissions_required' => 'array'
    ];

    /**
     * Get the parent module.
     */
    public function parent()
    {
        return $this->belongsTo(Module::class, 'parent_id');
    }

    /**
     * Get the child modules.
     */
    public function children()
    {
        return $this->hasMany(Module::class, 'parent_id');
    }

    /**
     * Get active modules.
     */
    public function scopeActive($query)
    {
        return $query->where('active', 'S');
    }

    /**
     * Get root modules (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get modules by section.
     */
    public function scopeBySection($query, $section)
    {
        return $query->where('section', $section);
    }

    /**
     * Order modules by ordering field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('ordering');
    }
}
