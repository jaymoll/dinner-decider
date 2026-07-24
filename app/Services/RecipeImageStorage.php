<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores only verified recipe image content under application-managed public-disk paths.
 */
final readonly class RecipeImageStorage
{
    private const DIRECTORY = 'recipe-images';

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        if (! $image->isValid()) {
            throw new RuntimeException('The recipe image upload did not complete successfully.');
        }

        // Inspect decoded image metadata instead of trusting the client filename or MIME header.
        $contents = file_get_contents($image->getRealPath());
        $details = $contents === false ? false : @getimagesizefromstring($contents);

        if ($details === false || ! isset(self::EXTENSIONS[$details['mime']])) {
            throw new RuntimeException('The recipe image must contain a valid JPEG, PNG, or WebP image.');
        }

        // Check both transport metadata and actual bytes because either value may be unreliable.
        $maximumBytes = (int) config('measurements.limits.recipe_image_kilobytes') * 1024;
        if (($image->getSize() ?: strlen($contents)) > $maximumBytes || strlen($contents) > $maximumBytes) {
            throw new RuntimeException('The recipe image exceeds the maximum allowed size.');
        }

        $maximumDimension = (int) config('measurements.limits.recipe_image_dimension_pixels');
        if ($details[0] < 1 || $details[1] < 1 || $details[0] > $maximumDimension || $details[1] > $maximumDimension) {
            throw new RuntimeException('The recipe image dimensions are not allowed.');
        }

        $path = self::DIRECTORY.'/'.Str::uuid().'.'.self::EXTENSIONS[$details['mime']];
        if (! Storage::disk('public')->put($path, $contents)) {
            throw new RuntimeException('The recipe image could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        // The prefix guard prevents callers from turning recipe cleanup into arbitrary disk deletion.
        if ($path !== null && str_starts_with($path, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
