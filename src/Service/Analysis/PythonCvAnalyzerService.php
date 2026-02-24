<?php

namespace App\Service\Analysis;

use App\Dto\CvAnalysisResultDto;
use App\Mapper\CvAnalysisMapper;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PythonCvAnalyzerService implements CvAnalyzerInterface
{
    public function __construct(
        private readonly string $projectDir,
        private readonly CvAnalysisMapper $mapper
    ) {
    }

    public function analyze(string $filePath, string $originalExtension = ''): CvAnalysisResultDto
    {
        $pythonPath = $this->projectDir . '/../Python AI /cv_analyzer.py';
        
        $process = new Process(['python3', $pythonPath, $filePath, $originalExtension]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse Python result: ' . json_last_error_msg());
        }

        if (isset($data['error'])) {
            throw new \RuntimeException('Python analysis error: ' . $data['error']);
        }

        return $this->mapper->mapToDto($data);
    }
}
