<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Test Model for Atom Framework Testing
 */
class TestModel extends Model
{
    protected $table = 'test_models';
    
    protected $fillable = [
        'name',
        'email', 
        'description',
        'active',
        'priority',
    ];

    protected $casts = [
        'active' => 'boolean',
        'priority' => 'integer',
    ];
}