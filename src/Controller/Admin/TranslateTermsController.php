<?php
namespace Translate\Controller\Admin;

use Translate\Controller\TranslateAppController;

/**
 * TranslateTerms Controller
 *
 * @property \Translate\Model\Table\TranslateTermsTable $TranslateTerms
 */
class TranslateTermsController extends TranslateAppController {

	/**
	 * @var array
	 */
	public $paginate = ['order' => ['TranslateTerms.modified' => 'DESC']];

	/**
	 * @return void
	 */
	public function initialize() {
		parent::initialize();
		$this->loadComponent('Search.Prg', [
			'actions' => ['index'],
			'emptyValues' => [
			],
		]);
	}

	/**
	 * Index method
	 *
	 * @return \Cake\Http\Response|null
	 */
	public function index() {
		$this->paginate = [
			'contain' => ['TranslateStrings', 'TranslateLanguages']
		];

		$query = $this->TranslateTerms->find('search', ['search' => $this->request->getQuery()]);
		$translateTerms = $this->paginate($query);

		$this->set(compact('translateTerms'));
		$this->set('_serialize', ['translateTerms']);
	}

	/**
	 * View method
	 *
	 * @param string|null $id Translate Term id.
	 * @return \Cake\Http\Response|null
	 * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
	 */
	public function view($id = null) {
		$translateTerm = $this->TranslateTerms->get($id, [
			'contain' => ['TranslateStrings', 'TranslateLanguages']
		]);

		$this->set(compact('translateTerm'));
		$this->set('_serialize', ['translateTerm']);
	}

	/**
	 * Edit method
	 *
	 * @param string|null $id Translate Term id.
	 * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
	 * @throws \Cake\Network\Exception\NotFoundException When record not found.
	 */
	public function edit($id = null) {
		$translateTerm = $this->TranslateTerms->get($id, [
			'contain' => []
		]);
		if ($this->request->is(['patch', 'post', 'put'])) {
			$translateTerm = $this->TranslateTerms->patchEntity($translateTerm, $this->request->getData());
			if ($this->TranslateTerms->save($translateTerm)) {
				$this->Flash->success(__d('translate', 'The translate term has been saved.'));
				return $this->redirect(['action' => 'index']);
			}

			$this->Flash->error(__d('translate', 'The translate term could not be saved. Please, try again.'));
		}
		$translateStrings = $this->TranslateTerms->TranslateStrings->find('list', ['limit' => 200]);
		$translateLanguages = $this->TranslateTerms->TranslateLanguages->find('list', ['limit' => 200]);

		$this->set(compact('translateTerm', 'translateStrings', 'translateLanguages'));
		$this->set('_serialize', ['translateTerm']);
	}

	/**
	 * Delete method
	 *
	 * @param string|null $id Translate Term id.
	 * @return \Cake\Http\Response|null Redirects to index.
	 * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
	 */
	public function delete($id = null) {
		$this->request->allowMethod(['post', 'delete']);
		$translateTerm = $this->TranslateTerms->get($id);
		if ($this->TranslateTerms->delete($translateTerm)) {
			$this->Flash->success(__d('translate', 'The translate term has been deleted.'));
		} else {
			$this->Flash->error(__d('translate', 'The translate term could not be deleted. Please, try again.'));
		}
		return $this->redirect(['action' => 'index']);
	}

}
