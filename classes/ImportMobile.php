<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   mobile
 * @author    Kirsten Roschanski
 * @license   LGPL
 * @copyright inszenium 2017
 */


/**
 * Namespace
 */
namespace inszenium;

use GuzzleHttp\Client;
use GuzzleHttp\Stream;

//-------------------------------------------------------------------
// AutoBackupDB.php	Backup Contao-Datenbank mit Cron-Job
//
// Copyright (c) 2007-2014 by Softleister
//
// Der Cron-Job nimmt diese Datei als Include-Datei für CronController.php
// aktueller Pfad bei Ausführung: system/modules/cron
//
//-------------------------------------------------------------------
//  Systeminitialisierung, wenn direkt aufgerufen
//-------------------------------------------------------------------
if( !defined('TL_MODE') ) {
  define('TL_MODE', 'BE');

  // search the initialize.php // Danke an xtra
  $dir = dirname( $_SERVER['SCRIPT_FILENAME'] );

  while( ($dir != '.') && ($dir != '/') && !is_file($dir . '/system/initialize.php') ) {
    $dir = dirname( $dir );
  }

  if( !is_file($dir . '/system/initialize.php') ) {
    echo 'Could not find initialize.php, where is Contao?';
    exit;
  }

  require( $dir . '/system/initialize.php' );
  define('DIRECT_CALL', 1 );
  error_reporting(0);
}

/**
 * Class ImportMobile
 *
 * @copyright  inszenium 2017
 * @author     Kirsten Roschanski
 * @package    Devtools
 */
class ImportMobile extends \Backend
{
	
	private $APIURL = 'https://services.mobile.de/1.0.0/ad/search?page.size=20';
	
	private $arrADs = array();
	
	public function __construct( )
	{
		parent::__construct(); 	
	}
	
	public function run() {				
		if(!is_dir(TL_ROOT . '/files/mobile')) {
			mkdir( TL_ROOT . '/files/mobile');
		}

		$response = $this->get_xml();
		$this->print_xml($response);

		if($this->max_pages($response) > 1) {
			for ($i = 1; $i <= $this->max_pages($response); $i++) {
				$response = $this->get_xml($i);
				$this->print_xml($response);
				
			}	
		}
		
		//Filesystem sycronisieren
		\Dbafs::syncFiles();
		
		// DCA laden
		\Module::loadDataContainer('tl_vehicle');
		$arrFields = array_keys($GLOBALS['TL_DCA']['tl_vehicle']['fields']);
		
		if(is_array($this->arrADs) && count($this->arrADs) > 0) {
			$objDatabase = \Database::getInstance();
			
			$objDatabase->execute('TRUNCATE TABLE tl_vehicle');
			
			
			foreach ($this->arrADs as $order_number => $arrCarData ) {				
				$arrInsert    = array();
				$arrImageUUID = array();
				
				foreach( $arrFields as $fieldname ) {
					 $arrInsert[$fieldname] = $arrCarData[$fieldname];					
				}
				
				unset($arrInsert['id']);
				unset($arrInsert['pid']);
				unset($arrInsert['sorting']);
				unset($arrInsert['cssClass']);
				unset($arrInsert['start']);
				unset($arrInsert['stop']);
				
				foreach ( $arrCarData['images'] as $imagePath ) {
					$arrImageUUID[] = $objDatabase->prepare("SELECT uuid FROM tl_files WHERE path = ?")->execute($imagePath)->uuid;
				}
				
				$arrInsert['description']     = str_replace(' - ', '<br>', $arrCarData['description']);
				$arrInsert['headline']        = $arrCarData['make'] . ' ' . ($arrCarData['model_description'] ? $arrCarData['model_description'] : $arrCarData['model']);
				$arrInsert['tstamp']          = strtotime($arrCarData['modification']);
				$arrInsert['order_number']    = $order_number;
				$arrInsert['features']        = serialize($arrCarData['features']);
				$arrInsert['addImage']        = '1';
				$arrInsert['published']       = '1';
				$arrInsert['metaIgnore']      = '1';
				$arrInsert['alias']           = standardize($arrInsert['headline'] . '-' . $order_number);
				$arrInsert['number_of_previous_owners']           = $arrCarData['number_of_previous_owners'] ? $arrCarData['make'] : '0';
				$arrInsert['sortBy']          = 'customer';
				$arrInsert['multiSRC']        = serialize($arrImageUUID);
				$arrInsert['orderSRC']        = serialize($arrImageUUID);
				
				$objDatabase->prepare("INSERT INTO tl_vehicle %s")->set($arrInsert)->execute();
				
				echo "INSERT " . $arrInsert['order_number'] . ' ' . $arrInsert['headline'] . PHP_EOL;
			}
		}
	}
	
