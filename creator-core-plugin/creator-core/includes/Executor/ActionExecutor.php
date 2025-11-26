<?php
/**
 * Action Executor
 *
 * @package CreatorCore
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;
use CreatorCore\Audit\OperationTracker;
use CreatorCore\Backup\SnapshotManager;
use CreatorCore\Backup\DeltaBackup;
use CreatorCore\Permission\CapabilityChecker;

/**
 * Class ActionExecutor
 *
 * Executes WordPress actions requested by the AI
 */
class ActionExecutor {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Operation tracker instance
     *
     * @var OperationTracker
     */
    private OperationTracker $tracker;

    /**
     * Snapshot manager instance
     *
     * @var SnapshotManager
     */
    private SnapshotManager $snapshot_manager;

    /**
     * Delta backup instance
     *
     * @var DeltaBackup
     */
    private DeltaBackup $delta_backup;

    /**
     * Capability checker instance
     *
     * @var CapabilityChecker
     */
    private CapabilityChecker $capability_checker;

    /**
     * Operation factory instance
     *
     * @var OperationFactory
     */
    private OperationFactory $operation_factory;

    /**
     * Error handler instance
     *
     * @var ErrorHandler
     */
    private ErrorHandler $error_handler;

    /**
     * Constructor
     *
     * @param AuditLogger|null       $logger             Audit logger instance.
     * @param CapabilityChecker|null $capability_checker Capability checker instance.
     */
    public function __construct( ?AuditLogger $logger = null, ?CapabilityChecker $capability_checker = null ) {
        $this->logger             = $logger ?? new AuditLogger();
        $this->capability_checker = $capability_checker ?? new CapabilityChecker();
        $this->tracker            = new OperationTracker( $this->logger );
        $this->snapshot_manager   = new SnapshotManager( $this->logger );
        $this->delta_backup       = new DeltaBackup();
        $this->operation_factory  = new OperationFactory();
        $this->error_handler      = new ErrorHandler( $this->logger );
    }

    /**
     * Execute an action
     *
     * @param array $action     Action data with type and params.
     * @param int   $chat_id    Chat ID.
     * @param int   $message_id Message ID.
     * @return array Result with success status and data.
     */
    public function execute( array $action, int $chat_id, int $message_id ): array {
        $action_type = $action['type'] ?? '';
        $params      = $action['params'] ?? [];
        $target      = $params['target'] ?? $action_type;

        // Check permissions
        $permission_check = $this->capability_checker->check_permission( $action_type );
        if ( is_wp_error( $permission_check ) ) {
            return [
                'success' => false,
                'error'   => $permission_check->get_error_message(),
                'code'    => 'permission_denied',
            ];
        }

        // Start tracking operation
        $operation_id = $this->tracker->start_operation( $action_type, $target, $message_id );

        if ( ! $operation_id ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to start operation', 'creator-core' ),
                'code'    => 'operation_error',
            ];
        }

        // Capture before state
        $before_state = $this->capture_before_state( $action_type, $params );

