<?php
require 'vendor/autoload.php';
use Symfony\Component\Process\Process;

$pythonPath = __DIR__ . '/../Python AI /cv_analyzer.py';
$filePath = __DIR__ . '/test_python_call.php'; // dummy file
$process = new Process(['python3', $pythonPath, $filePath, 'txt']);
$process->run();
echo "Is Successful: " . ($process->isSuccessful() ? 'Yes' : 'No') . "\n";
echo "Output: " . $process->getOutput() . "\n";
echo "Error Output: " . $process->getErrorOutput() . "\n";