	private function get_xml($page=1) {
		$client = new Client();
		
		$response = $client->get($this->APIURL.'&page.number='.$page, [
			'auth' => [\Config::get('mobileUser'), \Config::get('mobilePass')],
			'Content-Type' => 'application/xml'
		]);
		
		return $response;
		
	}
	
	private function max_pages($response) {
		$xml = new \SimpleXMLElement($response->getBody());
		
		return $xml->attributes()['max-pages'];
		
	}
	
	private function print_xml($response) {
		$content = new \SimpleXMLElement($response->getBody());
		$properties = $content->children('ad', TRUE);
		foreach($properties as $propertie) {			
			$client = new Client();	
			
			$responseAD = $client->get((string)$propertie->attributes()['url'], [
				'auth' => [\Config::get('mobileUser'), \Config::get('mobilePass')],
				'headers' => ['Accept-Language' => 'de', 'Content-Type' => 'application/xml'],
			]);
			
			$ad = new \SimpleXMLElement($responseAD->getBody());
			$sku = (int)$ad->attributes()['key'];
			
			
			$this->arrADs[$sku]['url'] = (string)$ad->attributes()['url'];
			$this->arrADs[$sku]['class'] = (string)$ad->xpath("//ad:class/@key")[0];
			$this->arrADs[$sku]['creation'] = (string)$ad->xpath("//ad:creation-date/@value")[0];
			$this->arrADs[$sku]['modification'] = (string)$ad->xpath("//ad:modification-date/@value")[0];
			$this->arrADs[$sku]['description'] = (string)$ad->xpath("//ad:description")[0];
			$this->arrADs[$sku]['currency'] = (string)$ad->xpath("//ad:price/@currency")[0];
			$this->arrADs[$sku]['price'] = (string)$ad->xpath("//ad:consumer-price-amount/@value")[0];
			$this->arrADs[$sku]['vat'] = (float)($ad->xpath("//ad:vat-rate/@value")[0])*100;
			$this->arrADs[$sku]['make'] = (string)$ad->xpath("//ad:make/@key")[0];
			$this->arrADs[$sku]['category'] = (string)$ad->xpath("//ad:category/@key")[0];
			$this->arrADs[$sku]['model'] = (string)$ad->xpath("//ad:model/@key")[0];
			$this->arrADs[$sku]['model_description'] = (string)$ad->xpath("//ad:model-description/@value")[0];
			$this->arrADs[$sku]['damage_and_unrepaired'] = (string)$ad->xpath("//ad:damage-and-unrepaired/@value")[0];
			$this->arrADs[$sku]['accident_damaged'] = (string)$ad->xpath("//ad:accident-damaged/@value")[0];
			$this->arrADs[$sku]['roadworthy'] = (string)$ad->xpath("//ad:roadworthy/@value")[0];
			
			// Bilder
			$images = $ad->xpath("//ad:representation");
			$i = 1;
			foreach($images as $image) {
				if ((string)$image->attributes()['size'] == 'XL') {
					if(!is_dir(TL_ROOT . '/files/mobile/' . $sku)) {
						mkdir( TL_ROOT . '/files/mobile/' . $sku);
					}
					$url = (string)$image->attributes()['url'];
					$file = TL_ROOT . '/files/mobile/' . $sku . '/' . $i . '.JPG';

					if (!file_exists($file))	{
						$client = new Client();
						$request = $client->get($url, ['save_to' => $file]);			
					}
					
					$this->arrADs[$sku]['images'][] = 'files/mobile/' . $sku . '/' . $i . '.JPG';
					$i++;
				}
				
			}
			
			// Zubehör
			$features = $ad->xpath("//ad:feature");
			foreach($features as $feature) {				
				$name = (string)$feature->attributes()['key'];				
				$this->arrADs[$sku]['features'][] = $name;
			}
			
			// Spezifikation
			$this->arrADs[$sku]['exterior_color'] = (string)$ad->xpath("//ad:exterior-color/@key")[0];
			$this->arrADs[$sku]['door_count'] = (string)$ad->xpath("//ad:door-count/@key")[0];
			$this->arrADs[$sku]['emission_class'] = (string)$ad->xpath("//ad:emission-class/@key")[0];
			$this->arrADs[$sku]['emission_sticker'] = (string)$ad->xpath("//ad:emission-sticker/@key")[0];
			$this->arrADs[$sku]['fuel'] = (string)$ad->xpath("//ad:fuel/@key")[0];
			$this->arrADs[$sku]['gearbox'] = (string)$ad->xpath("//ad:gearbox/@key")[0];
			$this->arrADs[$sku]['climatisation'] = (string)$ad->xpath("//ad:climatisation/@key")[0];
			$this->arrADs[$sku]['condition'] = (string)$ad->xpath("//ad:condition/@key")[0];
			$this->arrADs[$sku]['usage_type'] = (string)$ad->xpath("//ad:usage-type/@key")[0];
			$this->arrADs[$sku]['interior_color'] = (string)$ad->xpath("//ad:interior-color/@key")[0];
			$this->arrADs[$sku]['interior_type'] = (string)$ad->xpath("//ad:interior-type/@key")[0];
			$this->arrADs[$sku]['number_of_previous-owners'] = (string)$ad->xpath("//ad:number-of-previous-owners")[0];			
			$this->arrADs[$sku]['mileage'] = (string)$ad->xpath("//ad:mileage/@value")[0];
			$this->arrADs[$sku]['general_inspection'] = (string)$ad->xpath("//ad:general-inspection/@value")[0];
			$this->arrADs[$sku]['first_registration'] = (string)$ad->xpath("//ad:first-registration/@value")[0];
			$this->arrADs[$sku]['power'] = (string)$ad->xpath("//ad:power/@value")[0];
			$this->arrADs[$sku]['schwacke_code'] = (string)$ad->xpath("//ad:schwacke-code/@value")[0];
			$this->arrADs[$sku]['num_seats'] = (string)$ad->xpath("//ad:num-seats/@value")[0];
			$this->arrADs[$sku]['cubic_capacity'] = (string)$ad->xpath("//ad:cubic-capacity/@value")[0];
			$this->arrADs[$sku]['envkv_compliant'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@envkv-compliant")[0];
			$this->arrADs[$sku]['energy_efficiency_class'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@energy-efficiency-class")[0];
			$this->arrADs[$sku]['co2_emission'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@co2-emission")[0];
			$this->arrADs[$sku]['petrol_inner'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@inner")[0];
			$this->arrADs[$sku]['petrol_outer'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@outer")[0];
			$this->arrADs[$sku]['petrol_combined'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@combined")[0];
			$this->arrADs[$sku]['unit'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@unit")[0];
			$this->arrADs[$sku]['petrol_type'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@petrol-type")[0];
			$this->arrADs[$sku]['petrol_type'] =  (string)$ad->xpath("//ad:emission-fuel-consumption/@petrol-type")[0];
			$this->arrADs[$sku]['hsn'] = (string)$ad->xpath("//ad:kba/@hsn")[0];
			$this->arrADs[$sku]['tsn'] = (string)$ad->xpath("//ad:kba/@tsn")[0];
		}			
	}	
}

//-------------------------------------------------------------------
//  Programmstart
//-------------------------------------------------------------------
$objImport = new ImportMobile( );
$objImport->run( );

