<?php

namespace Sunnysideup\EnvFileGenerator;

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
    // Function to load .env file into an associative array
    protected static function loadEnv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            die("Error: .env file not found.\n");
        }

        $variables = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue; // Skip comments
            }
            [$key, $value] = explode('=', $line, 2);
            $variables[trim($key)] = trim($value);
        }

        return $variables;
    }
}
