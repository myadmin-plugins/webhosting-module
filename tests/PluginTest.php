<?php

declare(strict_types=1);

namespace Detain\MyAdminWebhosting\Tests;

use Detain\MyAdminWebhosting\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Test suite for the Plugin class.
 *
 * Covers class structure, static properties, hook registration,
 * event handler signatures, and settings configuration.
 *
 * @package Detain\MyAdminWebhosting\Tests
 */
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass<Plugin>
     */
    private ReflectionClass $reflection;

    /**
     * Set up reflection instance for introspection tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(Plugin::class);
    }

    // ---------------------------------------------------------------
    // Class Structure Tests
    // ---------------------------------------------------------------

    /**
     * Test that Plugin class exists and is instantiable.
     *
     * @return void
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Plugin::class));
    }

    /**
     * Test that Plugin can be instantiated.
     *
     * @return void
     */
    public function testCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Test that the constructor is public and takes no required parameters.
     *
     * @return void
     */
    public function testConstructorIsPublicNoParams(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPublic());
        $this->assertSame(0, $constructor->getNumberOfRequiredParameters());
    }

    /**
     * Test that the class resides in the correct namespace.
     *
     * @return void
     */
    public function testNamespace(): void
    {
        $this->assertSame('Detain\\MyAdminWebhosting', $this->reflection->getNamespaceName());
    }

    /**
     * Test that the class is not abstract or final.
     *
     * @return void
     */
    public function testClassIsConcreteAndNotFinal(): void
    {
        $this->assertFalse($this->reflection->isAbstract());
        $this->assertFalse($this->reflection->isFinal());
    }

    // ---------------------------------------------------------------
    // Static Property Tests
    // ---------------------------------------------------------------

    /**
     * Test that the $name static property equals 'Webhosting'.
     *
     * @return void
     */
    public function testNameProperty(): void
    {
        $this->assertSame('Webhosting', Plugin::$name);
    }

    /**
     * Test that the $description static property is a non-empty string.
     *
     * @return void
     */
    public function testDescriptionProperty(): void
    {
        $this->assertIsString(Plugin::$description);
        $this->assertNotEmpty(Plugin::$description);
    }

    /**
     * Test that the $help static property is a string.
     *
     * @return void
     */
    public function testHelpProperty(): void
    {
        $this->assertIsString(Plugin::$help);
    }

    /**
     * Test that the $module static property equals 'webhosting'.
     *
     * @return void
     */
    public function testModuleProperty(): void
    {
        $this->assertSame('webhosting', Plugin::$module);
    }

    /**
     * Test that the $type static property equals 'module'.
     *
     * @return void
     */
    public function testTypeProperty(): void
    {
        $this->assertSame('module', Plugin::$type);
    }

    /**
     * Test that all required setting keys are present in $settings.
     *
     * @return void
     */
    public function testSettingsContainsRequiredKeys(): void
    {
        $requiredKeys = [
            'SERVICE_ID_OFFSET',
            'USE_REPEAT_INVOICE',
            'USE_PACKAGES',
            'BILLING_DAYS_OFFSET',
            'IMGNAME',
            'REPEAT_BILLING_METHOD',
            'DELETE_PENDING_DAYS',
            'SUSPEND_DAYS',
            'SUSPEND_WARNING_DAYS',
            'TITLE',
            'EMAIL_FROM',
            'TBLNAME',
            'TABLE',
            'PREFIX',
            'TITLE_FIELD',
            'TITLE_FIELD2',
            'MENUNAME',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, Plugin::$settings, "Missing settings key: {$key}");
        }
    }

    /**
     * Test that $settings is an array.
     *
     * @return void
     */
    public function testSettingsIsArray(): void
    {
        $this->assertIsArray(Plugin::$settings);
    }

    /**
     * Test specific settings values for correctness.
     *
     * @return void
     */
    public function testSettingsValues(): void
    {
        $this->assertSame(1000, Plugin::$settings['SERVICE_ID_OFFSET']);
        $this->assertTrue(Plugin::$settings['USE_REPEAT_INVOICE']);
        $this->assertTrue(Plugin::$settings['USE_PACKAGES']);
        $this->assertSame(0, Plugin::$settings['BILLING_DAYS_OFFSET']);
        $this->assertSame('website.png', Plugin::$settings['IMGNAME']);
        $this->assertSame(45, Plugin::$settings['DELETE_PENDING_DAYS']);
        $this->assertSame(14, Plugin::$settings['SUSPEND_DAYS']);
        $this->assertSame(7, Plugin::$settings['SUSPEND_WARNING_DAYS']);
        $this->assertSame('Webhosting', Plugin::$settings['TITLE']);
        $this->assertSame('invoice@interserver.net', Plugin::$settings['EMAIL_FROM']);
        $this->assertSame('Websites', Plugin::$settings['TBLNAME']);
        $this->assertSame('websites', Plugin::$settings['TABLE']);
        $this->assertSame('website', Plugin::$settings['PREFIX']);
        $this->assertSame('website_hostname', Plugin::$settings['TITLE_FIELD']);
        $this->assertSame('website_username', Plugin::$settings['TITLE_FIELD2']);
        $this->assertSame('Webhosting', Plugin::$settings['MENUNAME']);
    }

    // ---------------------------------------------------------------
    // getHooks() Tests
    // ---------------------------------------------------------------

    /**
     * Test that getHooks() returns an array.
     *
     * @return void
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * Test that getHooks() contains the expected event keys.
     *
     * @return void
     */
    public function testGetHooksContainsExpectedKeys(): void
    {
        $hooks = Plugin::getHooks();
        $expectedKeys = [
            'api.register',
            'function.requirements',
            'webhosting.load_processing',
            'webhosting.settings',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $hooks, "Missing hook key: {$key}");
        }
    }

    /**
     * Test that each hook value is a callable array referencing the Plugin class.
     *
     * @return void
     */
    public function testGetHooksValuesAreCallableArrays(): void
    {
        $hooks = Plugin::getHooks();

        foreach ($hooks as $eventName => $handler) {
            $this->assertIsArray($handler, "Hook '{$eventName}' should be an array.");
            $this->assertCount(2, $handler, "Hook '{$eventName}' should have exactly 2 elements.");
            $this->assertSame(Plugin::class, $handler[0], "Hook '{$eventName}' should reference Plugin class.");
            $this->assertIsString($handler[1], "Hook '{$eventName}' method name should be a string.");
        }
    }

    /**
     * Test that all hook handler methods exist on the Plugin class.
     *
     * @return void
     */
    public function testGetHooksMethodsExist(): void
    {
        $hooks = Plugin::getHooks();

        foreach ($hooks as $eventName => $handler) {
            $this->assertTrue(
                method_exists(Plugin::class, $handler[1]),
                "Method Plugin::{$handler[1]}() referenced by hook '{$eventName}' does not exist."
            );
        }
    }

    /**
     * Test that the load_processing hook key uses the module property dynamically.
     *
     * @return void
     */
    public function testLoadProcessingHookKeyMatchesModule(): void
    {
        $hooks = Plugin::getHooks();
        $expectedKey = Plugin::$module . '.load_processing';
        $this->assertArrayHasKey($expectedKey, $hooks);
    }

    /**
     * Test that the settings hook key uses the module property dynamically.
     *
     * @return void
     */
    public function testSettingsHookKeyMatchesModule(): void
    {
        $hooks = Plugin::getHooks();
        $expectedKey = Plugin::$module . '.settings';
        $this->assertArrayHasKey($expectedKey, $hooks);
    }

    /**
     * Test that getHooks() returns exactly 4 hooks.
     *
     * @return void
     */
    public function testGetHooksCount(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertCount(4, $hooks);
    }

    // ---------------------------------------------------------------
    // Event Handler Signature Tests
    // ---------------------------------------------------------------

    /**
     * Test that getRequirements() accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetRequirementsSignature(): void
    {
        $method = $this->reflection->getMethod('getRequirements');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertCount(1, $method->getParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $this->assertNotNull($param->getType());
        $this->assertSame(GenericEvent::class, $param->getType()->getName());
    }

    /**
     * Test that apiRegister() accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testApiRegisterSignature(): void
    {
        $method = $this->reflection->getMethod('apiRegister');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertCount(1, $method->getParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $this->assertNotNull($param->getType());
        $this->assertSame(GenericEvent::class, $param->getType()->getName());
    }

    /**
     * Test that loadProcessing() accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testLoadProcessingSignature(): void
    {
        $method = $this->reflection->getMethod('loadProcessing');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertCount(1, $method->getParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $this->assertNotNull($param->getType());
        $this->assertSame(GenericEvent::class, $param->getType()->getName());
    }

    /**
     * Test that getSettings() accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetSettingsSignature(): void
    {
        $method = $this->reflection->getMethod('getSettings');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertCount(1, $method->getParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $this->assertNotNull($param->getType());
        $this->assertSame(GenericEvent::class, $param->getType()->getName());
    }

    /**
     * Test that all event handler methods have void or no explicit return type.
     *
     * @return void
     */
    public function testEventHandlersReturnTypes(): void
    {
        $eventMethods = ['getRequirements', 'apiRegister', 'loadProcessing', 'getSettings'];

        foreach ($eventMethods as $methodName) {
            $method = $this->reflection->getMethod($methodName);
            $returnType = $method->getReturnType();
            // These methods either have no return type or return void
            if ($returnType !== null) {
                $this->assertSame('void', $returnType->getName());
            } else {
                $this->assertNull($returnType);
            }
        }
    }

    // ---------------------------------------------------------------
    // apiRegister() Behavior Test
    // ---------------------------------------------------------------

    /**
     * Test that apiRegister() can be called without error (it is a no-op).
     *
     * @return void
     */
    public function testApiRegisterDoesNotThrow(): void
    {
        $event = new GenericEvent(new \stdClass());
        Plugin::apiRegister($event);
        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // getRequirements() Behavior Test
    // ---------------------------------------------------------------

    /**
     * Test that getRequirements() calls add_requirement on the event subject.
     *
     * Uses an anonymous class to avoid mocking vendor classes.
     *
     * @return void
     */
    public function testGetRequirementsCallsAddRequirement(): void
    {
        $calls = [];
        $loader = new class($calls) {
            /** @var array<int, array{string, string}> */
            private array $callsRef;

            /**
             * @param array<int, array{string, string}> $calls
             */
            public function __construct(array &$calls)
            {
                $this->callsRef = &$calls;
            }

            /**
             * @param string $name
             * @param string $path
             * @return void
             */
            public function add_requirement(string $name, string $path): void
            {
                $this->callsRef[] = [$name, $path];
            }
        };

        $event = new GenericEvent($loader);
        Plugin::getRequirements($event);

        $this->assertCount(2, $calls);
        $this->assertSame('api_place_buy_website', $calls[0][0]);
        $this->assertStringContainsString('api.php', $calls[0][1]);
        $this->assertSame('api_validate_buy_website', $calls[1][0]);
        $this->assertStringContainsString('api.php', $calls[1][1]);
    }

    // ---------------------------------------------------------------
    // Static Properties Type Tests
    // ---------------------------------------------------------------

    /**
     * Test that all static properties have the expected types.
     *
     * @return void
     */
    public function testStaticPropertyTypes(): void
    {
        $this->assertIsString(Plugin::$name);
        $this->assertIsString(Plugin::$description);
        $this->assertIsString(Plugin::$help);
        $this->assertIsString(Plugin::$module);
        $this->assertIsString(Plugin::$type);
        $this->assertIsArray(Plugin::$settings);
    }

    /**
     * Test that all declared static properties are public.
     *
     * @return void
     */
    public function testAllStaticPropertiesArePublic(): void
    {
        $properties = $this->reflection->getProperties(\ReflectionProperty::IS_STATIC);

        foreach ($properties as $property) {
            $this->assertTrue(
                $property->isPublic(),
                "Static property \${$property->getName()} should be public."
            );
        }
    }

    /**
     * Test that the class has exactly 6 static properties.
     *
     * @return void
     */
    public function testStaticPropertyCount(): void
    {
        $properties = $this->reflection->getProperties(\ReflectionProperty::IS_STATIC);
        $this->assertCount(6, $properties);
    }

    // ---------------------------------------------------------------
    // Settings Consistency Tests
    // ---------------------------------------------------------------

    /**
     * Test that TABLE and PREFIX settings are consistent with each other.
     *
     * @return void
     */
    public function testTableAndPrefixConsistency(): void
    {
        $table = Plugin::$settings['TABLE'];
        $prefix = Plugin::$settings['PREFIX'];
        // PREFIX should be the singular of TABLE (websites -> website)
        $this->assertStringStartsWith($prefix, $table);
    }

    /**
     * Test that TITLE_FIELD starts with the PREFIX.
     *
     * @return void
     */
    public function testTitleFieldStartsWithPrefix(): void
    {
        $prefix = Plugin::$settings['PREFIX'];
        $this->assertStringStartsWith($prefix . '_', Plugin::$settings['TITLE_FIELD']);
        $this->assertStringStartsWith($prefix . '_', Plugin::$settings['TITLE_FIELD2']);
    }

    /**
     * Test that EMAIL_FROM is a valid email format.
     *
     * @return void
     */
    public function testEmailFromIsValidFormat(): void
    {
        $this->assertNotFalse(
            filter_var(Plugin::$settings['EMAIL_FROM'], FILTER_VALIDATE_EMAIL),
            'EMAIL_FROM should be a valid email address.'
        );
    }

    /**
     * Test that numeric settings have reasonable values.
     *
     * @return void
     */
    public function testNumericSettingsAreReasonable(): void
    {
        $this->assertGreaterThan(0, Plugin::$settings['SERVICE_ID_OFFSET']);
        $this->assertGreaterThanOrEqual(0, Plugin::$settings['BILLING_DAYS_OFFSET']);
        $this->assertGreaterThan(0, Plugin::$settings['DELETE_PENDING_DAYS']);
        $this->assertGreaterThan(0, Plugin::$settings['SUSPEND_DAYS']);
        $this->assertGreaterThan(0, Plugin::$settings['SUSPEND_WARNING_DAYS']);
    }

    /**
     * Test that SUSPEND_WARNING_DAYS is less than SUSPEND_DAYS.
     *
     * @return void
     */
    public function testSuspendWarningIsBeforeSuspend(): void
    {
        $this->assertLessThan(
            Plugin::$settings['SUSPEND_DAYS'],
            Plugin::$settings['SUSPEND_WARNING_DAYS'],
            'SUSPEND_WARNING_DAYS should be less than SUSPEND_DAYS.'
        );
    }

    /**
     * Test that DELETE_PENDING_DAYS is greater than SUSPEND_DAYS.
     *
     * @return void
     */
    public function testDeletePendingIsAfterSuspend(): void
    {
        $this->assertGreaterThan(
            Plugin::$settings['SUSPEND_DAYS'],
            Plugin::$settings['DELETE_PENDING_DAYS'],
            'DELETE_PENDING_DAYS should be greater than SUSPEND_DAYS.'
        );
    }

    // ---------------------------------------------------------------
    // Hook Method Static Analysis
    // ---------------------------------------------------------------

    /**
     * Test that getHooks() is a pure static method (no dependencies).
     *
     * @return void
     */
    public function testGetHooksIsPureStatic(): void
    {
        $method = $this->reflection->getMethod('getHooks');
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $this->assertSame(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();
        if ($returnType !== null) {
            $this->assertSame('array', $returnType->getName());
        }
    }

    /**
     * Test that getHooks() is idempotent (returns same result on multiple calls).
     *
     * @return void
     */
    public function testGetHooksIsIdempotent(): void
    {
        $first = Plugin::getHooks();
        $second = Plugin::getHooks();
        $this->assertSame($first, $second);
    }
}
