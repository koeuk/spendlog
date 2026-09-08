<?php

namespace App\Support\Concerns;

use App\Http\Requests\BrandingRequest;
use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storing the logo and favicon, shared by the web Settings page and the API
 * so both put files in the same place and shape the favicon the same way.
 */
trait StoresBrandingImages
{
    /** Where branding uploads live on the 'public' disk. */
    protected const BRANDING_DIR = 'branding';

    /**
     * Replace, clear, or leave an image alone — and delete whatever it replaced,
     * so the disk does not fill with orphaned uploads.
     */
    protected function applyImage(BrandingRequest $request, AppSetting $settings, string $field, string $column): void
    {
        $removing = $request->boolean("remove_{$field}");
        $file = $request->file($field);

        if (! $removing && ! $file instanceof UploadedFile) {
            return;
        }

        $previous = $settings->{$column};

        $settings->{$column} = match (true) {
            $removing => null,
            $field === 'favicon' => $this->storeFavicon($file),
            default => $file->store(self::BRANDING_DIR, 'public'),
        };

        if ($previous && $previous !== $settings->{$column}) {
            Storage::disk('public')->delete($previous);
        }
    }

    /**
     * Store a favicon as a small square PNG, whatever was handed to us.
     *
     * Stored verbatim, an upload is whatever came off the admin's camera roll —
     * the one that prompted this was a 965x1240 progressive JPEG of 471KB, with
     * the EXIF still on it. Nothing rejects that: it is a valid image, it saves,
     * it serves 200, and it appears on the branding page, so the only symptom is
     * a tab that keeps the default mark. A favicon is a 16px square by the time
     * anyone sees it, and browsers are far narrower about what they will decode
     * for that slot than about what they will draw in a page.
     *
     * So it is converted rather than validated: telling an admin their logo is
     * the wrong shape is a worse answer than cropping it, and the crop is from
     * the centre, which is where the subject of a square-ish logo is.
     */
    protected function storeFavicon(UploadedFile $file): string
    {
        // ICO is already an icon — and GD cannot read it anyway.
        if (strtolower((string) $file->getClientOriginalExtension()) === 'ico') {
            return $file->store(self::BRANDING_DIR, 'public');
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        // Unreadable by GD but past validation — store it as-is rather than
        // losing the upload. Same outcome as before this method existed.
        if ($source === false) {
            return $file->store(self::BRANDING_DIR, 'public');
        }

        // 64, not 16: browsers pick from what they are given and scale down, and
        // a retina tab or a bookmark bar asks for more than 16.
        $size = 64;
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $canvas = imagecreatetruecolor($size, $size);
        // Or the transparency in a PNG logo fills with black.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled(
            $canvas,
            $source,
            0, 0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            $size, $size,
            $side, $side,
        );

        ob_start();
        imagepng($canvas, null, 9);
        $png = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        $path = self::BRANDING_DIR.'/'.Str::random(40).'.png';
        Storage::disk('public')->put($path, $png);

        return $path;
    }
}
