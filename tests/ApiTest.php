<?php

declare(strict_types=1);

namespace Detain\MyAdminWebhosting\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for the API functions in api.php.
 *
 * Since these functions depend heavily on global state and database calls,
 * tests focus on static analysis: function existence, signatures, and
 * parameter definitions.
 *
 * @package Detain\MyAdminWebhosting\Tests
 */
class ApiTest extends TestCase
{
    /**
     * Path to the API file.
     *
     * @var string
     */
    private static string $apiFile;

    /**
     * Contents of the API file.
     *
     * @var string
     */
    private static string $apiContent;

    /**
     * Resolve the API file path once for the test suite.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$apiFile = dirname(__DIR__) . '/src/api.php';
        $content = file_get_contents(self::$apiFile);
        self::$apiContent = $content !== false ? $content : '';
    }

    // ---------------------------------------------------------------
    // File Existence
    // ---------------------------------------------------------------

    /**
     * Test that the api.php source file exists.
     *
     * @return void
     */
    public function testApiFileExists(): void
    {
        $this->assertFileExists(self::$apiFile);
    }

    /**
     * Test that api.php is readable and non-empty.
     *
     * @return void
     */
    public function testApiFileIsReadableAndNonEmpty(): void
    {
        $this->assertNotEmpty(self::$apiContent, 'api.php should be non-empty.');
    }

    /**
     * Test that api.php has no syntax errors using php -l.
     *
     * @return void
     */
    public function testApiFileIsValidPhp(): void
    {
        $output = [];
        $exitCode = 0;
        $escapedPath = escapeshellarg(self::$apiFile);
        // Using exec for PHP lint check - input is a known file path, not user data
        exec("php -l {$escapedPath} 2>&1", $output, $exitCode);
        $this->assertSame(0, $exitCode, 'api.php should have no syntax errors: ' . implode("\n", $output));
    }

    /**
     * Test that api.php contains the expected function definitions via token analysis.
     *
     * @return void
     */
    public function testApiFileContainsExpectedFunctions(): void
    {
        $this->assertStringContainsString('function api_place_buy_website', self::$apiContent);
        $this->assertStringContainsString('function api_validate_buy_website', self::$apiContent);
    }

    // ---------------------------------------------------------------
    // Function Signature Static Analysis (via token parsing)
    // ---------------------------------------------------------------

    /**
     * Test that api_place_buy_website has the correct parameter count by parsing tokens.
     *
     * @return void
     */
    public function testPlaceBuyWebsiteParameterCount(): void
    {
        $params = $this->extractFunctionParams('api_place_buy_website');
        $this->assertCount(6, $params, 'api_place_buy_website should accept 6 parameters.');
    }

    /**
     * Test that api_validate_buy_website has the correct parameter count by parsing tokens.
     *
     * @return void
     */
    public function testValidateBuyWebsiteParameterCount(): void
    {
        $params = $this->extractFunctionParams('api_validate_buy_website');
        $this->assertCount(7, $params, 'api_validate_buy_website should accept 7 parameters.');
    }

    /**
     * Test that api_place_buy_website parameter names match expectations.
     *
     * @return void
     */
    public function testPlaceBuyWebsiteParameterNames(): void
    {
        $params = $this->extractFunctionParams('api_place_buy_website');
        $expected = ['$service_type', '$period', '$hostname', '$coupon', '$password', '$script'];
        $this->assertSame($expected, $params);
    }

    /**
     * Test that api_validate_buy_website parameter names match expectations.
     *
     * @return void
     */
    public function testValidateBuyWebsiteParameterNames(): void
    {
        $params = $this->extractFunctionParams('api_validate_buy_website');
        $expected = ['$period', '$coupon', '$tos', '$service_type', '$hostname', '$password', '$script'];
        $this->assertSame($expected, $params);
    }

    // ---------------------------------------------------------------
    // Docblock Analysis
    // ---------------------------------------------------------------

    /**
     * Test that api_place_buy_website has a docblock.
     *
     * @return void
     */
    public function testPlaceBuyWebsiteHasDocblock(): void
    {
        $this->assertMatchesRegularExpression(
            '/\/\*\*[\s\S]*?\*\/\s*function\s+api_place_buy_website/',
            self::$apiContent,
            'api_place_buy_website should have a docblock.'
        );
    }

    /**
     * Test that api_validate_buy_website has a docblock.
     *
     * @return void
     */
    public function testValidateBuyWebsiteHasDocblock(): void
    {
        $this->assertMatchesRegularExpression(
            '/\/\*\*[\s\S]*?\*\/\s*function\s+api_validate_buy_website/',
            self::$apiContent,
            'api_validate_buy_website should have a docblock.'
        );
    }

