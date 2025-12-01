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
            // Note: Next step button is now a direct link (<a> tag), no JS needed

            // License validation
            $('#validate-license-btn').on('click', this.validateLicense.bind(this));

            // Plugin installation
            $(document).on('click', '.creator-install-plugin', this.installPlugin.bind(this));
            $(document).on('click', '.creator-activate-plugin', this.activatePlugin.bind(this));

            // Profile selection
            $('.creator-profile-option input[type="radio"]').on('change', this.handleProfileSelection.bind(this));

            // If on profile step, intercept the Continue button
            if (typeof creatorSetupData !== 'undefined' && creatorSetupData.currentStep === 'profile') {
                $('#next-step-btn').on('click', this.saveProfileAndContinue.bind(this));
            }
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
                    action: 'creator_wizard_validate_license',
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
        },

        /**
         * Handle profile selection (visual feedback)
         */
        handleProfileSelection: function(e) {
            const $radio = $(e.currentTarget);
            const $option = $radio.closest('.creator-profile-option');

            // Remove selected class from all options
            $('.creator-profile-option').removeClass('selected');

            // Add selected class to current option
            $option.addClass('selected');
        },

        /**
         * Save profile and continue to next step
         */
        saveProfileAndContinue: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const selectedLevel = $('input[name="user_level"]:checked').val();

            // Get the next URL from the button's href or from creatorSetupData
            const nextUrl = $btn.attr('href') || (typeof creatorSetupData !== 'undefined' ? creatorSetupData.nextUrl : '');

            // Validate selection
            if (!selectedLevel) {
                alert('Please select your competency level before continuing.');
                return false;
            }

            // Show loading
            $btn.addClass('loading').css('pointer-events', 'none');
            $btn.html('<span class="dashicons dashicons-update creator-spin"></span> Saving...');

            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_save_user_profile',
                    nonce: creatorSetup.nonce,
                    user_level: selectedLevel
                },
                success: function(response) {
                    if (response.success) {
                        // Navigate to next step using the stored URL
                        if (nextUrl) {
                            window.location.href = nextUrl;
                        } else {
                            // Fallback: construct URL manually
                            window.location.href = creatorSetup.adminUrl + 'admin.php?page=creator-setup&step=finish';
                        }
                    } else {
                        alert('Failed to save profile: ' + (response.data?.message || 'Unknown error'));
                        $btn.removeClass('loading').css('pointer-events', '');
                        $btn.html('Continue <span class="dashicons dashicons-arrow-right-alt2"></span>');
                    }
                },
                error: function() {
                    alert('Failed to save profile. Please try again.');
                    $btn.removeClass('loading').css('pointer-events', '');
                    $btn.html('Continue <span class="dashicons dashicons-arrow-right-alt2"></span>');
                }
            });

            return false;
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
