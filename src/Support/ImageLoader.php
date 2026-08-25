<?php

namespace Nadar\PdfGenerator\Support;

use Nadar\PdfGenerator\Exception\MissingImageException;

/**
 * Resolves image sources once per document.
 *
 * Reading an image's dimensions and embedding it are two separate needs, and
 * naively serving both downloads a remote image twice. This loader fetches each
 * remote source a single time, keeps the bytes, and hands them to TCPDF from
 * memory.
 *
 * Local paths are not buffered - the dimensions are read from the file and
 * TCPDF reads it again from disk, which is cheap and keeps memory flat.
 */
final class ImageLoader
{
    /** @var array<string,ImageSource> */
    private array $cache = [];

    /** @var array<string,string> source => failure reason, so a retry does not refetch */
    private array $failures = [];

    /**
     * @param int $timeout seconds to wait for a remote source
     * @param int $maxRedirects how many redirects to follow
     */
    public function __construct(
        private readonly int $timeout = 10,
        private readonly int $maxRedirects = 3
    ) {
    }

    /**
     * Resolve a source, reusing an earlier resolution of the same string.
     *
     * @throws MissingImageException when the source cannot be read or is not an image
     */
    public function load(string $source): ImageSource
    {
        if (isset($this->cache[$source])) {
            return $this->cache[$source];
        }

        if (isset($this->failures[$source])) {
            throw new MissingImageException($this->failures[$source]);
        }

        try {
            return $this->cache[$source] = $this->resolve($source);
        } catch (MissingImageException $exception) {
            $this->failures[$source] = $exception->getMessage();

            throw $exception;
        }
    }

    /**
     * Resolve a source, or `null` when it cannot be read.
     *
     * The non-throwing variant, for callers that have a placeholder to fall
     * back to.
     */
    public function tryLoad(?string $source): ?ImageSource
    {
        if ($source === null || trim($source) === '') {
            return null;
        }

        try {
            return $this->load($source);
        } catch (MissingImageException) {
            return null;
        }
    }

    /** Whether a source is remote rather than a local path. */
    public static function isRemote(string $source): bool
    {
        return (bool) preg_match('#^https?://#i', $source);
    }

    /** Drop everything cached, including remembered failures. */
    public function clear(): void
    {
        $this->cache = [];
        $this->failures = [];
    }

    /** How many sources are currently buffered. */
    public function cached(): int
    {
        return count($this->cache);
    }

    private function resolve(string $source): ImageSource
    {
        return self::isRemote($source) ? $this->resolveRemote($source) : $this->resolveLocal($source);
    }

    private function resolveLocal(string $path): ImageSource
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new MissingImageException(sprintf('Image "%s" does not exist or is not readable.', $path));
        }

        $size = @getimagesize($path);
        if ($size === false) {
            throw new MissingImageException(sprintf('File "%s" is not a readable image.', $path));
        }

        return new ImageSource($path, (int) $size[0], (int) $size[1]);
    }

    private function resolveRemote(string $url): ImageSource
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'follow_location' => $this->maxRedirects > 0 ? 1 : 0,
                'max_redirects' => max(1, $this->maxRedirects),
                'ignore_errors' => false,
                'user_agent' => 'nadar/pdf-generator',
            ],
            'https' => [
                'timeout' => $this->timeout,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);
        if ($data === false || $data === '') {
            throw new MissingImageException(sprintf(
                'Could not fetch image "%s" (timeout %ds). Remote images fail in production; '
                . 'pass a placeholder on the ImageBox or an $onMissing callback to render a designed fallback.',
                $url,
                $this->timeout
            ));
        }

        $size = @getimagesizefromstring($data);
        if ($size === false) {
            throw new MissingImageException(sprintf('Response from "%s" is not a readable image.', $url));
        }

        return new ImageSource($url, (int) $size[0], (int) $size[1], $data);
    }
}
