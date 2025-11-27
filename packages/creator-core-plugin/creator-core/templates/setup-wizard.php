<?php
/**
 * Setup Wizard Template
 *
 * @package CreatorCore
 * @var string $current_step Current step
 * @var array $steps All steps
 * @var array $step_data Data for current step
 */

defined( 'ABSPATH' ) || exit;

$setup_wizard = new \CreatorCore\Admin\SetupWizard( new \CreatorCore\Integrations\PluginDetector() );
?>
<div class="creator-setup-wizard">
    <div class="creator-setup-header">
        <div class="creator-setup-logo">
            <span class="dashicons dashicons-superhero-alt"></span>
            <span class="creator-setup-title"><?php esc_html_e( 'Creator Core', 'creator-core' ); ?></span>
        </div>
    </div>

    <!-- Progress Steps -->
    <div class="creator-setup-steps">
        <?php foreach ( $data['steps'] as $step_key => $step ) : ?>
            <?php
            $is_current   = $step_key === $data['current_step'];
            $step_number  = $step['order'];
            $current_order = $data['steps'][ $data['current_step'] ]['order'];
            $is_completed = $step_number < $current_order;
            ?>
            <div class="creator-setup-step <?php echo $is_current ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
                <span class="step-number">
                    <?php if ( $is_completed ) : ?>
                        <span class="dashicons dashicons-yes"></span>
                    <?php else : ?>
                        <?php echo esc_html( $step_number ); ?>
                    <?php endif; ?>
                </span>
                <span class="step-name"><?php echo esc_html( $step['name'] ); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Step Content -->
    <div class="creator-setup-content">
        <?php
        switch ( $data['current_step'] ) :
            case 'dependencies':
                ?>
                <div class="creator-setup-section">
                    <h2><?php esc_html_e( 'Plugin Dependencies', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Creator works best with these plugins. Required plugins must be installed.', 'creator-core' ); ?></p>

                    <div class="creator-plugin-list">
                        <h3><?php esc_html_e( 'Required Plugins', 'creator-core' ); ?></h3>
                        <?php foreach ( $data['step_data']['required'] as $key => $plugin ) : ?>
                            <?php include CREATOR_CORE_PATH . 'templates/plugin-detector.php'; ?>
                        <?php endforeach; ?>

                        <h3><?php esc_html_e( 'Recommended Plugins', 'creator-core' ); ?></h3>
                        <?php foreach ( $data['step_data']['optional'] as $key => $plugin ) : ?>
                            <?php include CREATOR_CORE_PATH . 'templates/plugin-detector.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                break;

            case 'backup':
                ?>
                <div class="creator-setup-section">
                    <h2><?php esc_html_e( 'Configure Backup', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Creator automatically creates backups before making changes. Configure your backup preferences.', 'creator-core' ); ?></p>

                    <form id="creator-backup-form" class="creator-setup-form">
                        <div class="creator-form-row">
                            <label for="backup-path"><?php esc_html_e( 'Backup Location', 'creator-core' ); ?></label>
                            <input type="text" id="backup-path" value="<?php echo esc_attr( $data['step_data']['backup_path'] ); ?>" readonly>
                            <?php if ( $data['step_data']['path_writable'] ) : ?>
                                <span class="creator-status-ok">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e( 'Writable', 'creator-core' ); ?>
                                </span>
                            <?php else : ?>
                                <span class="creator-status-error">
                                    <span class="dashicons dashicons-no"></span>
                                    <?php esc_html_e( 'Not writable', 'creator-core' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="creator-form-row">
                            <label for="retention-days"><?php esc_html_e( 'Retention Period (days)', 'creator-core' ); ?></label>
                            <input type="number" id="retention-days" name="retention_days"
                                   value="<?php echo esc_attr( $data['step_data']['retention_days'] ); ?>" min="1" max="365">
                            <p class="description"><?php esc_html_e( 'How long to keep backup snapshots', 'creator-core' ); ?></p>
                        </div>

                        <div class="creator-form-row">
                            <label for="max-size"><?php esc_html_e( 'Maximum Size (MB)', 'creator-core' ); ?></label>
                            <input type="number" id="max-size" name="max_size_mb"
                                   value="<?php echo esc_attr( $data['step_data']['max_size_mb'] ); ?>" min="50" max="5000">
                            <p class="description"><?php esc_html_e( 'Maximum total backup storage', 'creator-core' ); ?></p>
                        </div>
                    </form>
                </div>
                <?php
                break;

            case 'license':
                ?>
                <div class="creator-setup-section">
                    <h2><?php esc_html_e( 'License Activation', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Enter your license key to activate Creator. This connects to our AI services.', 'creator-core' ); ?></p>

                    <?php if ( $data['step_data']['mock_mode'] ) : ?>
                        <div class="creator-notice creator-notice-info">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e( 'Mock Mode is enabled. You can skip license validation for testing.', 'creator-core' ); ?>
                        </div>
                    <?php endif; ?>

                    <form id="creator-license-form" class="creator-setup-form">
                        <div class="creator-form-row">
                            <label for="license-key"><?php esc_html_e( 'License Key', 'creator-core' ); ?></label>
                            <input type="text" id="license-key" name="license_key"
                                   value="<?php echo esc_attr( $data['step_data']['license_key'] ); ?>"
                                   placeholder="CREATOR-XXXX-XXXX-XXXX">

                            <?php if ( $data['step_data']['is_validated'] ) : ?>
                                <span class="creator-status-ok">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e( 'Valid', 'creator-core' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="creator-form-row">
                            <button type="button" id="validate-license-btn" class="creator-btn creator-btn-secondary">
                                <?php esc_html_e( 'Validate License', 'creator-core' ); ?>
                            </button>
                            <span id="license-status" class="creator-inline-status"></span>
                        </div>
                    </form>
                </div>
                <?php
                break;

            case 'finish':
                ?>
                <div class="creator-setup-section creator-setup-finish">
                    <div class="creator-finish-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <h2><?php esc_html_e( 'You\'re All Set!', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Creator is ready to help you build amazing WordPress sites.', 'creator-core' ); ?></p>

                    <div class="creator-finish-features">
                        <h3><?php esc_html_e( 'Active Features', 'creator-core' ); ?></h3>
                        <ul class="creator-feature-list">
                            <?php foreach ( $data['step_data']['features'] as $feature ) : ?>
                                <li>
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $feature ) ) ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="creator-finish-actions">
                        <a href="<?php echo esc_url( $data['step_data']['chat_url'] ); ?>" class="creator-btn creator-btn-primary creator-btn-lg">
                            <span class="dashicons dashicons-format-chat"></span>
                            <?php esc_html_e( 'Start Your First Chat', 'creator-core' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $data['step_data']['dashboard_url'] ); ?>" class="creator-btn creator-btn-outline">
                            <?php esc_html_e( 'Go to Dashboard', 'creator-core' ); ?>
                        </a>
                    </div>
                </div>
                <?php
                break;
        endswitch;
        ?>
    </div>

    <!-- Navigation -->
    <div class="creator-setup-nav">
        <?php $prev_url = $setup_wizard->get_previous_step_url( $data['current_step'] ); ?>
        <?php if ( $prev_url ) : ?>
            <a href="<?php echo esc_url( $prev_url ); ?>" class="creator-btn creator-btn-outline">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php esc_html_e( 'Back', 'creator-core' ); ?>
            </a>
        <?php else : ?>
            <div></div>
        <?php endif; ?>

        <?php if ( $data['current_step'] !== 'finish' ) : ?>
            <div class="creator-nav-right">
                <button type="button" id="skip-setup-btn" class="creator-btn creator-btn-link">
                    <?php esc_html_e( 'Skip Setup', 'creator-core' ); ?>
                </button>
                <a href="<?php echo esc_url( $setup_wizard->get_next_step_url( $data['current_step'] ) ); ?>" id="next-step-btn" class="creator-btn creator-btn-primary">
                    <?php esc_html_e( 'Continue', 'creator-core' ); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    var creatorSetupData = {
        currentStep: '<?php echo esc_js( $data['current_step'] ); ?>',
        nextUrl: '<?php echo esc_js( $setup_wizard->get_next_step_url( $data['current_step'] ) ); ?>'
    };
    console.log('Creator Setup Data:', creatorSetupData);
</script>
