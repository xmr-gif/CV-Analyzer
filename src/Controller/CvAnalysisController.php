<?php

namespace App\Controller;

use App\Service\Analysis\CvAnalyzerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cv-analysis', name: 'api_cv_analysis_')]
class CvAnalysisController extends AbstractController
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];

    private const EXTENSION_MIME_MAP = [
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    /**
     * Magic byte signatures for allowed file types.
     * These are the first bytes of valid files that cannot be spoofed by renaming.
     */
    private const MAGIC_BYTES = [
        'application/pdf' => '%PDF',          // PDF header
        'image/png'       => "\x89PNG",       // PNG header  
        'image/jpeg'      => "\xFF\xD8\xFF",  // JPEG header (SOI + APP marker)
    ];

    public function __construct(
        private readonly CvAnalyzerInterface $cvAnalyzer
    ) {
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('cv');

        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // === SECURITY LAYER 1: File size check ===
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return new JsonResponse(
                ['error' => 'File too large. Maximum allowed size is 10 MB.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // === SECURITY LAYER 2: Extension whitelist ===
        $extension = strtolower($file->getClientOriginalExtension());
        if (!array_key_exists($extension, self::EXTENSION_MIME_MAP)) {
            return new JsonResponse(
                ['error' => 'Invalid file type. Only PDF, PNG, JPG, and JPEG files are allowed.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // === SECURITY LAYER 3: MIME type validation ===
        $detectedMime = $file->getMimeType();
        if (!in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse(
                ['error' => 'Invalid file content type. The file does not appear to be a valid PDF or image.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // === SECURITY LAYER 4: Extension-MIME consistency ===
        $expectedMime = self::EXTENSION_MIME_MAP[$extension];
        if ($detectedMime !== $expectedMime) {
            return new JsonResponse(
                ['error' => 'File extension does not match file content. Possible file spoofing detected.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // === SECURITY LAYER 5: Magic bytes verification ===
        $filePath = $file->getRealPath();
        if (!$this->verifyMagicBytes($filePath, $detectedMime)) {
            return new JsonResponse(
                ['error' => 'File content verification failed. The file header does not match the expected format.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        try {
            $result = $this->cvAnalyzer->analyze($filePath, $extension);
            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify that the first bytes of the file match the expected magic bytes
     * for the given MIME type. This prevents attacks where a malicious file
     * is renamed to a different extension.
     */
    private function verifyMagicBytes(string $filePath, string $mimeType): bool
    {
        if (!isset(self::MAGIC_BYTES[$mimeType])) {
            return false;
        }

        $expectedHeader = self::MAGIC_BYTES[$mimeType];
        $headerLength = strlen($expectedHeader);

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $fileHeader = fread($handle, $headerLength);
        fclose($handle);

        return $fileHeader === $expectedHeader;
    }
}
