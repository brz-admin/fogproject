<?php
/**
 * Add GLPI type registration.
 *
 * PHP version 5
 *
 * @category AddGLPIType
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Add GLPI type registration.
 *
 * @category AddGLPIType
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddGLPIType extends Hook
{
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddGLPIType';
    /**
     * Description of the hook.
     *
     * @var string
     */
    public $description = 'Add GLPI registration to system';
    /**
     * Active or not?
     *
     * @var bool
     */
    public $active = true;
    /**
     * Node to work with.
     *
     * @var string
     */
    public $node = 'glpi';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'MENU_DATA',
                array($this, 'menuData')
            )
            ->register(
                'SUB_MENUDATA',
                array($this, 'subMenuData')
            );
    }
    /**
     * Create menu data.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function menuData($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
    }
    /**
     * Sub menu data.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function subMenuData($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
    }
}