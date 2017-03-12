<?php
App::uses('Inflector', 'Utility');

class SluggableBehavior extends ModelBehavior {
	public $name = 'Sluggable';

	private $slugField = 'slug';

	public function setup(Model $Model, $settings = []) {
		$default = [
			'unique' => false,
			'slugField' => 'slug',
			'displayField' => $Model->displayField,
		];

		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $default;
		}
		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], (array) $settings);
		return parent::setup($Model, $settings);
	}

	public function beforeFind(Model $Model, $query = array()) {
		$settings = $this->settings[$Model->alias];

		// Searches by either the slug or the id
		if (!empty($query['slug'])) {
			if (is_numeric($query['slug'])) {
				$query['conditions'][$Model->primaryKey] = $query['slug'];
			} else {
				$query['conditions'][$Model->escapeField($settings['slugField'])] = $query['slug'];
			}
			unset($query['slug']);
			return $query;
		}

		return parent::beforeFind($Model, $query);
	}

	public function beforeSave(Model $Model, $options = array()) {
		$settings = $this->settings[$Model->alias];
		if (!empty($Model->data[$Model->alias][$settings['displayField']])) {
			$Model->data[$Model->alias][$settings['slugField']] = $this->inflectSlug($Model->data[$Model->alias][$settings['displayField']]);
		}
		return parent::beforeSave($Model, $options);
	}

/**
 * Public method to retrieve the sluggable field
 *
 * @return string The slug field
 **/
	public function getSluggableField(Model $Model) {
		$settings = $this->settings[$Model->alias];
		return $settings['slugField'];
	}

/**
 * Sets the slug of a row based on the displayField
 *
 * @param Model $Model The model obejct
 * @param int $id The id of the specified row
 * @return bool on success;
 **/
	public function setSlug(Model $Model, $id) {
		$settings = $this->settings[$Model->alias];
		$slug = $this->getSlug($Model, $id);
		return $Model->save(array(
			$Model->primaryKey => $id,
			$settings['slugField'] => $slug,
		), array('callbacks' => false, 'validate' => false));
	}

/**
 * Sets all the slugs of any rows with blank slugs
 *
 * @param Model $Model The model obejct
 * @return int The number of affected rows
 **/
	public function resetAllSlugs(Model $Model) {
		$settings = $this->settings[$Model->alias];
		$slugField = $Model->escapeField($settings['slugField']);
		$result = $Model->find('all', array(
			'fields' => array($Model->escapeField()),
			'conditions' => [
				'OR' => [
					[$slugField => ""],
					[$slugField => null]
				]
			]
		));
		foreach ($result as $row) {
			$Model->create();
			$this->setSlug($Model, $row[$Model->alias][$Model->primaryKey]);
		}
		return count($result);
	}

/**
 * Finds a slug based on the model row values
 *
 * @param Model $Model The model obejct
 * @param int $id The id of the specified row
 * @return string The newly created slug value
 **/
	public function getSlug(Model $Model, $id) {
		$settings = $this->settings[$Model->alias];
		$result = $Model->read([$settings['displayField']], $id);
		$slug = $this->inflectSlug($result[$Model->alias][$settings['displayField']]);

		// Ensures the slug doesn't already exist
		if (!empty($settings['unique'])) {
			$slugCount = 0;
			$newSlug = $slug;
			while ($result = $Model->find('first', [
				'recursive' => -1,
				'conditions' => [
					'NOT' => [$Model->escapeField() => $id],
					$Model->escapeField($settings['slugField']) => $newSlug,
				],
				'limit' => 1,
			])) {
				$newSlug = $slug . '_' . ($slugCount++);
			}
			$slug = $newSlug;
		}
		return $slug;
	}

/**
 * Converts the text to slug format
 *
 * @param string The text to be converted
 * @return string The converted text
 **/
	protected function inflectSlug($text) {
		return Inflector::slug($text);
	}
}