<?php

namespace App\Mapper;

use App\Dto\CvAnalysisResultDto;

class CvAnalysisMapper
{
    /**
     * @param array<string, mixed> $data
     */
    public function mapToDto(array $data): CvAnalysisResultDto
    {
        return new CvAnalysisResultDto(
            mistakes: $data['mistakes'] ?? [],
            score: (float) ($data['score'] ?? 0),
            summary: $data['summary'] ?? ''
        );
    }
}
