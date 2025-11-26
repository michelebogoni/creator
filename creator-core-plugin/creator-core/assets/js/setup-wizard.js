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
        currentStep: 1,
        totalSteps: 4,

        /**
         * Initialize wizard
         */
        init: function() {
            this.currentStep = parseInt(creatorSetup.currentStep) || 1;
            this.bindEvents();
            this.updateStepIndicators();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Navigation buttons
            $('.creator-wizard-prev').on('click', this.prevStep.bind(this));
            $('.creator-wizard-next').on('click', this.nextStep.bind(this));
            $('.creator-wizard-skip').on('click', this.skipStep.bind(this));

            // License validation
            $('#validate-license-btn').on('click', this.validateLicense.bind(this));

            // Plugin installation
            $('.creator-install-plugin').on('click', this.installPlugin.bind(this));

            // Complete wizard
            $('.creator-wizard-complete').on('click', this.completeWizard.bind(this));
        },

        /**
         * Go to previous step
         */
        prevStep: function(e) {
            e.preventDefault();

            if (this.currentStep > 1) {
                this.goToStep(this.currentStep - 1);
            }
        },

        /**
         * Go to next step
         */
        nextStep: function(e) {
            e.preventDefault();

            // Validate current step before proceeding
            if (!this.validateStep(this.currentStep)) {
                return;
            }

            if (this.currentStep < this.totalSteps) {
                this.goToStep(this.currentStep + 1);
            }
        },

        /**
         * Skip current step
         */
        skipStep: function(e) {
            e.preventDefault();

            if (this.currentStep < this.totalSteps) {
                this.goToStep(this.currentStep + 1);
            }
        },

        /**
         * Go to specific step
         */
        goToStep: function(step) {
            const self = this;

            // Hide current section
            $(`.creator-setup-section[data-step="${this.currentStep}"]`).fadeOut(200, function() {
                // Show new section
                $(`.creator-setup-section[data-step="${step}"]`).fadeIn(200);

                self.currentStep = step;
                self.updateStepIndicators();
                self.updateNavigation();

                // Save progress
                self.saveProgress();
            });
        },

        /**
         * Update step indicators
         */
        updateStepIndicators: function() {
            const self = this;

            $('.creator-setup-step').each(function(index) {
                const stepNum = index + 1;
                const $step = $(this);

                $step.removeClass('active completed');

                if (stepNum === self.currentStep) {
                    $step.addClass('active');
                } else if (stepNum < self.currentStep) {
                    $step.addClass('completed');
                }
            });
        },

        /**
         * Update navigation buttons
         */
        updateNavigation: function() {
            const $prevBtn = $('.creator-wizard-prev');
            const $nextBtn = $('.creator-wizard-next');
            const $skipBtn = $('.creator-wizard-skip');
            const $completeBtn = $('.creator-wizard-complete');

            // Show/hide prev button
            $prevBtn.toggle(this.currentStep > 1);

            // Show/hide next/complete button
            if (this.currentStep === this.totalSteps) {
                $nextBtn.hide();
                $skipBtn.hide();
                $completeBtn.show();
            } else {
                $nextBtn.show();
                $skipBtn.show();
                $completeBtn.hide();
            }
        },

        /**
         * Validate current step
         */
        validateStep: function(step) {
            switch (step) {
                case 1:
                    // License validation (optional)
                    return true;

                case 2:
                    // Plugins step (optional)
                    return true;

                case 3:
                    // Configuration step
                    return this.validateConfiguration();

                default:
                    return true;
            }
        },

        /**
         * Validate configuration step
         */
        validateConfiguration: function() {
            // Add any required configuration validation
            return true;
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

                        // Mark step as complete
                        $('.creator-setup-step[data-step="1"]').addClass('completed');
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
            const $status = $item.find('.creator-plugin-status-text');

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
                        $status.html('<span class="installed"><span class="dashicons dashicons-yes"></span> Installed</span>');
                        $item.addClass('active');

                        // Update status icon
                        $item.find('.creator-plugin-status .dashicons')
                            .removeClass('status-inactive')
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
         * Save progress
         */
        saveProgress: function() {
            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_save_wizard_progress',
                    nonce: creatorSetup.nonce,
                    step: this.currentStep
                }
            });
        },

        /**
         * Complete wizard
         */
        completeWizard: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);

            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update creator-spin"></span> Completing...');

            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_complete_wizard',
                    nonce: creatorSetup.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to dashboard
                        window.location.href = response.data.redirect || creatorSetup.dashboardUrl;
                    } else {
                        alert('Failed to complete setup: ' + (response.data?.message || 'Unknown error'));
                        $btn.prop('disabled', false);
                        $btn.html('Complete Setup');
                    }
                },
                error: function() {
                    alert('Failed to complete setup. Please try again.');
                    $btn.prop('disabled', false);
                    $btn.html('Complete Setup');
                }
            });
        }
    };

    /**
     * Plugin Detector
     */
    const PluginDetector = {
        /**
         * Initialize detector
         */
        init: function() {
            this.checkPlugins();
        },

        /**
         * Check plugin statuses
         */
        checkPlugins: function() {
            const $list = $('.creator-plugin-list');

            if ($list.length === 0) {
                return;
            }

            $.ajax({
                url: creatorSetup.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'creator_check_plugins',
                    nonce: creatorSetup.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        PluginDetector.updatePluginStatus(response.data);
                    }
                }
            });
        },

        /**
         * Update plugin status in UI
         */
        updatePluginStatus: function(plugins) {
            Object.keys(plugins).forEach(function(slug) {
                const plugin = plugins[slug];
                const $item = $(`.creator-plugin-item[data-plugin="${slug}"]`);

                if ($item.length) {
                    const $status = $item.find('.creator-plugin-status .dashicons');
                    const $actions = $item.find('.creator-plugin-actions');

                    $status.removeClass('status-ok status-warning status-error status-inactive');

                    if (plugin.active) {
                        $status.addClass('status-ok');
                        $item.addClass('active');
                        $actions.html('<span class="creator-plugin-status-text installed"><span class="dashicons dashicons-yes"></span> Active</span>');
                    } else if (plugin.installed) {
                        $status.addClass('status-warning');
                        $actions.html(`
                            <span class="creator-plugin-warning">Installed but not active</span>
                            <button class="creator-btn creator-btn-sm creator-btn-primary creator-activate-plugin" data-plugin="${slug}">
                                Activate
                            </button>
                        `);
                    } else {
                        $status.addClass('status-inactive');
                        $actions.html(`
                            <button class="creator-btn creator-btn-sm creator-btn-secondary creator-install-plugin" data-plugin="${slug}">
                                Install
                            </button>
                        `);
                    }

                    if (plugin.version) {
                        $item.find('.creator-plugin-version').text('v' + plugin.version);
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.creator-setup-wizard').length && typeof creatorSetup !== 'undefined') {
            SetupWizard.init();
            PluginDetector.init();
        }
    });

})(jQuery);
