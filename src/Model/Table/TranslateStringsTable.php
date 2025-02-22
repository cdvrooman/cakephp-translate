<?php
namespace Translate\Model\Table;

use ArrayObject;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\I18n\Time;
use Cake\Log\Log;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Query;
use Tools\Model\Table\Table;
use Translate\Translator\Translator;

/**
 * @property \App\Model\Table\UsersTable|\Cake\ORM\Association\BelongsTo $Users
 * @property \Translate\Model\Table\TranslateTermsTable|\Cake\ORM\Association\HasMany $TranslateTerms
 * @property \Translate\Model\Table\TranslateDomainsTable|\Cake\ORM\Association\BelongsTo $TranslateDomains
 *
 * @method \Translate\Model\Entity\TranslateString get($primaryKey, $options = [])
 * @method \Translate\Model\Entity\TranslateString newEntity($data = null, array $options = [])
 * @method \Translate\Model\Entity\TranslateString[] newEntities(array $data, array $options = [])
 * @method \Translate\Model\Entity\TranslateString|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Translate\Model\Entity\TranslateString patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Translate\Model\Entity\TranslateString[] patchEntities($entities, array $data, array $options = [])
 * @method \Translate\Model\Entity\TranslateString findOrCreate($search, callable $callback = null, $options = [])
 * @mixin \Shim\Model\Behavior\NullableBehavior
 * @mixin \Search\Model\Behavior\SearchBehavior
 * @method \Translate\Model\Entity\TranslateString|bool saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class TranslateStringsTable extends Table {

	/**
	 * @var array
	 */
	public $order = ['name' => 'ASC'];

	/**
	 * @var array
	 */
	public $validate = [
		'name' => [
			'unique' => [
				'rule' => ['validateUnique', ['scope' => ['translate_domain_id', 'context']]],
				'provider' => 'table',
				'message' => 'This name is already in use',
			],
			'minLength' => [
				'rule' => ['minLength', 2],
				'message' => 'Should have at least 2 characters'
			],
		],
		'user_id' => [
			'notEmpty' => [
				'rule' => ['notEmpty'],
				'message' => 'valErrMandatoryField'
			],
		],
		'translate_domain_id' => [
			'numeric' => [
				'rule' => ['numeric'],
				'message' => 'valErrMandatoryField'
			],
		],
	];

	/**
	 * @var array
	 */
	public $belongsTo = [
		'User' => [
			'className' => 'User',
			'foreignKey' => 'user_id',
		],

	];

	/**
	 * @var array
	 */
	public $hasMany = [
		'TranslateTerm' => [
			'className' => 'Translate.TranslateTerm',
			'dependent' => true,
		]
	];

	/**
	 * @param \Cake\Database\Schema\TableSchema $schema
	 * @return \Cake\Database\Schema\TableSchema
	 */
	protected function _initializeSchema(TableSchema $schema) {
		$schema->setColumnType('flags', 'json');

		return $schema;
	}

	/**
	 * @param array $config
	 *
	 * @return void
	 */
	public function initialize(array $config) {
		parent::initialize($config);

		$this->addBehavior('Shim.Nullable');
		$this->addBehavior('Search.Search');
		$this->belongsTo('TranslateDomains', [
			'className' => 'Translate.TranslateDomains',
		]);
	}

	/**
	 * @param \Cake\Event\Event $event The beforeSave event that was fired
	 * @param \Translate\Model\Entity\TranslateString $entity The entity that is going to be saved
	 * @param \ArrayObject $options the options passed to the save method
	 * @return void
	 */
	public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options) {
		$user = $event->getData('_footprint');
		if ($user) {
			$entity->user_id = $user['id'];
		}
	}

	/**
	 * @return \Search\Manager
	 */
	public function searchManager() {
		$searchManager = $this->behaviors()->Search->searchManager();
		$searchManager
			->value('translate_domain_id', [
			])
			->callback('missing_translation', [
				'callback' => function (Query $query, array $args, $filter) {
					if (empty($args['missing_translation'])) {
						return false;
					}

					$query->leftJoinWith('TranslateTerms')
						->where(['TranslateTerms.content IS' => null]);

					return $query;
				},
				'filterEmpty' => 0,
			])
			->like('search', [
				'field' => [$this->aliasField('name'), 'plural', 'context'],
			]);

		return $searchManager;
	}

	/**
	 * @param int $id
	 * @param array|null $languages Languages list: [id => ...]
	 *   (defaults to ALL languages)
	 * @return array coverage
	 */
	public function coverage($id, array $languages = null) {
		$res = [];
		if ($languages === null) {
			$languages = $this->TranslateTerms->TranslateLanguages->find()
				->where(['translate_project_id' => $id])
				->find('list', ['keyField' => 'id', 'valueField' => 'locale'])->toArray();
		}

		$options = [
			//'TranslateStrings.active' => true,
			'TranslateDomains.translate_project_id' => $id
		];
		$total = $this->find()->contain(['TranslateDomains'])->where($options)->count();

		foreach ($languages as $key => $lang) {
			$options = [
				'TranslateTerms.translate_language_id' => $key,
				'TranslateTerms.content IS NOT' => null,
				//'TranslateTerms.flags' => en-not-needed
			];
			$translated = $this->TranslateTerms->find()->where($options)->count();

			$res[$lang] = $this->_coverage($total, $translated);
		}
		return $res;
	}

	/**
	 * @param int $total
	 * @param int $translated
	 *
	 * @return int
	 */
	protected function _coverage($total, $translated) {
		if ($total < 1) {
			return 0;
		}
		return (int)(($translated / $total) * 100);
	}

	/**
	 * Get next string that needs to be worked on
	 *
	 * @param int $id
	 * @param array $options
	 *
	 * @return \Cake\ORM\Query
	 */
	public function getNext($id, array $options = []) {
		$options = [
			'conditions' => [
				'TranslateStrings.id !=' => $id,
			]
		] + $options;
		$query = $this->find('all', $options);
		$query->leftJoinWith('TranslateTerms');
		$query->andWhere(['TranslateTerms.content IS' => null]);

		return $query;
	}

	/**
	 * Get next string that needs to be worked on
	 *
	 * @return \Cake\ORM\Query
	 */
	public function getUntranslated() {
		$query = $this->find();
		$query->leftJoinWith('TranslateTerms');
		$query->where(['TranslateTerms.content IS' => null])->orWhere(['TranslateStrings.plural IS NOT' => null, 'TranslateTerms.plural_2 IS' => null]);

		return $query;
	}

	/**
	 * @param int $translate_language_id
	 * @param array $translateLanguages
	 *
	 * @return string
	 */
	public function resolveLanguageKey($translate_language_id, $translateLanguages) {
		foreach ($translateLanguages as $translateLanguage) {
			if ($translateLanguage->id === $translate_language_id) {
				return $translateLanguage->iso2;
			}
		}

		throw new InternalErrorException('Language not found');
	}

	/**
	 * @param array $translation
	 * @param int $groupId
	 * @return \Translate\Model\Entity\TranslateString|null
	 */
	public function import(array $translation, $groupId) {
		if (!isset($this->lastImported)) {
			$this->lastImported = new Time();
		}

		$translation += [
			'last_imported' => $this->lastImported,
			'is_html' => $this->containsHtml($translation),
			'translate_domain_id' => $groupId,
		];

		$translateString = $this->find()->where([
			'name' => $translation['name'],
			//'plural' => isset($translation['plural']) ? $translation['plural'] : null,
			'context IS' => isset($translation['context']) ? $translation['context'] : null,
			'translate_domain_id' => $groupId,
		])->first();
		if (!$translateString) {
			$translation['active'] = true;
			$translateString = $this->newEntity($translation);
		} else {
			$translateString = $this->patchEntity($translateString, $translation);
		}

		if (!$this->save($translateString)) {
			Log::write('info', 'String `' . $translateString->name . '`: ' . print_r($translateString->errors(), true), ['scope' => 'import']);

			return null;
		}

		return $translateString;
	}

	/**
	 * @param array $translation
	 *
	 * @return bool
	 */
	protected function containsHtml(array $translation) {
		if (strpos($translation['name'], '<') !== false || strpos($translation['name'], '>') !== false) {
			return true;
		}
		if (empty($translation['plural'])) {
			return false;
		}
		if (strpos($translation['plural'], '<') !== false || strpos($translation['plural'], '>') !== false) {
			return true;
		}
		return false;
	}

	/**
	 * @param \Translate\Model\Entity\TranslateString $translateString
	 * @param \Translate\Model\Entity\TranslateLanguage[] $translateLanguages
	 * @param \Translate\Model\Entity\TranslateTerm[] $translateTerms
	 *
	 * @return array
	 */
	public function getSuggestions($translateString, array $translateLanguages, array $translateTerms) {
		$translator = new Translator();

		$baseLanguage = $this->TranslateTerms->TranslateLanguages->getBaseLanguage($translateLanguages);

		$result = [];
		foreach ($translateLanguages as $translateLanguage) {
			if ($translateLanguage->iso2 === $baseLanguage) {
				continue;
			}

			$translations = $translator->suggest($translateString->name, $translateLanguage->iso2, $baseLanguage);
			$result[$translateLanguage->iso2] = $translations;
		}

		return $result;
	}

}
