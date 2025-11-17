<?php

// bunny.net WordPress Plugin
// Copyright (C) 2024-2025 BunnyWay d.o.o.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
declare(strict_types=1);

namespace Bunny\Wordpress\Admin\Controller;

use Bunny\Wordpress\Admin\Container;

class Wizard implements ControllerInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function run(bool $isAjax): void
    {
        if ('1' === get_option('bunnycdn_wizard_finished') && (!isset($_GET['step']) || '3' !== $_GET['step'])) {
            $this->container->redirectToSection('overview');

            return;
        }
        $step = 1;
        if (isset($_GET['step'])) {
            $step = (int) sanitize_key($_GET['step']);
        }
        switch ($step) {
            case 1:
                $this->step1();
                break;
            case 2:
                $this->step2();
                break;
            case 3:
                $this->step3();
                break;
            default:
                $wizardUrl = $this->container->getSectionUrl('wizard');
                $this->container->renderTemplateFile('wizard.error.php', ['error' => 'Invalid wizard step.', 'wizardUrl' => $wizardUrl], ['cssClass' => 'wizard error'], '_base.wizard.php');
                break;
        }
    }

    private function step1(): void
    {
        $continueUrlSafe = $this->container->getSectionUrl('wizard', ['step' => 2]);
        $agencyModeUrlSafe = $this->container->getSectionUrl('wizard', ['step' => 2, 'mode' => 'agency']);
        $this->container->renderTemplateFile('wizard.1.php', ['continueUrlSafe' => $continueUrlSafe, 'agencyModeUrlSafe' => $agencyModeUrlSafe], ['cssClass' => 'wizard', 'step' => 1], '_base.wizard.php');
    }

    private function step2(): void
    {
        // Simplified wizard - no CDN/Pullzone creation, just URL confirmation
        $url = site_url();
        $mode = 'standalone';
        if ('agency' === ($_GET['mode'] ?? 'standalone') || 'agency' === ($_POST['mode'] ?? 'standalone')) {
            $mode = 'agency';
        }
        $backUrlSafe = $this->container->getSectionUrl('wizard');
        $formUrlSafe = $this->container->getSectionUrl('wizard', ['step' => 2, 'mode' => $mode]);

        if (!empty($_POST)) {
            check_admin_referer('bunnycdn-save-wizard-step2');
            if (empty($_POST['url']) || empty($_POST['mode'])) {
                $this->container->renderTemplateFile('wizard.error.php', ['error' => 'Invalid data provided.', 'wizardUrl' => $formUrlSafe], ['cssClass' => 'wizard error'], '_base.wizard.php');

                return;
            }
            $url = strlen($_POST['url']) > 0 ? esc_url_raw(trim($_POST['url'])) : $url;
            $url = $this->container->getWizardUtils()->normalizeUrl($url);

            // Save wizard configuration (no CDN config - that's managed on Bunny.net dashboard)
            update_option('_bunnycdn_migrated_excluded_extensions', true);
            update_option('bunnycdn_wizard_mode', $mode);
            update_option('bunnycdn_wizard_finished', '1', true);
            delete_option('_bunnycdn_migration_warning');
            
            if ('agency' === $mode) {
                delete_option('bunnycdn_api_key');
                delete_option('bunnycdn_api_user');
            }
            
            $this->container->redirectToSection('wizard', ['step' => 3]);

            return;
        }
        
        $this->container->renderTemplateFile('wizard.2.php', ['formUrlSafe' => $formUrlSafe, 'url' => $url, 'backUrlSafe' => $backUrlSafe, 'mode' => $mode, 'error' => null], ['cssClass' => 'wizard', 'step' => 2], '_base.wizard.php');
    }

    private function step3(): void
    {
        $overviewUrlSafe = $this->container->getSectionUrl('index');
        $this->container->renderTemplateFile('wizard.3.php', ['overviewUrlSafe' => $overviewUrlSafe], ['cssClass' => 'wizard', 'step' => 3], '_base.wizard.php');
    }

    // step2CreatePullzone() removed - Pullzone creation now handled on Bunny.net dashboard
    // Plugin focuses on Storage Zone setup for media offloading
}
