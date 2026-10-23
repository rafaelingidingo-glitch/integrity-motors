<?php
/**
 * Image upload helper — port of the missing POST /api/upload route (sharp
 * 1600px / webp q82 in the Next.js app). Shared by admin vehicle image
 * management. Loaded by bootstrap.php together with the other lib files.
 */

declare(strict_types=1);

/** Create UPLOAD_DIR if missing and harden it with a static .htaccess. */
function ensure_upload_dir(): void
{
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0775, true);
    }
    $htaccess = UPLOAD_DIR . '/.htaccess';
    if (is_dir(UPLOAD_DIR) && !is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "<FilesMatch \"\\.(?i:php|phtml|php\\d)\$\">\n  Require all denied\n</FilesMatch>\n"
        );
    }
}

/** Fresh, collision-free upload filename stem, e.g. "202610-a1b2c3". */
function upload_unique_stem(): string
{
    do {
        $stem   = date('Ym') . '-' . bin2hex(random_bytes(3));
        $exists = false;
        foreach (['.webp', '.jpg', '.jpeg', '.png', '.gif'] as $ext) {
            if (is_file(UPLOAD_DIR . '/' . $stem . $ext)) {
                $exists = true;
                break;
            }
        }
    } while ($exists);
    return $stem;
}

/**
 * Validate + store one PHP upload. Scales the longest side to
 * UPLOAD_MAX_DIM (1600px, never enlarges) and re-encodes as WebP q82 when
 * GD supports WebP; otherwise stores the original file untouched with a
 * proper extension.
 *
 * @return array{ok:bool,url:?string,error:?string}
 */
function save_uploaded_image(array $file): array
{
    // ---- transport-level validation ---------------------------------------
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        $msg = match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum upload size',
            UPLOAD_ERR_PARTIAL  => 'Upload was interrupted, please retry',
            UPLOAD_ERR_NO_FILE  => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server could not store the upload',
            default             => 'Upload failed (code ' . $err . ')',
        };
        return ['ok' => false, 'url' => null, 'error' => $msg];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp) || !is_file($tmp)) {
        return ['ok' => false, 'url' => null, 'error' => 'Invalid upload'];
    }

    if ((int) $file['size'] > UPLOAD_MAX_BYTES || (int) filesize($tmp) > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'url' => null, 'error' => 'File exceeds the ' . round(UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB limit'];
    }

    // ---- type validation (real bytes, never the client-supplied mime) ------
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmp);
    if (!in_array($mime, UPLOAD_ACCEPTED_MIME, true)) {
        return ['ok' => false, 'url' => null, 'error' => 'Unsupported image type (use JPEG, PNG, WebP or GIF)'];
    }

    // ---- decompression-bomb guard ------------------------------------------
    // GD decodes the FULL pixel buffer into memory before we can downscale —
    // a small JPEG crafted at 20000×20000px would allocate ~2.4 GB. Reject
    // absurd dimensions before touching GD (legit photos are ≤ 12000px).
    $dims = @getimagesize($tmp);
    if ($dims === false) {
        return ['ok' => false, 'url' => null, 'error' => 'Unsupported or corrupt image file'];
    }
    if ((int) $dims[0] > 8000 || (int) $dims[1] > 8000) {
        return ['ok' => false, 'url' => null, 'error' => 'Image is too large (maximum 8000 pixels on the longest side)'];
    }

    ensure_upload_dir();
    $maxBytes = UPLOAD_MAX_DIM;

    // ---- preferred path: GD → resized WebP q82 -----------------------------
    if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
        $src = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($src instanceof GdImage) {
            $w = imagesx($src);
            $h = imagesy($src);
            if ($w > $maxBytes || $h > $maxBytes) {
                if ($w >= $h) {
                    $nw = $maxBytes;
                    $nh = max(1, (int) round($h * $maxBytes / $w));
                } else {
                    $nh = $maxBytes;
                    $nw = max(1, (int) round($w * $maxBytes / $h));
                }
            } else {
                $nw = $w; // never enlarge
                $nh = $h;
            }

            $dst = imagecreatetruecolor($nw, $nh);
            if ($mime === 'image/png' || $mime === 'image/gif') {
                // preserve transparency for PNG / GIF sources
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            } else {
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $target = UPLOAD_DIR . '/' . upload_unique_stem() . '.webp';
            $saved  = imagewebp($dst, $target, 82);
            imagedestroy($dst);
            imagedestroy($src);

            if ($saved) {
                @chmod($target, 0644);
                return ['ok' => true, 'url' => UPLOAD_URL_PREFIX . '/' . basename($target), 'error' => null];
            }
            // imagewebp failed — fall through to the original-file path
        }
        // unparsable bytes with an image magic number — reject rather than store junk
        return ['ok' => false, 'url' => null, 'error' => 'Unsupported or corrupt image file'];
    }

    // ---- fallback: no GD/WebP available — store the original as-is ---------
    $ext    = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'bin',
    };
    $target = UPLOAD_DIR . '/' . upload_unique_stem() . '.' . $ext;
    if (!move_uploaded_file($tmp, $target)) {
        return ['ok' => false, 'url' => null, 'error' => 'Server could not store the upload'];
    }
    @chmod($target, 0644);
    return ['ok' => true, 'url' => UPLOAD_URL_PREFIX . '/' . basename($target), 'error' => null];
}

/**
 * Delete an uploaded image file once NO vehicle_images row references its URL
 * anymore. Only local files under UPLOAD_DIR with our generated stem names are
 * removed (basename() prevents traversal); linked remote URLs are ignored.
 */
function delete_orphan_image_file(string $url): void
{
    $url = (string) $url;
    if (!str_starts_with($url, UPLOAD_URL_PREFIX . '/')) {
        return; // remote/linked image — nothing on disk
    }
    $name = basename($url);
    if ($name === '' || !preg_match('/^\d{6}-[0-9a-f]{6,}\.(webp|jpe?g|png|gif)$/i', $name)) {
        return; // not one of our generated upload names
    }
    $others = db_scalar(
        'SELECT COUNT(*) FROM vehicle_images WHERE url = ?',
        [UPLOAD_URL_PREFIX . '/' . $name]
    );
    if ((int) $others === 0) {
        $path = UPLOAD_DIR . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Validate a remote image URL pasted by staff (no server-side HTTP preflight —
 * the browser renders it; the original app only preflighted client-side).
 *
 * @return array{ok:bool,url:?string,error:?string}
 */
function handle_image_link(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'url' => null, 'error' => 'Image link is required'];
    }
    if (mb_strlen($url) > 600) {
        return ['ok' => false, 'url' => null, 'error' => 'Image link must be at most 600 characters'];
    }
    if (!preg_match('#^https?://\S+$#i', $url)) {
        return ['ok' => false, 'url' => null, 'error' => 'Image link must be a full URL starting with http:// or https://'];
    }
    return ['ok' => true, 'url' => $url, 'error' => null];
}
