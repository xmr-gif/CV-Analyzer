<?php

namespace App\Service\Analysis;

use App\Dto\CvAnalysisResultDto;
use App\Mapper\CvAnalysisMapper;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PythonCvAnalyzerService implements CvAnalyzerInterface
{
    public function __construct(
        private readonly string $projectDir,
        private readonly CvAnalysisMapper $mapper,
        private readonly CacheInterface $cache
    ) {
    }

    public function analyze(string $filePath, string $originalExtension = ''): CvAnalysisResultDto
    {
        // Increase limits to account for LLM processing and OCR which can take >30s
        set_time_limit(180);

        // Compute SHA-256 hash of the file content
        $fileHash = hash_file('sha256', $filePath);
        $cacheKey = 'cv_analysis_' . $fileHash;

        // Check Redis cache - if we've seen this exact file before, return instantly
        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filePath, $originalExtension) {
            // Cache miss: run the full Python analysis
            $item->expiresAfter(604800); // 7 days

            $pythonPath = $this->projectDir . '/../Python AI /cv_analyzer.py';

            $process = new Process(['python3', $pythonPath, $filePath, $originalExtension]);
            $process->setTimeout(180);
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

            return $data;
        });

        return $this->mapper->mapToDto($data);
    }
}
