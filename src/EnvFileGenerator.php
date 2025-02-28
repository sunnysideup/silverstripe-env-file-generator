<?php

namespace Sunnysideup\EnvFileGenerator;

use RuntimeException;

class EnvFileGenerator
{

    public static function OutputExampleFile(?string $filePath = '.env.yml')
    {
        $variables = [
            // Basics
            'BasicAuthUser',
            'BasicAuthPassword',
            'WebsiteURL',
            'DBServer',
            'DBName',
            'DBUser',
            'DBPassword',
            'Branch',
            'FIAPingURL',
            'MFASecretKey',
            'SessionKey',
            'SendAllEmailsTo',
            'AdminUser',
            'AdminPassword',
        ];
        foreach ($variables as $variable) {
            file_put_contents($filePath, $variable . ': "foobar"' . "\n", FILE_APPEND);
        }
    }

    public static function BuildEnvFile(?string $filePath = '.env.yml')
    {

        // Load environment variables from .env
        $envVariables = self::loadEnv($filePath);
        print_r($filePath);
        print_r($envVariables);

        $template = <<<EOT
# Basics
SS_BASE_URL="\$WebsiteURL"
SS_ENVIRONMENT_TYPE="test"
SS_HOSTED_WITH_SITEHOST=true

# Paths
TEMP_PATH="/container/application/tmp"

# DB
SS_DATABASE_SERVER="\$DBServer"
SS_DATABASE_NAME="\$DBName"
SS_DATABASE_USERNAME="\$DBUser"
SS_DATABASE_PASSWORD="\$DBPassword"
# mysqldump \$DBName -u \$DBUser -p\$DBPassword -h \$DBServer --column-statistics=0 > \$DBName.sql
# mysql     \$DBName -u \$DBUser -p\$DBPassword -h \$DBServer < \$DBName.sql

# Logins
SS_BASIC_AUTH_USER="\$BasicAuthUser"
SS_BASIC_AUTH_PASSWORD="\$BasicAuthPassword"
SS_USE_BASIC_AUTH=true
SS_DEFAULT_ADMIN_USERNAME="\$AdminUser"
SS_DEFAULT_ADMIN_PASSWORD="\$AdminPassword"

# Release
SS_RELEASE_BRANCH="\$Branch"
FIA_RELEASE_PING_URL="\$FIAPingURL"

# Secrets
SS_MFA_SECRET_KEY="\$MFASecretKey"
SS_SESSION_KEY="\$SessionKey"

# Email
SS_SEND_ALL_EMAILS_TO="\$SendAllEmailsTo"

EOT;

        // Replace variables using regex
        $result = preg_replace_callback('/\$(\w+)/', function ($matches) use ($envVariables) {
            return $envVariables[$matches[1]] ?? $matches[0]; // Keep original if not found
        }, $template);

        // Output result
        echo $result;

        // Optional: Write to


    }
    /**
     * Load environment variables from a .env file into an associative array.
     *
     * @param string $filePath The path to the .env file.
     * @return array The loaded environment variables.
     * @throws RuntimeException If the file is missing or unreadable.
     */
    protected static function loadEnv(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("Error: .env file not found or not readable at {$filePath}.");
        }

        $variables = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Match key=value, supporting quotes
            if (preg_match('/^([\w.]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(.+))$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = $matches[2] ?? $matches[3] ?? $matches[4] ?? '';

                // Store in array
                $variables[$key] = trim($value);
            } else {
                // Handle malformed lines gracefully
                echo "Warning: Skipping malformed line in .env file: {$line}\n";
            }
        }

        return $variables;
    }
}
