<?php
/**
 * ActionExecutor Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Executor\ActionExecutor;

/**
 * Test class for ActionExecutor
 */
class ActionExecutorTest extends TestCase {

    /**
     * ActionExecutor instance
     *
     * @var ActionExecutor
     */
    private $executor;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->executor = new ActionExecutor();
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( ActionExecutor::class, $this->executor );
    }

    /**
     * Test execute with create_post action
     */
    public function test_execute_create_post(): void {
        $action = [
            'type' => 'create_post',
            'params' => [
                'post_title' => 'Test Post',
                'post_content' => 'Test content',
                'post_status' => 'draft',
            ],
        ];

        $result = $this->executor->execute( $action );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertArrayHasKey( 'target_id', $result );
    }

    /**
     * Test execute with create_page action
     */
    public function test_execute_create_page(): void {
        $action = [
            'type' => 'create_page',
            'params' => [
                'post_title' => 'Test Page',
                'post_content' => 'Test page content',
            ],
        ];

        $result = $this->executor->execute( $action );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
    }

    /**
     * Test execute with update_post action
     */
    public function test_execute_update_post(): void {
        $action = [
            'type' => 'update_post',
            'params' => [
                'ID' => 1,
                'post_title' => 'Updated Title',
            ],
        ];

        $result = $this->executor->execute( $action );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
    }

    /**
     * Test execute with update_option action
     */
    public function test_execute_update_option(): void {
        $action = [
            'type' => 'update_option',
            'params' => [
                'option_name' => 'test_option',
                'option_value' => 'test_value',
            ],
        ];

        $result = $this->executor->execute( $action );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
    }

    /**
     * Test execute with unknown action type
     */
    public function test_execute_unknown_action(): void {
        $action = [
            'type' => 'unknown_action',
            'params' => [],
        ];

        $result = $this->executor->execute( $action );

        $this->assertIsArray( $result );
        $this->assertFalse( $result['success'] );
        $this->assertArrayHasKey( 'error', $result );
    }

    /**
     * Test execute returns before/after state
     */
    public function test_execute_captures_state(): void {
        $action = [
            'type' => 'update_post',
            'params' => [
                'ID' => 1,
                'post_title' => 'Updated Title',
            ],
        ];

        $result = $this->executor->execute( $action );

        $this->assertArrayHasKey( 'before_state', $result );
        $this->assertArrayHasKey( 'after_state', $result );
    }

    /**
     * Test execute with missing required params
     */
    public function test_execute_missing_params(): void {
        $action = [
            'type' => 'create_post',
            'params' => [],
        ];

        $result = $this->executor->execute( $action );

        // Should handle gracefully - either succeed with defaults or fail with error
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'success', $result );
    }

    /**
     * Test validate_action method
     */
    public function test_validate_action(): void {
        $valid_action = [
            'type' => 'create_post',
            'params' => [
                'post_title' => 'Test',
            ],
        ];

        $result = $this->executor->validate_action( $valid_action );
        $this->assertTrue( $result );
    }

    /**
     * Test validate_action with invalid action
     */
    public function test_validate_action_invalid(): void {
        $invalid_action = [
            'type' => '',
            'params' => [],
        ];

        $result = $this->executor->validate_action( $invalid_action );
        $this->assertFalse( $result );
    }

    /**
     * Test get_supported_actions returns array
     */
    public function test_get_supported_actions(): void {
        $actions = $this->executor->get_supported_actions();

        $this->assertIsArray( $actions );
        $this->assertContains( 'create_post', $actions );
        $this->assertContains( 'update_post', $actions );
        $this->assertContains( 'create_page', $actions );
    }

    /**
     * Test can_rollback returns boolean
     */
    public function test_can_rollback(): void {
        $can_rollback = $this->executor->can_rollback( 'create_post' );
        $this->assertIsBool( $can_rollback );
    }
}