    /**
     * Test that the api_place_buy_website docblock documents a return type.
     *
     * @return void
     */
    public function testPlaceBuyWebsiteDocblockHasReturn(): void
    {
        preg_match('/(\/\*\*[\s\S]*?\*\/)\s*function\s+api_place_buy_website/', self::$apiContent, $matches);
        $this->assertNotEmpty($matches, 'Should find docblock for api_place_buy_website.');
        $this->assertStringContainsString('@return', $matches[1]);
    }

    /**
     * Test that the api_validate_buy_website docblock documents a return type.
     *
     * @return void
     */
    public function testValidateBuyWebsiteDocblockHasReturn(): void
    {
        preg_match('/(\/\*\*[\s\S]*?\*\/)\s*function\s+api_validate_buy_website/', self::$apiContent, $matches);
        $this->assertNotEmpty($matches, 'Should find docblock for api_validate_buy_website.');
        $this->assertStringContainsString('@return', $matches[1]);
    }

    // ---------------------------------------------------------------
    // Return Structure Static Analysis
    // ---------------------------------------------------------------

    /**
     * Test that api_place_buy_website returns an array with status keys.
     *
     * @return void
     */
    public function testPlaceBuyWebsiteReturnsStatusArray(): void
    {
        $this->assertStringContainsString("\$return['status']", self::$apiContent);
        $this->assertStringContainsString("\$return['status_text']", self::$apiContent);
    }

    /**
     * Test that both functions use 'ok' and 'error' status values.
     *
     * @return void
     */
    public function testFunctionsUseExpectedStatusValues(): void
    {
        $this->assertStringContainsString("'ok'", self::$apiContent);
        $this->assertStringContainsString("'error'", self::$apiContent);
    }

    // ---------------------------------------------------------------
    // Dependency Analysis
    // ---------------------------------------------------------------

    /**
     * Test that api_place_buy_website calls function_requirements for its dependencies.
     *
     * @return void
     */
    public function testPlaceBuyWebsiteCallsFunctionRequirements(): void
    {
        $this->assertStringContainsString("function_requirements('validate_buy_website')", self::$apiContent);
        $this->assertStringContainsString("function_requirements('place_buy_website')", self::$apiContent);
    }

    /**
     * Test that api_validate_buy_website calls function_requirements for validation.
     *
     * @return void
     */
    public function testValidateBuyWebsiteCallsFunctionRequirements(): void
    {
        preg_match(
            '/function\s+api_validate_buy_website[\s\S]*?^}/m',
            self::$apiContent,
            $matches
        );
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString("function_requirements('validate_buy_website')", $matches[0]);
    }

    /**
     * Test that both API functions access the global $tf session for custid.
     *
     * @return void
     */
    public function testBothFunctionsAccessGlobalSession(): void
    {
        $count = substr_count(self::$apiContent, "get_custid(\$GLOBALS['tf']->session->account_id, 'vps')");
        $this->assertSame(2, $count, 'Both API functions should call get_custid via global session.');
    }

    /**
     * Test that api_place_buy_website has a default parameter value for $script.
     *
     * @return void
     */
    public function testPlaceBuyWebsiteScriptDefaultValue(): void
    {
        preg_match('/function\s+api_place_buy_website\s*\(([^)]+)\)/', self::$apiContent, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('$script = 0', $matches[1]);
    }

    // ---------------------------------------------------------------
    // File Structure Tests
    // ---------------------------------------------------------------

    /**
     * Test that api.php starts with a PHP open tag.
     *
     * @return void
     */
    public function testFileStartsWithPhpTag(): void
    {
        $this->assertStringStartsWith('<?php', self::$apiContent);
    }

    /**
     * Test that api.php does not declare a namespace (procedural functions).
     *
     * @return void
     */
    public function testFileHasNoNamespace(): void
    {
        $this->assertStringNotContainsString('namespace ', self::$apiContent);
    }

    /**
     * Test that api.php defines exactly 2 functions.
     *
     * @return void
     */
    public function testFileDefinesExactlyTwoFunctions(): void
    {
        $count = preg_match_all('/^\s*function\s+\w+\s*\(/m', self::$apiContent);
        $this->assertSame(2, $count, 'api.php should define exactly 2 functions.');
    }

    // ---------------------------------------------------------------
    // Helper Methods
    // ---------------------------------------------------------------

    /**
     * Extract parameter names from a function definition by parsing the source file.
     *
     * @param string $functionName The function name to find.
     * @return array<int, string> List of parameter variable names.
     */
    private function extractFunctionParams(string $functionName): array
    {
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\(([^)]*)\)/';
        if (!preg_match($pattern, self::$apiContent, $matches)) {
            return [];
        }

        $paramString = trim($matches[1]);
        if ($paramString === '') {
            return [];
        }

        $params = [];
        foreach (explode(',', $paramString) as $param) {
            $param = trim($param);
            if (preg_match('/(\$\w+)/', $param, $varMatch)) {
                $params[] = $varMatch[1];
            }
        }

        return $params;
    }
}
