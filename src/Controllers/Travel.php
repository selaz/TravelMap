<?php

namespace Selaz\Controllers;

use SafeMySQL,
	Selaz\BotApi,
	Selaz\Entities\Map\Point,
	Selaz\Entities\Map\PointCollection,
	Selaz\Telegram\Entity\Chat,
	Selaz\Telegram\Entity\InlineKeyboardButton,
	Selaz\Telegram\Entity\InlineKeyboardMarkup,
	Selaz\Telegram\Entity\Location,
	Selaz\Telegram\Entity\Message,
	Selaz\Telegram\Entity\Update,
	Selaz\Tools\Config;
use Selaz\Tools\Database;
use SimpleXMLElement;
use XMLWriter;

class Travel extends Controller {
	
	private $tg;
	
	protected $pointTypes = [
		1 => '🛠 Сервис',
		2 => '❗️ Внимание',
		3 => '🏕 Кемпинг',
		4 => '👀 Посмотреть',
		5 => '🍽 Покушать',
		6 => '⛽️ Заправки',
		7 => '🛏 Гостинница',
		8 => '💵 Магазин',
		9 => '🅿️ Отдых'
	];


	const IMG_PATH = '/var/www/img/';
	const REGEX_COORDS = '~([^\d]*((\d{1,3}\.\d{3,}),\s*(\d{1,3}\.\d{3,}))[^\d]*)~s';
	
	public function __construct() {
		$this->setTitle('План путешествия');
	}
	
	public function index() {
		$db = Database::getInstance();
		
		$this->setTemplate('yandex.twig');
		$this->setTemplateData('center', [45.135923600045686,51.583865736593374,5]);
	
		$this->setTemplateData('t',['Сервис','Внимание','Кемпинг','Посмотреть','Покушать','Заправки','Гостинница','Магазин','Отдых']);
		
		$this->setTemplateData(
			'bounds', 
			$db->getRow("select min(st_x(coords)) as min_lat,min(st_y(coords)) as min_lon,max(st_x(coords)) as max_lat,max(st_y(coords)) as max_lon from points;")
		);
	}
	
	public function getPolygon( array $coords ) { //@todo private
		$lat = [$coords[0], $coords[2]];
		$lon = [$coords[1], $coords[3]];
	
		$polygon[] = sprintf('%f %f',min($lat),min($lon));
		$polygon[] = sprintf('%f %f',max($lat),min($lon));
		$polygon[] = sprintf('%f %f',max($lat),max($lon));
		$polygon[] = sprintf('%f %f',min($lat),max($lon));
		$polygon[] = sprintf('%f %f',min($lat),min($lon));
		
		return $polygon;
	}

	public function export() {
		$pc = new PointCollection();
		$pc->whereType($this->getRequest()->get('type'));
		$pc->load();
		
		$styleMap = [
			1 => 'placemark-gray',
			2 => 'placemark-red',
			3 => 'placemark-green',
			4 => 'placemark-blue',
			5 => 'placemark-deeppurple',
			6 => 'placemark-bluegray',
			7 => 'placemark-cyan',
			8 => 'placemark-brown',
			9 => 'placemark-yellow',
		];

		$folders = [];
		foreach ($pc->getCollection() as $point) {
			$folders[$point->getTypeName()][] = $point;
		}

		$xw = new XMLWriter();
		$xw->openMemory();
		$xw->startDocument("1.0",'UTF-8');
		$xw->startElement("kml");
		$xw->writeAttribute('xmlns','http://earth.google.com/kml/2.1');
		foreach ($folders as $folderName => $points) {
			$xw->startElement('Document');
			$this->addStyles($xw);
			$this->xmlSimpleElement($xw, 'name',$folderName);
			$this->xmlSimpleElement($xw, 'visibility',1);
			foreach ($points as $p) {
				$xw->startElement('Placemark');
				$this->xmlSimpleElement($xw, 'name',$p->getName());
				$this->xmlSimpleElement($xw,'styleUrl',sprintf('#%s',$styleMap[$p->getType()]));
				$this->xmlSimpleElement($xw,'styleUrlDebug',sprintf('%s',$p->getType()));
				$this->xmlSimpleElement($xw, 'description',$p->getBallonBody()??'');
				$xw->startElement('Point');
				$this->xmlSimpleElement($xw, 'coordinates',implode(',',$p->getCoords(true)));
				$xw->endElement(); //Point
				$xw->endElement(); //Placemark
			}
			$xw->endElement(); //Document
		}

		$xw->endElement(); //kml
		$xw->endDocument();

		$this->setTemplate('raw.twig');
		$this->setTemplateData('data',$xw->outputMemory());
		$this->addHeader('Content-type','application/xml');
	}

