<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/UploadedFile.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Uploaded file abstraction providing validation, storage, moving, and MIME inspection.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Http;

/**
 * NetFusion Uploaded File
 *
 * Wraps a single entry from $_FILES into a clean object
 * providing move, validation, and metadata access methods.
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class UploadedFile
{
    /**
     * @param string $tmpName   The temporary file path on disk.
     * @param string $name      The original filename from the client.
     * @param string $mimeType  The MIME type reported by the client.
     * @param int    $size      File size in bytes.
     * @param int    $error     One of the UPLOAD_ERR_* constants.
     */
    public function __construct(
        private readonly string $tmpName,
        private readonly string $name,
        private readonly string $mimeType,
        private readonly int    $size,
        private readonly int    $error,
    ) {}

    /**
     * Build an UploadedFile from a $_FILES entry array.
     *
     * @param  array<string, mixed> $fileArray
     * @return static
     */
    public static function fromArray(array $fileArray): static
    {
        return new static(
            tmpName : $fileArray['tmp_name'] ?? '',
            name    : $fileArray['name']     ?? '',
            mimeType: $fileArray['type']     ?? '',
            size    : (int) ($fileArray['size'] ?? 0),
            error   : (int) ($fileArray['error'] ?? UPLOAD_ERR_NO_FILE),
        );
    }

    /**
     * Determine if the upload was successful.
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpName);
    }

    /**
     * Move the uploaded file to a destination path.
     *
     * @param  string $destination Absolute destination path.
     * @return bool
     *
     * @throws \RuntimeException If the file cannot be moved.
     */
    public function move(string $destination): bool
    {
        if (!$this->isValid()) {
            throw new \RuntimeException(
                sprintf('Cannot move invalid uploaded file: error code %d.', $this->error)
            );
        }

        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        if (!move_uploaded_file($this->tmpName, $destination)) {
            throw new \RuntimeException(
                sprintf('Failed to move uploaded file to [%s].', $destination)
            );
        }

        return true;
    }

    /**
     * Store the file in a directory with a generated filename.
     *
     * @param  string      $directory The directory to store the file in.
     * @param  string|null $filename  Optional custom filename (without extension).
     * @return string                 The final stored filename (without directory).
     */
    public function store(string $directory, ?string $filename = null): string
    {
        $extension = $this->extension();
        $basename  = ($filename ?? bin2hex(random_bytes(16))) . ($extension ? '.' . $extension : '');
        $this->move(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $basename);
        return $basename;
    }

    /**
     * Store the uploaded file on a configured filesystem disk.
     *
     * @param  string      $path      Directory or folder path on the disk.
     * @param  string|null $disk      Filesystem disk name (e.g. 'public', 'local', 's3').
     * @param  string|null $filename  Optional custom filename (without extension).
     * @return string                 The stored path relative to the disk root.
     */
    public function storeOn(string $path = '', ?string $disk = null, ?string $filename = null): string
    {
        $extension  = $this->extension();
        $basename   = ($filename ?? bin2hex(random_bytes(16))) . ($extension ? '.' . $extension : '');
        $targetPath = trim($path, '/\\');
        $fullPath   = $targetPath === '' ? $basename : $targetPath . '/' . $basename;

        $stream = fopen($this->tmpName, 'rb');
        if ($stream === false) {
            throw new \RuntimeException(sprintf('Failed to open uploaded temporary file at [%s].', $this->tmpName));
        }

        storage($disk)->put($fullPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $fullPath;
    }

    /**
     * Get the original client-provided filename.
     */
    public function originalName(): string
    {
        return $this->name;
    }

    /**
     * Get the file extension based on the original filename.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Get the file's MIME type as reported by the client.
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the file size in bytes.
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Get the upload error code.
     */
    public function error(): int
    {
        return $this->error;
    }

    /**
     * Get a human-readable upload error message.
     */
    public function errorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK         => 'Upload successful.',
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
            default               => 'Unknown upload error.',
        };
    }
}
