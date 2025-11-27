/**
 * Creator Core - Setup Wizard Scripts
 *
 * @package CreatorCore
 */

(function($) {
    'use strict';

    /**
     * Setup Wizard Manager
     */
    const SetupWizard = {
        /**
         * Initialize wizard
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Navigation buttons (using IDs from template)
            $('#next-step-btn').on('click', this.nextStep.bind(this));
            $('#skip-setup-btn').on('click', this.skipSetup.bind(this));

            // License validation
            $('#validate-license-btn').on('click', this.validateLicense.bind(this));

            // Plugin installation
            $(document).on('click', '.creator-install-plugin', this.installPlugin.bind(this));
            $(document).on('click', '.creator-activate-plugin', this.activatePlugin.bind(this));
        },

        /**
         * Go to next step (server-side navigation)
         */
        nextStep: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);

            console.log('Next button clicked');
            console.log('creatorSetupData:', typeof creatorSetupData !== 'undefined' ? creatorSetupData : 'undefined');

            // Get next URL from inline script variable
            if (typeof creatorSetupData !== 'undefined' && creatorSetupData.nextUrl) {
                const nextUrl = creatorSetupData.nextUrl;
                console.log('Navigating to:', nextUrl);

                $btn.prop('disabled', true);
                $btn.html('Loading... <span class="dashicons dashicons-update creator-spin"></span>');

                // Use direct navigation
                window.location.assign(nextUrl);
            } else {
                console.error('creatorSetupData.nextUrl not defined');
                alert('Error: Unable to navigate to next step. Please refresh the page.');
            }
        },

        /**
         * Skip setup and go to dashboard
         */
        skipSetup: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text('Redirecting...');

            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_skip_setup',
                    nonce: creatorSetup.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url || creatorSetup.dashboardUrl;
                    } else {
                        alert('Failed to skip setup: ' + (response.data?.message || 'Unknown error'));
                        $btn.prop('disabled', false).text('Skip Setup');
                    }
                },
                error: function() {
                    // Still redirect on error
                    window.location.href = creatorSetup.dashboardUrl;
                }
            });
        },

        /**
         * Validate license key
         */
        validateLicense: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $input = $('#license-key');
            const $status = $('#license-status');
            const licenseKey = $input.val().trim();

            if (!licenseKey) {
                $status.html('<span class="creator-status-error"><span class="dashicons dashicons-warning"></span> Please enter a license key</span>');
                return;
            }

            // Show loading
            $btn.prop('disabled', true);
            $status.html('<span class="creator-pulse">Validating...</span>');

            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_validate_license',
                    nonce: creatorSetup.nonce,
                    license_key: licenseKey
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="creator-status-ok"><span class="dashicons dashicons-yes"></span> License valid</span>');
                        $input.prop('readonly', true);
                        $btn.text('Validated').prop('disabled', true);
                    } else {
                        $status.html('<span class="creator-status-error"><span class="dashicons dashicons-no"></span> ' + (response.data?.message || 'Invalid license') + '</span>');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.html('<span class="creator-status-error"><span class="dashicons dashicons-warning"></span> Validation failed</span>');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Install plugin
         */
        installPlugin: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const pluginSlug = $btn.data('plugin');
            const $item = $btn.closest('.creator-plugin-item');

            // Show loading
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update creator-spin"></span> Installing...');

            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_install_plugin',
                    nonce: creatorSetup.nonce,
                    plugin: pluginSlug
                },
                success: function(response) {
                    if (response.success) {
                        $btn.remove();
                        $item.find('.creator-plugin-actions').html(
                            '<span class="creator-status-ok"><span class="dashicons dashicons-yes"></span> Installed</span>'
                        );
                        $item.addClass('active');
                        $item.find('.creator-plugin-status .dashicons')
                            .removeClass('status-inactive status-warning')
                            .addClass('status-ok');
                    } else {
                        $btn.html('Install');
                        $btn.prop('disabled', false);
                        alert('Installation failed: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $btn.html('Install');
                    $btn.prop('disabled', false);
                    alert('Installation failed. Please try again.');
                }
            });
        },

        /**
         * Activate plugin
         */
        activatePlugin: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const pluginSlug = $btn.data('plugin');
            const $item = $btn.closest('.creator-plugin-item');

            // Show loading
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update creator-spin"></span> Activating...');

            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_activate_plugin',
                    nonce: creatorSetup.nonce,
                    plugin: pluginSlug
                },
                success: function(response) {
                    if (response.success) {
                        $btn.parent().html(
                            '<span class="creator-status-ok"><span class="dashicons dashicons-yes"></span> Active</span>'
                        );
                        $item.addClass('active');
                        $item.find('.creator-plugin-status .dashicons')
                            .removeClass('status-inactive status-warning')
                            .addClass('status-ok');
                    } else {
                        $btn.html('Activate');
                        $btn.prop('disabled', false);
                        alert('Activation failed: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $btn.html('Activate');
                    $btn.prop('disabled', false);
                    alert('Activation failed. Please try again.');
                }
            });
        }
    };

    /**
     * Plugin Detector - checks plugin status on page load
     */
    const PluginDetector = {
        init: function() {
            // Plugin status is rendered server-side, no need for AJAX check
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.creator-setup-wizard').length) {
            SetupWizard.init();
            PluginDetector.init();

            // Debug: log if variables are available
            if (typeof creatorSetup === 'undefined') {
                console.error('creatorSetup variable is not defined');
            }
            if (typeof creatorSetupData === 'undefined') {
                console.error('creatorSetupData variable is not defined');
            }
        }
    });

})(jQuery);