        try {
            // Execute the operation
            $result = $this->execute_operation( $action_type, $params );

            if ( ! $result['success'] ) {
                $this->tracker->fail_operation( $result['error'] ?? 'Unknown error' );
                return $result;
            }

            // Capture after state
            $after_state = $this->capture_after_state( $action_type, $params, $result );

            // Create snapshot
            $operations = [
                $this->delta_backup->format_operation(
                    $action_type,
                    $target,
                    $before_state,
                    $after_state,
                    'completed'
                ),
            ];

            $snapshot_id = $this->snapshot_manager->create_snapshot(
                $chat_id,
                $message_id,
                $operation_id,
                $operations
            );

            // Complete operation
            $this->tracker->complete_operation( $snapshot_id, $result['data'] ?? [] );

            return [
                'success'      => true,
                'operation_id' => $operation_id,
                'snapshot_id'  => $snapshot_id,
                'data'         => $result['data'] ?? [],
                'message'      => $result['message'] ?? __( 'Action completed successfully', 'creator-core' ),
            ];

        } catch ( \Exception $e ) {
            $this->tracker->fail_operation( $e->getMessage() );
            $this->error_handler->handle( $e, $action );

            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'code'    => 'execution_error',
            ];
        }
    }

    /**
     * Execute the actual operation
     *
     * @param string $action_type Action type.
     * @param array  $params      Action parameters.
     * @return array
     */
    private function execute_operation( string $action_type, array $params ): array {
        $this->tracker->add_step( 'execute_start', [ 'action_type' => $action_type ] );

        switch ( $action_type ) {
            case 'create_post':
                return $this->create_post( $params );

            case 'create_page':
                return $this->create_page( $params );

            case 'update_post':
            case 'update_page':
                return $this->update_post( $params );

            case 'delete_post':
                return $this->delete_post( $params );

            case 'update_meta':
                return $this->update_meta( $params );

            case 'add_elementor_widget':
                return $this->add_elementor_widget( $params );

            case 'update_option':
                return $this->update_option( $params );

            default:
                return [
                    'success' => false,
                    'error'   => sprintf(
                        /* translators: %s: Action type */
                        __( 'Unknown action type: %s', 'creator-core' ),
                        $action_type
                    ),
                ];
        }
    }

    /**
     * Create a post
     *
     * @param array $params Post parameters.
     * @return array
     */
    private function create_post( array $params ): array {
        $post_data = [
            'post_title'   => $params['title'] ?? 'New Post',
            'post_content' => $params['content'] ?? '',
            'post_excerpt' => $params['excerpt'] ?? '',
            'post_status'  => $params['status'] ?? 'draft',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        ];

        if ( isset( $params['category'] ) ) {
            $post_data['post_category'] = (array) $params['category'];
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return [
                'success' => false,
                'error'   => $post_id->get_error_message(),
            ];
        }

        $this->tracker->add_step( 'post_created', [ 'post_id' => $post_id ] );

        return [
            'success' => true,
            'data'    => [
                'post_id'   => $post_id,
                'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
                'view_url'  => get_permalink( $post_id ),
            ],
            'message' => sprintf(
                /* translators: %s: Post title */
                __( 'Post "%s" created successfully', 'creator-core' ),
                $post_data['post_title']
            ),
        ];
    }

    /**
     * Create a page
     *
     * @param array $params Page parameters.
     * @return array
     */
    private function create_page( array $params ): array {
        $post_data = [
            'post_title'   => $params['title'] ?? 'New Page',
            'post_content' => $params['content'] ?? '',
            'post_status'  => $params['status'] ?? 'draft',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ];

        if ( isset( $params['parent'] ) ) {
            $post_data['post_parent'] = absint( $params['parent'] );
        }

        if ( isset( $params['template'] ) ) {
            $post_data['page_template'] = sanitize_file_name( $params['template'] );
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return [
                'success' => false,
                'error'   => $post_id->get_error_message(),
            ];
        }

        $this->tracker->add_step( 'page_created', [ 'post_id' => $post_id ] );

        // If Elementor is available and requested, set up for Elementor
        if ( isset( $params['use_elementor'] ) && $params['use_elementor'] ) {
            update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $post_id, '_elementor_data', '[]' );
        }

        return [
            'success' => true,
            'data'    => [
                'post_id'      => $post_id,
                'edit_url'     => get_edit_post_link( $post_id, 'raw' ),
                'view_url'     => get_permalink( $post_id ),
                'elementor_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
            ],
            'message' => sprintf(
                /* translators: %s: Page title */
                __( 'Page "%s" created successfully', 'creator-core' ),
                $post_data['post_title']
            ),
        ];
    }

    /**
     * Update a post/page
     *
     * @param array $params Update parameters.
     * @return array
     */
    private function update_post( array $params ): array {
        $post_id = $params['post_id'] ?? 0;

        if ( ! $post_id ) {
            return [
                'success' => false,
                'error'   => __( 'Post ID is required', 'creator-core' ),
            ];
        }

        $post = get_post( $post_id );

        if ( ! $post ) {
            return [
                'success' => false,
                'error'   => __( 'Post not found', 'creator-core' ),
            ];
        }

        $update_data = [ 'ID' => $post_id ];

        if ( isset( $params['title'] ) ) {
            $update_data['post_title'] = $params['title'];
        }
        if ( isset( $params['content'] ) ) {
            $update_data['post_content'] = $params['content'];
        }
        if ( isset( $params['excerpt'] ) ) {
            $update_data['post_excerpt'] = $params['excerpt'];
        }
        if ( isset( $params['status'] ) ) {
            $update_data['post_status'] = $params['status'];
        }

        $result = wp_update_post( $update_data, true );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'error'   => $result->get_error_message(),
            ];
        }

        $this->tracker->add_step( 'post_updated', [ 'post_id' => $post_id ] );

        return [
            'success' => true,
            'data'    => [
                'post_id'  => $post_id,
                'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            ],
            'message' => __( 'Post updated successfully', 'creator-core' ),
        ];
    }

    /**
     * Delete a post
     *
     * @param array $params Delete parameters.
     * @return array
     */
    private function delete_post( array $params ): array {
        $post_id = $params['post_id'] ?? 0;
        $force   = $params['force'] ?? false;

        if ( ! $post_id ) {
            return [
                'success' => false,
                'error'   => __( 'Post ID is required', 'creator-core' ),
            ];
        }

        $result = wp_delete_post( $post_id, $force );

        if ( ! $result ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to delete post', 'creator-core' ),
            ];
        }

        $this->tracker->add_step( 'post_deleted', [ 'post_id' => $post_id ] );

        return [
            'success' => true,
            'data'    => [ 'post_id' => $post_id ],
            'message' => __( 'Post deleted successfully', 'creator-core' ),
        ];
    }

    /**
     * Update meta
     *
     * @param array $params Meta parameters.
     * @return array
     */
    private function update_meta( array $params ): array {
        $object_id = $params['object_id'] ?? 0;
        $meta_key  = $params['meta_key'] ?? '';
        $meta_value = $params['meta_value'] ?? '';

        if ( ! $object_id || ! $meta_key ) {
            return [
                'success' => false,
                'error'   => __( 'Object ID and meta key are required', 'creator-core' ),
            ];
        }

        $result = update_post_meta( $object_id, $meta_key, $meta_value );

        $this->tracker->add_step( 'meta_updated', [
            'object_id' => $object_id,
            'meta_key'  => $meta_key,
        ]);

        return [
            'success' => true,
            'data'    => [
                'object_id' => $object_id,
                'meta_key'  => $meta_key,
            ],
            'message' => __( 'Meta updated successfully', 'creator-core' ),
        ];
    }

    /**
     * Add Elementor widget
     *
     * @param array $params Widget parameters.
     * @return array
     */
    private function add_elementor_widget( array $params ): array {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return [
                'success' => false,
                'error'   => __( 'Elementor is not available', 'creator-core' ),
            ];
        }

        $post_id     = $params['post_id'] ?? 0;
        $widget_type = $params['widget_type'] ?? 'text-editor';
        $settings    = $params['settings'] ?? [];

        if ( ! $post_id ) {
            return [
                'success' => false,
                'error'   => __( 'Post ID is required', 'creator-core' ),
            ];
        }

        $elementor = new \CreatorCore\Integrations\ElementorIntegration();
        $result    = $elementor->add_widget( $post_id, $widget_type, $settings );

        if ( ! $result ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to add widget', 'creator-core' ),
            ];
        }

        $this->tracker->add_step( 'elementor_widget_added', [
            'post_id'     => $post_id,
            'widget_type' => $widget_type,
        ]);

        return [
            'success' => true,
            'data'    => [
                'post_id'      => $post_id,
                'elementor_url' => $elementor->get_edit_url( $post_id ),
            ],
            'message' => __( 'Widget added successfully', 'creator-core' ),
        ];
    }

    /**
     * Update option
     *
     * @param array $params Option parameters.
     * @return array
     */
    private function update_option( array $params ): array {
        $option_name  = $params['option_name'] ?? '';
        $option_value = $params['option_value'] ?? '';

        if ( ! $option_name ) {
            return [
                'success' => false,
                'error'   => __( 'Option name is required', 'creator-core' ),
            ];
        }

        $result = update_option( $option_name, $option_value );

        $this->tracker->add_step( 'option_updated', [ 'option_name' => $option_name ] );

        return [
            'success' => true,
            'data'    => [ 'option_name' => $option_name ],
            'message' => __( 'Option updated successfully', 'creator-core' ),
        ];
    }

    /**
     * Capture before state for an operation
     *
     * @param string $action_type Action type.
     * @param array  $params      Parameters.
     * @return array|null
     */
    private function capture_before_state( string $action_type, array $params ): ?array {
        switch ( $action_type ) {
            case 'update_post':
            case 'update_page':
            case 'delete_post':
                $post_id = $params['post_id'] ?? 0;
                return $post_id ? $this->delta_backup->capture_post_state( $post_id ) : null;

            case 'update_meta':
                $object_id = $params['object_id'] ?? 0;
                $meta_key  = $params['meta_key'] ?? '';
                return [
                    'object_id'  => $object_id,
                    'meta_key'   => $meta_key,
                    'meta_value' => get_post_meta( $object_id, $meta_key, true ),
                ];

            case 'add_elementor_widget':
            case 'update_elementor':
                $post_id = $params['post_id'] ?? 0;
                return $post_id ? [
                    'post_id'        => $post_id,
                    'elementor_data' => get_post_meta( $post_id, '_elementor_data', true ),
                ] : null;

            case 'update_option':
                $option_name = $params['option_name'] ?? '';
                return $this->delta_backup->capture_option_state( $option_name );

            default:
                return null;
        }
    }

    /**
     * Capture after state for an operation
     *
     * @param string $action_type Action type.
     * @param array  $params      Parameters.
     * @param array  $result      Operation result.
     * @return array|null
     */
    private function capture_after_state( string $action_type, array $params, array $result ): ?array {
        $data = $result['data'] ?? [];

        switch ( $action_type ) {
            case 'create_post':
            case 'create_page':
                $post_id = $data['post_id'] ?? 0;
                return $post_id ? $this->delta_backup->capture_post_state( $post_id ) : null;

            case 'update_post':
            case 'update_page':
                $post_id = $params['post_id'] ?? 0;
                return $post_id ? $this->delta_backup->capture_post_state( $post_id ) : null;

            case 'delete_post':
                return [ 'deleted' => true, 'post_id' => $params['post_id'] ?? 0 ];

            case 'update_meta':
                return [
                    'object_id'  => $params['object_id'] ?? 0,
                    'meta_key'   => $params['meta_key'] ?? '',
                    'meta_value' => $params['meta_value'] ?? '',
                ];

            case 'add_elementor_widget':
                $post_id = $params['post_id'] ?? 0;
                return $post_id ? [
                    'post_id'        => $post_id,
                    'elementor_data' => get_post_meta( $post_id, '_elementor_data', true ),
                ] : null;

            default:
                return $data;
        }
    }
}
