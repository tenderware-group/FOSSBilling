<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of active and inactive extensions on system.
     *
     * @optional bool $installed - return installed only extensions
     * @optional bool $active - return installed and core extensions
     * @optional bool $has_settings - return extensions with configuration pages only
     * @optional string $search - filter extensions by search keyword
     * @optional string $type - filter extensions by type
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();

        return $service->getExtensionsList($data);
    }

    /**
     * Get list of extensions from extensions.fossbilling.org
     * which can be installed on current version of FOSSBilling.
     *
     * @return array - list of extensions
     */
    public function get_latest($data)
    {
        $type = $data['type'] ?? null;

        try {
            $list = $this->di['extension_manager']->getExtensionList($type);
        } catch (\Exception) {
            $list = [];
        }

        return $list;
    }

    /**
     * Get admin area navigation.
     *
     * @return array
     */
    public function get_navigation($data)
    {
        $url = null;
        if (isset($data['url'])) {
            $url = $data['url'];
        }
        $service = $this->getService();

        return $service->getAdminNavigation($this->identity, $url);
    }

    /**
     * Get list of available languages on the system.
     *
     * @return array
     */
    public function languages()
    {
        return \FOSSBilling\i18n::getLocales(true);
    }

    /**
     * Update existing extension.
     *
     * @return array
     *
     * @throws \Box_Exception
     */
    public function update($data)
    {
        $ext = $this->_getExtension($data);
        $service = $this->getService();

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminUpdateExtension', 'params' => $ext]);
        $ext2 = $service->update($ext);
        $this->di['events_manager']->fire(['event' => 'onAfterAdminUpdateExtension', 'params' => $ext2]);

        return $ext2;
    }

    /**
     * Activate existing extension.
     *
     * @return array
     *
     * @throws \Box_Exception
     */
    public function activate($data)
    {
        $required = [
            'id' => 'Extension ID was not passed',
            'type' => 'Extension type was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $result = $service->activateExistingExtension($data);

        return $result;
    }

    /**
     * Deactivate existing extension.
     *
     * @return bool - true
     *
     * @throws \Box_Exception
     */
    public function deactivate($data)
    {
        $ext = $this->_getExtension($data);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminDeactivateExtension', 'params' => ['id' => $ext->id]]);

        $service = $this->getService();
        $service->deactivate($ext);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminDeactivateExtension', 'params' => ['id' => $data['id'], 'type' => $data['type']]]);

        $this->di['logger']->info('Deactivated extension "%s"', $data['type'] . ' ' . $data['id']);

        return true;
    }

    /**
     * Completely remove extension from FOSSBilling.
     *
     * @return bool
     */
    public function uninstall($data)
    {
        $ext = $this->_getExtension($data);
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminUninstallExtension', 'params' => ['id' => $ext->id]]);

        $service = $this->getService();
        $service->uninstall($ext);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminUninstallExtension', 'params' => ['id' => $ext->id]]);

        return true;
    }

    /**
     * Install new extension from extensions site.
     *
     * @return array
     *
     * @throws \Box_Exception
     */
    public function install($data)
    {
        $required = [
            'id' => 'Extension ID was not passed',
            'type' => 'Extension type was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInstallExtension', 'params' => $data]);

        $service = $this->getService();
        $service->downloadAndExtract($data['type'], $data['id']);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInstallExtension', 'params' => $data]);

        return [
            'success' => true,
            'id' => $data['id'],
            'type' => $data['type'],
        ];
    }

    /**
     * Universal method for FOSSBilling extensions
     * to retrieve configuration from database
     * It is recommended to store your extension configuration
     * using this method. Automatic decryption is available
     * All parameters in "public" array will be accessible by guest API.
     *
     * @return array - configuration parameters
     *
     * @throws \Box_Exception
     */
    public function config_get($data)
    {
        $required = [
            'ext' => 'Parameter ext was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $config = $service->getConfig($data['ext']);

        return $config;
    }

    /**
     * Universal method for FOSSBilling extensions
     * to update or save extension configuration to database
     * Always pass all configuration parameters to this method.
     *
     * Config is automatically encrypted and stored in database
     *
     * @optional string $any - Any variable passed to this method is config parameter
     *
     * @return bool
     *
     * @throws \Box_Exception
     */
    public function config_save($data)
    {
        $required = [
            'ext' => 'Parameter ext was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->setConfig($data);
    }

    private function _getExtension($data)
    {
        $required = [
            'id' => 'Extension ID was not passed',
            'type' => 'Extension type was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $ext = $service->findExtension($data['type'], $data['id']);
        if (!$ext instanceof \Model_Extension) {
            throw new \Box_Exception('Extension not found');
        }

        return $ext;
    }
}
