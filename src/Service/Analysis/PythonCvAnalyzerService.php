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
        set_time_limit(300);

        // Compute SHA-256 hash of the file content
        $fileHash = hash_file('sha256', $filePath);
        $cacheKey = 'cv_analysis_' . $fileHash;

        // Check Redis cache - if we've seen this exact file before, return instantly
        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filePath, $originalExtension) {
            // Cache miss: run the full Python analysis
            $item->expiresAfter(604800); // 7 days

            [$pythonExecutable, $pythonPath] = $this->getPythonPaths();

            $process = new Process([$pythonExecutable, $pythonPath, $filePath, $originalExtension]);
            $process->setTimeout(300);

            // Inherit the environment variables from the current process
            // This ensures that Java, Python, and the user's home directory (cache) are found
            // Only add PATH if it's not already set
            $process->setEnv(['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin']);

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

    public function analyzeFromJson(string $jsonData): CvAnalysisResultDto
    {
        set_time_limit(300);

        // Compute SHA-256 hash of the JSON content
        $jsonHash = hash('sha256', $jsonData);
        $cacheKey = 'cv_analysis_json_' . $jsonHash;

        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($jsonData) {
            $item->expiresAfter(604800); // 7 days

            [$pythonExecutable, $pythonPath] = $this->getPythonPaths();

            // Pass the JSON data as the first argument, and '--json' as the second
            $process = new Process([$pythonExecutable, $pythonPath, $jsonData, '--json']);
            $process->setTimeout(300);

            $process->setEnv(['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin']);

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
    /**
     * Safely resolve the Python directory path, accounting for Docker stripping
     * the trailing space from 'Python AI '.
     * 
     * @return array{0: string, 1: string} [pythonExecutable, pythonScriptPath]
     */
    private function getPythonPaths(): array
    {
        $baseDirs = [
            $this->projectDir . '/../Python AI',
            $this->projectDir . '/../Python AI ',
        ];
        
        $pythonDir = $baseDirs[0]; // fallback
        foreach ($baseDirs as $dir) {
            if (is_dir($dir)) {
                $pythonDir = $dir;
                break;
            }
        }
        
        $pythonPath = $pythonDir . '/cv_analyzer.py';
        $venvPython = $pythonDir . '/.venv/bin/python3';
        $pythonExecutable = file_exists($venvPython) ? $venvPython : 'python3';
        
        return [$pythonExecutable, $pythonPath];
    }
}
