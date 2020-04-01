<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Bake\View;

use Cake\Core\Configure;
use Cake\Core\ConventionsTrait;
use Cake\Core\InstanceConfigTrait;
use Cake\Core\Plugin;
use Cake\Event\EventInterface;
use WyriHaximus\TwigView\View\TwigView;

class BakeView extends TwigView
{
    use ConventionsTrait;
    use InstanceConfigTrait;

    /**
     * Folder containing bake templates.
     *
     * @var string
     */
    public const BAKE_TEMPLATE_FOLDER = 'bake';

    /**
     * Path where bake's intermediary files are written.
     * Defaults to `TMP . 'bake' . DS`.
     *
     * @var string
     */
    protected $_tmpLocation;

    /**
     * Templates extensions to search for.
     *
     * @var string[]
     */
    protected $extensions = [
        '.twig',
    ];

    /**
     * Initialize view
     *
     * @return void
     */
    public function initialize(): void
    {
        $bakeTemplates = Plugin::templatePath('Bake');
        $paths = (array)Configure::read('App.paths.templates');

        if (!in_array($bakeTemplates, $paths)) {
            $paths[] = $bakeTemplates;
            Configure::write('App.paths.templates', $paths);
        }

        $this->_tmpLocation = TMP . 'bake' . DS;
        if (!file_exists($this->_tmpLocation)) {
            mkdir($this->_tmpLocation);
        }

        Configure::write(TwigView::ENV_CONFIG, [
            'cache' => false,
        ]);

        parent::initialize();
    }

    /**
     * Renders view for given view file and layout.
     *
     * Render triggers helper callbacks, which are fired before and after the view are rendered,
     * as well as before and after the layout. The helper callbacks are called:
     *
     * - `beforeRender`
     * - `afterRender`
     *
     * View names can point to plugin views/layouts. Using the `Plugin.view` syntax
     * a plugin view/layout can be used instead of the app ones. If the chosen plugin is not found
     * the view will be located along the regular view path cascade.
     *
     * View can also be a template string, rather than the name of a view file
     *
     * @param string|null $view Name of view file to use, or a template string to render
     * @param string|false|null $layout Layout to use. Not used, for consistency with other views only
     * @return string Rendered content.
     * @throws \Cake\Core\Exception\Exception If there is an error in the view.
     */
    public function render(?string $view = null, $layout = null): string
    {
        $viewFileName = $this->_getTemplateFileName($view);
        $templateEventName = str_replace(
            ['.twig', DS],
            ['', '.'],
            explode('templates' . DS . static::BAKE_TEMPLATE_FOLDER . DS, $viewFileName)[1]
        );

        $this->_currentType = static::TYPE_TEMPLATE;
        $this->dispatchEvent('View.beforeRender', [$viewFileName]);
        $this->dispatchEvent('View.beforeRender.' . $templateEventName, [$viewFileName]);
        $this->Blocks->set('content', $this->_render($viewFileName));
        $this->dispatchEvent('View.afterRender', [$viewFileName]);
        $this->dispatchEvent('View.afterRender.' . $templateEventName, [$viewFileName]);

        if ($layout === null) {
            $layout = $this->layout;
        }
        if ($layout && $this->autoLayout) {
            $this->Blocks->set('content', $this->renderLayout('', $layout));
        }

        return $this->Blocks->get('content');
    }

    /**
     * Wrapper for creating and dispatching events.
     *
     * Use the Bake prefix for bake related view events
     *
     * @param string $name Name of the event.
     * @param mixed $data Any value you wish to be transported with this event to
     * it can be read by listeners.
     *
     * @param mixed $subject The object that this event applies to
     * ($this by default).
     * @return \Cake\Event\EventInterface
     */
    public function dispatchEvent(string $name, $data = null, $subject = null): EventInterface
    {
        $name = preg_replace('/^View\./', 'Bake.', $name);

        return parent::dispatchEvent($name, $data, $subject);
    }

    /**
     * Return all possible paths to find view files in order
     *
     * @param string $plugin Optional plugin name to scan for view files.
     * @param bool $cached Set to false to force a refresh of view paths. Default true.
     * @return array paths
     */
    protected function _paths(?string $plugin = null, bool $cached = true): array
    {
        $paths = parent::_paths($plugin, false);
        foreach ($paths as &$path) {
            $path .= static::BAKE_TEMPLATE_FOLDER . DS;
        }

        return $paths;
    }
}
