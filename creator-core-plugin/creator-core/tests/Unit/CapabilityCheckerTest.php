<?php
/**
 * CapabilityChecker Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Permission\CapabilityChecker;

/**
 * Test class for CapabilityChecker
 */
class CapabilityCheckerTest extends TestCase {

    /**
     * CapabilityChecker instance
     *
     * @var CapabilityChecker
     */
    private $checker;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->checker = new CapabilityChecker();
    }

    /**
     * Test can_manage returns true for administrators
     */
    public function test_can_manage_for_admin(): void {
        $result = $this->checker->can_manage();
        $this->assertTrue( $result );
    }

    /**
     * Test can_use_chat returns true for valid users
     */
    public function test_can_use_chat(): void {
        $result = $this->checker->can_use_chat();
        $this->assertTrue( $result );
    }

    /**
     * Test can_execute_action with allowed action
     */
    public function test_can_execute_allowed_action(): void {
        $result = $this->checker->can_execute_action( 'create_post' );
        $this->assertTrue( $result );
    }

    /**
     * Test get_allowed_actions returns array
     */
    public function test_get_allowed_actions(): void {
        $actions = $this->checker->get_allowed_actions();

        $this->assertIsArray( $actions );
        $this->assertNotEmpty( $actions );
    }

    /**
     * Test default allowed actions
     */
    public function test_default_allowed_actions(): void {
        $actions = $this->checker->get_allowed_actions();

        $expected_actions = [
            'create_post',
            'update_post',
            'create_page',
            'update_page',
            'upload_media',
        ];

        foreach ( $expected_actions as $action ) {
            $this->assertContains( $action, $actions );
        }
    }

    /**
     * Test can_rollback returns true for administrators
     */
    public function test_can_rollback(): void {
        $result = $this->checker->can_rollback();
        $this->assertTrue( $result );
    }

    /**
     * Test can_view_logs returns true for administrators
     */
    public function test_can_view_logs(): void {
        $result = $this->checker->can_view_logs();
        $this->assertTrue( $result );
    }

    /**
     * Test get_user_role returns correct role
     */
    public function test_get_user_role(): void {
        $role = $this->checker->get_user_role();
        $this->assertEquals( 'administrator', $role );
    }
}
