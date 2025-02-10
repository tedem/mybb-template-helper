<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/TemplateHelper.php';

if ($argc < 3) {
    echo "\033[31m[MyBB Template Helper] Usage: php <script_name> <theme_name> <action> [specific_templates...]\033[0m\n";

    exit;
}

$TemplateHelper = new \tedem\MyBBTemplateHelper\TemplateHelper($db);

match ($argv[1]) {
    '-d' => $TemplateHelper->downloadTemplates($argv[2]),
    '-u' => $TemplateHelper->uploadTemplates($argv[2], array_slice($argv, 3)),
    default => function () use ($argv): void {
        echo "\033[31m[MyBB Template Helper] Error: Invalid action '{$argv[1]}'. Use '-d' or '-u'.\033[0m\n";

        exit;
    },
};
