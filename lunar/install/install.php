<?php
defined('_JEXEC') or die('Restricted access');


use Joomla\CMS\Factory;

/**
 * 
 */
class plgVmPaymentLunarInstallerScript
{
	private $db;
	private $mobilePayName;

	public function __construct()
	{
		$this->db = Factory::getDbo();
		$this->mobilePayName = 'lunar_mobilepay';
	}

	public function uninstall($parent){}
	public function postflight($type, $parent){}

	public function preflight($type, $parent)
	{
		if (!class_exists( 'VmConfig' )) {
			require(JPATH_ROOT .'/administrator/components/com_virtuemart/helpers/config.php');
		}

		VmConfig::loadConfig();	
	}

	public function install($parent)
	{
		if(!class_exists('GenericTableUpdater')) {
			require(VMPATH_ADMIN .'/helpers/tableupdater.php');
		}

		$updater = new GenericTableUpdater();

		$updater->updateMyVmTables(dirname(__FILE__) .'/install.sql');

		$this->maybeInstallMobilePayMethod();
	}

	/** */
	private function maybeInstallMobilePayMethod()
	{
		$methodInstalled = $this->db->setQuery(
				$this->db->getQuery(true)
					->select('COUNT(*)')
					->from('#__extensions')
					->where('element = ' . $this->db->quote($this->mobilePayName))
			)->loadResult();

		$manifestFile = JPATH_VM_PLUGINS . DS . 'vmpayment' . DS . $this->mobilePayName . DS . $this->mobilePayName . '.xml';
		$manifest = simplexml_load_file($manifestFile);

		$manifestData = [
			'name' => (string) $manifest->name,
			'type' => 'plugin',
			'description' => (string) $manifest->description,
			'creationDate' => (string) $manifest->creationDate,
			'author' => (string) $manifest->author,
			'copyright' => (string) $manifest->copyright,
			'authorEmail' => (string) $manifest->authorEmail,
			'authorUrl' => (string) $manifest->authorUrl,
			'group' => '',
			'filename' => $this->mobilePayName,

		];

		if ((int) $methodInstalled > 0) {
			$this->updateMobilePayMethod($manifestData);

		} else {
			$this->insertMobilePayMethod($manifestData);
		}
	}

	/** */
	private function updateMobilePayMethod($manifestData)
	{
		$this->db->setQuery(
			$this->db->getQuery(true)
				->update('#__extensions')
				->set(['manifest_cache = ' . $this->db->quote(json_encode($manifestData))])
				->where('element=' . $this->db->quote($this->mobilePayName))
		)->execute();
	}

	/** */
	private function insertMobilePayMethod($manifestData)
	{
		$this->db->setQuery(
			$this->db->getQuery(true)
				->insert('#__extensions')
				->set([
					'name = ' . $this->db->quote($manifestData['name']), 
					'type = ' . $this->db->quote('plugin'), 
					'element = ' . $this->db->quote($this->mobilePayName), 
					'folder = ' . $this->db->quote('vmpayment'), 
					'client_id = 0', 
					'manifest_cache = ' . $this->db->quote(json_encode($manifestData)), 
					'params = ' . $this->db->quote('{}'), 
					'custom_data = ""', 
					'system_data = ""',
			])
		)->execute();
	}
	
}