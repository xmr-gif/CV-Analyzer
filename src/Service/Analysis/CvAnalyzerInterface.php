<?php

namespace App\Service\Analysis;

use App\Dto\CvAnalysisResultDto;

interface CvAnalyzerInterface
{
    public function analyze(string $filePath, string $originalExtension = ''): CvAnalysisResultDto;
    public function analyzeFromJson(string $jsonData): CvAnalysisResultDto;
}
