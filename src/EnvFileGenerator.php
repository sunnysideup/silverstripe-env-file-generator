<?php

declare(strict_types=1);

namespace Sunnysideup\EnvFileGenerator;

use RuntimeException;
use Spyc;

class EnvFileGenerator
{
    private const YAML_VARIABLES = [
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
        'BYPASS_MFA',
        'EnvironmentType',
    ];

    /**
     * Maps actual .env keys to template placeholders.
     */
    private const ENV_TO_TEMPLATE_MAP = [
        'SS_BASE_URL' => 'WebsiteURL',
        'SS_DATABASE_SERVER' => 'DBServer',
        'SS_DATABASE_NAME' => 'DBName',
        'SS_DATABASE_USERNAME' => 'DBUser',
        'SS_DATABASE_PASSWORD' => 'DBPassword',
        'SS_BASIC_AUTH_USER' => 'BasicAuthUser',
        'SS_BASIC_AUTH_PASSWORD' => 'BasicAuthPassword',
        'SS_DEFAULT_ADMIN_USERNAME' => 'AdminUser',
        'SS_DEFAULT_ADMIN_PASSWORD' => 'AdminPassword',
        'FIA_RELEASE_PING_URL' => 'FIAPingURL',
        'SS_RELEASE_BRANCH' => 'Branch',
        'SS_SESSION_KEY' => 'SessionKey',
        'SS_MFA_SECRET_KEY' => 'MFASecretKey',
        'SS_SEND_ALL_EMAILS_TO' => 'SendAllEmailsTo',
        'SS_ALLOWED_HOSTS' => 'DomainName',
        'BYPASS_MFA' => 'BYPASS_MFA',
    ];

    public static function init(): void
    {
        require __DIR__ . '/../../../autoload.php';
    }

    public static function outputExampleFile(?string $filePath = '.env.yml'): void
    {
        self::init();

        $targetPath = self::normalisePath($filePath);
        $content = '';

        foreach (self::YAML_VARIABLES as $variable) {
            $content .= $variable . ': ' . self::exampleValueFor($variable) . PHP_EOL;
        }

        file_put_contents($targetPath, $content);
    }

    /**
     * Build final .env based on template format/order.
     *
     * Rules:
     * - If .env exists: keep all *real* values from .env, reorder to template format, append unknown keys.
     * - If .env does not exist: use .env.yml values to fill template placeholders.
     * - Any existing .env value that is still an unresolved placeholder (e.g. "$WebsiteURL")
     *   is ignored so a previously broken .env cannot keep re-injecting placeholders.
     */
    public static function buildEnvFile(
        ?string $yamlFilePath = '.env.yml',
        ?string $envFilePath = '.env',
        ?string $outputEnvFilePath = '.env'
    ): string {
        self::init();

        $yamlPath = self::normalisePath($yamlFilePath);
        $envPath = self::normalisePath($envFilePath);
        $outputPath = self::normalisePath($outputEnvFilePath ?? $envFilePath);

        $yamlVariables = [];
        if (is_file($yamlPath)) {
            $yamlVariables = self::loadYamlEnv($yamlPath);
        }

        $existingEnvVariables = [];
        if (is_file($envPath)) {
            $existingEnvVariables = self::loadDotEnv($envPath);
        }

        // Existing .env values mapped to template placeholders (priority source if .env exists)
        $existingMappedTemplateVariables = self::mapEnvKeysToTemplateVariables($existingEnvVariables);

        // Placeholder values used for template replacement: .env wins over yaml
        $templateVariables = array_merge($yamlVariables, $existingMappedTemplateVariables);

        foreach ($templateVariables as $key => $value) {
            if ((string) $value === 'RANDOM') {
                $templateVariables[$key] = bin2hex(random_bytes(32));
            }
        }

        // Derive DomainName from WebsiteURL when it is not supplied explicitly.
        $templateVariables['DomainName'] = self::resolveDomainName($templateVariables);

        $template = self::getTemplate();

        $rendered = (string) preg_replace_callback(
            '/\$(\w+)/',
            function (array $matches) use ($templateVariables): string {
                $placeholderName = $matches[1];

                if (array_key_exists($placeholderName, $templateVariables)) {
                    return (string) $templateVariables[$placeholderName];
                }

                return $matches[0];
            },
            $template
        );

        // Force existing .env values to win for any matching key already present in the rendered
        // template - but only for real values, never for unresolved placeholders.
        $rendered = self::replaceTemplateValuesWithExistingEnvValues($rendered, $existingEnvVariables);

        $extraEnvVariables = self::findExtraEnvVariablesNotInRenderedTemplate($existingEnvVariables, $rendered);

        if ($extraEnvVariables !== []) {
            $rendered = rtrim($rendered) . PHP_EOL . PHP_EOL;
            $rendered .= '# Additional variables found in existing .env' . PHP_EOL;

            foreach ($extraEnvVariables as $key => $value) {
                $rendered .= self::formatEnvLine($key, $value) . PHP_EOL;
            }
        }

        $rendered = rtrim($rendered) . PHP_EOL;

        file_put_contents($outputPath, $rendered);

        return $rendered;
    }

