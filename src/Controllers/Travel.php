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
	
	private $config;

	public function __construct() {
		$this->setTitle('План путешествия');
		$this->config = Config::load('main.ini');
	}
	
	public function index() {
		$db = new SafeMySQL($this->config->getBlock('mysql'));
		
		$this->setTemplate('yandex.twig');
		$this->setTemplateData('center', [45.135923600045686,51.583865736593374,5]);
	
		$this->setTemplateData('t',['Сервис','Внимание','Кемпинг','Посмотреть','Покушать','Заправки','Гостинница','Магазин','Отдых']);
		
		$this->setTemplateData(
			'bounds', 
			$db->getRow("select min(st_x(coords)) as min_lat,min(st_y(coords)) as min_lon,max(st_x(coords)) as max_lat,max(st_y(coords)) as max_lon from points;")
		);
		$this->setTemplateData('key',$this->config->get('key','maps'));
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

	public function processCommand(Update $update) {
		
		if (empty($update->getMessage()) && empty($update->getCallbackQuery())) {
			return;
		}
		
		if ($callback = $update->getCallbackQuery()) {
			$origId = $callback->getMessage()->getReplyToMessage()->getMessageId();
			$origChatId = $callback->getMessage()->getReplyToMessage()->getChat()->getId();
			$type = $callback->getData();
			$this->setPointType(sprintf("%d:%d",$origChatId,$origId), $type);
			
			$pc = new PointCollection();
			$pc->whereId(sprintf("%d:%d",$origChatId,$origId));
			$pc->load();
			
			$p = $pc->getNext();
			
			$this->tg->editMessageText(
				$update->getCallbackQuery()->getMessage()->getChat(),
				$update->getCallbackQuery()->getMessage(),
				sprintf(
					"%s\nВыбран тип %s\n[Точка на карте](%s)", 
					$update->getCallbackQuery()->getMessage()->getText(),
					$this->pointTypes[$type],
					sprintf('https://c.selaz.org/#type=map&center=%s&zoom=17&open=%s',implode(',',$p->getCoords()),$p->getId())
				),
				null,
				'Markdown'
			);
		} elseif ( $reply = $update->getMessage()->getReplyToMessage() ) {
			$this->chechUpdate($update->getMessage(),$reply);
		} else {
			$this->checkInsert($update->getMessage());
		}
	}
	
	public function setPointType(string $id, int $type) {
		$p = new Point();
		$p->setId($id);
		$p->setType($type);
		$p->update();
	}
	
	private function parseCoords(Message $message): ?Location {
		$loc = $m = null;
		
		if (preg_match(self::REGEX_COORDS, $message->getText() ?? $message->getCaption(),$m)) {
			$loc = new Location([
				'latitude' => $m[3],
				'longitude' => $m[4],
			]);
		}
		
		return $loc;
	}
	
	private function parseImage(Message $message): ?string {
		$img = null;
		
		if ($message->hasPhoto()) {
			$fid = $message->getPhoto(0)->getFileId();
			$file = $this->tg->downloadFile($this->tg->getFile($fid));

			$file->move(sprintf('%s%s', self::IMG_PATH, sprintf('%s.jpg',$fid)));
			$img = sprintf('https://c.selaz.org/img/%s.jpg', $fid);
		}
		
		return $img;
	}
	
	private function parseLinks(Message $message) {
		$links = [];
		$text = $message->getText() ?? $message->getCaption();
		if ($enl = $message->getEntities()) {
			foreach ($enl as $en) {
				if (!empty($en->getUrl())) {
					$links[] = $en->getUrl();
				} elseif ($en->getType() == 'url') {
					$links[] = mb_substr($text, $en->getOffset(), $en->getLength());
				}
			}
		}
		
		return $links;
	}
	
	private function parseText(Message $message, Point $point, ?string $default = null) {
		$m = $remove = [];
		$text = $message->getText() ?? $message->getCaption() ?? $default;
		
		if (empty($text)) {
			return [null,null];
		}
		
		preg_match(self::REGEX_COORDS, $text ,$m);
		
		if (!empty($m[2])) {
			$remove[] = $m[2];
		}
		$remove = array_merge($point->getLinks(), $remove);
		
		foreach ( $remove as $s ) {
			$text = str_replace($s, '', $text);
		}
		
		$text = preg_replace('~[\s]{2,}~s', ' ', $text);
		$text = preg_replace('~\s+\n~', "\n", $text);
		$text = trim($text);
		$texts = explode("\n", $text, 2);
		
		if (count($texts) == 1) {
			$texts[] = null;
		}
		
		return $texts;
	}

	public function checkInsert(Message $message) {
		$loc = $message->getLocation() ?? $this->parseCoords($message);
		
		if (!empty($loc)) {
			$point = new Point();
			$pointId = sprintf("%d:%d",$message->getChat()->getId(),$message->getMessageId());
			$point->setId($pointId);
			$point->setCoords([$loc->getLatitude(),$loc->getLongitude()]);
			$point->setImage($this->parseImage($message));
			
			$urls = $this->parseLinks($message);
			$point->setLinks($urls);
			
			$t = $this->parseText($message, $point, 'Нет описания');
			$point->setName($t[0]);
			$point->setDesc($t[1]);
			
			$keyboard = new InlineKeyboardMarkup();
			
			foreach ( $this->pointTypes as $tid => $tname) {
				$keyboard->addButton(new InlineKeyboardButton(['text' => $tname,'callback_data'=> $tid]));	
			}
			
			$point->save();
			
			$this->tg->sendMessage($message->getChat(), 'Укажите категорию точки', $message, $keyboard);
		}
	}

	protected function chechUpdate(Message $update, Message $orig) {
		$point = new Point();
		$pointId = sprintf("%d:%d",$orig->getChat()->getId(),$orig->getMessageId());
		$point->setId($pointId);
		
		$point->setImage($this->parseImage($update));
		$point->setLinks($this->parseLinks($update));
		$t = $this->parseText($update, $point, null);
		$point->setName($t[0]);
		$point->setDesc($t[1]);
		
		if ($point->update()) {
			$this->tg->sendMessage($update->getChat(), 'Данные успешно обновлены', $update);
		}
	}


} 