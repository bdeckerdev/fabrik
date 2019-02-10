<?php
/**
 * Fabrik Timeline Viz Controller
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.visualization.timeline
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

namespace Fabrik\Plugin\FabrikVisualization\Timeline\Controller;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Component\Fabrik\Site\Controller\VisualizationController;

/**
 * Fabrik Time line Viz Controller
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.visualization.timeline
 * @since       4.0
 */
class TimelineController extends VisualizationController
{
	/**
	 * Get a series of timeline events
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	public function ajax_getEvents()
	{
		$viewName = 'timeline';
		$model    = $this->getModel($viewName);
		$id       = $this->input->getInt('visualizationid', 0);
		$model->setId($id);
		$model->onAjax_getEvents();
	}
}