    protected static function getTemplate(): string
    {
        return <<<'EOT'

# Basics
SS_BASE_URL="$WebsiteURL"
SS_ENVIRONMENT_TYPE="$EnvironmentType"
SS_ALLOWED_HOSTS="$DomainName"
SS_HOSTED_WITH_SITEHOST=false
# SS_ALLOWED_HOSTS="add your domain here, e.g. www.mydomain.com"

# Paths
TEMP_PATH="/container/application/tmp"

# DB
SS_DATABASE_SERVER="$DBServer"
SS_DATABASE_NAME="$DBName"
SS_DATABASE_USERNAME="$DBUser"
SS_DATABASE_PASSWORD="$DBPassword"
# mysqldump $DBName -u $DBUser -p$DBPassword -h $DBServer  --column-statistics=0 > $DBName.sql
# mysql     $DBName -u $DBUser -p$DBPassword -h $DBServer  < $DBName.sql
# mysql     $DBName -u $DBUser -p$DBPassword -h $DBServer  -A $DBName.sql

# Logins
SS_BASIC_AUTH_USER="$BasicAuthUser"
SS_BASIC_AUTH_PASSWORD="$BasicAuthPassword"
SS_USE_BASIC_AUTH=false
SS_DEFAULT_ADMIN_USERNAME="$AdminUser"
SS_DEFAULT_ADMIN_PASSWORD="$AdminPassword"
BYPASS_MFA=$BYPASS_MFA


# Debug and Development
# SS_ERROR_LOG="./silverstripe.log"
# SS_SERVER_FOR_EXAMPLE_DATA="great for dev environments"
# SS_ALLOW_SMOKE_TEST=true

# Release
SS_RELEASE_BRANCH="$Branch"
FIA_RELEASE_PING_URL="$FIAPingURL"

# Secrets
SS_MFA_SECRET_KEY="$MFASecretKey"
SS_SESSION_KEY="$SessionKey"

# Email
SS_SEND_ALL_EMAILS_TO="$SendAllEmailsTo"

# Branding
SS_WHITE_LABEL_ONLY=false


EOT;
    }

    /**
     * Example value used when generating the .env.yml stub.
     * Secret keys default to RANDOM so they are auto-generated on build.
     */
    protected static function exampleValueFor(string $variable): string
    {
        $randomByDefault = [
            'SessionKey' => true,
            'MFASecretKey' => true,
        ];

        if (isset($randomByDefault[$variable])) {
            return "'RANDOM'";
        }

        return "'foobar'";
    }

    /**
     * Resolve the DomainName placeholder.
     *
     * Priority:
     *  1. An explicit, real DomainName supplied via yaml / existing .env.
     *  2. Derived from WebsiteURL (scheme, path, query, port stripped).
     *
     * @param array<string, string> $templateVariables
     */
    protected static function resolveDomainName(array $templateVariables): string
    {
        $existing = (string) ($templateVariables['DomainName'] ?? '');
        if ($existing !== '' && !self::isUnresolvedPlaceholder($existing)) {
            return $existing;
        }

        $websiteUrl = (string) ($templateVariables['WebsiteURL'] ?? '');
        if ($websiteUrl === '' || self::isUnresolvedPlaceholder($websiteUrl)) {
            return $existing;
        }

        return self::deriveDomainNameFromUrl($websiteUrl);
    }

    /**
     * Turn a website URL into a bare host name, e.g.
     *  https://www.example.com/path?x=1  ->  www.example.com
     *  example.com:8080                   ->  example.com
     */
    protected static function deriveDomainNameFromUrl(string $websiteUrl): string
    {
        $websiteUrl = trim($websiteUrl);
        if ($websiteUrl === '') {
            return '';
        }

        // parse_url needs a scheme (or //) to recognise the host reliably.
        $hasScheme = preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $websiteUrl) === 1;
        $toParse = $hasScheme ? $websiteUrl : '//' . ltrim($websiteUrl, '/');

        $host = parse_url($toParse, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            // Fallback: strip scheme, then anything from the first / ? or # and any port.
            $host = (string) preg_replace('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', '', $websiteUrl);
            $host = (string) preg_replace('#[/?#].*$#', '', $host);
            $host = (string) preg_replace('#:\d+$#', '', $host);
        }

