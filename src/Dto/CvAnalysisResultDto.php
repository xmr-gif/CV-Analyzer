<?php

namespace App\Dto;

class CvAnalysisResultDto
{
    /**
     * @param array<int, array<string, mixed>> $mistakes
     * @param float $score
     * @param string $summary
     */
    public function __construct(
        public readonly array $mistakes,
        public readonly float $score,
        public readonly string $summary
    ) {
    }
}
