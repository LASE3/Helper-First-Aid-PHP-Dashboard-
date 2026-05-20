<?php

declare(strict_types=1);

function api_log(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $line = date('Y-m-d H:i:s') . ' | ' . $message;

    if (!empty($context)) {
        $line .= ' | ' . json_encode($context);
    }

    file_put_contents($logDir . '/api.log', $line . PHP_EOL, FILE_APPEND);
}
?>