	protected function addStyles(XMLWriter &$xw) {
		$styles = [
			'placemark-red' => 'http://maps.me/placemarks/placemark-red.png',
			'placemark-blue' => 'http://maps.me/placemarks/placemark-blue.png',
			'placemark-purple' => 'http://maps.me/placemarks/placemark-purple.png',
			'placemark-yellow' => 'http://maps.me/placemarks/placemark-yellow.png',
			'placemark-pink' => 'http://maps.me/placemarks/placemark-pink.png',
			'placemark-brown' => 'http://maps.me/placemarks/placemark-brown.png',
			'placemark-green' => 'http://maps.me/placemarks/placemark-green.png',
			'placemark-orange' => 'http://maps.me/placemarks/placemark-orange.png',
			'placemark-deeppurple' => 'http://maps.me/placemarks/placemark-deeppurple.png',
			'placemark-lightblue' => 'http://maps.me/placemarks/placemark-lightblue.png',
			'placemark-cyan' => 'http://maps.me/placemarks/placemark-cyan.png',
			'placemark-teal' => 'http://maps.me/placemarks/placemark-teal.png',
			'placemark-lime' => 'http://maps.me/placemarks/placemark-lime.png',
			'placemark-deeporange' => 'http://maps.me/placemarks/placemark-deeporange.png',
			'placemark-gray' => 'http://maps.me/placemarks/placemark-gray.png',
			'placemark-bluegray' => 'http://maps.me/placemarks/placemark-bluegray.png',
		];

		foreach ($styles as $style => $icon) {
			$xw->startElement('Style');
			$xw->writeAttribute('id',$style);
			$xw->startElement('IconStyle');
			$xw->startElement('Icon');
			$this->xmlSimpleElement($xw,'href', $icon);
			$xw->endElement(); //Icon
			$xw->endElement(); //IconStyle
			$xw->endElement(); //Style
		}
			
	}

	protected function xmlSimpleElement(XMLWriter &$xw, string $name, string $val) {
		$xw->startElement($name);
		$xw->writeCdata($val);
		$xw->endElement();
	}
	
	public function points() {
		$pc = new PointCollection();
		
		$coords = explode(',', $this->getRequest()->get('bbox'));
		if (count($coords) == 4) {
			$pc->wherePolygon($this->getPolygon($coords));
		}
		
		$debug = $pc->load();
		$data = $pc->getCollection();
		$tplData = [];

		$callback = $this->getRequest()->get('callback');
		$coordReverse = empty($callback);

		foreach ($data as $point) {
			$tplData[] = [
				'type' => 'Feature',
				'id' => $point->getId(),
				'geometry' => [
					'type' => 'Point',
					'coordinates' => $point->getCoords($coordReverse)
				],
				'properties' => [
					'balloonContentHeader' => $point->getName(),
					'balloonContentBody' => $point->getBallonBody(),
					'balloonContentFooter' => implode(',',$point->getCoords()),
					'balloonType' => $point->getTypeName(),
					'hintContent' => $point->getName(),
					'id' => $point->getId(),
				],
				'options' => [
					'preset' => sprintf('islands#%s%sCircleIcon',$point->getColor(),$point->getIcon())
				]
			];
		}

		
		$dataPonts = [
			'type' => 'FeatureCollection',
			'features' => $tplData
		];
		
		$tplDataJson = [
			'count' => count($tplData),
			'rectangle' => $this->getRequest()->get('bbox'),
			'error' => null,
			'data' => $dataPonts
		];

		$toTpl = ($callback) ? $tplDataJson : $dataPonts;
		$this->setTemplateData('data', json_encode($toTpl));
		$this->setTemplateData('func', $callback);
		$this->setTemplate('points.twig');
		$this->addHeader('Content-type','application/json');
	}
} 