<?php

namespace Lle\EntityFileBundle\Service;

use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Response sending the contents of a file, for the read routes and the public links.
 */
class EntityFileStreamer
{
    private const PDF_MIME_TYPE = "application/pdf";
    private const IMAGE_MIME_PREFIX = "image/";

    /**
     * @param bool $preview display in the browser even with "disposition: attachment",
     *     only for the types it displays itself (PDF, images)
     */
    public function stream(EntityFileManager $manager, EntityFileInterface $entityFile, bool $preview = false): StreamedResponse
    {
        $resource = $manager->readStream($entityFile);
        $name = (string)$entityFile->getName() ?: "file";
        $mimeType = (string)$entityFile->getMimeType();

        // Slug the basename only: slugging the whole name turns "toto.docx" into "toto-docx",
        // and the client then appends an extension of its own. The slug is only the ASCII
        // fallback, the original name is sent as the UTF-8 filename.
        $slugger = new AsciiSlugger();
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $fallbackName = $slugger->slug(pathinfo($name, PATHINFO_FILENAME))
            . ($extension ? "." . $extension : "");

        // A file stored before its type was refused (see "*_mime_types" options) is only offered as a download
        $allowed = $manager->isAllowedMimeType($mimeType);
        $disposition = $allowed ? $manager->getConfig()["disposition"] : HeaderUtils::DISPOSITION_ATTACHMENT;

        if ($preview && $allowed && self::isPreviewable($mimeType)) {
            $disposition = HeaderUtils::DISPOSITION_INLINE;
        }

        return new StreamedResponse(function () use ($resource) {
            fpassthru($resource);
            fclose($resource);
        }, 200, [
            "Content-Type" => $allowed ? $mimeType : "application/octet-stream",
            "Content-Length" => $entityFile->getSize(),
            "Content-Disposition" => HeaderUtils::makeDisposition($disposition, $name, $fallbackName ?: "file"),
            "X-Content-Type-Options" => "nosniff",
            // Scripts of an HTML or SVG file cannot run on this domain, even if such a type is accepted.
            // Not for PDF: browsers refuse to display it in a sandbox.
            ...($mimeType === self::PDF_MIME_TYPE ? [] : ["Content-Security-Policy" => "sandbox"]),
        ]);
    }

    /**
     * @return bool true for the types a browser displays itself: PDF and images (not SVG, which runs scripts)
     */
    public static function isPreviewable(string $mimeType): bool
    {
        return $mimeType === self::PDF_MIME_TYPE
            || (str_starts_with($mimeType, self::IMAGE_MIME_PREFIX) && $mimeType !== "image/svg+xml");
    }
}
