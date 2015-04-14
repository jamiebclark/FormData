<?php
App::uses('Inflector', 'Utility');

class SluggableBehavior extends ModelBehavior {
	public $name = 'Sluggable';

	private $slugField = 'slug';

	public function beforeFind(Model $Model, $query = array()) {
		if (!empty($query['slug'])) {
			if (is_numeric($query['slug'])) {
				$query['conditions'][$Model->primaryKey] = $query['slug'];
			} else {
				$query['conditions'][$Model->escapeField($this->slugField)] = $query['slug'];
			}
			unset($query['slug']);
			return $query;
		}
		return parent::beforeFind($Model, $query);
	}

	public function beforeSave(Model $Model, $options = array()) {
		if (!empty($Model->data[$Model->alias][$Model->displayField])) {
			$Model->data[$Model->alias][$this->slugField] = $this->inflectSlug($Model->data[$Model->alias][$Model->displayField]);
		}
		return parent::beforeSave($Model, $options);
	}

/**
 * Public method to retrieve the sluggable field
 *
 * @return string The slug field
 **/
	public function getSluggableField() {
		return $this->slugField;
	}

/**
 * Sets the slug of a row based on the displayField
 *
 * @param Model $Model The model obejct
 * @param int $id The id of the specified row
 * @return bool on success;
 **/
	public function setSlug(Model $Model, $id) {
		$result = $Model->read(array($Model->displayField), $id);
		return $Model->save(array(
			$Model->primaryKey => $id,
			$this->slugField => $this->inflectSlug($result[$Model->alias][$Model->displayField]),
		), array('callbacks' => false, 'validate' => false));
	}

/**
 * Sets all the slugs of any rows with blank slugs
 *
 * @param Model $Model The model obejct
 * @return int The number of affected rows
 **/
	public function resetAllSlugs(Model $Model) {
		$result = $Model->find('all', array(
			'fields' => array($Model->escapeField()),
			'conditions' => array(
				$Model->escapeField($this->slugField) => array('', null),
			)
		));
		foreach ($result as $row) {
			$Model->create();
			$this->setSlug($Model, $row[$Model->alias][$Model->primaryKey]);
		}
		return count($result);
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