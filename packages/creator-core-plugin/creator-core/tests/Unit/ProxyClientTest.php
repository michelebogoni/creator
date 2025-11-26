<?php
/**
 * ProxyClient Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Integrations\ProxyClient;

/**
 * Test class for ProxyClient
 */
class ProxyClientTest extends TestCase {

    /**
     * ProxyClient instance
     *
     * @var ProxyClient
     */
    private $client;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->client = new ProxyClient();
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( ProxyClient::class, $this->client );
    }

    /**
     * Test mock mode is enabled in test environment
     */
    public function test_mock_mode_enabled(): void {
        // CREATOR_MOCK_MODE is defined as true in bootstrap
        $this->assertTrue( defined( 'CREATOR_MOCK_MODE' ) );
        $this->assertTrue( CREATOR_MOCK_MODE );
    }

    /**
     * Test send_message in mock mode
     */
    public function test_send_message_mock(): void {
        $response = $this->client->send_message( 'Test message', [] );

        $this->assertIsArray( $response );
        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'response', $response );
        $this->assertArrayHasKey( 'mock', $response );
        $this->assertTrue( $response['mock'] );
    }

    /**
     * Test send_message with context
     */
    public function test_send_message_with_context(): void {
        $context = [
            'site_info' => [
                'name' => 'Test Site',
                'url' => 'http://example.com',
            ],
            'plugins' => [
                'elementor' => true,
            ],
        ];

        $response = $this->client->send_message( 'Create a page', $context );

        $this->assertIsArray( $response );
        $this->assertTrue( $response['success'] );
    }

    /**
     * Test validate_license in mock mode
     */
    public function test_validate_license_mock(): void {
        $result = $this->client->validate_license( 'test-license-key' );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['valid'] );
        $this->assertArrayHasKey( 'expires', $result );
    }

    /**
     * Test get_proxy_url returns correct URL
     */
    public function test_get_proxy_url(): void {
        $url = $this->client->get_proxy_url();

        $this->assertEquals( CREATOR_PROXY_URL, $url );
    }

    /**
     * Test health check
     */
    public function test_health_check(): void {
        $result = $this->client->health_check();

        $this->assertIsArray( $result );
        $this->assertTrue( $result['healthy'] );
    }

    /**
     * Test mock response contains expected fields
     */
    public function test_mock_response_structure(): void {
        $response = $this->client->send_message( 'What can you do?', [] );

        $this->assertArrayHasKey( 'success', $response );
        $this->assertArrayHasKey( 'response', $response );
        $this->assertArrayHasKey( 'actions', $response );
        $this->assertIsArray( $response['actions'] );
    }

    /**
     * Test action generation in mock mode
     */
    public function test_mock_action_generation(): void {
        $response = $this->client->send_message( 'Create a new blog post', [] );

        $this->assertTrue( $response['success'] );
        // Mock mode should return sample actions
        $this->assertArrayHasKey( 'actions', $response );
    }
}
