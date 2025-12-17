<?php
/**
 * Add GLPI menu item.
 *
 * PHP version 5
 *
 * @category AddGLPIMenuItem
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Add GLPI menu item.
 *
 * @category AddGLPIMenuItem
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddGLPIMenuItem extends Hook
{
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddGLPIMenuItem';
    /**
     * Description of the hook.
     *
     * @var string
     */
    public $description = 'Add menu item for GLPI Integration';
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
                'MAIN_MENU_DATA',
                array($this, 'menuData')
            )
            ->register(
                'SEARCH_PAGES',
                array($this, 'addSearch')
            )
            ->register(
                'PAGES_WITH_OBJECTS',
                array($this, 'addPageWithObject')
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
        self::arrayInsertAfter(
            'task',
            $arguments['main'],
            $this->node,
            array(
                'GLPI Integration',
                'fa fa-database'
            )
        );
    }
    /**
     * Add page with objects.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        array_push($arguments['PagesWithObjects'], $this->node);
    }
    /**
     * Add search for this item.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        array_push($arguments['searchPages'], $this->node);
    }
}