        return trim((string) $host);
    }

    /**
     * @return array<string, string>
     */
    protected static function loadYamlEnv(string $filePath): array
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('YAML file is not readable: ' . $filePath);
        }

        $data = Spyc::YAMLLoad($filePath);

        if (!is_array($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[(string) $key] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Parse KEY=value lines from an existing .env file.
     *
     * Unresolved placeholders (e.g. SS_BASE_URL="$WebsiteURL") are dropped so a
     * previously broken .env cannot keep overriding freshly substituted values.
     *
     * @return array<string, string>
     */
    protected static function loadDotEnv(string $filePath): array
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Env file is not readable: ' . $filePath);
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Could not read env file: ' . $filePath);
        }

        $result = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            if (str_starts_with($trimmedLine, '#')) {
                continue;
            }

            if (!str_contains($trimmedLine, '=')) {
                continue;
            }

            [$rawKey, $rawValue] = explode('=', $line, 2);

            $key = trim($rawKey);
            $value = trim($rawValue);

            if ($key === '') {
                continue;
            }

            $value = self::stripWrappingQuotes($value);

            // Ignore values that are still unresolved template placeholders.
            if (self::isUnresolvedPlaceholder($value)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, string> $envVariables
     * @return array<string, string>
     */
    protected static function mapEnvKeysToTemplateVariables(array $envVariables): array
    {
        $result = [];

        foreach (self::ENV_TO_TEMPLATE_MAP as $envKey => $templateKey) {
            if (array_key_exists($envKey, $envVariables)) {
                $result[$templateKey] = $envVariables[$envKey];
            }
        }

        return $result;
    }

    /**
     * Replace values in rendered template with values from existing .env for any matching key.
     *
     * @param array<string, string> $existingEnvVariables
     */
    protected static function replaceTemplateValuesWithExistingEnvValues(string $renderedTemplate, array $existingEnvVariables): string
    {
        $lines = explode(PHP_EOL, $renderedTemplate);

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$rawKey] = explode('=', $line, 2);
            $key = trim($rawKey);

            if ($key === '') {
                continue;
            }

            if (!array_key_exists($key, $existingEnvVariables)) {
                continue;
            }

            $value = $existingEnvVariables[$key];

            // Never re-inject an unresolved placeholder over a freshly substituted value.
            if (self::isUnresolvedPlaceholder($value)) {
                continue;
            }

            $lines[$index] = self::formatEnvLine($key, $value);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Find keys in existing .env that are NOT present in the rendered template.
     *
     * @param array<string, string> $envVariables
     * @return array<string, string>
     */
    protected static function findExtraEnvVariablesNotInRenderedTemplate(array $envVariables, string $renderedTemplate): array
    {
        $renderedKeys = self::extractEnvKeysFromContent($renderedTemplate);
        $extraVariables = [];

        foreach ($envVariables as $key => $value) {
            if (self::isUnresolvedPlaceholder($value)) {
                continue;
            }

            if (!array_key_exists($key, $renderedKeys)) {
                $extraVariables[$key] = $value;
            }
        }

        return $extraVariables;
    }

    /**
     * @return array<string, true>
     */
    protected static function extractEnvKeysFromContent(string $content): array
    {
        $lines = explode(PHP_EOL, $content);
        $keys = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$rawKey] = explode('=', $line, 2);
            $key = trim($rawKey);

            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * True when $value is exactly an unresolved template placeholder, e.g. "$WebsiteURL".
     */
    protected static function isUnresolvedPlaceholder(string $value): bool
    {
        if (preg_match('/^\$(\w+)$/', trim($value), $matches) !== 1) {
            return false;
        }

        return in_array($matches[1], self::getKnownPlaceholderNames(), true);
    }

    /**
     * All placeholder names that may appear in the template.
     *
     * @return list<string>
     */
    protected static function getKnownPlaceholderNames(): array
    {
        return array_values(array_unique(array_merge(
            self::YAML_VARIABLES,
            array_values(self::ENV_TO_TEMPLATE_MAP),
            ['DomainName']
        )));
    }

    protected static function stripWrappingQuotes(string $value): string
    {
        $length = strlen($value);

        if ($length < 2) {
            return $value;
        }

        $firstChar = $value[0];
        $lastChar = $value[$length - 1];

        if (($firstChar === '\'' && $lastChar === '\'') || ($firstChar === '"' && $lastChar === '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    protected static function formatEnvLine(string $key, string $value): string
    {
        if ($value === '') {
            return $key . '=';
        }

        if (preg_match('/^[A-Za-z0-9_:\-\.\/@]+$/', $value) === 1) {
            return $key . '=' . $value;
        }

        $escapedValue = str_replace('"', '\"', $value);

        return $key . '="' . $escapedValue . '"';
    }

    protected static function normalisePath(?string $filePath): string
    {
        $path = trim((string) $filePath);

        if ($path === '') {
            throw new RuntimeException('Empty file path provided');
        }

        return $path;
    }
}
