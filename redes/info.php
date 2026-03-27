<?php
echo "<pre>";
echo "Current PHP Binary process: " . PHP_BINARY . "\n\n";

echo "Global PHP Location (where php):\n";
echo shell_exec('where php 2>&1') . "\n";

echo "Global PHP INI Config:\n";
echo shell_exec('php --ini 2>&1') . "\n";

echo "Global PHP Version & Modules:\n";
echo shell_exec('php -v 2>&1') . "\n";
echo shell_exec('php -m 2>&1') . "\n";
echo "</pre>";
?>
