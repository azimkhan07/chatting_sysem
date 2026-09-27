<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';

    /**
     * An animated GIF resolved from the composer picker. Carries the remote URL
     * in `media_url` and needs no upload.
     */
    case Gif = 'gif';

    /**
     * A freehand sketch rendered to a PNG in the browser and sent as an image.
     */
    case Drawing = 'drawing';
}
