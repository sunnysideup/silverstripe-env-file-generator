<?php

namespace Sunnysideup\EnvFileGenerator;

use RuntimeException;
use Spyc;

class EnvFileGenerator
{

    public static function init()
    {
        require __DIR__ . '/../../../autoload.php';
    }

    private const VARIABLES = [
        // Basics
        'WebsiteURL',
        'Branch',
        'DBServer',
        'DBName',
        'DBUser',
        'DBPassword',
        'BasicAuthUser',
        'BasicAuthPassword',
        'AdminUser',
        'AdminPassword',
        'FIAPingURL',
        'SessionKey',
        'SendAllEmailsTo',
        'MFASecretKey',
    ];

    public static function OutputExampleFile(?string $filePath = '.env.yml')
    {
        self::init();
        $filePath = self::getRealPath($filePath, false);
        foreach (self::VARIABLES as $variable) {
            file_put_contents($filePath, $variable . ': "foobar"' . "\n", FILE_APPEND);
        }
    }

    public static function BuildEnvFile(?string $filePath = '.env.yml')
    {
        self::init();
        $filePath = self::getRealPath($filePath, true);
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
# mysqldump \$DBName -u \$DBUser -p\$DBPassword -h \$DBServer  --column-statistics=0 > \$DBName.sql
# mysql     \$DBName -u \$DBUser -p\$DBPassword -h \$DBServer  < \$DBName.sql
# mysql     \$DBName -u \$DBUser -p\$DBPassword -h \$DBServer  -A \$DBName.sql

# Logins
SS_BASIC_AUTH_USER="\$BasicAuthUser"
SS_BASIC_AUTH_PASSWORD="\$BasicAuthPassword"
SS_USE_BASIC_AUTH=false
SS_DEFAULT_ADMIN_USERNAME="\$AdminUser"
SS_DEFAULT_ADMIN_PASSWORD="\$AdminPassword"
BYPASS_MFA=\$BYPASS_MFA

# Release
SS_RELEASE_BRANCH="\$Branch"
FIA_RELEASE_PING_URL="\$FIAPingURL"

# Secrets
SS_MFA_SECRET_KEY="\$MFASecretKey"
SS_SESSION_KEY="\$SessionKey"

# Email
SS_SEND_ALL_EMAILS_TO="\$SendAllEmailsTo"


EOT;
        foreach ($envVariables as $key => $value) {
            if ($value === 'RANDOM') {
                $envVariables[$key] = bin2hex(random_bytes(32));
            }
        }
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

        $data = Spyc::YAMLLoad($filePath);
        return $data;
    }

    protected static function getRealPath(string $filePath, ?bool $mustExist = false): string
    {
        $realPath = realpath($filePath);
        if ($realPath === false && $mustExist) {
            throw new RuntimeException("File not found: $filePath");
        }
        if ($mustExist && !file_exists($filePath)) {
            die('File does not exist: ' . $filePath);
        }
        return $realPath;
    }
}
