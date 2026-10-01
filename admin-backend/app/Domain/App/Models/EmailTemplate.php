<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Email template row. `html_body` is a small HTML fragment (not full page) that
 * the composer renders into the mail layout; `text_body` is the plain-text
 * equivalent shown as the text preview in the admin editor.
 *
 * @property int $id
 * @property string $title
 * @property string $subject
 * @property string $html_body
 * @property string|null $text_body
 * @property string|null $from_name
 * @property bool $enabled
 */
final class EmailTemplate extends Model
{

    protected $connection = 'app';
    protected $table = 'email_templates';

    protected $fillable = ['title', 'subject', 'html_body', 'text_body', 'from_name', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}