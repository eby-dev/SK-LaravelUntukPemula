<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    /**
     * fillable
     * 
     * @var array
     */
    protected $fillable = [
        'image',
        'title',
        'content',
    ];

    /**
     * content
     *
     * Rendered with {!! !!} to preserve editor formatting, so strip anything
     * script-capable before it is ever stored. Applied on the model rather
     * than in the controller so no write path can skip it.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => HtmlSanitizer::clean($value),
        );
    }
}
