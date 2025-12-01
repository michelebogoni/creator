<?php
/**
 * Settings Page Template
 *
 * @package CreatorCore
 * @var array $data Settings data
 */

defined( 'ABSPATH' ) || exit;

$settings_page = new \CreatorCore\Admin\Settings(
    new \CreatorCore\Integrations\ProxyClient(),
    new \CreatorCore\Integrations\PluginDetector()
);
?>
<div class="wrap creator-settings">
    <h1><?php esc_html_e( 'Creator Settings', 'creator-core' ); ?></h1>

    <?php settings_errors( 'creator_settings' ); ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'creator_save_settings', 'creator_settings_nonce' ); ?>

        <div class="creator-settings-tabs">
            <nav class="creator-tabs-nav">
                <a href="#api" class="creator-tab active" data-tab="api"><?php esc_html_e( 'API Configuration', 'creator-core' ); ?></a>
                <a href="#profile" class="creator-tab" data-tab="profile"><?php esc_html_e( 'Your Profile', 'creator-core' ); ?></a>
                <a href="#backup" class="creator-tab" data-tab="backup"><?php esc_html_e( 'Backup Settings', 'creator-core' ); ?></a>
                <a href="#integrations" class="creator-tab" data-tab="integrations"><?php esc_html_e( 'Integrations', 'creator-core' ); ?></a>
                <a href="#permissions" class="creator-tab" data-tab="permissions"><?php esc_html_e( 'Permissions', 'creator-core' ); ?></a>
                <a href="#advanced" class="creator-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'creator-core' ); ?></a>
            </nav>

            <!-- API Configuration -->
            <div id="api" class="creator-tab-content active">
                <h2><?php esc_html_e( 'API Configuration', 'creator-core' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="creator_license_key"><?php esc_html_e( 'License Key', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="creator_license_key" name="creator_license_key"
                                   value="<?php echo esc_attr( $data['settings']['license_key'] ); ?>"
                                   class="regular-text" placeholder="CREATOR-XXXX-XXXX-XXXX">
                            <button type="button" id="validate-license" class="button">
                                <?php esc_html_e( 'Validate', 'creator-core' ); ?>
                            </button>
                            <span id="license-validation-status"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Connection Status', 'creator-core' ); ?></th>
                        <td>
                            <?php if ( $data['connection']['connected'] ) : ?>
                                <span class="creator-status-badge success">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e( 'Connected', 'creator-core' ); ?>
                                </span>
                                <?php if ( ! empty( $data['connection']['admin_mode'] ) ) : ?>
                                    <span class="creator-status-badge info">
                                        <?php esc_html_e( 'Admin License', 'creator-core' ); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="creator-status-badge error">
                                    <span class="dashicons dashicons-no"></span>
                                    <?php esc_html_e( 'Not Connected', 'creator-core' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Profile Settings -->
            <div id="profile" class="creator-tab-content">
                <h2><?php esc_html_e( 'Your Competency Level', 'creator-core' ); ?></h2>
                <p><?php esc_html_e( 'This setting helps Creator communicate with you appropriately and suggest solutions that match your skill level.', 'creator-core' ); ?></p>

                <?php if ( $data['user_profile']['is_set'] ) : ?>
                    <div class="creator-current-profile">
                        <strong><?php esc_html_e( 'Current Level:', 'creator-core' ); ?></strong>
                        <span class="creator-profile-badge" id="current-profile-badge">
                            <?php
                            $current_level = $data['user_profile']['current_level'];
                            $levels_info = $data['user_profile']['levels'];
                            echo esc_html( $levels_info[ $current_level ]['label'] ?? ucfirst( $current_level ) );
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="creator-profile-options">
                    <?php foreach ( $data['user_profile']['levels'] as $level_key => $level_info ) : ?>
                        <div class="creator-profile-option-card <?php echo $data['user_profile']['current_level'] === $level_key ? 'selected' : ''; ?>">
                            <label>
                                <input type="radio" name="creator_user_level" value="<?php echo esc_attr( $level_key ); ?>"
                                       <?php checked( $data['user_profile']['current_level'], $level_key ); ?>>

                                <div class="creator-profile-card-content">
                                    <div class="creator-profile-card-header">
                                        <span class="creator-profile-level-badge level-<?php echo esc_attr( $level_key ); ?>">
                                            <?php echo esc_html( $level_info['label'] ); ?>
                                        </span>
                                        <span class="creator-profile-level-title"><?php echo esc_html( $level_info['title'] ); ?></span>
                                    </div>

                                    <p class="creator-profile-description">
                                        <?php echo esc_html( $level_info['description'] ); ?>
                                    </p>

                                    <div class="creator-profile-caps">
                                        <?php if ( ! empty( $level_info['capabilities']['can'] ) ) : ?>
                                            <?php foreach ( $level_info['capabilities']['can'] as $cap ) : ?>
                                                <span class="creator-cap-badge cap-yes">
                                                    <span class="dashicons dashicons-yes-alt"></span>
                                                    <?php echo esc_html( $cap ); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $level_info['capabilities']['cannot'] ) ) : ?>
                                            <?php foreach ( $level_info['capabilities']['cannot'] as $cap ) : ?>
                                                <span class="creator-cap-badge cap-no">
                                                    <span class="dashicons dashicons-dismiss"></span>
                                                    <?php echo esc_html( $cap ); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="creator-profile-behavior-info">
                                        <strong><?php esc_html_e( 'Creator will:', 'creator-core' ); ?></strong>
                                        <?php echo esc_html( $level_info['behavior'] ); ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="creator-profile-actions">
                    <button type="button" id="save-profile-btn" class="button button-primary">
                        <?php esc_html_e( 'Update Profile', 'creator-core' ); ?>
                    </button>
                    <span id="profile-status"></span>
                </div>
            </div>

            <!-- Backup Settings -->
            <div id="backup" class="creator-tab-content">
                <h2><?php esc_html_e( 'Backup Settings', 'creator-core' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="creator_backup_retention"><?php esc_html_e( 'Retention Period', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="creator_backup_retention" name="creator_backup_retention"
                                   value="<?php echo esc_attr( $data['settings']['backup_retention'] ); ?>"
                                   min="1" max="365" class="small-text"> <?php esc_html_e( 'days', 'creator-core' ); ?>
                            <p class="description"><?php esc_html_e( 'How long to keep backup snapshots', 'creator-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="creator_max_backup_size_mb"><?php esc_html_e( 'Maximum Size', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="creator_max_backup_size_mb" name="creator_max_backup_size_mb"
                                   value="<?php echo esc_attr( $data['settings']['max_backup_size_mb'] ); ?>"
                                   min="50" max="5000" class="small-text"> MB
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Current Usage', 'creator-core' ); ?></th>
                        <td>
                            <strong><?php echo esc_html( $data['backup_stats']['total_size_mb'] ); ?> MB</strong>
                            (<?php echo esc_html( $data['backup_stats']['total_snapshots'] ); ?> <?php esc_html_e( 'snapshots', 'creator-core' ); ?>)
                            <br>
                            <button type="button" id="cleanup-backups" class="button">
                                <?php esc_html_e( 'Cleanup Old Backups', 'creator-core' ); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Integrations -->
            <div id="integrations" class="creator-tab-content">
                <h2><?php esc_html_e( 'Integrations', 'creator-core' ); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Plugin', 'creator-core' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'creator-core' ); ?></th>
                            <th><?php esc_html_e( 'Version', 'creator-core' ); ?></th>
                            <th><?php esc_html_e( 'Features', 'creator-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $data['integrations'] as $key => $integration ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $integration['name'] ); ?></strong></td>
                                <td>
                                    <?php if ( $integration['active'] ) : ?>
                                        <span class="creator-status-badge success"><?php esc_html_e( 'Active', 'creator-core' ); ?></span>
                                    <?php elseif ( $integration['installed'] ) : ?>
                                        <span class="creator-status-badge warning"><?php esc_html_e( 'Inactive', 'creator-core' ); ?></span>
                                    <?php else : ?>
                                        <span class="creator-status-badge"><?php esc_html_e( 'Not Installed', 'creator-core' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $integration['version'] ? esc_html( $integration['version'] ) : '-'; ?></td>
                                <td><?php echo esc_html( implode( ', ', $integration['features'] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Permissions -->
            <div id="permissions" class="creator-tab-content">
                <h2><?php esc_html_e( 'User Permissions', 'creator-core' ); ?></h2>

                <p><?php esc_html_e( 'Select which roles can use Creator:', 'creator-core' ); ?></p>

                <table class="form-table">
                    <?php foreach ( $data['roles'] as $role_slug => $role ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $role['name'] ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="creator_allowed_roles[]"
                                           value="<?php echo esc_attr( $role_slug ); ?>"
                                           <?php checked( $role['enabled'] ); ?>
                                           <?php disabled( $role_slug === 'administrator' ); ?>>
                                    <?php esc_html_e( 'Can use Creator', 'creator-core' ); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Advanced -->
            <div id="advanced" class="creator-tab-content">
                <h2><?php esc_html_e( 'Advanced Settings', 'creator-core' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Debug Mode', 'creator-core' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="creator_debug_mode" value="1"
                                       <?php checked( $data['settings']['debug_mode'] ); ?>>
                                <?php esc_html_e( 'Enable debug mode', 'creator-core' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Shows additional debugging information', 'creator-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="creator_log_level"><?php esc_html_e( 'Log Level', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <select id="creator_log_level" name="creator_log_level">
                                <?php foreach ( $settings_page->get_log_levels() as $level => $label ) : ?>
                                    <option value="<?php echo esc_attr( $level ); ?>" <?php selected( $data['settings']['log_level'], $level ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Clear Cache', 'creator-core' ); ?></th>
                        <td>
                            <button type="button" id="clear-cache" class="button">
                                <?php esc_html_e( 'Clear Cache', 'creator-core' ); ?>
                            </button>
                            <span id="cache-status"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Uninstall', 'creator-core' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="creator_delete_data_on_uninstall" value="1"
                                       <?php checked( $data['settings']['delete_data_on_uninstall'] ); ?>>
                                <?php esc_html_e( 'Delete all data when uninstalling', 'creator-core' ); ?>
                            </label>
                            <p class="description creator-warning">
                                <?php esc_html_e( 'Warning: This will permanently delete all Creator data including chats and backups.', 'creator-core' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button( __( 'Save Settings', 'creator-core' ) ); ?>
    </form>
</div>
