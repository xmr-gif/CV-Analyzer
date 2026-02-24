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

        $tempPath = $file->getRealPath();
        $extension = $file->getClientOriginalExtension();
        
        try {
            $result = $this->cvAnalyzer->analyze($tempPath, $extension);
            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
