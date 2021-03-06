<?php
/**
 * Polymorphic Behavior.
 *
 * Allow the model to be associated with any other model object
 *
 * PHP versions 4 and 5
 *
 * Copyright (c) 2008, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2008, Andy Dawson
 * @link          www.ad7six.com
 * @package       mi
 * @subpackage    mi.models.behaviors
 * @since         v 0.1
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * PolymorphicBehavior class
 *
 * @uses          ModelBehavior
 * @package       mi
 * @subpackage    mi.models.behaviors
 */
class PolymorphicBehavior extends ModelBehavior {

/**
 * defaultSettings property
 *
 * @var array
 * @access protected
 */
	var $_defaultSettings = array(
		'modelField' => 'model',
		'foreignKey' => 'foreign_key',
		'accept' => null
	);

/**
 * setup method
 *
 * @param mixed $Model
 * @param array $config
 * @return void
 * @access public
 */
	function setup(&$Model, $config = array()) {
		$this->settings[$Model->alias] = am ($this->_defaultSettings, $config);
		if(!$this->settings[$Model->alias]['accept']) {
			$this->settings[$Model->alias]['accept'] = $this->__getModels();
		}
	}

/**
 * afterFind method
 *
 * @param mixed $Model
 * @param mixed $results
 * @param bool $primary
 * @access public
 * @return void
 */
	function afterFind(&$Model, $results, $primary = false) {
		extract($this->settings[$Model->alias]);
	
		if ($primary && isset($results[0][$Model->alias][$modelField]) && isset($results[0][$Model->alias][$foreignKey]) && $Model->recursive > 0) {
			foreach ($results as $key => $result) {
				$associated = array();
				$model = Inflector::classify($result[$Model->alias][$modelField]);
				//account for plugin models
				$_model = end(explode('.', $model));
				$foreignId = $result[$Model->alias][$foreignKey];
				if ($model && $foreignId && in_array($model, $accept)) {
					$this->log(array($model, $_model, $result));
					$result = $result[$Model->alias];
					if (!isset($Model->$_model)) {
						$Model->bindModel(array('belongsTo' => array(
							$_model => array(
								'conditions' => array($Model->alias . '.' . $modelField => $model),
								'foreignKey' => $foreignKey,
								'className' => $model
							)
						)));
					}
					$conditions = array($_model . '.' . $Model->$_model->primaryKey => $result[$foreignKey]);
					$recursive = 0;
					$associated = $Model->$_model->find('first', compact('conditions', 'recursive'));
					$name = $this->display($Model->$_model, $result[$foreignKey]);
					$associated[$_model]['display_field'] = $name?$name:'*missing*';
					$results[$key][$_model] = $associated[$_model];
				}
			}
		} elseif(isset($results[$Model->alias][$modelField])) {
			$associated = array();
			$model = Inflector::classify($result[$Model->alias][$modelField]);
			$foreignId = $results[$Model->alias][$foreignKey];
			if ($model && $foreignId) {
				$result = $results[$Model->alias];
				if (!isset($Model->$model)) {
					$Model->bindModel(array('belongsTo' => array(
						$model => array(
							'conditions' => array($Model->alias . '.' . $modelField => $model),
							'foreignKey' => $foreignKey
						)
					)));
				}
				$conditions = array($model . '.' . $Model->$model->primaryKey => $result[$foreignKey]);
				$recursive = -1;
				$associated = $Model->$model->find('first', compact('conditions', 'recursive'));
				$name = $this->display($model, $result[$foreignKey]);
				$associated[$model]['display_field'] = $name?$name:'*missing*';
				$results[$model] = $associated[$model];
			}
		}
		return $results;
	}

/**
 * display method
 *
 * Fall back. Assumes that find list is setup such that it returns users real names
 *
 * @param mixed $id
 * @return string
 * @access public
 */
	function display(&$Model, $id = null) {
		if (!$id) {
			if (!$Model->id) {
				return false;
			}
			$id = $Model->id;
		}
		return current($Model->find('list', array('conditions' => array($Model->alias . '.' . $Model->primaryKey => $id))));
	}
	private function __getModels() {
		$models = App::objects('model');
		$plugins = App::objects('plugin');
		if (!empty($plugins)) {
			foreach ($plugins as $plugin) {
				$pluginModels = App::objects('model', App::pluginPath($plugin) . 'models' . DS, false);
				if (!empty($pluginModels)) {
					foreach ($pluginModels as $model) {
						$models[] = "$plugin.$model";
					}
				}
			}
		}
		return $models;
	}
}
?>