<?php
namespace Translate\Shell;

use Cake\Console\Shell;

/**
 * @property \Translate\Model\Table\TranslateStringsTable $TranslateStrings
 */
class TranslateShell extends Shell {

	/**
	 * @var string
	 */
	public $modelClass = 'Translate.TranslateStrings';

	/**
	 * Output some basic usage Info.
	 *
	 * @return void
	 */
	public function help() {
		$this->out('CakePHP Translate Plugin:');
		$this->out();

		$this->out('Run `bin/cake i18n extract` first to create POT files.');
		$this->out('Import them then via `bin/cake translate import`.');
		$this->out('When done translating, you can export them via `bin/cake translate export`.');

		$this->hr();
	}

	/**
	 * @return void
	 */
	public function import() {
		$this->out('TODO - Done via controller right now.');
	}

	/**
	 * @return void
	 */
	public function export() {
		$this->out('TODO - Done via controller right now.');
	}

	/**
	 * Display help for this console.
	 *
	 * @return \Cake\Console\ConsoleOptionParser
	 */
	public function getOptionParser() {
		$consoleOptionParser = parent::getOptionParser();

		$consoleOptionParser->addSubcommand('import', [
			'help' => 'Import from POT files.',
		]);
		$consoleOptionParser->addSubcommand('export', [
			'help' => 'Export into PO files.',
		]);

		$consoleOptionParser->addSubcommand('help', [
			'help' => 'Display some help.',
		]);

		return $consoleOptionParser;
	}

}
